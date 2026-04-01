<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public $fillable = [
        'name',
        'actual_price',
        'offer_price',
        'job_posts_limit',
        'validity_days',
        'job_live_days',
        'features',
        'is_active'
    ];

    protected $casts = [
        'features' => 'array',
    ];
}
