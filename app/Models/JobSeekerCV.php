<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobSeekerCV extends Model
{
    use SoftDeletes;
    public $table= 'job_seeker_cvs';
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
