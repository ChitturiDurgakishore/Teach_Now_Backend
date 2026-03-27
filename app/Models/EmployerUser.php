<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class EmployerUser extends Authenticatable
{

    use SoftDeletes;
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class, 'created_by');
    }

    public  $fillable = [
        'employer_id',
        'name',
        'email',
        'password',
        'is_active'
    ];
}
