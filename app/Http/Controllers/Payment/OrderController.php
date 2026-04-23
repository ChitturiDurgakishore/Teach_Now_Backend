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
                'secret_present' => $secret
            ]);

            $api = new Api($key, $secret);
            Log::info('Razorpay API initialized', [
                'api' => $api
            ]);
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

            $api = new Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );

            $api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Payment verified. Awaiting confirmation...'
            ]);
        } catch (SignatureVerificationError $e) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid payment signature'
            ], 400);
        }
    }
}
