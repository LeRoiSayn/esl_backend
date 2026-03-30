<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'accent_color',
        'font_size',
        'dashboard_widgets',
        'language',
        'email_notifications',
        'push_notifications',
        'sms_notifications',
    ];

    protected $casts = [
        'dashboard_widgets' => 'array',
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'sms_notifications' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getDefaults()
    {
        return [
            'theme' => 'auto',
            'accent_color' => 'green',
            'font_size' => 100,
            'dashboard_widgets' => ['calendar', 'recent_grades', 'upcoming_courses', 'announcements'],
            'language' => 'fr',
            'email_notifications' => true,
            'push_notifications' => true,
            'sms_notifications' => false,
        ];
    }
}
