<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobQuestion extends Model
{
   public $fillable = [
    'job_id',
    'question_type',
    'question',
    'recruiter_answer'
   ];
}
