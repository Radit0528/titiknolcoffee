<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'image_path', 'starts_at', 'ends_at', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Active banners whose period includes now, in display order.
     */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->orderByDesc('id');
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Status shown to the owner: aktif / terjadwal / berakhir / nonaktif.
     *
     * @return array{0: string, 1: string} label and badge variant
     */
    public function status(): array
    {
        return match (true) {
            ! $this->is_active => ['Nonaktif', 'neutral'],
            $this->starts_at?->isFuture() ?? false => ['Terjadwal', 'warning'],
            $this->ends_at?->isPast() ?? false => ['Berakhir', 'danger'],
            default => ['Tayang', 'success'],
        };
    }
}
