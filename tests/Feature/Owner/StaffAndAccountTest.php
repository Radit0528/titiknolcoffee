<?php

namespace Tests\Feature\Owner;

use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAndAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_cashier_who_can_log_in(): void
    {
        $this->actingAs($this->owner())->post('/owner/staff', [
            'name' => 'Dina', 'username' => ' Dina.K ', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect('/owner/staff');

        $dina = User::where('username', 'dina.k')->firstOrFail();
        $this->assertTrue($dina->isCashier());
        $this->assertTrue(AuditLog::where('action', 'staff.created')->exists());

        auth()->logout();
        $this->post('/login', ['username' => 'dina.k', 'password' => 'rahasia123'])->assertRedirect('/kasir');
    }

    public function test_validation_on_create(): void
    {
        User::factory()->create(['username' => 'kasir']);

        $this->actingAs($this->owner())->post('/owner/staff', [
            'name' => '', 'username' => 'kasir', 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertSessionHasErrors(['name', 'username', 'password']);

        $this->actingAs($this->owner())->post('/owner/staff', [
            'name' => 'X', 'username' => 'bad name!', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertSessionHasErrors('username');
    }

    public function test_deactivated_cashier_is_logged_out_and_cannot_log_in(): void
    {
        config(['session.driver' => 'database']);
        $cashier = $this->cashier();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $cashier->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->owner())->put("/owner/staff/{$cashier->id}", [
            'name' => $cashier->name, 'username' => $cashier->username, 'is_active' => '0',
        ])->assertRedirect('/owner/staff');

        $this->assertFalse($cashier->fresh()->is_active);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $cashier->id)->count());
        $log = AuditLog::where('action', 'staff.updated')->firstOrFail();
        $this->assertSame(['is_active' => true], $log->old_values);
        $this->assertSame(['is_active' => false], $log->new_values);

        $this->actingAs($cashier->fresh())->get('/kasir/shift/buka')->assertRedirect('/login');
    }

    public function test_cannot_deactivate_cashier_with_open_shift(): void
    {
        $cashier = $this->cashier();
        Shift::factory()->for($cashier)->create();

        $this->actingAs($this->owner())->from("/owner/staff/{$cashier->id}/edit")->put("/owner/staff/{$cashier->id}", [
            'name' => $cashier->name, 'username' => $cashier->username, 'is_active' => '0',
        ])->assertSessionHasErrors('is_active');

        $this->assertTrue($cashier->fresh()->is_active);
    }

    public function test_reset_password(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($this->owner())->put("/owner/staff/{$cashier->id}/password", [
            'password' => 'baru12345', 'password_confirmation' => 'baru12345',
        ])->assertRedirect('/owner/staff');

        $this->assertTrue(Hash::check('baru12345', $cashier->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'staff.password_reset')->exists());
    }

    public function test_owner_accounts_are_not_editable_here_and_cashier_is_forbidden(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->actingAs($owner)->get("/owner/staff/{$other->id}/edit")->assertNotFound();
        $this->actingAs($owner)->put("/owner/staff/{$other->id}/password", ['password' => 'baru12345', 'password_confirmation' => 'baru12345'])->assertNotFound();
        $this->actingAs($owner)->get('/owner/staff')->assertOk();

        $cashier = $this->cashier();
        foreach (['/owner/staff', '/owner/akun', '/owner/audit-log', '/owner/banners'] as $url) {
            $this->actingAs($cashier)->get($url)->assertForbidden();
        }
    }

    public function test_owner_sets_pin_with_password(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/owner/akun/pin', [
            'pin_current_password' => 'salah', 'pin' => '482915', 'pin_confirmation' => '482915',
        ])->assertSessionHasErrorsIn('pin', ['pin_current_password']);

        $this->actingAs($owner)->put('/owner/akun/pin', [
            'pin_current_password' => 'password', 'pin' => '123456', 'pin_confirmation' => '123456',
        ])->assertSessionHasErrorsIn('pin', ['pin']);

        $this->actingAs($owner)->put('/owner/akun/pin', [
            'pin_current_password' => 'password', 'pin' => '48291', 'pin_confirmation' => '48291',
        ])->assertSessionHasErrorsIn('pin', ['pin']);

        $this->actingAs($owner)->put('/owner/akun/pin', [
            'pin_current_password' => 'password', 'pin' => '482915', 'pin_confirmation' => '482915',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('482915', $owner->fresh()->pin));
        $this->assertTrue(AuditLog::where('action', 'owner.pin_set')->exists());
        $this->assertStringNotContainsString('482915', (string) AuditLog::all()->toJson());
        $this->actingAs($owner)->get('/owner/akun')->assertOk()->assertSee('Sudah diatur');
    }

    public function test_owner_changes_password(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/owner/akun/password', [
            'current_password' => 'password', 'password' => 'kopi-titik-nol', 'password_confirmation' => 'kopi-titik-nol',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('kopi-titik-nol', $owner->fresh()->password));

        $this->actingAs($owner)->put('/owner/akun/password', [
            'current_password' => 'salah', 'password' => 'x', 'password_confirmation' => 'y',
        ])->assertSessionHasErrorsIn('password', ['current_password', 'password']);
    }
}
