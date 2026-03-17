<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageTestimonial extends Model
{
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
