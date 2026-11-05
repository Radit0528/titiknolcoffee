<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Concerns\ParsesDateFilters;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\User;
use App\Support\AuditActions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only log of sensitive actions. There are no edit or delete routes.
 */
class AuditLogController extends Controller
{
    use ParsesDateFilters;

    public function index(Request $request): View
    {
        $action = (string) $request->query('action', '');
        $userId = $request->integer('user') ?: null;
        $from = $this->dateFilter($request->query('from'));
        $to = $this->dateFilter($request->query('to'));

        $logs = AuditLog::query()
            ->with('user')
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<', $to->copy()->addDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action')
            ->mapWithKeys(fn ($a) => [$a => AuditActions::label($a)]);

        $ingredientIds = $logs->getCollection()
            ->where('action', 'recipe.updated')
            ->flatMap(fn (AuditLog $log) => [...array_keys($log->old_values ?? []), ...array_keys($log->new_values ?? [])])
            ->unique()->values();

        return view('owner.audit.index', [
            'logs' => $logs,
            'ingredientNames' => Ingredient::withTrashed()->whereIn('id', $ingredientIds)->pluck('name', 'id')->all(),
            'actions' => $actions,
            'users' => User::orderBy('name')->get(['id', 'name', 'role']),
            'filters' => [
                'action' => $action,
                'user' => $userId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }
}
