<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationLink extends Model
{
    public $fillable = [
        'slug',
        'url',
        'display_order',
        'is_active',
        'title',
        'meta_description',
        'meta_keywords',
        'meta_title',
        'parent_id',
        'show_in_nav',
        'created_at',
        'updated_at'

    ];

    public function children()
    {
        return $this->hasMany(NavigationLink::class, 'parent_id');
    }
}
