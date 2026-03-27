<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class HomepageCtaSection extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'button_link',
        'background_image',
        'is_active'
    ];
}
