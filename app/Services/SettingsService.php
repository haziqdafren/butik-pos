<?php

namespace App\Services;

use App\Models\StoreSettings;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'store_settings';
    private const TTL = 3600;

    public function defaults(): array
    {
        return [
            'store_name'         => '',
            'store_address'      => '',
            'store_phone'        => '',
            'receipt_footer'     => 'Terima kasih telah berbelanja!',
            'owner_email'        => '',
            'store_open_time'    => '08:00',
            'store_close_time'   => '18:00',
            'auto_print_receipt' => '1',
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $val = $this->all()[$key] ?? null;

        if (($val === null || $val === '') && $default !== null) {
            return $default;
        }

        return $val;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $all = $this->all();

        if (!array_key_exists($key, $all) || $all[$key] === null || $all[$key] === '') {
            return $default;
        }

        return filter_var($all[$key], FILTER_VALIDATE_BOOLEAN);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function (): array {
            $rows = StoreSettings::query()->pluck('value', 'key')->toArray();
            return array_merge($this->defaults(), $rows);
        });
    }

    public function set(string $key, ?string $value): void
    {
        StoreSettings::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->clearCache();
    }

    public function setMany(array $data): void
    {
        foreach ($data as $key => $value) {
            StoreSettings::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
