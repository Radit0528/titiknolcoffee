<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_owner_logs_in_and_lands_on_dashboard(): void
    {
        $owner = User::factory()->owner()->create(['username' => 'owner']);

        $this->post('/login', ['username' => 'owner', 'password' => 'password'])
            ->assertRedirect(route('owner.dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertNotNull($owner->fresh()->last_login_at);
    }

    public function test_cashier_logs_in_and_lands_on_menu(): void
    {
        $cashier = User::factory()->cashier()->create(['username' => 'kasir']);

        $this->post('/login', ['username' => 'kasir', 'password' => 'password'])
            ->assertRedirect(route('cashier.pos'));

        $this->assertAuthenticatedAs($cashier);
    }

    public function test_username_is_case_insensitive(): void
    {
        User::factory()->cashier()->create(['username' => 'kasir']);

        $this->post('/login', ['username' => '  KASIR ', 'password' => 'password'])
            ->assertRedirect(route('cashier.pos'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['username' => 'kasir']);

        $this->post('/login', ['username' => 'kasir', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'kasir']);

        $this->post('/login', ['username' => 'kasir', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        User::factory()->create(['username' => 'kasir']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['username' => 'kasir', 'password' => 'salah']);
        }

        // Even the correct password is refused while locked out.
        $this->post('/login', ['username' => 'kasir', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertStringStartsWith('Terlalu banyak percobaan login', session('errors')->first('username'));

        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->owner())->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_logged_in_user_visiting_login_is_sent_home(): void
    {
        $this->actingAs($this->cashier())->get('/login')->assertRedirect(route('cashier.pos'));
    }
}
