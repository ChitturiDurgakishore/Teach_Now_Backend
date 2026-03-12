<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resume extends Model
{
    //
    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public $fillable = [
        'job_seeker_id',
        'file_name',
        'file_url',
        'is_default',
        'slug'
    ];
}
