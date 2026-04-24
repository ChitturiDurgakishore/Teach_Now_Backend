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
use App\Services\MailService;
class WebhookController extends Controller
{

    public function handle(Request $request)
    {
        Log::info('🔔 Webhook Triggered', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);

        try {

            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');
            $secret = env('RAZORPAY_WEBHOOK_SECRET');

            Log::info('📥 Raw Webhook Data', [
                'signature_present' => !!$signature,
                'payload_length' => strlen($payload)
            ]);

            // ❗ SAFETY CHECK
            if (!$signature || !$secret) {
                Log::error('❌ Missing signature or secret');
                return response()->json(['status' => false], 400);
            }

            // ✅ VERIFY SIGNATURE
            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::error('❌ Signature mismatch');
                return response()->json(['status' => false], 400);
            }

            Log::info('✅ Signature verified');

            // ✅ PARSE JSON
            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('❌ Invalid JSON', ['error' => json_last_error_msg()]);
                return response()->json(['status' => false], 400);
            }

            $event = $data['event'] ?? null;

            Log::info('📌 Event received', ['event' => $event]);

            if (!$event) {
                Log::error('❌ Event missing');
                return response()->json(['status' => false], 400);
            }

            switch ($event) {

                /*
            |--------------------------------------------------------------------------
            | ✅ PAYMENT SUCCESS
            |--------------------------------------------------------------------------
            */
                case 'payment.captured':

                    Log::info('💰 Processing payment.captured');

                    $payment = $data['payload']['payment']['entity'] ?? null;

                    if (!$payment) {
                        Log::error('❌ Payment entity missing');
                        return response()->json(['status' => false], 400);
                    }

                    $razorpayOrderId = $payment['order_id'] ?? null;
                    $razorpayPaymentId = $payment['id'] ?? null;

                    Log::info('📊 Payment Data', [
                        'order_id' => $razorpayOrderId,
                        'payment_id' => $razorpayPaymentId
                    ]);

                    if (!$razorpayOrderId || !$razorpayPaymentId) {
                        Log::error('❌ Missing order_id or payment_id');
                        return response()->json(['status' => false], 400);
                    }

                    DB::transaction(function () use ($razorpayOrderId, $razorpayPaymentId, $payment) {

                        Log::info('🔄 DB Transaction started');

                        $order = Order::where('razorpay_order_id', $razorpayOrderId)
                            ->lockForUpdate()
                            ->first();

                        if (!$order) {
                            Log::error('❌ Order not found', ['order_id' => $razorpayOrderId]);
                            return;
                        }

                        Log::info('✅ Order found', ['order_db_id' => $order->id]);

                        // Prevent duplicate
                        if ($order->status === 'paid') {
                            Log::warning('⚠️ Order already paid, skipping');
                            return;
                        }

                        // Update order
                        $order->update([
                            'status' => 'paid',
                            'razorpay_payment_id' => $razorpayPaymentId
                        ]);

                        Log::info('✅ Order updated to PAID');

                        if (Subscription::where('order_id', $order->id)->exists()) {
                            Log::warning('⚠️ Subscription already exists');
                            return;
                        }

                        $plan = Plan::find($order->plan_id);

                        if (!$plan) {
                            Log::error('❌ Plan not found', ['plan_id' => $order->plan_id]);
                            return;
                        }

                        Log::info('✅ Plan found', ['plan' => $plan->name]);

                        // STACKING
                        $lastSubscription = Subscription::where('employer_id', $order->employer_id)
                            ->where('expires_at', '>', now())
                            ->orderBy('expires_at', 'desc')
                            ->first();

                        $startDate = $lastSubscription
                            ? $lastSubscription->expires_at
                            : now();

                        $expiresAt = $startDate->copy()->addDays($plan->validity_days);

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

                        Log::info('✅ Subscription created', [
                            'subscription_id' => $subscription->id
                        ]);

                        // PAYMENT STORE
                        if (!Payment::where('transaction_id', $razorpayPaymentId)->exists()) {

                            Payment::create([
                                'employer_id' => $order->employer_id,
                                'subscription_id' => $subscription->id,
                                'amount' => $order->amount,
                                'payment_method' => $payment['method'] ?? 'unknown',
                                'payment_status' => 'success',
                                'transaction_id' => $razorpayPaymentId,
                            ]);

                            Log::info('✅ Payment stored');
                        } else {
                            Log::warning('⚠️ Payment already exists');
                        }

                        $employer = \App\Models\Employer::find($order->employer_id);

                        if (!$employer) {
                            Log::error('❌ Employer not found');
                            return;
                        }

                        Log::info('✅ Employer found');

                        // Notifications
                        app(\App\Services\Notification::class)->send(
                            'payment_success',
                            'employer',
                            $employer->id,
                            'Payment Successful',
                            "Your payment for '{$plan->name}' plan is successful",
                            []
                        );

                        Log::info('🔔 Employer notified');


                        // Invoice
                        app(\App\Services\InvoiceService::class)->generate($order, $subscription);

                        Log::info('🧾 Invoice generated');

                        //admin notification
                        $mailService = app(MailService::class);

                        $admins = \App\Models\User::where('role', 'admin')->get();

                        foreach ($admins as $admin) {

                            $mailService->send(
                                'payment_received_admin', // 🔥 your template slug
                                [
                                    'admin_name' => $admin->name ?? 'Admin',
                                    'company_name' => $employer->company_name ?? '',
                                    'plan_name' => $plan->name ?? '',
                                    'amount' => $order->amount,
                                    'currency' => 'INR',
                                    'date' => now()->format('d M Y'),
                                ],
                                $admin->email
                            );
                        }
                    });

                    break;

                /*
            |--------------------------------------------------------------------------
            | ❌ PAYMENT FAILED
            |--------------------------------------------------------------------------
            */
                case 'payment.failed':

                    Log::warning('❌ Payment failed event');

                    $payment = $data['payload']['payment']['entity'] ?? null;
                    if (!$payment) break;

                    $razorpayOrderId = $payment['order_id'] ?? null;

                    $order = Order::where('razorpay_order_id', $razorpayOrderId)->first();

                    if ($order) {

                        $order->update(['status' => 'failed']);

                        Payment::create([
                            'employer_id' => $order->employer_id,
                            'subscription_id' => null,
                            'amount' => ($payment['amount'] ?? 0) / 100,
                            'payment_method' => $payment['method'] ?? 'unknown',
                            'payment_status' => 'failed',
                            'transaction_id' => $payment['id'] ?? null,
                        ]);

                        Log::info('❌ Failed payment stored');
                    }

                    break;
            }

            Log::info('✅ Webhook processed successfully');

            return response()->json(['status' => true]);
        } catch (\Exception $e) {

            Log::error('🔥 Webhook Exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => false], 500);
        }
    }
}
