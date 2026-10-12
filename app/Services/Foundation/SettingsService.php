<?php

namespace App\Services\Foundation;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'wms_settings_all';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key, with optional default.
     * Results are cached for 1 hour.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all();

        return $all[$key] ?? $default;
    }

    /**
     * Persist a setting and clear the cache.
     */
    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        $serialized = match ($type) {
            'json'    => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default   => (string) $value,
        };

        Setting::updateOrCreate(['key' => $key], [
            'value' => $serialized,
            'type'  => $type,
        ]);

        Cache::forget(static::CACHE_KEY);
    }

    /**
     * Get all settings as a key→value array (typed).
     * Cached to avoid repeated DB reads.
     */
    public static function all(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return Setting::all()->mapWithKeys(function ($setting) {
                return [$setting->key => static::cast($setting->value, $setting->type)];
            })->toArray();
        });
    }

    /**
     * Clear the settings cache (call after bulk save).
     */
    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    private static function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => (bool) $value,
            'float'   => (float) $value,
            'json'    => json_decode($value, true),
            default   => (string) $value,
        };
    }
}
