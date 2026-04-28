<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /*
    |--------------------------------------------------------------------------
    | 🔥 JOB CREDIT (FIFO)
    |--------------------------------------------------------------------------
    */
    private function getAvailableSubscription($employerId)
    {
        return Subscription::where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereColumn('job_posts_used', '<', 'job_posts_total')
            ->orderBy('starts_at', 'asc') // oldest first (FIFO)
            ->lockForUpdate()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 CONSUME JOB CREDIT
    |--------------------------------------------------------------------------
    */
    public function consumeJobCredit($employerId)
    {
        return DB::transaction(function () use ($employerId) {

            $subscription = $this->getAvailableSubscription($employerId);

            if (!$subscription) {
                return [
                    'status' => false,
                    'message' => 'No job credits available'
                ];
            }

            $subscription->increment('job_posts_used');

            return [
                'status' => true,
                'subscription' => $subscription
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 CONSUME FEATURE CREDIT (FIFO + PLAN CHECK)
    |--------------------------------------------------------------------------
    */
    public function consumeFeatureCredit($employerId)
    {
        return DB::transaction(function () use ($employerId) {

            $subscription = Subscription::where('employer_id', $employerId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->whereHas('plan', function ($q) {
                    $q->where('company_featured', true);
                })
                ->whereColumn('featured_jobs_used', '<', 'featured_jobs_total')
                ->orderBy('starts_at', 'asc') // FIFO
                ->lockForUpdate()
                ->first();

            if (!$subscription) {
                return [
                    'status' => false,
                    'message' => 'Feature limit reached'
                ];
            }

            $subscription->increment('featured_jobs_used');

            return [
                'status' => true,
                'subscription' => $subscription
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ⚠️ OPTIONAL (READ ONLY)
    |--------------------------------------------------------------------------
    | Use ONLY for display purposes
    | DO NOT use for consuming credits
    */
    public function getAvailableFeatureSubscription($employerId)
    {
        return Subscription::with('plan')
            ->where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereHas('plan', function ($q) {
                $q->where('company_featured', true);
            })
            ->whereColumn('featured_jobs_used', '<', 'featured_jobs_total')
            ->orderBy('starts_at', 'asc')
            ->first();
    }

    //Function only for Featured COMpany API
    public function getFeatureEnabledPlan($employerId)
    {
        $subscription = Subscription::with('plan')
            ->where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereHas('plan', function ($q) {
                $q->where('company_featured', true);
            })
            ->first(); // just check ANY valid plan

        if (!$subscription) {
            return [
                'status' => false,
                'message' => 'No active plan supports company featuring'
            ];
        }

        return [
            'status' => true,
            'subscription' => $subscription
        ];
    }
}
