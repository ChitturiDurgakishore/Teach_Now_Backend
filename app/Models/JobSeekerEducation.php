<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerEducation extends Model
{
    protected $fillable = [
        'job_seeker_id',
        'degree',
        'institution',
        'field_of_study',
        'start_year',
        'end_year',
        'grade',
        'is_current'
    ];

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }
}
