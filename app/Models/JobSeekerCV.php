<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerCV extends Model
{
    protected $fillable = [
        'job_seeker_id',
        'title',
        'content',
        'pdf_path',
        'is_default'
    ];

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }
}
