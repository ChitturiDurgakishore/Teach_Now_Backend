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

    // Order.php



    public function subscription()
    {
        // You have order_id in subscription table, so Order HAS ONE subscription
        return $this->hasOne(Subscription::class, 'order_id', 'id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'order_id', 'id');
    }
}
