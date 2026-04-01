<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Subscription extends Model
{

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public $fillable = [
        'employer_id',
        'plan_id',
        'order_id',
        'job_posts_total',
        'job_posts_used',
        'purchase_date',
        'starts_at',
        'expires_at',
        'status'
    ];
}
