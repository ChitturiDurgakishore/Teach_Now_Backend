<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResourceDownload extends Model
{
    protected $fillable = [
        'user_id',
        'resource_type',
        'resource_id',
        'file_name',
        'file_path',
        'ip_address',
        'user_agent'
    ];
}
