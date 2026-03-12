<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookmarkedJob extends Model
{
    public $fillable = [
        'job_seeker_id',
        'job_id',
        'slug'
    ];
    public function job()
    {
        return $this->belongsTo(Job::class, 'job_id');
    }
}
