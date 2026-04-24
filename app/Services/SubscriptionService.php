<?php

namespace App\Services;

use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    private function getAvailableSubscription($employerId)
    {
        return Subscription::where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereColumn('job_posts_used', '<', 'job_posts_total')
            ->orderBy('starts_at', 'asc') // 🔥 oldest first
            ->lockForUpdate()
            ->first();
    }
    public function consumeFeatureCredit($employerId)
    {
        return DB::transaction(function () use ($employerId) {

            $subscription = Subscription::with('plan')
                ->where('employer_id', $employerId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->whereColumn('featured_jobs_used', '<', 'featured_jobs_total')
                ->orderBy('starts_at', 'asc')
                ->lockForUpdate()
                ->first();

            if (!$subscription) {
                return [
                    'status' => false,
                    'message' => 'Feature limit reached'
                ];
            }

            if (!$subscription->plan || !$subscription->plan->company_featured) {
                return [
                    'status' => false,
                    'message' => 'Your plan does not support featuring'
                ];
            }

            $subscription->increment('featured_jobs_used');

            return [
                'status' => true,
                'subscription' => $subscription
            ];
        });
    }

    public function consumeJobCredit($employerId)
    {
        return DB::transaction(function () use ($employerId) {

            $subscription = Subscription::where('employer_id', $employerId)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->whereColumn('job_posts_used', '<', 'job_posts_total')
                ->orderBy('starts_at', 'asc')
                ->lockForUpdate()
                ->first();

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

    public function getAvailableFeatureSubscription($employerId)
    {
        return Subscription::with('plan')
            ->where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereColumn('featured_jobs_used', '<', 'featured_jobs_total')
            ->orderBy('starts_at', 'asc')
            ->first();
    }
}
