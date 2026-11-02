<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function current(User $user): ?Shift
    {
        return Shift::where('user_id', $user->id)->open()->latest('id')->first();
    }

    /**
     * Open a shift with the starting cash in the drawer.
     *
     * The unique `open_user_id` column guarantees one open shift per cashier
     * even if two tabs submit at the same moment.
     *
     * @throws CheckoutException when the cashier already has an open shift
     */
    public function open(User $user, int $openingCash): Shift
    {
        try {
            return DB::transaction(function () use ($user, $openingCash) {
                if ($this->current($user)) {
                    throw new CheckoutException('Anda masih memiliki shift yang aktif.', 'opening_cash');
                }

                return Shift::create([
                    'user_id' => $user->id,
                    'open_user_id' => $user->id,
                    'opening_cash' => $openingCash,
                    'opened_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new CheckoutException('Anda masih memiliki shift yang aktif.', 'opening_cash');
        }
    }
}
