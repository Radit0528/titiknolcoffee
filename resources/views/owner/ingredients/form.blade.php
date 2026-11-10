@extends('layouts.owner')

@section('title', $ingredient->exists ? 'Ubah Bahan' : 'Tambah Bahan')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header :title="$ingredient->exists ? 'Ubah Bahan Baku' : 'Tambah Bahan Baku'" :back="route('owner.ingredients.index')" back-label="Bahan baku" />

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $ingredient->exists ? route('owner.ingredients.update', $ingredient) : route('owner.ingredients.store') }}"
              class="card lg:col-span-2" x-data="{ busy: false }" x-on:submit="busy = true">
            @csrf
            @if ($ingredient->exists) @method('PUT') @endif

            <div class="card-body flex flex-col gap-5">
                <x-form.field label="Nama bahan" for="name" required>
                    <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $ingredient->name) }}"
                           @class(['input', 'input-invalid' => $errors->has('name')])>
                </x-form.field>

                <x-form.field label="Satuan" for="unit" :required="! $ingredient->exists"
                              :hint="$ingredient->exists ? 'Satuan tidak dapat diubah karena takaran resep bergantung padanya.' : 'Gunakan satuan terkecil yang dipakai di resep.'">
                    @if ($ingredient->exists)
                        <input id="unit" type="text" value="{{ $ingredient->unit->label() }}" class="input" disabled>
                    @else
                        <select id="unit" name="unit" required @class(['select', 'input-invalid' => $errors->has('unit')])>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>{{ $unit->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-form.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.field label="Harga modal per satuan" for="cost_per_unit" hint="Contoh: susu 1 liter Rp20.000 → 20 per ml. Diperbarui otomatis (rata-rata) saat ada pembelian." required>
                        <div class="input-group">
                            <span class="input-addon">Rp</span>
                            <input id="cost_per_unit" name="cost_per_unit" type="number" min="0" step="0.0001" required
                                   value="{{ old('cost_per_unit', $ingredient->exists ? (float) $ingredient->cost_per_unit : '') }}"
                                   @class(['input', 'input-invalid' => $errors->has('cost_per_unit')])>
                        </div>
                    </x-form.field>

                    <x-form.field label="Minimum stok" for="min_stock" hint="Di bawah angka ini bahan ditandai menipis." required>
                        <input id="min_stock" name="min_stock" type="number" min="0" step="0.001" required
                               value="{{ old('min_stock', $ingredient->exists ? (float) $ingredient->min_stock : '') }}"
                               @class(['input', 'input-invalid' => $errors->has('min_stock')])>
                    </x-form.field>
                </div>

                @unless ($ingredient->exists)
                    <x-form.field label="Stok awal" for="initial_stock" hint="Dicatat sebagai mutasi “Stok awal”. Perubahan berikutnya lewat pembelian, stok keluar, atau penyesuaian." required>
                        <input id="initial_stock" name="initial_stock" type="number" min="0" step="0.001" required value="{{ old('initial_stock', 0) }}"
                               @class(['input sm:max-w-60', 'input-invalid' => $errors->has('initial_stock')])>
                    </x-form.field>
                @endunless
            </div>

            <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
                <x-ui.button :href="route('owner.ingredients.index')" variant="secondary">Batal</x-ui.button>
                <x-ui.button x-bind:disabled="busy">Simpan</x-ui.button>
            </div>
        </form>

        @if ($ingredient->exists)
            <aside class="flex flex-col gap-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Stok saat ini</h2></div>
                    <div class="card-body">
                        <p class="text-3xl font-bold tabular-nums">{{ Format::qty($ingredient->stock) }} <span class="text-base font-medium text-muted">{{ $ingredient->unit->label() }}</span></p>
                        <x-ui.badge class="mt-2" :variant="$ingredient->stockStatus()->badge()">{{ $ingredient->stockStatus()->label() }}</x-ui.badge>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <x-ui.button :href="route('owner.purchases.create', ['ingredient' => $ingredient->id])" variant="secondary" size="sm" icon="shopping-basket">Beli</x-ui.button>
                            <x-ui.button :href="route('owner.stock.adjustment.create', ['ingredient' => $ingredient->id])" variant="secondary" size="sm" icon="clipboard-check">Sesuaikan</x-ui.button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Mutasi terakhir</h2>
                        <a href="{{ route('owner.stock.index', ['ingredient' => $ingredient->id]) }}" class="text-xs font-semibold text-accent hover:underline">Lihat semua</a>
                    </div>
                    @if ($mutations->isEmpty())
                        <p class="px-5 py-6 text-sm text-muted">Belum ada mutasi stok.</p>
                    @else
                        <ul class="divide-y divide-line">
                            @foreach ($mutations as $mutation)
                                <li class="flex items-start justify-between gap-3 px-5 py-3 text-sm">
                                    <div>
                                        <p class="font-medium">{{ $mutation->type->label() }}</p>
                                        <p class="text-xs text-muted">{{ $mutation->created_at->translatedFormat('d M Y H:i') }} · {{ $mutation->user?->name ?? 'Sistem' }}</p>
                                    </div>
                                    <span @class(['font-semibold tabular-nums', 'text-success' => $mutation->quantity > 0, 'text-danger' => $mutation->quantity < 0])>
                                        {{ $mutation->quantity > 0 ? '+' : '' }}{{ Format::qty($mutation->quantity) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        @endif
    </div>
@endsection
