<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class HomepageStat extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'total_jobs',
        'total_companies',
        'total_candidates',
        'total_recruiters',
        'is_active'
    ];
}
