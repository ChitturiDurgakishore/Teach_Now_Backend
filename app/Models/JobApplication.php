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

    public function answers()
    {
        return $this->hasMany(JobAnswer::class, 'job_id', 'job_id')
            ->where('job_seeker_id', $this->job_seeker_id);
    }
    // Remove the 'answers' function and keep only this one
    public function jobAnswers()
    {
        // Ensure 'application_id' is the ACTUAL column name in your job_answers table
        return $this->hasMany(JobAnswer::class, 'application_id');
    }
}
