<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class NavigationLink extends Model
{
    use SoftDeletes;
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
        return $this->hasMany(NavigationLink::class, 'parent_id')
            ->where('is_active', true)
            ->where('show_in_nav', true)
            ->orderBy('display_order');
    }

    // 🔥 Recursive relation
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
}
