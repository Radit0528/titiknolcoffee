<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Shop settings (store identity, receipt, tax, service charge, cash rounding).
 * Values are cached and the cache is cleared whenever settings are saved.
 */
class SettingService
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, string|int|float|null> */
    public const DEFAULTS = [
        'store_name' => 'Kopi Titik Nol',
        'store_address' => '',
        'store_phone' => '',
        'store_logo' => null,
        'receipt_footer' => 'Terima kasih, sampai jumpa lagi!',
        'receipt_width' => 58,
        'tax_percent' => 0,
        'service_charge_percent' => 0,
        'cash_rounding' => 0,
    ];

    /** Cash totals can be rounded to the nearest of these (0 = no rounding). */
    public const ROUNDING_OPTIONS = [0, 100, 500, 1000];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function taxRate(): float
    {
        return (float) $this->get('tax_percent');
    }

    public function serviceChargeRate(): float
    {
        return (float) $this->get('service_charge_percent');
    }

    public function cashRounding(): int
    {
        return (int) $this->get('cash_rounding');
    }

    public function logoUrl(): ?string
    {
        $path = $this->get('store_logo');

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Save several settings at once and audit what changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        $values = array_intersect_key($values, self::DEFAULTS);
        $before = $this->all();

        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
            }
        });

        Cache::forget(self::CACHE_KEY);

        $changed = array_filter($values, fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) ($value ?? ''), ARRAY_FILTER_USE_BOTH);

        if ($changed) {
            $this->audit->log('settings.updated', null, array_intersect_key($before, $changed), $changed);
        }
    }
}
