<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One report table. Numbers are written as numbers (not formatted text) so
 * the owner can keep calculating in Excel; formats are applied per column.
 */
class ReportSectionSheet implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private const FORMATS = [
        'money' => '#,##0',
        'int' => '#,##0',
        'qty' => '#,##0.###',
        'percent' => '0.0"%"',
    ];

    /**
     * @param  array<string, mixed>  $section
     */
    public function __construct(private array $section) {}

    public function headings(): array
    {
        return array_column($this->section['columns'], 'label');
    }

    public function array(): array
    {
        $rows = $this->section['rows'];

        if ($this->section['totals']) {
            $rows[] = $this->section['totals'];
        }

        return $rows;
    }

    public function title(): string
    {
        // Excel sheet names: max 31 chars, no special characters.
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $this->section['title']), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->section['rows']) + ($this->section['totals'] ? 2 : 1);

        foreach ($this->section['columns'] as $index => $column) {
            if (isset(self::FORMATS[$column['type']])) {
                $letter = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")->getNumberFormat()->setFormatCode(self::FORMATS[$column['type']]);
            }
        }

        $styles = [1 => ['font' => ['bold' => true]]];

        if ($this->section['totals']) {
            $styles[$lastRow] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}
