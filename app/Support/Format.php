<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class Format
{
    /**
     * 18000 → "Rp18.000". Rupiah is shown without decimals.
     */
    public static function rupiah(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $sign = $value < 0 ? '-' : '';

        return $sign.'Rp'.number_format(abs(round($value)), 0, ',', '.');
    }

    /**
     * Unit cost may be fractional (Rp0,25/ml), so keep up to 2 decimals.
     */
    public static function unitCost(float|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $formatted = number_format($value, 2, ',', '.');

        return 'Rp'.rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * 1500.000 → "1.500", 0.500 → "0,5".
     */
    public static function qty(float|string|null $quantity): string
    {
        $formatted = number_format((float) ($quantity ?? 0), 3, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * 10 → "10%", 2.5 → "2,5%".
     */
    public static function percent(float|string|null $value): string
    {
        $formatted = number_format((float) ($value ?? 0), 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',').'%';
    }

    /**
     * Format one report cell by its column type (see ReportService).
     */
    public static function cell(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '–';
        }

        return match ($type) {
            'money' => self::rupiah($value),
            'int' => number_format((float) $value, 0, ',', '.'),
            'qty' => self::qty($value),
            'percent' => number_format((float) $value, 1, ',', '.').'%',
            'date' => Carbon::parse($value)->translatedFormat('D, d M Y'),
            'datetime' => Carbon::parse($value)->format('d/m/Y H:i'),
            default => (string) $value,
        };
    }
}
