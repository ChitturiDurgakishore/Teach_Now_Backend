<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;

class Employer extends Authenticatable
{
    public function users()
    {
        return $this->hasMany(EmployerUser::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function employerUsers()
    {
        return $this->hasMany(EmployerUser::class, 'employer_id');
    }
    protected $fillable = [

        'company_name',
        'company_description',
        'industry',
        'website',
        'company_logo',
        'address',
        'email',
        'phone',
        'country',
        'city',
        'map_link',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_verified',
        'is_featured',
        'password'
    ];
}
