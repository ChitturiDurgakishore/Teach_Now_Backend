<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutUsSection extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'content',
        'display_order',
        'is_active'
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
