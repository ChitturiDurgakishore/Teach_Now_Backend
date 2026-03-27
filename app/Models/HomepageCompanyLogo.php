<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class HomepageCompanyLogo extends Model
{
    use SoftDeletes;
    public $fillable = [
        'company_name',
        'company_logo',
        'slug',
        'company_url',
        'display_order',
        'is_active',
        'meta_description',
        'meta_keywords',
        'meta_title'
    ];
}
