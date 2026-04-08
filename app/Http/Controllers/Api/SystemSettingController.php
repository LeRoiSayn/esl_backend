<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemSettingController extends Controller
{
    /**
     * GET /api/system-settings
     * Returns all settings grouped by group.
     */
    public function index()
    {
        return $this->success([
            'grouped' => SystemSetting::allGrouped(),
            'flat'    => SystemSetting::allKeyed(),
        ]);
    }

    /**
     * PUT /api/system-settings
     * Update one or many settings at once.
     * Body: { "institution_name": "...", "grading_max_score": 20, ... }
     */
    public function update(Request $request)
    {
        $data = $request->except(['logo']); // logo handled separately

        foreach ($data as $key => $value) {
            $setting = SystemSetting::where('key', $key)->first();
            if ($setting) {
                $castValue = match ($setting->type) {
                    'json'    => is_array($value) ? json_encode($value) : $value,
                    'boolean' => $value ? '1' : '0',
                    default   => (string) $value,
                };
                $setting->update(['value' => $castValue]);
            }
        }

        ActivityLog::log('update', 'Updated system settings', null);

        return $this->success(SystemSetting::allKeyed(), 'Paramètres mis à jour');
    }

    /**
     * POST /api/system-settings/logo
     * Upload institution logo.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $file = $request->file('logo');
        $filename = 'institution_logo.' . $file->getClientOriginalExtension();
        $file->move(public_path(), $filename);

        // Update the logo path setting
        $setting = SystemSetting::where('key', 'institution_logo')->first();
        if ($setting) {
            $setting->update(['value' => $filename]);
        }

        ActivityLog::log('update', 'Updated institution logo', null);

        return $this->success(['filename' => $filename], 'Logo mis à jour');
    }

    /**
     * GET /api/system-settings/public
     * Public endpoint — returns safe settings (no auth required).
     */
    public function publicSettings()
    {
        $keys = ['institution_name', 'institution_logo', 'currency', 'currency_symbol', 'current_academic_year', 'fee_categories', 'grade_letter_thresholds', 'grade_weight_attendance', 'grade_weight_quiz', 'grade_weight_ca', 'grade_weight_exam'];
        $all = SystemSetting::allKeyed(); // already cast
        $settings = collect($keys)
            ->filter(fn($k) => array_key_exists($k, $all))
            ->mapWithKeys(fn($k) => [$k => $all[$k]]);

        return $this->success($settings);
    }
}
