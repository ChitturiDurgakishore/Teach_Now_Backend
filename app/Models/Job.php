<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    //
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function creator()
    {
        return $this->belongsTo(EmployerUser::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function jobApplications()
    {
        // This links the 'id' of this job to the 'job_id' in the job_applications table
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    public $fillable = [
        'employer_id',
        'created_by',
        'category_id',
        'title',
        'description',
        'salary_min',
        'salary_max',
        'vacancies',
        'location',
        'experience_required',
        'job_type',
        'job_status',
        'status',
        'featured',
        'admin_featured',
        'application_deadline'
    ];
}
