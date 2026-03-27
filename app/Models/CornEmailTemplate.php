<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class CornEmailTemplate extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'type',
        'subject',
        'html_template',
        'is_active'
    ];
}
