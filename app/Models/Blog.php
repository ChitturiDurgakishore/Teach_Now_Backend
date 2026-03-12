<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
