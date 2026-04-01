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
}
