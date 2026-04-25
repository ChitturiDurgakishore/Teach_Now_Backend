<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResumeLimitAdmin extends Model
{
    protected $table='resume_limit_admin';
    protected $fillable = [
        'limit'
    ];
}
