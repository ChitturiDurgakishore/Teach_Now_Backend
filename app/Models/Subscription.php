<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Subscription extends Model
{

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
