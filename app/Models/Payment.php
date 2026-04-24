<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
    protected $fillable = [
        'employer_id',
        'subscription_id',
        'amount',
        'payment_method',
        'payment_status',
        'transaction_id'
    ];

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'subscription_id', 'subscription_id');
    }
}
