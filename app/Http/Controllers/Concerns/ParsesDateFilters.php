<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Carbon;

trait ParsesDateFilters
{
    /**
     * Turn a ?from=YYYY-MM-DD query value into a date, ignoring junk input.
     */
    protected function dateFilter(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
