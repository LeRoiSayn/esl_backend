<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradeModification extends Model
{
    use HasFactory;

    protected $fillable = [
        'grade_id',
        'modified_by',
        'old_value',
        'new_value',
        'reason',
        'ip_address',
    ];

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
