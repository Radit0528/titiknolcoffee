<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel workbook for one report: a summary sheet plus one sheet per section.
 */
class ReportExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report  as returned by ReportService
     */
    public function __construct(private array $report, private string $period) {}

    public function sheets(): array
    {
        $sheets = [new ReportSummarySheet($this->report, $this->period)];

        foreach ($this->report['sections'] as $section) {
            $sheets[] = new ReportSectionSheet($section);
        }

        return $sheets;
    }
}
