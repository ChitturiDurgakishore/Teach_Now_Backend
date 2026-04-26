<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditHistories extends Model
{
    public $table='credit_histories';
    public $fillable = [
        'job_id',
        'employer_id',
        'recruiter_id',
        'subscription_id',
        'type'
    ];
}
