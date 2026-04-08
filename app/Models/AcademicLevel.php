<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicLevel extends Model
{
    protected $fillable = ['code', 'label', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'order'     => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Return an array of active level codes for validation rules.
     */
    public static function activeCodes(): array
    {
        return static::active()->pluck('code')->toArray();
    }
}
