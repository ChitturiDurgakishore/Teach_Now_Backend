<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FooterLink extends Model
{
    use SoftDeletes;
    public $fillable = [
        'section_id',
        'title',
        'url',
        'order',
        'display_order',
        'is_active',
        'icon',
        'slug'
    ];
    public function section()
    {
        return $this->belongsTo(FooterSection::class, 'section_id');
    }
}
