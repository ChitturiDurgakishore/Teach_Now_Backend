<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class DocumentVerification extends Model
{
    use SoftDeletes;
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
