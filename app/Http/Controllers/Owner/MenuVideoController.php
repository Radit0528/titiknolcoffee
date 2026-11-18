<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\MenuVideoRequest;
use App\Models\MenuVideo;
use App\Services\MenuVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Promo videos that autoplay on the public landing page.
 */
class MenuVideoController extends Controller
{
    public function __construct(private MenuVideoService $videos) {}

    public function index(): View
    {
        return view('owner.videos.index', ['videos' => MenuVideo::ordered()->get()]);
    }

    public function create(): View
    {
        return view('owner.videos.form', ['video' => new MenuVideo(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(MenuVideoRequest $request): RedirectResponse
    {
        $video = $this->videos->create($request->videoData(), $request->file('video'));

        return redirect()->route('owner.videos.index')->with('success', "Video \"{$video->title}\" ditambahkan.");
    }

    public function edit(MenuVideo $video): View
    {
        return view('owner.videos.form', ['video' => $video]);
    }

    public function update(MenuVideoRequest $request, MenuVideo $video): RedirectResponse
    {
        $this->videos->update($video, $request->videoData(), $request->file('video'));

        return redirect()->route('owner.videos.index')->with('success', 'Video diperbarui.');
    }

    public function destroy(MenuVideo $video): RedirectResponse
    {
        $this->videos->delete($video);

        return redirect()->route('owner.videos.index')->with('success', 'Video dihapus.');
    }
}
