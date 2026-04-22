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

    public function consumeJobCredit($employerId)
    {
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
    }

    public function getAvailableFeatureSubscription($employerId)
    {
        // 1. Check any subscription exists
        $subscription = Subscription::where('employer_id', $employerId)
            ->latest()
            ->first();

        if (!$subscription) {
            return [
                'status' => false,
                'message' => 'No subscription found'
            ];
        }

        // 2. Check active
        if ($subscription->status !== 'active') {
            return [
                'status' => false,
                'message' => 'Subscription is not active'
            ];
        }

        // 3. Check expiry
        if ($subscription->expires_at <= now()) {
            return [
                'status' => false,
                'message' => 'Subscription has expired'
            ];
        }

        // 4. Check plan feature
        if (!$subscription->plan || !$subscription->plan->company_featured) {
            return [
                'status' => false,
                'message' => 'Your plan does not include company featuring'
            ];
        }

        // 5. (Optional) If you add limit later
        if (
            isset($subscription->featured_jobs_total) &&
            $subscription->featured_jobs_used >= $subscription->featured_jobs_total
        ) {
            return [
                'status' => false,
                'message' => 'Featured limit reached'
            ];
        }

        return [
            'status' => true,
            'subscription' => $subscription
        ];
    }
}
