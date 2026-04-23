<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use SoftDeletes;
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function creator()
    {
        return $this->belongsTo(EmployerUser::class, 'created_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function applications()
    {
        return $this->hasMany(JobApplication::class);
    }

    public function jobApplications()
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    public function questions()
    {
        return $this->hasMany(JobQuestion::class, 'job_id');
    }

    protected $fillable = [
        'employer_id',
        'created_by',
        'category_id',
        'title',
        'description',
        'salary_min',
        'salary_max',
        'vacancies',
        'location',
        'experience_required',
        'job_type',
        'job_status',
        'status',
        'featured',
        'featured_until',
        'admin_featured',
        'application_deadline',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'keywords',
        'gender',
        'experience_type',
        'expires_at',
        'is_active',
    ];

    // 🔥 UNIQUE SLUG GENERATOR
    public static function generateUniqueSlug($title)
    {
        $slug = Str::slug($title);
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
                $model->slug = self::generateUniqueSlug($model->title);
            }
        });

        // 🔥 On Update
        static::updating(function ($model) {
            if ($model->isDirty('title')) {
                $model->slug = self::generateUniqueSlug($model->title);
            }
        });
    }

    protected $casts = [
        'featured_until' => 'datetime',
    ];

}
