<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prompt extends Model
{
    public $table = 'prompts';
    protected $fillable = [
        'key',
        'title',
        'prompt',
        'is_active'
    ];
}
