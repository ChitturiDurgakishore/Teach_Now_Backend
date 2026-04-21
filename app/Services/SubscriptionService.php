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
        return Subscription::where('employer_id', $employerId)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->whereColumn('featured_jobs_used', '<', 'featured_jobs_total')
            ->orderBy('starts_at', 'asc') // oldest first
            ->lockForUpdate()
            ->first();
    }
}
