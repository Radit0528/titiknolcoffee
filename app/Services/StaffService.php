<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\CheckoutException;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cashier accounts managed by the owner.
 */
class StaffService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, username: string, password: string}  $data
     */
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'role' => UserRole::Cashier,
            'is_active' => true,
        ]);

        $this->audit->log('staff.created', $user, [], ['name' => $user->name, 'username' => $user->username]);

        return $user;
    }

    /**
     * @param  array{name: string, username: string, is_active: bool}  $data
     *
     * @throws CheckoutException when deactivating a cashier whose shift is still open
     */
    public function update(User $user, array $data): User
    {
        if ($user->is_active && ! $data['is_active'] && Shift::open()->where('user_id', $user->id)->exists()) {
            throw new CheckoutException("{$user->name} masih punya shift yang terbuka. Tutup shift-nya dulu sebelum menonaktifkan akun.", 'is_active');
        }

        $before = $user->only(['name', 'username', 'is_active']);
        $user->update($data);
        $after = $user->only(array_keys($before));

        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));
        if ($changed) {
            $this->audit->log('staff.updated', $user, array_intersect_key($before, array_flip($changed)), array_intersect_key($after, array_flip($changed)));
        }

        if (! $user->is_active) {
            $this->endSessions($user);
        }

        return $user;
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
        $this->endSessions($user);

        $this->audit->log('staff.password_reset', $user);
    }

    /**
     * Log the user out everywhere: drop stored sessions and the remember-me token.
     */
    private function endSessions(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
