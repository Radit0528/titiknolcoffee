<?php

namespace App\Services;

use App\Models\Banner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BannerService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $image): Banner
    {
        $data['image_path'] = $image->store('banners', 'public');
        $banner = Banner::create($data);

        $this->audit->log('banner.created', $banner, [], ['title' => $banner->title]);

        return $banner;
    }

    /**
     * A new image replaces (and deletes) the old file.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Banner $banner, array $data, ?UploadedFile $image = null): Banner
    {
        $oldImage = $banner->image_path;
        $before = $banner->only(['title', 'is_active']);

        if ($image) {
            $data['image_path'] = $image->store('banners', 'public');
        }

        $banner->update($data);

        if ($oldImage !== $banner->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        $this->audit->log('banner.updated', $banner, $before, $banner->only(array_keys($before)));

        return $banner;
    }

    public function delete(Banner $banner): void
    {
        $this->audit->log('banner.deleted', $banner, ['title' => $banner->title]);
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();
    }
}
