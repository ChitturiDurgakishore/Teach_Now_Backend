<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\InvoiceService;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {

            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');
            $secret = env('RAZORPAY_WEBHOOK_SECRET');

            // 🔒 VERIFY SIGNATURE
            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if ($expectedSignature !== $signature) {
                Log::error('Webhook signature mismatch');
                return response()->json(['status' => false], 400);
            }

            $data = json_decode($payload, true);

            $event = $data['event'] ?? null;

            if (!$event) {
                return response()->json(['status' => false], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 HANDLE EVENTS
        |--------------------------------------------------------------------------
        */

            switch ($event) {

                case 'payment.captured':

                    $payment = $data['payload']['payment']['entity'];

                    $razorpayOrderId = $payment['order_id'];
                    $razorpayPaymentId = $payment['id'];

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

                        // 🔒 PREVENT DUPLICATE
                        if ($order->status === 'paid') {
                            return;
                        }

                        // ✅ MARK PAID
                        $order->update([
                            'status' => 'paid',
                            'razorpay_payment_id' => $razorpayPaymentId
                        ]);

                        // 🔥 CREATE SUBSCRIPTION (IF NOT EXISTS)
                        $existingSubscription = Subscription::where('order_id', $order->id)->first();

                        if ($existingSubscription) {
                            return; // already created
                        }

                        $plan = Plan::find($order->plan_id);

                        if (!$plan) {
                            Log::error('Webhook: Plan not found', [
                                'plan_id' => $order->plan_id
                            ]);
                            return;
                        }

                        // 🔥 STACKING LOGIC
                        $lastSubscription = Subscription::where('employer_id', $order->employer_id)
                            ->where('expires_at', '>', now())
                            ->orderBy('expires_at', 'desc')
                            ->first();

                        $startDate = $lastSubscription
                            ? $lastSubscription->expires_at
                            : now();

                        $expiresAt = $startDate->copy()->addDays($plan->validity_days);

                        Subscription::create([
                            'employer_id' => $order->employer_id,
                            'plan_id' => $plan->id,
                            'order_id' => $order->id,

                            'job_posts_total' => $plan->job_posts_limit,
                            'job_posts_used' => 0,

                            'purchase_date' => now(),
                            'starts_at' => $startDate,
                            'expires_at' => $expiresAt,

                            'status' => 'active'
                        ]);
                        $invoiceService = app(InvoiceService::class);

                        $invoiceService->generate($order, $subscription);
                    });

                    break;

                case 'payment.failed':

                    $payment = $data['payload']['payment']['entity'];
                    $razorpayOrderId = $payment['order_id'];

                    Order::where('razorpay_order_id', $razorpayOrderId)
                        ->update(['status' => 'failed']);

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
