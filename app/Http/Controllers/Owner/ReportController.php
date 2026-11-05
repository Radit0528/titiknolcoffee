<?php

namespace App\Http\Controllers\Owner;

use App\Enums\PaymentMethod;
use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Services\SettingService;
use App\Support\ReportFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function show(Request $request, string $type): View
    {
        $filters = ReportFilters::fromRequest($request);

        return view('owner.reports.show', [
            'type' => $type,
            'types' => ReportService::TYPES,
            'report' => $this->reports->build($type, $filters),
            'filters' => $filters,
            'cashiers' => $this->reports->cashiers(),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    public function pdf(Request $request, string $type, SettingService $settings): Response
    {
        $filters = ReportFilters::fromRequest($request);
        $report = $this->reports->build($type, $filters);

        return Pdf::loadView('owner.reports.pdf', [
            'report' => $report,
            'filters' => $filters,
            'storeName' => $settings->get('store_name'),
        ])
            ->setPaper('a4', $type === 'bahan-baku' || $type === 'shift' ? 'landscape' : 'portrait')
            ->download("laporan-{$type}-{$filters->fileSuffix()}.pdf");
    }

    public function excel(Request $request, string $type): BinaryFileResponse
    {
        $filters = ReportFilters::fromRequest($request);
        $report = $this->reports->build($type, $filters);

        return Excel::download(new ReportExport($report, $filters->label()), "laporan-{$type}-{$filters->fileSuffix()}.xlsx");
    }
}
