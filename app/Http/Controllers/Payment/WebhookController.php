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

            /*
        |--------------------------------------------------------------------------
        | 🔒 SIGNATURE VALIDATION (SAFE)
        |--------------------------------------------------------------------------
        */

            if (!$signature || !$secret) {
                Log::error('Webhook signature or secret missing');
                return response()->json(['status' => false], 400);
            }

            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('Webhook signature mismatch');
                return response()->json(['status' => false], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔒 PARSE PAYLOAD
        |--------------------------------------------------------------------------
        */

            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in webhook');
                return response()->json(['status' => false], 400);
            }

            $event = $data['event'] ?? null;

            if (!$event) {
                return response()->json(['status' => false], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 ONLY PROCESS IMPORTANT EVENTS
        |--------------------------------------------------------------------------
        */

            if (!in_array($event, ['payment.captured', 'payment.failed'])) {
                return response()->json([
                    'status' => true,
                    'message' => 'Event ignored'
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | ✅ PAYMENT SUCCESS
        |--------------------------------------------------------------------------
        */

            if ($event === 'payment.captured') {

                $payment = $data['payload']['payment']['entity'] ?? null;

                if (!$payment) {
                    Log::error('Webhook: payment entity missing');
                    return response()->json(['status' => false], 400);
                }

                $razorpayOrderId = $payment['order_id'] ?? null;
                $razorpayPaymentId = $payment['id'] ?? null;

                if (!$razorpayOrderId || !$razorpayPaymentId) {
                    Log::error('Webhook: missing payment data');
                    return response()->json(['status' => false], 400);
                }

                DB::transaction(function () use ($razorpayOrderId, $razorpayPaymentId) {

                    $order = Order::where('razorpay_order_id', $razorpayOrderId)
                        ->lockForUpdate()
                        ->first();

                    if (!$order) {
                        Log::error('Webhook: Order not found', [
                            'order_id' => $razorpayOrderId
                        ]);
                        return;
                    }

                    // ✅ DUPLICATE PROTECTION
                    if ($order->status === 'paid') {
                        return;
                    }

                    // ✅ UPDATE ORDER
                    $order->update([
                        'status' => 'paid',
                        'razorpay_payment_id' => $razorpayPaymentId
                    ]);

                    // ✅ PREVENT DUPLICATE SUBSCRIPTION
                    if (Subscription::where('order_id', $order->id)->exists()) {
                        return;
                    }

                    $plan = Plan::find($order->plan_id);

                    if (!$plan) {
                        Log::error('Webhook: Plan not found');
                        return;
                    }

                    /*
                |--------------------------------------------------------------------------
                | 🔥 STACKING LOGIC
                |--------------------------------------------------------------------------
                */

                    $lastSubscription = Subscription::where('employer_id', $order->employer_id)
                        ->where('expires_at', '>', now())
                        ->orderBy('expires_at', 'desc')
                        ->first();

                    $startDate = $lastSubscription
                        ? $lastSubscription->expires_at
                        : now();

                    $expiresAt = \Carbon\Carbon::parse($startDate)
                        ->addDays($plan->validity_days);

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

                    $employer = \App\Models\Employer::find($order->employer_id);
                    if (!$employer) return;

                    /*
                |--------------------------------------------------------------------------
                | 🔔 NOTIFICATIONS
                |--------------------------------------------------------------------------
                */

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
                                'order_id' => $order->id,
                                'employer_id' => $employer->id
                            ]
                        );
                    }

                    /*
                |--------------------------------------------------------------------------
                | 📧 MAILS
                |--------------------------------------------------------------------------
                */

                    try {
                        $mailService = new \App\Services\MailService();

                        $mailService->send('payment_success', [
                            'name' => $employer->company_name,
                            'plan_name' => $plan->name,
                            'amount' => $order->amount
                        ], $employer->email);
                    } catch (\Exception $e) {
                        Log::error('Mail failed', ['error' => $e->getMessage()]);
                    }

                    /*
                |--------------------------------------------------------------------------
                | 🧾 INVOICE
                |--------------------------------------------------------------------------
                */

                    try {
                        app(\App\Services\InvoiceService::class)
                            ->generate($order, $subscription);
                    } catch (\Exception $e) {
                        Log::error('Invoice failed', ['error' => $e->getMessage()]);
                    }
                });
            }

            /*
        |--------------------------------------------------------------------------
        | ❌ PAYMENT FAILED
        |--------------------------------------------------------------------------
        */

            if ($event === 'payment.failed') {

                $payment = $data['payload']['payment']['entity'] ?? null;

                if ($payment) {

                    $razorpayOrderId = $payment['order_id'] ?? null;

                    if ($razorpayOrderId) {

                        $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

                        if ($order) {

                            $order->update(['status' => 'failed']);

                            $employer = \App\Models\Employer::find($order->employer_id);

                            if ($employer) {
                                app(\App\Services\Notification::class)->send(
                                    'payment_failed',
                                    'employer',
                                    $employer->id,
                                    'Payment Failed',
                                    "Your payment failed. Please try again",
                                    ['order_id' => $order->id]
                                );
                            }
                        }
                    }
                }
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
