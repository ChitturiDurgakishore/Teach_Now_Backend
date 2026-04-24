<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobRepublishHistory extends Model
{
    public $table = 'job_republish_histories';
    protected $fillable = [
        'job_id',
        'subscription_id',
        'user_id',
        'employer_id',
    ];
}
