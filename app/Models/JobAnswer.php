<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobAnswer extends Model
{

    public function question()
    {
        return $this->belongsTo(JobQuestion::class, 'job_question_id');
    }
    public function jobApplication()
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    public $fillable = [
        'job_question_id',
        'job_id',
        'job_seeker_id',
        'candidate_answer',
    ];
}
