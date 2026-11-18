<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Portrait promo video that autoplays on the public landing page.
 */
class MenuVideo extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'caption', 'video_path', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Active videos in display order.
     */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)->ordered();
    }

    public function videoUrl(): string
    {
        return Storage::disk('public')->url($this->video_path);
    }
}
