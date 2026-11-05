@extends('layouts.owner')

@section('title', 'Audit Log')

@use('App\Support\AuditActions')

@section('content')
    <x-ui.page-header title="Audit Log" subtitle="Catatan aksi penting: void, perubahan harga, resep, akun, PIN, dan pengaturan. Tidak bisa diubah atau dihapus." />

    <form method="GET" class="filter-bar">
        <x-form.field label="Aksi" for="action">
            <select id="action" name="action" class="select">
                <option value="">Semua aksi</option>
                @foreach ($actions as $key => $label)
                    <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </x-form.field>
        <x-form.field label="Pengguna" for="user">
            <select id="user" name="user" class="select">
                <option value="">Semua pengguna</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected($filters['user'] === $u->id)>{{ $u->name }} ({{ $u->role->label() }})</option>
                @endforeach
            </select>
        </x-form.field>
        <x-form.field label="Dari" for="from">
            <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="input">
        </x-form.field>
        <x-form.field label="Sampai" for="to">
            <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="input">
        </x-form.field>
        <div class="flex gap-2">
            <x-ui.button variant="secondary">Terapkan</x-ui.button>
            @if (array_filter($filters))
                <x-ui.button :href="route('owner.audit.index')" variant="ghost">Reset</x-ui.button>
            @endif
        </div>
    </form>

    <div class="card">
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="history" title="Belum ada catatan" description="Tidak ada aksi yang cocok dengan filter." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Data</th><th>Perubahan</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php($keys = array_unique([...array_keys($log->old_values ?? []), ...array_keys($log->new_values ?? [])]))
                            <tr class="align-top">
                                <td class="text-sm whitespace-nowrap">
                                    {{ $log->created_at->format('d/m/Y H:i') }}
                                    @if ($log->ip_address)<p class="text-xs text-muted">{{ $log->ip_address }}</p>@endif
                                </td>
                                <td class="text-sm">{{ $log->user?->name ?? 'Sistem' }}</td>
                                <td><x-ui.badge :variant="AuditActions::badge($log->action)">{{ AuditActions::label($log->action) }}</x-ui.badge></td>
                                <td class="text-sm whitespace-nowrap">
                                    @if ($log->auditable_type)
                                        {{ AuditActions::subject($log->auditable_type) }} #{{ $log->auditable_id }}
                                    @else
                                        –
                                    @endif
                                </td>
                                <td class="text-sm">
                                    @if ($keys)
                                        <dl class="audit-changes">
                                            @foreach ($keys as $key)
                                                <div>
                                                    <dt>{{ AuditActions::key($log->action, $key, $ingredientNames) }}</dt>
                                                    <dd>
                                                        @if (array_key_exists($key, $log->old_values ?? []))
                                                            <span class="audit-old">{{ AuditActions::value($log->old_values[$key]) }}</span>
                                                            @if (array_key_exists($key, $log->new_values ?? []))<span class="text-muted">→</span>@endif
                                                        @endif
                                                        @if (array_key_exists($key, $log->new_values ?? []))
                                                            <span>{{ AuditActions::value($log->new_values[$key]) }}</span>
                                                        @endif
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @else
                                        <span class="text-muted">–</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="pagination-wrap">{{ $logs->links() }}</div>
            @endif
        @endif
    </div>
@endsection
