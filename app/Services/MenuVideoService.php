<?php

namespace App\Services;

use App\Models\MenuVideo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MenuVideoService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, UploadedFile $video): MenuVideo
    {
        $data['video_path'] = $video->store('menu-videos', 'public');
        $menuVideo = MenuVideo::create($data);

        $this->audit->log('video.created', $menuVideo, [], ['title' => $menuVideo->title]);

        return $menuVideo;
    }

    /**
     * A new file replaces the old one; the old file is deleted only after the
     * new path is saved.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MenuVideo $menuVideo, array $data, ?UploadedFile $video = null): MenuVideo
    {
        $oldPath = $menuVideo->video_path;
        $before = $menuVideo->only(['title', 'is_active']);

        if ($video) {
            $data['video_path'] = $video->store('menu-videos', 'public');
        }

        $menuVideo->update($data);

        if ($oldPath !== $menuVideo->video_path) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->audit->log('video.updated', $menuVideo, $before, $menuVideo->only(array_keys($before)));

        return $menuVideo;
    }

    public function delete(MenuVideo $menuVideo): void
    {
        $this->audit->log('video.deleted', $menuVideo, ['title' => $menuVideo->title]);
        Storage::disk('public')->delete($menuVideo->video_path);
        $menuVideo->delete();
    }
}
