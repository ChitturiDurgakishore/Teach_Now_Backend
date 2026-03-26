<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HomepageTestimonial extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'designation',
        'company',
        'photo',
        'message',
        'display_order',
        'is_active',
        'user_id'
    ];
}
