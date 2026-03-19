<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    public function jobSeekers()
    {
        return $this->belongsToMany(JobSeeker::class, 'job_seeker_skills');
    }

    protected $fillable = ['name', 'is_custom','is_active'];
}
