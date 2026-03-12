<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    //
    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }

    public function resume()
    {
        return $this->belongsTo(Resume::class);
    }

    public $fillable = [
        'job_id',
        'job_seeker_id',
        'resume_id',
        'cover_letter',
        'status',
        'slug'
    ];
}
