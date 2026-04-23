<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\InvoiceService;
use App\Models\Payment;
use App\Models\Employer;
use App\Models\User;
use App\Services\Notification;

class WebhookController extends Controller
{

    public function handle(Request $request)
    {
        Log::info('Razorpay Handle Triggered', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);

        try {

            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');
            $secret = env('RAZORPAY_WEBHOOK_SECRET');

            // ❗ SAFETY CHECK
            if (!$signature || !$secret) {
                Log::error('Webhook missing signature or secret');
                return response()->json(['status' => false], 400);
            }

            // ✅ VERIFY SIGNATURE
            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Webhook signature mismatch');
                return response()->json(['status' => false], 400);
            }

            // ✅ PARSE JSON
            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON');
                return response()->json(['status' => false], 400);
            }

            $event = $data['event'] ?? null;

            if (!$event) {
                return response()->json(['status' => false], 400);
            }

            switch ($event) {

                /*
            |--------------------------------------------------------------------------
            | ✅ PAYMENT SUCCESS
            |--------------------------------------------------------------------------
            */
                case 'payment.captured':

                    $payment = $data['payload']['payment']['entity'] ?? null;

                    if (!$payment) return response()->json(['status' => false], 400);

                    $razorpayOrderId = $payment['order_id'] ?? null;
                    $razorpayPaymentId = $payment['id'] ?? null;

                    if (!$razorpayOrderId || !$razorpayPaymentId) {
                        return response()->json(['status' => false], 400);
                    }

                    DB::transaction(function () use ($razorpayOrderId, $razorpayPaymentId, $payment) {

                        $order = Order::where('razorpay_order_id', $razorpayOrderId)
                            ->lockForUpdate()
                            ->first();

                        if (!$order) return;

                        // ✅ prevent duplicate processing
                        if ($order->status === 'paid') return;

                        // ✅ update order
                        $order->update([
                            'status' => 'paid',
                            'razorpay_payment_id' => $razorpayPaymentId
                        ]);

                        // ✅ prevent duplicate subscription
                        if (Subscription::where('order_id', $order->id)->exists()) return;

                        $plan = Plan::find($order->plan_id);
                        if (!$plan) return;

                        // 🔥 STACKING
                        $lastSubscription = Subscription::where('employer_id', $order->employer_id)
                            ->where('expires_at', '>', now())
                            ->orderBy('expires_at', 'desc')
                            ->first();

                        $startDate = $lastSubscription
                            ? $lastSubscription->expires_at
                            : now();

                        $expiresAt = $startDate->copy()->addDays($plan->validity_days);

                        // ✅ CREATE SUBSCRIPTION
                        $subscription = Subscription::create([
                            'employer_id' => $order->employer_id,
                            'plan_id' => $plan->id,
                            'order_id' => $order->id,
                            'job_posts_total' => $plan->job_posts_limit,
                            'job_posts_used' => 0,
                            'purchase_date' => now(),
                            'starts_at' => $startDate,
                            'expires_at' => $expiresAt,
                            'status' => 'active',
                            'featured_jobs_total' => $plan->featured_jobs_limit,
                            'featured_jobs_used' => 0,
                        ]);

                        // ✅ STORE PAYMENT
                        if (!Payment::where('transaction_id', $razorpayPaymentId)->exists()) {
                            Payment::create([
                                'employer_id' => $order->employer_id,
                                'subscription_id' => $subscription->id,
                                'amount' => $order->amount,
                                'payment_method' => $payment['method'] ?? 'unknown',
                                'payment_status' => 'success',
                                'transaction_id' => $razorpayPaymentId,
                            ]);
                        }

                        $employer = \App\Models\Employer::find($order->employer_id);
                        if (!$employer) return;

                        // 🔔 NOTIFICATIONS
                        app(\App\Services\Notification::class)->send(
                            'payment_success',
                            'employer',
                            $employer->id,
                            'Payment Successful',
                            "Your payment for '{$plan->name}' plan is successful",
                            [
                                'order_id' => $order->id,
                                'subscription_id' => $subscription->id
                            ]
                        );

                        $admins = \App\Models\User::where('role', 'admin')->get();

                        foreach ($admins as $admin) {
                            app(\App\Services\Notification::class)->send(
                                'payment_success',
                                'admin',
                                $admin->id,
                                'New Payment Received',
                                "Payment received from '{$employer->company_name}'",
                                [
                                    'order_id' => $order->id
                                ]
                            );
                        }

                        // 📄 INVOICE
                        app(\App\Services\InvoiceService::class)->generate($order, $subscription);
                    });

                    break;


                /*
            |--------------------------------------------------------------------------
            | ❌ PAYMENT FAILED
            |--------------------------------------------------------------------------
            */
                case 'payment.failed':

                    $payment = $data['payload']['payment']['entity'] ?? null;
                    if (!$payment) break;

                    $razorpayOrderId = $payment['order_id'] ?? null;

                    $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

                    if ($order) {
                        $order->update(['status' => 'failed']);

                        // ✅ STORE FAILED PAYMENT
                        Payment::create([
                            'employer_id' => $order->employer_id,
                            'subscription_id' => null,
                            'amount' => ($payment['amount'] ?? 0) / 100,
                            'payment_method' => $payment['method'] ?? 'unknown',
                            'payment_status' => 'failed',
                            'transaction_id' => $payment['id'] ?? null,
                        ]);
                    }

                    break;
            }

            return response()->json(['status' => true]);
        } catch (\Exception $e) {

            Log::error('Webhook error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['status' => false], 500);
        }
    }
}
