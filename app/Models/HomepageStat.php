<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageStat extends Model
{
    protected $fillable = [
        'total_jobs',
        'total_companies',
        'total_candidates',
        'total_recruiters',
        'is_active'
    ];
}
