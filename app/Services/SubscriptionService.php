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
        $subscription = Subscription::with('plan')
            ->where('employer_id', $employerId)
            ->latest()
            ->first();

        if (!$subscription) {
            return [
                'status' => false,
                'message' => 'No active subscription found'
            ];
        }

        // ❌ Not active
        if ($subscription->status !== 'active') {
            return [
                'status' => false,
                'message' => 'Subscription is not active'
            ];
        }

        // ❌ Expired
        if ($subscription->expires_at <= now()) {
            return [
                'status' => false,
                'message' => 'Your subscription has expired'
            ];
        }

        // ❌ PLAN DOES NOT SUPPORT FEATURE
        if (!$subscription->plan || !$subscription->plan->company_featured) {
            return [
                'status' => false,
                'message' => 'Your plan does not include company featuring. Please upgrade your plan.'
            ];
        }

        // ❌ LIMIT CHECK (ONLY if feature exists in plan)
        if (
            isset($subscription->featured_jobs_total) &&
            $subscription->featured_jobs_total > 0 &&
            $subscription->featured_jobs_used >= $subscription->featured_jobs_total
        ) {
            return [
                'status' => false,
                'message' => 'You have reached your featured limit. Please upgrade your plan.'
            ];
        }

        return [
            'status' => true,
            'subscription' => $subscription
        ];
    }
}
