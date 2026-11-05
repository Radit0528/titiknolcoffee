{{-- One report section (see ReportService for the shape). --}}
@props(['section'])
@use('App\Support\Format')

@php($numeric = ['money', 'int', 'qty', 'percent'])
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                @foreach ($section['columns'] as $column)
                    <th @class(['num' => in_array($column['type'], $numeric, true)])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($section['rows'] as $row)
                <tr>
                    @foreach ($section['columns'] as $i => $column)
                        <td @class([
                            'num' => in_array($column['type'], $numeric, true),
                            'text-danger' => in_array($column['type'], ['money', 'qty'], true) && is_numeric($row[$i]) && $row[$i] < 0,
                            'font-medium' => $i === 0,
                        ])>{{ Format::cell($row[$i], $column['type']) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($section['columns']) }}" class="py-8 text-center text-muted">Tidak ada data pada periode ini.</td></tr>
            @endforelse
        </tbody>
        @if ($section['totals'] && count($section['rows']))
            <tfoot>
                <tr class="report-total">
                    @foreach ($section['columns'] as $i => $column)
                        <td @class(['num' => in_array($column['type'], $numeric, true)])>{{ $i === 0 ? $section['totals'][0] : ($section['totals'][$i] === null ? '' : Format::cell($section['totals'][$i], $column['type'])) }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>
