<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Owner PIN: a 6-digit code the owner types to approve sensitive actions
 * (void) on a cashier's screen. Stored hashed in users.pin.
 */
class OwnerPinService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 300;

    /** PINs too easy to guess by looking at the keypad. */
    public const WEAK_PINS = ['000000', '111111', '123456', '654321', '112233', '121212', '999999', '888888', '777777', '666666', '555555', '444444', '333333', '222222'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Return the owner whose PIN matches. Wrong guesses are limited per person
     * asking (5 per 5 minutes) so the 6-digit space cannot be brute-forced.
     *
     * @throws ValidationException
     */
    public function verify(string $pin, User $askedBy, string $field = 'pin'): User
    {
        $key = 'owner-pin:'.$askedBy->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([$field => "Terlalu banyak PIN salah. Coba lagi dalam {$minutes} menit."]);
        }

        $owners = User::query()
            ->where('role', UserRole::Owner)
            ->where('is_active', true)
            ->whereNotNull('pin')
            ->get();

        if ($owners->isEmpty()) {
            throw ValidationException::withMessages([$field => 'Owner belum mengatur PIN. Atur dulu di menu Akun & PIN.']);
        }

        foreach ($owners as $owner) {
            if (Hash::check($pin, $owner->pin)) {
                RateLimiter::clear($key);

                return $owner;
            }
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
        $this->audit->log('owner.pin_failed', $askedBy);

        $left = RateLimiter::remaining($key, self::MAX_ATTEMPTS);

        throw ValidationException::withMessages([$field => "PIN owner salah. Sisa {$left} percobaan."]);
    }

    public function set(User $owner, string $pin): void
    {
        $hadPin = $owner->pin !== null;
        $owner->forceFill(['pin' => $pin])->save();

        $this->audit->log($hadPin ? 'owner.pin_changed' : 'owner.pin_set', $owner);
    }
}
