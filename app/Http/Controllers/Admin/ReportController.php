<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportQueryService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, ReportQueryService $reports): View
    {
        $filters = $request->validate([
            'period' => ['nullable', 'in:week,month,year'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'period.in' => __('admin.reports.validation.period_invalid'),
            'date.date_format' => __('admin.reports.validation.date_invalid'),
        ]);
        $timezone = 'Africa/Cairo';
        $period = $filters['period'] ?? 'month';
        $anchor = isset($filters['date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], $timezone)
            : CarbonImmutable::now($timezone);
        [$startLocal, $endLocal] = $this->periodBounds($period, $anchor);
        $report = $reports->report($startLocal->utc(), $endLocal->utc());

        return view('admin.reports.index', [
            'report' => $report,
            'filters' => ['period' => $period, 'date' => $anchor->format('Y-m-d')],
            'periodStart' => $startLocal->format('Y-m-d'),
            'periodEnd' => $endLocal->subSecond()->format('Y-m-d'),
            'timezone' => $timezone,
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodBounds(string $period, CarbonImmutable $anchor): array
    {
        $start = match ($period) {
            'week' => $anchor->startOfWeek(CarbonInterface::SATURDAY),
            'year' => $anchor->startOfYear(),
            default => $anchor->startOfMonth(),
        };
        $end = match ($period) {
            'week' => $start->addWeek(),
            'year' => $start->addYear(),
            default => $start->addMonth(),
        };

        return [$start, $end];
    }
}
