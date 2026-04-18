<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\JobSeekerCertifications;

class JobSeeker extends Model
{
    //
    use SoftDeletes;
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

    public function educations()
    {
        return $this->hasMany(JobSeekerEducation::class);
    }

    public function certifications()
    {
        return $this->hasMany(JobSeekerCertifications::class, 'user_id', 'user_id');
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
        'slug',
        'gender',
        'notice_period'
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'job_seeker_skills');
    }
    public function experiences()
    {
        return $this->hasMany(JobSeekerExperience::class)->latest();
    }
}
