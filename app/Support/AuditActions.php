<?php

namespace App\Support;

/**
 * Human labels for audit_logs.action. Unknown actions fall back to the raw key.
 */
class AuditActions
{
    public const LABELS = [
        'order.voided' => 'Transaksi dibatalkan (void)',
        'shift.closed' => 'Shift ditutup',
        'owner.pin_set' => 'PIN owner dibuat',
        'owner.pin_changed' => 'PIN owner diubah',
        'owner.pin_failed' => 'PIN owner salah dimasukkan',
        'owner.password_changed' => 'Password owner diubah',
        'staff.created' => 'Akun kasir dibuat',
        'staff.updated' => 'Akun kasir diubah',
        'staff.password_reset' => 'Password kasir direset',
        'product.price_changed' => 'Harga produk diubah',
        'product.deleted' => 'Produk dihapus',
        'category.deleted' => 'Kategori dihapus',
        'recipe.updated' => 'Resep diubah',
        'ingredient.cost_changed' => 'Harga modal bahan diubah',
        'ingredient.deleted' => 'Bahan dihapus',
        'voucher.created' => 'Voucher dibuat',
        'voucher.updated' => 'Voucher diubah',
        'voucher.deleted' => 'Voucher dihapus',
        'banner.created' => 'Banner dibuat',
        'banner.updated' => 'Banner diubah',
        'banner.deleted' => 'Banner dihapus',
        'settings.updated' => 'Pengaturan toko diubah',
    ];

    /** Actions shown with a warning colour in the log. */
    public const SENSITIVE = ['order.voided', 'owner.pin_failed', 'staff.password_reset', 'owner.pin_changed', 'owner.password_changed'];

    /** Readable names for stored keys. */
    public const KEYS = [
        'status' => 'status', 'code' => 'kode', 'amount' => 'jumlah', 'reason' => 'alasan', 'approved_by' => 'disetujui',
        'name' => 'nama', 'username' => 'username', 'is_active' => 'aktif', 'title' => 'judul', 'price' => 'harga',
        'cost_per_unit' => 'harga modal', 'type' => 'jenis', 'value' => 'nilai', 'max_discount' => 'maks. diskon',
        'quota' => 'kuota', 'opening_cash' => 'modal awal', 'expected_cash' => 'uang seharusnya',
        'closing_cash' => 'uang fisik', 'cash_variance' => 'selisih', 'notes' => 'catatan',
    ];

    /**
     * Key shown in the log. Recipe logs are keyed by ingredient id, so those
     * are replaced with the ingredient name when known.
     *
     * @param  array<int|string, string>  $ingredientNames
     */
    public static function key(string $action, int|string $key, array $ingredientNames = []): string
    {
        if ($action === 'recipe.updated' && isset($ingredientNames[$key])) {
            return $ingredientNames[$key];
        }

        return self::KEYS[$key] ?? (string) $key;
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    public static function badge(string $action): string
    {
        return in_array($action, self::SENSITIVE, true) ? 'warning' : 'neutral';
    }

    /**
     * Short name of the record type, e.g. App\Models\Order → "Transaksi".
     */
    public static function subject(?string $type): ?string
    {
        return match ($type) {
            null => null,
            'App\Models\Order' => 'Transaksi',
            'App\Models\Shift' => 'Shift',
            'App\Models\User' => 'Pengguna',
            'App\Models\Product' => 'Produk',
            'App\Models\Category' => 'Kategori',
            'App\Models\Recipe' => 'Resep',
            'App\Models\Ingredient' => 'Bahan',
            'App\Models\Voucher' => 'Voucher',
            'App\Models\Banner' => 'Banner',
            'App\Models\Setting' => 'Pengaturan',
            default => class_basename($type),
        };
    }

    /**
     * Render one stored value for the log table.
     */
    public static function value(mixed $value): string
    {
        return match (true) {
            $value === null => '–',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // "180.000" (3 decimals from the database) would read as 180 thousand in Indonesian.
            is_int($value), is_float($value), is_string($value) && is_numeric($value) => Format::qty($value),
            default => (string) $value,
        };
    }
}
