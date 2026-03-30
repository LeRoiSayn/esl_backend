<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = ['user_id', 'code', 'type', 'expires_at', 'used_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }

    /**
     * Generate a new OTP for a user (invalidates previous ones of the same type).
     */
    public static function generate(User $user, string $type): self
    {
        // Invalidate previous unused OTPs of the same type
        self::where('user_id', $user->id)
            ->where('type', $type)
            ->whereNull('used_at')
            ->delete();

        return self::create([
            'user_id'    => $user->id,
            'code'       => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'type'       => $type,
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
