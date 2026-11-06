<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ReportQueryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ReportQueryService $reports): View
    {
        $todayInCairo = CarbonImmutable::now('Africa/Cairo')->startOfDay();
        $todayStartUtc = $todayInCairo->utc();
        $tomorrowStartUtc = $todayInCairo->addDay()->utc();

        $user = Auth::user();
        $dashboard = $reports->dashboard($todayStartUtc, $tomorrowStartUtc, $user);
        $statRoutes = [
            'orders_today' => route('admin.orders.index'),
            'waiting_orders' => route('admin.orders.index'),
            'received_today' => route('admin.orders.index', ['status' => 'received']),
            'failed_orders' => route('admin.orders.index'),
            'damaged_returns' => route('admin.returns.index'),
            'open_returns' => route('admin.returns.index'),
            'open_tickets' => route('admin.support-tickets.index'),
            'available_products' => route('admin.products.index'),
            'pending_reviews' => route('admin.reviews.index', ['scope' => 'pending']),
        ];
        $statsCards = collect($dashboard['stats'])->map(fn (int $count, string $key): array => [
            'label' => __('admin.dashboard.stats.'.$key),
            'count' => $count,
            'url' => $statRoutes[$key] ?? route('admin.dashboard'),
        ])->values();
        $queueLabels = [
            'waiting_orders' => __('admin.dashboard.queues.waiting_orders'),
            'failed_orders' => __('admin.dashboard.queues.failed_orders'),
            'delivered_today' => __('admin.dashboard.queues.delivered_today'),
            'open_returns' => __('admin.dashboard.queues.open_returns'),
            'open_tickets' => __('admin.dashboard.queues.open_tickets'),
        ];
        $queueSections = collect($dashboard['queues'])->map(function ($items, string $key) use ($queueLabels): array {
            $rows = $items->map(function ($item) use ($key): array {
                return match ($key) {
                    'waiting_orders' => ['title' => $item->order_number, 'subtitle' => $item->customer_name, 'detail' => __('admin.order_status.'.$item->status), 'url' => route('admin.orders.show', $item->id)],
                    'failed_orders' => ['title' => $item->order_number, 'subtitle' => $item->customer_name, 'detail' => __('admin.orders.failure_reasons.'.$item->closure_reason_code), 'url' => route('admin.orders.show', $item->id)],
                    'delivered_today' => ['title' => $item->order_number, 'subtitle' => $item->customer_name, 'detail' => CarbonImmutable::parse($item->occurred_at, 'UTC')->timezone('Africa/Cairo')->format('H:i'), 'url' => route('admin.orders.show', $item->id)],
                    'open_returns' => ['title' => $item->return_number, 'subtitle' => $item->order_number.' · '.$item->customer_name, 'detail' => __('admin.returns.statuses.'.$item->status), 'url' => route('admin.returns.show', $item->id)],
                    default => ['title' => $item->ticket_number, 'subtitle' => $item->subject, 'detail' => __('admin.support_tickets.statuses.'.$item->status), 'url' => route('admin.support-tickets.show', $item->id)],
                };
            });

            return ['key' => $key, 'title' => $queueLabels[$key], 'rows' => $rows];
        })->values();
        $moduleSummaries = [];
        if ($user->hasPermission('customers.manage')) {
            $moduleSummaries[] = [
                'label' => __('admin.dashboard.customers'),
                'count' => DB::table('customers')->whereNull('deleted_at')->count(),
                'url' => route('admin.customers.index'),
            ];
        }
        if ($user->hasPermission('suppliers.manage')) {
            $moduleSummaries[] = [
                'label' => __('admin.dashboard.suppliers'),
                'count' => DB::table('suppliers')->whereNull('deleted_at')->where('is_active', true)->count(),
                'url' => route('admin.suppliers.index'),
            ];
        }
        if ($user->hasPermission('catalog.manage')) {
            $moduleSummaries[] = [
                'label' => __('admin.dashboard.categories'),
                'count' => DB::table('categories')->whereNull('deleted_at')->where('is_active', true)->count(),
                'url' => route('admin.categories.index'),
            ];
        }

        return view('admin.dashboard', [
            'moduleSummaries' => $moduleSummaries,
            'statsCards' => $statsCards,
            'queueSections' => $queueSections,
            'todayLabel' => CarbonImmutable::now('Africa/Cairo')->format('Y-m-d'),
            'canViewReports' => $user->hasPermission('reports.view'),
        ]);
    }
}
