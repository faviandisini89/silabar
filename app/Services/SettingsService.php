<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'silabar.settings';

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $stored = Setting::query()->pluck('value', 'name')->map(fn ($v) => json_decode((string) $v, true))->all();

            return array_replace(config('silabar.settings'), $stored);
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['name' => $key], ['value' => json_encode($value)]);
        Cache::forget(self::CACHE_KEY);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }
}
