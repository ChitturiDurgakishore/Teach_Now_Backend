<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyPolicySections extends Model
{
    protected $table = 'privacy_policy_sections';

    protected $fillable = [
        'title',
        'content',
        'order',
        'display_order',
        'is_active',
        'parent_id',
    ];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
