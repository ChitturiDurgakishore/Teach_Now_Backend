<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
