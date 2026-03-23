<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'author_id',
        'is_featured'
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
}
