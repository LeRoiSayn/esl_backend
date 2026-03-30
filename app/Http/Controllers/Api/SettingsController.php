<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Get user settings
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();
        
        $settings = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            UserSetting::getDefaults()
        );

        return response()->json(['settings' => $settings]);
    }

    /**
     * Update user settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'theme' => 'nullable|in:light,dark,auto',
            'accent_color' => 'nullable|string|max:20',
            'font_size' => 'nullable|integer|min:75|max:150',
            'dashboard_widgets' => 'nullable|array',
            'language' => 'nullable|in:fr,en',
            'email_notifications' => 'nullable|boolean',
            'push_notifications' => 'nullable|boolean',
            'sms_notifications' => 'nullable|boolean',
        ]);

        $user = $request->user();
        
        $settings = UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            array_filter($request->only([
                'theme',
                'accent_color',
                'font_size',
                'dashboard_widgets',
                'language',
                'email_notifications',
                'push_notifications',
                'sms_notifications',
            ]), fn($value) => $value !== null)
        );

        return response()->json([
            'message' => 'Paramètres mis à jour',
            'settings' => $settings,
        ]);
    }

    /**
     * Reset settings to defaults
     */
    public function resetSettings(Request $request)
    {
        $user = $request->user();
        
        $settings = UserSetting::updateOrCreate(
            ['user_id' => $user->id],
            UserSetting::getDefaults()
        );

        return response()->json([
            'message' => 'Paramètres réinitialisés',
            'settings' => $settings,
        ]);
    }

    /**
     * Get available widgets for dashboard
     */
    public function getAvailableWidgets(Request $request)
    {
        $user = $request->user();
        
        $widgets = [
            ['id' => 'calendar', 'name' => 'Calendrier', 'icon' => 'calendar', 'roles' => ['all']],
            ['id' => 'recent_grades', 'name' => 'Notes récentes', 'icon' => 'chart', 'roles' => ['student']],
            ['id' => 'upcoming_courses', 'name' => 'Prochains cours', 'icon' => 'clock', 'roles' => ['student', 'teacher']],
            ['id' => 'announcements', 'name' => 'Annonces', 'icon' => 'megaphone', 'roles' => ['all']],
            ['id' => 'quick_stats', 'name' => 'Statistiques rapides', 'icon' => 'stats', 'roles' => ['admin', 'finance']],
            ['id' => 'attendance', 'name' => 'Présences', 'icon' => 'users', 'roles' => ['teacher']],
            ['id' => 'fee_status', 'name' => 'Statut des frais', 'icon' => 'money', 'roles' => ['student']],
            ['id' => 'pending_assignments', 'name' => 'Devoirs en attente', 'icon' => 'document', 'roles' => ['student']],
            ['id' => 'submissions_to_grade', 'name' => 'À corriger', 'icon' => 'check', 'roles' => ['teacher']],
        ];

        // Filter by user role
        $availableWidgets = collect($widgets)->filter(function ($widget) use ($user) {
            return in_array('all', $widget['roles']) || in_array($user->role, $widget['roles']);
        })->values();

        return response()->json(['widgets' => $availableWidgets]);
    }

    /**
     * Public settings for anonymous visitors (no auth required)
     */
    public function publicSettings()
    {
        $locales = ['fr', 'en'];
        $default = config('app.locale', 'fr');

        $widgets = [
            ['id' => 'calendar', 'name' => 'Calendrier', 'icon' => 'calendar', 'roles' => ['all']],
            ['id' => 'recent_grades', 'name' => 'Notes récentes', 'icon' => 'chart', 'roles' => ['student']],
            ['id' => 'upcoming_courses', 'name' => 'Prochains cours', 'icon' => 'clock', 'roles' => ['student', 'teacher']],
            ['id' => 'announcements', 'name' => 'Annonces', 'icon' => 'megaphone', 'roles' => ['all']],
            ['id' => 'quick_stats', 'name' => 'Statistiques rapides', 'icon' => 'stats', 'roles' => ['admin', 'finance']],
            ['id' => 'attendance', 'name' => 'Présences', 'icon' => 'users', 'roles' => ['teacher']],
            ['id' => 'fee_status', 'name' => 'Statut des frais', 'icon' => 'money', 'roles' => ['student']],
            ['id' => 'pending_assignments', 'name' => 'Devoirs en attente', 'icon' => 'document', 'roles' => ['student']],
            ['id' => 'submissions_to_grade', 'name' => 'À corriger', 'icon' => 'check', 'roles' => ['teacher']],
        ];

        // only return widgets available to 'all' users for public listing
        $availableWidgets = collect($widgets)->filter(function ($widget) {
            return in_array('all', $widget['roles']);
        })->values();

        return response()->json([
            'settings' => [
                'default_language' => $default,
                'locales' => $locales,
            ],
            'widgets' => $availableWidgets,
        ]);
    }
}
