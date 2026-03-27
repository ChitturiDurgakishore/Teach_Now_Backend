<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Skill extends Model
{
    use SoftDeletes;
    public function jobSeekers()
    {
        return $this->belongsToMany(JobSeeker::class, 'job_seeker_skills');
    }

    protected $fillable = ['name', 'is_custom','is_active'];
}
