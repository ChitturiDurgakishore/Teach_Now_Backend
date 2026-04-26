<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditHistories extends Model
{
    public $table = 'credit_histories';
    public $fillable = [
        'job_id',
        'employer_id',
        'recruiter_id',
        'subscription_id',
        'type'
    ];

    public function job()
    {
        return $this->belongsTo(\App\Models\Job::class, 'job_id');
    }

    public function recruiter()
    {
        return $this->belongsTo(\App\Models\EmployerUser::class, 'recruiter_id');
    }

    public function employer()
    {
        return $this->belongsTo(\App\Models\Employer::class, 'employer_id');
    }

    public function subscription()
    {
        return $this->belongsTo(\App\Models\Subscription::class, 'subscription_id');
    }
}
