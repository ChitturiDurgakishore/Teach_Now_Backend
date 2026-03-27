<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class MediaLibrary extends Model
{
    use SoftDeletes;
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
