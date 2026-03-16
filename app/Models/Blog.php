<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    public $fillable = [
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
}
