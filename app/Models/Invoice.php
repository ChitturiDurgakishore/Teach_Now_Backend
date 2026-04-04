<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'employer_id',
        'order_id',
        'subscription_id',
        'amount',
        'currency',
        'invoice_date'
];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
