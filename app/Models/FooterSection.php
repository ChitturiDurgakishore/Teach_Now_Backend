<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FooterSection extends Model
{
    use SoftDeletes;
    public $fillable = [
        'title',
        'display_order',
        'is_active',
        'slug',
        'meta_title',
        'meta_description',
        'meta_keywords'
    ];

    public function links()
    {
        return $this->hasMany(FooterLink::class, 'section_id');
    }
}
