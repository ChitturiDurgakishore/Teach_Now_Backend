<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CVTemplate extends Model
{
    protected $fillable = [
        'name',
        'html_template',
        'preview_image',
        'is_active',
        'key_values'
    ];
}
