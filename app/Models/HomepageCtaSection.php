<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageCtaSection extends Model
{

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'button_link',
        'background_image',
        'is_active'
    ];
}
