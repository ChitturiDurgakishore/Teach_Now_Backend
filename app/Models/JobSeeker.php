<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeeker extends Model
{
    //
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resumes()
    {
        return $this->hasMany(Resume::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function bookmarks()
    {
        return $this->hasMany(BookmarkedJob::class);
    }

    public function generatedCvs()
    {
        return $this->hasMany(GeneratedCv::class);
    }
    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class, 'job_seeker_id');
    }

    protected $fillable = [
        'user_id',
        'title',
        'phone',
        'location',
        'experience_years',
        'availability',
        'dob',
        'portfolio_website',
        'bio',
        'profile_photo',
        'slug'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'job_seeker_skills');
    }
}
