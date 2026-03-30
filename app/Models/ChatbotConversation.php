<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'messages',
        'context',
    ];

    protected $casts = [
        'messages' => 'array',
        'context' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addMessage($role, $content)
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];
        $this->messages = $messages;
        $this->save();
        return $this;
    }

    public function updateContext($key, $value)
    {
        $context = $this->context ?? [];
        $context[$key] = $value;
        $this->context = $context;
        $this->save();
        return $this;
    }
}
