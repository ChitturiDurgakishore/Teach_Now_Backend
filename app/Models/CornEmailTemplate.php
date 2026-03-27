<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CornEmailTemplate extends Model
{
    protected $fillable = [
        'type',
        'subject',
        'html_template',
        'is_active'
    ];
}
