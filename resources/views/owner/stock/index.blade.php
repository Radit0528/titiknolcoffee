@extends('layouts.owner')

@section('title', 'Mutasi Stok')

@use('App\Support\Format')
@use('App\Models\Purchase')

@section('content')
    <x-ui.page-header title="Mutasi Stok" subtitle="Catatan setiap perubahan stok bahan baku, dari yang terbaru.">
        <x-ui.button :href="route('owner.stock.adjustment.create')" variant="secondary" icon="clipboard-check">Penyesuaian stok</x-ui.button>
        <x-ui.button :href="route('owner.stock.out.create')" icon="package-minus">Stok keluar</x-ui.button>
    </x-ui.page-header>

    <form method="GET" class="filter-bar">
        <x-form.field label="Bahan" for="ingredient">
            <select id="ingredient" name="ingredient" class="select">
                <option value="">Semua bahan</option>
                @foreach ($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}" @selected($ingredientId === $ingredient->id)>{{ $ingredient->name }}</option>
                @endforeach
            </select>
        </x-form.field>
        <x-form.field label="Jenis" for="type">
            <select id="type" name="type" class="select">
                <option value="">Semua jenis</option>
                @foreach ($types as $case)
                    <option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>
                @endforeach
            </select>
        </x-form.field>
        <x-form.field label="Dari" for="from">
            <input id="from" type="date" name="from" value="{{ $from }}" class="input">
        </x-form.field>
        <x-form.field label="Sampai" for="to">
            <input id="to" type="date" name="to" value="{{ $to }}" class="input">
        </x-form.field>
        <div class="flex gap-2">
            <x-ui.button variant="secondary">Terapkan</x-ui.button>
            @if ($type || $ingredientId || $from || $to)
                <x-ui.button :href="route('owner.stock.index')" variant="ghost">Reset</x-ui.button>
            @endif
        </div>
    </form>

    <div class="card">
        @if ($mutations->isEmpty())
            <x-ui.empty-state icon="arrow-left-right" title="Belum ada mutasi" description="Mutasi muncul saat stok awal, pembelian, stok keluar, atau penyesuaian dicatat." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th><th>Bahan</th><th>Jenis</th>
                            <th class="num">Perubahan</th><th class="num">Stok akhir</th><th class="num">Nilai</th><th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mutations as $mutation)
                            <tr>
                                <td class="whitespace-nowrap">
                                    {{ $mutation->created_at->translatedFormat('d M Y') }}
                                    <span class="block text-xs text-muted">{{ $mutation->created_at->format('H:i') }} · {{ $mutation->user?->name ?? 'Sistem' }}</span>
                                </td>
                                <td class="font-medium">{{ $mutation->ingredient->name }}</td>
                                <td><x-ui.badge :variant="$mutation->type->badge()">{{ $mutation->type->label() }}</x-ui.badge></td>
                                <td @class(['num font-semibold', 'text-success' => $mutation->quantity > 0, 'text-danger' => $mutation->quantity < 0])>
                                    {{ $mutation->quantity > 0 ? '+' : '' }}{{ Format::qty($mutation->quantity) }}
                                    <span class="text-xs font-normal text-muted">{{ $mutation->ingredient->unit->label() }}</span>
                                </td>
                                <td class="num">{{ Format::qty($mutation->stock_after) }}</td>
                                <td class="num text-muted">{{ Format::rupiah(abs((float) $mutation->quantity) * (float) $mutation->cost_per_unit) }}</td>
                                <td class="max-w-64 text-muted">
                                    @if ($mutation->reference instanceof Purchase)
                                        <a href="{{ route('owner.purchases.show', $mutation->reference) }}" class="font-medium text-accent hover:underline">{{ $mutation->reference->code }}</a>
                                    @else
                                        <span class="line-clamp-2">{{ $mutation->notes ?? '–' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($mutations->hasPages())
                <div class="pagination-wrap">{{ $mutations->links() }}</div>
            @endif
        @endif
    </div>
@endsection
