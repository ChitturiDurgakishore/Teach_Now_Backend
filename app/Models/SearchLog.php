<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class SearchLog extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'keyword',
        'location',
        'ip_address',
        'user_id'
    ];
}
