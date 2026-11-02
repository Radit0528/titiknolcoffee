@extends('layouts.owner')

@section('title', 'Supplier')

@section('content')
    <x-ui.page-header title="Supplier" subtitle="Pemasok bahan baku kedai.">
        <x-ui.button :href="route('owner.suppliers.create')" icon="plus">Tambah supplier</x-ui.button>
    </x-ui.page-header>

    <form method="GET" class="mb-4 flex gap-2">
        <div class="search-field flex-1">
            <x-ui.icon name="search" />
            <input type="search" name="q" value="{{ $search }}" placeholder="Cari supplier…" class="input">
        </div>
        <x-ui.button variant="secondary">Cari</x-ui.button>
    </form>

    <div class="card">
        @if ($suppliers->isEmpty())
            @if ($search !== '')
                <x-ui.empty-state icon="search" title="Supplier tidak ditemukan" />
            @else
                <x-ui.empty-state icon="truck" title="Belum ada supplier">
                    <x-ui.button :href="route('owner.suppliers.create')" icon="plus">Tambah supplier</x-ui.button>
                </x-ui.empty-state>
            @endif
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Nama</th><th>Kontak</th><th>Alamat</th><th class="num">Bahan</th><th class="w-24"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td class="font-medium">{{ $supplier->name }}</td>
                                <td>
                                    <p>{{ $supplier->phone ?? '–' }}</p>
                                    @if ($supplier->email)<p class="text-xs text-muted">{{ $supplier->email }}</p>@endif
                                </td>
                                <td class="max-w-xs truncate text-muted">{{ $supplier->address ?? '–' }}</td>
                                <td class="num">{{ $supplier->ingredients_count }}</td>
                                <td>
                                    <div class="flex justify-end gap-1">
                                        <x-ui.button :href="route('owner.suppliers.edit', $supplier)" variant="ghost" size="sm" class="btn-icon" icon="pencil" aria-label="Ubah" />
                                        <x-ui.modal :action="route('owner.suppliers.destroy', $supplier)" title="Hapus {{ $supplier->name }}?"
                                                    message="Bahan baku yang terhubung tetap tersimpan dengan nama supplier ini." />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($suppliers->hasPages())
                <div class="pagination-wrap">{{ $suppliers->links() }}</div>
            @endif
        @endif
    </div>
@endsection
