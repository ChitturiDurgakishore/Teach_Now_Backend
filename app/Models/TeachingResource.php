<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\SoftDeletes;
class TeachingResource extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'slug',
        'description',
        'pdf',
        'resource_photo',
        'author_name',
        'author_photo',
        'total_pages',
        'answer_include',
        'read_time',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_visible',
        'is_featured'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (empty($model->slug)) {

                $slug = Str::slug($model->title);
                $count = TeachingResource::where('slug', 'LIKE', "{$slug}%")->count();

                $model->slug = $count ? "{$slug}-{$count}" : $slug;
            }
        });

        static::updating(function ($model) {

            if ($model->isDirty('title')) {

                $slug = Str::slug($model->title);
                $count = TeachingResource::where('slug', 'LIKE', "{$slug}%")
                    ->where('id', '!=', $model->id)
                    ->count();

                $model->slug = $count ? "{$slug}-{$count}" : $slug;
            }
        });
    }
}
