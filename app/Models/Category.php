<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
class Category extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_visible',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_featured'
    ];

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }

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
                $model->slug = self::generateUniqueSlug($model->name);
            }
        });

        // 🔥 On Update
        static::updating(function ($model) {
            if ($model->isDirty('name')) {
                $model->slug = self::generateUniqueSlug($model->name);
            }
        });
    }
}
