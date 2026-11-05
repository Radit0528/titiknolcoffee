<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Period and filters shared by all owner reports. Defaults to this month.
 */
final class ReportFilters
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?int $cashierId = null,
        public readonly ?string $method = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $parse = function (mixed $value): ?Carbon {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->query('from')) ?? now()->startOfMonth();
        $to = $parse($request->query('to')) ?? now()->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $method = PaymentMethod::tryFrom((string) $request->query('method'))?->value;

        return new self($from, $to, $request->integer('cashier') ?: null, $method);
    }

    public function start(): Carbon
    {
        return $this->from->copy()->startOfDay();
    }

    public function end(): Carbon
    {
        return $this->to->copy()->endOfDay();
    }

    public function label(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->translatedFormat('d F Y')
            : $this->from->translatedFormat('d M Y').' – '.$this->to->translatedFormat('d M Y');
    }

    /**
     * @return array<string, string|int>
     */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'cashier' => $this->cashierId,
            'method' => $this->method,
        ]);
    }

    public function fileSuffix(): string
    {
        return $this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }
}
