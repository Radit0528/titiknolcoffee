<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function __construct(private array $report, private string $period) {}

    public function array(): array
    {
        $rows = [[$this->report['title']], ['Periode', $this->period], ['Dibuat', now()->format('d/m/Y H:i')], []];

        foreach ($this->report['summary'] as $item) {
            $rows[] = [$item['label'], $item['type'] === 'percent' ? round((float) $item['value'], 1).'%' : $item['value']];
        }

        if (! empty($this->report['notes'])) {
            $rows[] = [];
            foreach ($this->report['notes'] as $note) {
                $rows[] = [$note];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('B5:B20')->getNumberFormat()->setFormatCode('#,##0');

        return [1 => ['font' => ['bold' => true, 'size' => 14]]];
    }
}
