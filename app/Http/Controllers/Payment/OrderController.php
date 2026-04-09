<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Razorpay\Api\Api;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Models\Subscription;
use Carbon\Carbon;
use App\Services\Notification;

class OrderController extends Controller
{

    //Notification service
    protected $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }


    public function createOrder(Request $request)
    {
        try {

            Log::info('Create Order API HIT');

            $request->validate([
                'plan_id' => 'required|integer|exists:plans,id'
            ]);

            // ✅ AUTH CHECK
            $employer = Auth::guard('employer')->user();

            Log::info('Employer check', [
                'employer' => $employer
            ]);

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated employer'
                ], 401);
            }

            // ✅ FETCH PLAN
            $plan = Plan::where('id', $request->plan_id)->first();

            Log::info('Plan fetched', [
                'plan' => $plan
            ]);

            if (!$plan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            if (!$plan->is_active) {
                return response()->json([
                    'status' => false,
                    'message' => 'This plan is currently inactive'
                ], 403);
            }

            $price = $plan->offer_price ?? $plan->actual_price;

            Log::info('Price calculated', [
                'price' => $price
            ]);

            if (!$price || $price <= 0) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid plan pricing'
                ], 422);
            }

            $amount = (int) ($price * 100);

            Log::info('Amount in paise', [
                'amount' => $amount
            ]);

            DB::beginTransaction();

            /*
        |--------------------------------------------------------------------------
        | 🔥 RAZORPAY DEBUG
        |--------------------------------------------------------------------------
        */

            $key = config('services.razorpay.key');
            $secret = config('services.razorpay.secret');

            Log::info('Razorpay config', [
                'key' => $key,
                'secret_present' => $secret ? true : false
            ]);

            $api = new Api($key, $secret);

            Log::info('Creating Razorpay order...');

            $razorpayOrder = $api->order->create([
                'receipt' => 'order_' . Str::random(12),
                'amount' => $amount,
                'currency' => 'INR'
            ]);

            Log::info('Razorpay response', [
                'response' => $razorpayOrder
            ]);

            if (!isset($razorpayOrder['id'])) {
                throw new \Exception('Failed to create Razorpay order');
            }

            $order = Order::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'razorpay_order_id' => $razorpayOrder['id'],
                'amount' => $price,
                'currency' => 'INR',
                'status' => 'created',
                'receipt' => $razorpayOrder['receipt']
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $razorpayOrder['id'],
                    'amount' => $amount,
                    'currency' => 'INR',
                    'key' => $key,
                    'plan' => $plan
                ]
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Order creation failed FULL', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Order creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Verify payment

    public function verifyPayment(Request $request)
    {
        try {

            $request->validate([
                'razorpay_order_id' => 'required|string',
                'razorpay_payment_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            DB::beginTransaction();

            // ✅ FIND ORDER
            $order = Order::where('razorpay_order_id', $request->razorpay_order_id)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new \Exception('Order not found');
            }

            // ✅ PREVENT DUPLICATE PROCESSING
            if ($order->status === 'paid') {
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Payment already processed'
                ]);
            }

            // ✅ VERIFY SIGNATURE
            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ]);

            // ✅ UPDATE ORDER
            $order->update([
                'status' => 'paid',
                'razorpay_payment_id' => $request->razorpay_payment_id
            ]);

            // ✅ FETCH PLAN
            $plan = Plan::find($order->plan_id);

            if (!$plan || !$plan->is_active) {
                throw new \Exception('Invalid or inactive plan');
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 STACKING LOGIC
        |--------------------------------------------------------------------------
        */

            $lastSubscription = Subscription::where('employer_id', $employer->id)
                ->where('expires_at', '>', now())
                ->orderBy('expires_at', 'desc')
                ->first();

            $startDate = $lastSubscription
                ? Carbon::parse($lastSubscription->expires_at)
                : now();

            $expiresAt = (clone $startDate)->addDays($plan->validity_days);

            // ✅ CREATE SUBSCRIPTION
            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'order_id' => $order->id,

                'job_posts_total' => $plan->job_posts_limit,
                'job_posts_used' => 0,

                'purchase_date' => now(),
                'starts_at' => $startDate,
                'expires_at' => $expiresAt,

                'status' => 'active'
            ]);

            DB::commit();

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer Notification
            $this->notification->send(
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

            // ✅ Admin Notifications
            $admins = \App\Models\User::where('role', 'admin')
                ->whereNotNull('email')
                ->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'payment_success',
                    'admin',
                    $admin->id,
                    'Payment Received',
                    "Payment received from '{$employer->company_name}'",
                    [
                        'order_id' => $order->id
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

                // ✅ Employer Mail
                $mailService->send('payment_success', [
                    'name' => $employer->company_name,
                    'plan_name' => $plan->name,
                    'amount' => $order->amount
                ], $employer->email);

                // ✅ Admin Mails
                foreach ($admins as $admin) {
                    $mailService->send('payment_received_admin', [
                        'company_name' => $employer->company_name,
                        'plan_name' => $plan->name,
                        'amount' => $order->amount
                    ], $admin->email);
                }
            } catch (\Exception $mailException) {

                Log::error('Payment mail failed (verifyPayment)', [
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Payment verified and subscription activated',
                'data' => $subscription
            ]);
        } catch (SignatureVerificationError $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Invalid payment signature'
            ], 400);
        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Payment verification failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
