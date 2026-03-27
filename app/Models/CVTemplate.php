<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CVTemplate extends Model
{
    use SoftDeletes;
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
