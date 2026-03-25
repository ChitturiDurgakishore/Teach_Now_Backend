<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerExperience extends Model
{
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
