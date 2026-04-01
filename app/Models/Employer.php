<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Employer extends Authenticatable
{
    use SoftDeletes;
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

    public function documents()
    {
        return $this->hasMany(DocumentVerification::class, 'employer_id');
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
        'password',
        'company_verified',
        'latitude',   // ✅ add
        'longitude',
    ];

    // 🔥 UNIQUE SLUG GENERATOR
    public static function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    protected static function boot()
    {
        parent::boot();

        // 🔥 On Create
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = self::generateUniqueSlug($model->company_name);
            }
        });

        // 🔥 On Update
        static::updating(function ($model) {
            if ($model->isDirty('company_name')) {
                $model->slug = self::generateUniqueSlug($model->company_name);
            }
        });
    }
}
