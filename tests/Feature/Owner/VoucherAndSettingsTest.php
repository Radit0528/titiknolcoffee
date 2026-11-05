<?php

namespace Tests\Feature\Owner;

use App\Models\Order;
use App\Models\Voucher;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoucherAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_and_updates_voucher(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post('/owner/vouchers', [
            'code' => 'ngopi-pagi', 'name' => 'Ngopi pagi', 'type' => 'percent', 'value' => 15,
            'max_discount' => 5000, 'min_purchase' => 20000, 'quota' => 100,
            'starts_at' => '2026-11-01T07:00', 'ends_at' => '2026-11-30T10:00', 'is_active' => '1',
        ])->assertRedirect(route('owner.vouchers.index'));

        $voucher = Voucher::sole();
        $this->assertSame('NGOPI-PAGI', $voucher->code);
        $this->assertSame('15% (maks Rp5.000)', $voucher->describe());

        $this->actingAs($owner)->put("/owner/vouchers/{$voucher->id}", [
            'code' => 'NGOPI-PAGI', 'name' => 'Ngopi pagi', 'type' => 'fixed', 'value' => 3000, 'is_active' => '0',
        ])->assertRedirect(route('owner.vouchers.index'));

        $this->assertFalse($voucher->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voucher.updated']);
        $this->actingAs($owner)->get('/owner/vouchers')->assertOk()->assertSee('NGOPI-PAGI');
    }

    public function test_voucher_validation(): void
    {
        $this->actingAs($this->owner())->post('/owner/vouchers', [
            'code' => 'ada spasi', 'name' => '', 'type' => 'percent', 'value' => 150,
            'starts_at' => '2026-12-01T00:00', 'ends_at' => '2026-11-01T00:00',
        ])->assertSessionHasErrors(['code', 'name', 'value', 'ends_at']);
    }

    public function test_used_voucher_cannot_be_deleted(): void
    {
        $voucher = Voucher::factory()->create();
        $voucher->forceFill(['used_count' => 1])->save();
        $voucher->usages()->create(['order_id' => $this->fakeOrderId(), 'discount' => 1000]);

        $this->actingAs($this->owner())->delete("/owner/vouchers/{$voucher->id}")->assertSessionHas('error');
        $this->assertModelExists($voucher);

        $unused = Voucher::factory()->create();
        $this->actingAs($this->owner())->delete("/owner/vouchers/{$unused->id}");
        $this->assertModelMissing($unused);
    }

    public function test_owner_updates_settings_and_logo(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner())->put('/owner/settings', [
            'store_name' => 'Kopi Titik Nol', 'store_address' => 'Purwokerto', 'store_phone' => '0281-123',
            'receipt_footer' => 'Matur nuwun', 'receipt_width' => 80, 'tax_percent' => 10,
            'service_charge_percent' => 0, 'cash_rounding' => 500,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertRedirect(route('owner.settings.edit'));

        $settings = app(SettingService::class);
        $this->assertSame(10.0, $settings->taxRate());
        $this->assertSame(500, $settings->cashRounding());
        $this->assertSame('80', (string) $settings->get('receipt_width'));
        Storage::disk('public')->assertExists($settings->get('store_logo'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_validation_and_cashier_access(): void
    {
        $this->actingAs($this->owner())->put('/owner/settings', [
            'store_name' => '', 'receipt_width' => 70, 'tax_percent' => 101, 'service_charge_percent' => -1, 'cash_rounding' => 250,
        ])->assertSessionHasErrors(['store_name', 'receipt_width', 'tax_percent', 'service_charge_percent', 'cash_rounding']);

        $this->actingAs($this->cashier())->get('/owner/settings')->assertForbidden();
        $this->actingAs($this->cashier())->get('/owner/vouchers')->assertForbidden();
    }

    private function fakeOrderId(): int
    {
        $cashier = $this->cashier();

        return Order::create([
            'user_id' => $cashier->id, 'status' => 'paid', 'order_type' => 'take_away',
        ])->id;
    }
}
