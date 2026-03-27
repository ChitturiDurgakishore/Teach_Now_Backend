<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSetting extends Model
{
    public $fillable = [
        'type',
        'day',
        'time',
        'is_active',
        'last_sent_at'
    ];

    protected $casts = [
        'last_sent_at' => 'datetime',
    ];
}
