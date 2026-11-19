<?php

namespace Tests\Feature\Owner;

use App\Services\QrisService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Unit\QrisServiceTest;

class QrisSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_saves_valid_static_qris(): void
    {
        $payload = QrisServiceTest::staticPayload();

        $this->actingAs($this->owner())->put('/owner/settings/qris', ['qris_static_payload' => chunk_split($payload, 40, "\n")])
            ->assertRedirect('/owner/settings')->assertSessionHasNoErrors();

        $this->assertSame($payload, app(SettingService::class)->get('qris_static_payload'));
        $this->actingAs($this->owner())->get('/owner/settings')->assertOk()->assertSee('KEDAI CONTOH')->assertSee('ID1020000000001');
    }

    public function test_invalid_or_dynamic_qris_is_rejected(): void
    {
        $owner = $this->owner();
        $static = QrisServiceTest::staticPayload();

        $this->actingAs($owner)->put('/owner/settings/qris', ['qris_static_payload' => substr($static, 0, -4).'0000'])
            ->assertSessionHasErrorsIn('qris', ['qris_static_payload']);
        $this->actingAs($owner)->put('/owner/settings/qris', ['qris_static_payload' => app(QrisService::class)->withAmount($static, 5000)])
            ->assertSessionHasErrorsIn('qris', ['qris_static_payload']);
        $this->actingAs($owner)->put('/owner/settings/qris', ['qris_static_payload' => ''])
            ->assertSessionHasErrorsIn('qris', ['qris_static_payload']);

        $this->assertSame('', (string) app(SettingService::class)->get('qris_static_payload'));
    }

    public function test_owner_can_disable_and_cashier_cannot_change(): void
    {
        app(SettingService::class)->update(['qris_static_payload' => QrisServiceTest::staticPayload()]);

        $this->actingAs($this->cashier())->put('/owner/settings/qris', ['qris_static_payload' => QrisServiceTest::staticPayload()])->assertForbidden();
        $this->actingAs($this->owner())->delete('/owner/settings/qris')->assertRedirect('/owner/settings');

        $this->assertSame('', (string) app(SettingService::class)->get('qris_static_payload'));
    }
}
