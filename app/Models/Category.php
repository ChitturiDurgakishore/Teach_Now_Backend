<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug','icon', 'is_visible', 'meta_title', 'meta_description', 'meta_keywords','is_featured'];
}
