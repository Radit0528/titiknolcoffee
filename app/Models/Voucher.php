<?php

namespace App\Models;

use App\Enums\VoucherType;
use App\Support\Format;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_purchase', 'max_discount',
        'quota', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => VoucherType::class,
            'value' => 'decimal:2',
            'min_purchase' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'quota' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function remainingQuota(): ?int
    {
        return $this->quota === null ? null : max(0, $this->quota - $this->used_count);
    }

    /**
     * Short human description, e.g. "10% (maks Rp10.000)" or "Rp5.000".
     */
    public function describe(): string
    {
        if ($this->type === VoucherType::Percent) {
            $text = Format::percent($this->value);

            return $this->max_discount ? $text.' (maks '.Format::rupiah($this->max_discount).')' : $text;
        }

        return Format::rupiah($this->value);
    }
}
