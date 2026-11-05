<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\BannerRequest;
use App\Models\Banner;
use App\Services\BannerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Promo banners shown on the cashier's POS screen.
 */
class BannerController extends Controller
{
    public function __construct(private BannerService $banners) {}

    public function index(): View
    {
        return view('owner.banners.index', [
            'banners' => Banner::orderBy('sort_order')->orderByDesc('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('owner.banners.form', ['banner' => new Banner(['is_active' => true, 'sort_order' => 0])]);
    }

    public function store(BannerRequest $request): RedirectResponse
    {
        $banner = $this->banners->create($request->bannerData(), $request->file('image'));

        return redirect()->route('owner.banners.index')->with('success', "Banner \"{$banner->title}\" dibuat.");
    }

    public function edit(Banner $banner): View
    {
        return view('owner.banners.form', ['banner' => $banner]);
    }

    public function update(BannerRequest $request, Banner $banner): RedirectResponse
    {
        $this->banners->update($banner, $request->bannerData(), $request->file('image'));

        return redirect()->route('owner.banners.index')->with('success', 'Banner diperbarui.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->banners->delete($banner);

        return redirect()->route('owner.banners.index')->with('success', 'Banner dihapus.');
    }
}
