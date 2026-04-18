<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSeekerCertifications extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'issuer',
        'issued_at',
        'expires_at'
    ];

    public function jobSeeker()
    {
        return $this->belongsTo(JobSeeker::class);
    }
}
