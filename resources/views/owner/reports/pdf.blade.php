<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    {{-- DomPDF cannot load Vite assets, so the stylesheet kept in resources/css/pdf is read in here. --}}
    <style>{!! file_get_contents(resource_path('css/pdf/report.css')) !!}</style>
</head>
<body>
    @use('App\Support\Format')
    @php($numeric = ['money', 'int', 'qty', 'percent'])

    <header>
        <div class="store">{{ $storeName }}</div>
        <h1>{{ $report['title'] }}</h1>
        <div class="muted">Periode {{ $filters->label() }} · Dibuat {{ now()->format('d/m/Y H:i') }}</div>
    </header>

    <table class="summary">
        <tr>
            @foreach ($report['summary'] as $item)
                <td>
                    <div class="label">{{ $item['label'] }}</div>
                    <div class="value">{{ Format::cell($item['value'], $item['type']) }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        <table class="data">
            <thead>
                <tr>
                    @foreach ($section['columns'] as $column)
                        <th class="{{ in_array($column['type'], $numeric, true) ? 'num' : '' }}">{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($section['columns'] as $i => $column)
                            <td class="{{ in_array($column['type'], $numeric, true) ? 'num' : '' }}">{{ Format::cell($row[$i], $column['type']) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($section['columns']) }}" class="muted">Tidak ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
            @if ($section['totals'] && count($section['rows']))
                <tfoot>
                    <tr>
                        @foreach ($section['columns'] as $i => $column)
                            <td class="{{ in_array($column['type'], $numeric, true) ? 'num' : '' }}">{{ $i === 0 ? $section['totals'][0] : ($section['totals'][$i] === null ? '' : Format::cell($section['totals'][$i], $column['type'])) }}</td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    @endforeach

    @if (! empty($report['notes']))
        <div class="notes">
            @foreach ($report['notes'] as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif
</body>
</html>
