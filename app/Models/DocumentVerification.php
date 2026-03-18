<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVerification extends Model
{
    protected $fillable = [
        'employer_id',
        'document_name',
        'document_file',
        'is_verified',
        'status',
        'admin_remark'
    ];

    public function employer()
    {
        return $this->belongsTo(Employer::class);
    }
}
