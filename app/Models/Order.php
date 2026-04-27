<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    public $fillable = [
        'employer_id',
        'plan_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount',
        'status',
        'currency',
        'receipt',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'subscription_id', 'subscription_id');
    }
}
