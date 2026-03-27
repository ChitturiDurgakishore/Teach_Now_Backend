<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class JobQuestion extends Model
{
    use SoftDeletes;
   public $fillable = [
    'job_id',
    'question_type',
    'question',
    'recruiter_answer'
   ];
}
