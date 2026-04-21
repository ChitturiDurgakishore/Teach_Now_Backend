<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobApplication extends Model
{
    use SoftDeletes;
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

    public function cv()
    {
        return $this->belongsTo(JobSeekerCV::class, 'resume_id', 'id');
    }

    public $fillable = [
        'job_id',
        'job_seeker_id',
        'resume_id',
        'cover_letter',
        'status',
        'slug',
        'contact_status',
        'resume_type'
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

    public function applicationAnswers()
    {

        return $this->hasMany(JobAnswer::class, 'job_id', 'job_id');
    }

    public function applicationAnswersForViewApplication()
    {
        return $this->hasMany(JobAnswer::class, 'job_id', 'job_id')
            ->whereColumn('job_answers.job_seeker_id', 'job_applications.job_seeker_id');
    }
}
