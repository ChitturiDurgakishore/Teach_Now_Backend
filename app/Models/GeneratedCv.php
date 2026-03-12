<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneratedCv extends Model
{
    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class);
    }
}
