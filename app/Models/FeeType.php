<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'amount',
        'is_mandatory',
        'is_active',
        'level',
        'category',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function studentFees()
    {
        return $this->hasMany(StudentFee::class);
    }
}
