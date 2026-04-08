<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    /**
     * Get a setting value by key, with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) return $default;
        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::where('key', $key)->update(['value' => $value]);
    }

    /**
     * Get all settings as a flat key => value array.
     */
    public static function allKeyed(): array
    {
        return static::all()->mapWithKeys(function ($s) {
            return [$s->key => static::castValue($s->value, $s->type)];
        })->toArray();
    }

    /**
     * Get all settings grouped by group.
     */
    public static function allGrouped(): array
    {
        $grouped = [];
        foreach (static::orderBy('group')->orderBy('key')->get() as $s) {
            $grouped[$s->group][] = array_merge($s->toArray(), [
                'value' => static::castValue($s->value, $s->type),
            ]);
        }
        return $grouped;
    }

    private static function castValue(mixed $value, string $type): mixed
    {
        if ($value === null) return null;
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
