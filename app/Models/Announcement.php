<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'target_audience',
        'created_by',
        'publish_date',
        'expire_date',
        'is_active',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'expire_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('publish_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expire_date')
                    ->orWhere('expire_date', '>=', now());
            });
    }

    public function scopeForAudience($query, string $audience)
    {
        return $query->where(function ($q) use ($audience) {
            $q->where('target_audience', 'all')
                ->orWhere('target_audience', $audience);
        });
    }
}
