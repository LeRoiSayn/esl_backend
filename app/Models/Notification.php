<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->read_at = now();
        $this->save();
        return $this;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Create a notification for a specific user
     */
    public static function notify(int $userId, string $type, string $title, string $message, ?string $link = null, ?array $data = null): self
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'data' => $data,
        ]);
    }

    /**
     * Create notifications for multiple users
     */
    public static function notifyMany(array $userIds, string $type, string $title, string $message, ?string $link = null, ?array $data = null): void
    {
        $records = [];
        $now = now();
        foreach ($userIds as $userId) {
            $records[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'data' => json_encode($data),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        static::insert($records);
    }
}
