@extends('layouts.owner')

@section('title', $report['title'])

@use('App\Support\Format')

@section('content')
    <x-ui.page-header title="Laporan" :subtitle="$filters->label()">
        <x-ui.button :href="route('owner.reports.pdf', ['type' => $type] + $filters->query())" variant="secondary" icon="file-text">PDF</x-ui.button>
        <x-ui.button :href="route('owner.reports.excel', ['type' => $type] + $filters->query())" variant="secondary" icon="sheet">Excel</x-ui.button>
    </x-ui.page-header>

    <nav class="report-tabs" aria-label="Jenis laporan">
        @foreach ($types as $key => $label)
            <a href="{{ route('owner.reports.show', ['type' => $key] + $filters->query()) }}" @if ($key === $type) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" class="filter-bar">
        <x-form.field label="Dari" for="from">
            <input id="from" type="date" name="from" value="{{ $filters->from->toDateString() }}" class="input">
        </x-form.field>
        <x-form.field label="Sampai" for="to">
            <input id="to" type="date" name="to" value="{{ $filters->to->toDateString() }}" class="input">
        </x-form.field>
        @if (in_array($type, ['penjualan', 'laba-kotor', 'shift'], true))
            <x-form.field label="Kasir" for="cashier">
                <select id="cashier" name="cashier" class="select">
                    <option value="">Semua kasir</option>
                    @foreach ($cashiers as $cashier)
                        <option value="{{ $cashier->id }}" @selected($filters->cashierId === $cashier->id)>{{ $cashier->name }}</option>
                    @endforeach
                </select>
            </x-form.field>
        @endif
        @if (in_array($type, ['penjualan', 'laba-kotor'], true))
            <x-form.field label="Metode bayar" for="method">
                <select id="method" name="method" class="select">
                    <option value="">Semua metode</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}" @selected($filters->method === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
            </x-form.field>
        @endif
        <x-ui.button variant="secondary">Terapkan</x-ui.button>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($report['summary'] as $item)
            <x-ui.stat-card :label="$item['label']" :value="Format::cell($item['value'], $item['type'])" />
        @endforeach
    </div>

    <div class="flex flex-col gap-6">
        @foreach ($report['sections'] as $section)
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ $section['title'] }}</h2></div>
                <x-report.table :section="$section" />
            </section>
        @endforeach

        @if (! empty($report['notes']))
            <div class="alert alert-info">
                <x-ui.icon name="info" />
                <ul class="flex flex-col gap-1">
                    @foreach ($report['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
