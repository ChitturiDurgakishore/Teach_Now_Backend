<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Payment extends Model
{
    use SoftDeletes;
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
