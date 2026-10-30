<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $todayInCairo = CarbonImmutable::now('Africa/Cairo')->startOfDay();
        $todayStartUtc = $todayInCairo->utc();
        $tomorrowStartUtc = $todayInCairo->addDay()->utc();

        $stats = [
            'orders_today' => DB::table('orders')
                ->where('placed_at', '>=', $todayStartUtc)
                ->where('placed_at', '<', $tomorrowStartUtc)
                ->count(),
            'available_products' => DB::table('products')
                ->whereNull('deleted_at')
                ->where('status', 'published')
                ->where('store_availability_status', 'available')
                ->count(),
            'pending_reviews' => DB::table('product_reviews')
                ->where('moderation_status', 'pending')
                ->count(),
            'active_tickets' => DB::table('support_tickets')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->count(),
        ];

        $recentOrders = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->select([
                'orders.order_number',
                'orders.status',
                'orders.grand_total',
                'orders.currency',
                'orders.placed_at',
                'customers.display_name as customer_name',
            ])
            ->orderByDesc('orders.placed_at')
            ->limit(6)
            ->get();

        $user = Auth::user();
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
            'stats' => $stats,
            'moduleSummaries' => $moduleSummaries,
            'recentOrders' => $recentOrders,
            'todayLabel' => CarbonImmutable::now('Africa/Cairo')->format('Y-m-d'),
        ]);
    }
}
