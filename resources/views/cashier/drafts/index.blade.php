@extends('layouts.cashier')

@section('title', 'Draft Pesanan')

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Draft Pesanan" subtitle="Pesanan yang ditahan. Draft tidak mengurangi stok sampai dibayar.">
        <x-ui.button :href="route('cashier.pos')" variant="secondary" icon="shopping-cart">Ke kasir</x-ui.button>
    </x-ui.page-header>

    @if ($drafts->isEmpty())
        <div class="card">
            <x-ui.empty-state icon="save" title="Tidak ada draft" description="Tekan “Tahan” di layar kasir untuk menyimpan pesanan sementara." />
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($drafts as $draft)
                <article class="card flex flex-col">
                    <div class="card-header">
                        <div>
                            <p class="font-semibold">{{ $draft->customer_name ?: 'Tanpa nama' }}</p>
                            <p class="text-xs text-muted">{{ $draft->order_type->label() }} · {{ $draft->updated_at->diffForHumans() }}</p>
                        </div>
                        <span class="text-sm font-bold tabular-nums">{{ Format::rupiah($draft->subtotal) }}</span>
                    </div>
                    <ul class="flex-1 px-5 py-3 text-sm">
                        @foreach ($draft->items as $item)
                            <li class="flex justify-between gap-2 py-0.5">
                                <span>{{ $item->quantity }}× {{ $item->product_name }}</span>
                                @if ($item->note)<span class="truncate text-xs text-warning italic">{{ $item->note }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex items-center justify-between gap-2 border-t border-line px-5 py-3">
                        <x-ui.modal :action="route('cashier.drafts.destroy', $draft)" title="Hapus draft ini?" message="Pesanan yang ditahan akan dihapus." trigger-label="Hapus" />
                        <x-ui.button :href="route('cashier.pos', ['draft' => $draft->id])" size="sm">Lanjutkan</x-ui.button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
