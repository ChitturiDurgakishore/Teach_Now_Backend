<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterLink extends Model
{
    public $fillable = [
        'section_id',
        'title',
        'url',
        'order',
        'display_order',
        'is_active',
        'icon'
    ];
    public function section()
    {
        return $this->belongsTo(FooterSection::class, 'section_id');
    }
}
