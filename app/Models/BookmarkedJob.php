<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookmarkedJob extends Model
{
    use SoftDeletes;
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
