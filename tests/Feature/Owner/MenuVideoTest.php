<?php

namespace Tests\Feature\Owner;

use App\Models\AuditLog;
use App\Models\MenuVideo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function mp4(string $name = 'promo.mp4', int $kb = 500): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'video/mp4');
    }

    public function test_owner_uploads_replaces_and_deletes_a_video(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/videos', [
            'title' => 'Es Kopi Susu Aren', 'caption' => 'Andalan kami', 'video' => $this->mp4('a.mp4'), 'is_active' => '1', 'sort_order' => '1',
        ])->assertRedirect('/owner/videos');

        $video = MenuVideo::sole();
        $this->assertStringStartsWith('menu-videos/', $video->video_path);
        Storage::disk('public')->assertExists($video->video_path);
        $first = $video->video_path;

        $this->actingAs($owner)->get('/owner/videos')->assertOk()->assertSee('Es Kopi Susu Aren')->assertSee('Tayang');

        // Saving without a new file keeps the video.
        $this->actingAs($owner)->put("/owner/videos/{$video->id}", ['title' => 'Kopi Susu Aren', 'is_active' => '0'])->assertRedirect('/owner/videos');
        $video->refresh();
        $this->assertSame($first, $video->video_path);
        $this->assertFalse($video->is_active);

        $this->actingAs($owner)->put("/owner/videos/{$video->id}", ['title' => 'Kopi Susu Aren', 'video' => $this->mp4('b.mp4'), 'is_active' => '1'])->assertRedirect();
        $video->refresh();
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($video->video_path);

        $this->actingAs($owner)->delete("/owner/videos/{$video->id}")->assertRedirect('/owner/videos');
        $this->assertSame(0, MenuVideo::count());
        Storage::disk('public')->assertMissing($video->video_path);

        $this->assertSame(['video.created', 'video.deleted', 'video.updated', 'video.updated'], AuditLog::orderBy('action')->pluck('action')->all());
    }

    public function test_only_mp4_up_to_limit_and_title_required(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/videos', ['title' => 'Mov', 'video' => UploadedFile::fake()->create('clip.mov', 500, 'video/quicktime')])
            ->assertSessionHasErrors(['video' => 'Video harus berformat MP4.']);

        $this->actingAs($owner)->post('/owner/videos', ['title' => 'Besar', 'video' => $this->mp4('big.mp4', 21000)])
            ->assertSessionHasErrors(['video' => 'Ukuran video maksimal 20 MB.']);

        $this->actingAs($owner)->post('/owner/videos', ['title' => '', 'video' => $this->mp4()])->assertSessionHasErrors('title');
        $this->actingAs($owner)->post('/owner/videos', ['title' => 'Tanpa file'])->assertSessionHasErrors('video');

        $this->assertSame(0, MenuVideo::count());
    }

    public function test_cashier_cannot_manage_videos(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get('/owner/videos')->assertForbidden();
        $this->actingAs($cashier)->post('/owner/videos', ['title' => 'x', 'video' => $this->mp4()])->assertForbidden();
        $this->assertSame(0, MenuVideo::count());
    }

    public function test_videos_are_not_tied_to_products(): void
    {
        $this->assertFalse(Schema::hasColumn('products', 'video_path'));

        $product = Product::factory()->create();
        $this->actingAs($this->owner())->get("/owner/products/{$product->id}/edit")->assertOk()->assertDontSee('Video promosi');
    }
}
