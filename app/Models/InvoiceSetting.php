<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    protected $fillable = [
        'company_name',
        'email',
        'phone',
        'address',
        'gst_number',
        'logo',
        'footer'
    ];
}
