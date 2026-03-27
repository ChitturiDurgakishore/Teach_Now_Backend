<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JobSeekerExperience extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'job_seeker_id',
        'job_title',
        'company_name',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description'
    ];
}
