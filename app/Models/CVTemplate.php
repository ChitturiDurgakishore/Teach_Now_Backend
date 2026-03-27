<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CVTemplate extends Model
{

    public $table = 'cv_templates';
    protected $fillable = [
        'name',
        'html_template',
        'preview_image',
        'is_active',
        'key_values',
        'preview_image'
    ];
}
