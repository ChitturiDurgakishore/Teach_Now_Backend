<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmailSetting extends Model
{
    use SoftDeletes;
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
