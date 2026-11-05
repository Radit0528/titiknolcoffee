@extends('layouts.owner')

@section('title', 'Staf Kasir')

@section('content')
    <x-ui.page-header title="Staf Kasir" subtitle="Akun yang dipakai kasir untuk login dan membuka shift.">
        <x-ui.button :href="route('owner.staff.create')" icon="user-plus">Tambah kasir</x-ui.button>
    </x-ui.page-header>

    <div class="card">
        @if ($cashiers->isEmpty())
            <x-ui.empty-state icon="users" title="Belum ada akun kasir">
                <x-ui.button :href="route('owner.staff.create')" icon="user-plus">Tambah kasir</x-ui.button>
            </x-ui.empty-state>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Nama</th><th>Username</th><th>Login terakhir</th><th class="num">Shift</th><th>Status</th><th class="w-16"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($cashiers as $cashier)
                            <tr>
                                <td class="font-semibold">{{ $cashier->name }}</td>
                                <td class="font-mono text-sm">{{ $cashier->username }}</td>
                                <td class="text-sm whitespace-nowrap">{{ $cashier->last_login_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}</td>
                                <td class="num">{{ $cashier->shifts_count }}</td>
                                <td>
                                    @if (! $cashier->is_active)
                                        <x-ui.badge>Nonaktif</x-ui.badge>
                                    @elseif (in_array($cashier->id, $openShiftUserIds, true))
                                        <x-ui.badge variant="success">Sedang shift</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="success">Aktif</x-ui.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex justify-end">
                                        <x-ui.button :href="route('owner.staff.edit', $cashier)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <p class="hint mt-4">Akun kasir tidak dihapus agar riwayat transaksi dan shift tetap utuh. Nonaktifkan akun kasir yang sudah tidak bekerja.</p>
@endsection
