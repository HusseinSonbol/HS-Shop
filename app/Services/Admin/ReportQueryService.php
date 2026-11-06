<?php

namespace App\Services\Admin;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportQueryService
{
    public function dashboard(CarbonImmutable $startUtc, CarbonImmutable $endUtc, User $user): array
    {
        $stats = [];
        $queues = [];
        if ($user->hasPermission('orders.manage')) {
            $stats['orders_today'] = DB::table('orders')->where('placed_at', '>=', $startUtc)->where('placed_at', '<', $endUtc)->count();
            $stats['waiting_orders'] = DB::table('orders')->whereIn('status', ['new', 'confirmed', 'shipped'])->whereNull('close_outcome')->count();
            $stats['received_today'] = DB::table('order_activity_events')->where('event_type', 'status_changed')->where('current_status', 'received')
                ->where('occurred_at', '>=', $startUtc)->where('occurred_at', '<', $endUtc)->distinct('order_id')->count('order_id');
            $stats['failed_orders'] = DB::table('orders')->where('close_outcome', 'failure')->count();
            $queues['waiting_orders'] = DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->whereIn('orders.status', ['new', 'confirmed', 'shipped'])->whereNull('orders.close_outcome')
                ->select('orders.id', 'orders.order_number', 'orders.status', 'orders.placed_at', 'customers.display_name as customer_name')
                ->orderBy('orders.placed_at')->limit(6)->get();
            $queues['failed_orders'] = DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->where('orders.close_outcome', 'failure')
                ->select('orders.id', 'orders.order_number', 'orders.closure_reason_code', 'orders.closed_at', 'customers.display_name as customer_name')
                ->orderByDesc('orders.closed_at')->limit(6)->get();
            $queues['delivered_today'] = DB::table('order_activity_events as events')->join('orders', 'orders.id', '=', 'events.order_id')
                ->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->where('events.event_type', 'status_changed')->where('events.current_status', 'received')
                ->where('events.occurred_at', '>=', $startUtc)->where('events.occurred_at', '<', $endUtc)
                ->select('orders.id', 'orders.order_number', 'events.occurred_at', 'customers.display_name as customer_name')
                ->orderByDesc('events.occurred_at')->limit(6)->get();
        }
        if ($user->hasPermission('returns.manage')) {
            $stats['damaged_returns'] = DB::table('orders')->where('status', 'damaged_return')->count();
            $stats['open_returns'] = DB::table('returns')->whereNotIn('status', ['completed', 'refunded', 'rejected', 'cancelled'])->count();
            $queues['open_returns'] = DB::table('returns')->join('orders', 'orders.id', '=', 'returns.order_id')
                ->join('customers', 'customers.id', '=', 'returns.customer_id')
                ->whereNotIn('returns.status', ['completed', 'refunded', 'rejected', 'cancelled'])
                ->select('returns.id', 'returns.return_number', 'returns.status', 'returns.requested_at', 'orders.order_number', 'customers.display_name as customer_name')
                ->orderBy('returns.requested_at')->limit(6)->get();
        }
        if ($user->hasPermission('support_tickets.manage')) {
            $stats['open_tickets'] = DB::table('support_tickets')->whereNotIn('status', ['resolved', 'closed'])->count();
            $queues['open_tickets'] = DB::table('support_tickets')->whereNotIn('status', ['resolved', 'closed'])
                ->select('id', 'ticket_number', 'subject', 'status', 'updated_at')->orderBy('updated_at')->limit(6)->get();
        }
        if ($user->hasPermission('catalog.manage')) {
            $stats['available_products'] = DB::table('products')->whereNull('deleted_at')->where('status', 'published')->where('store_availability_status', 'available')->count();
        }
        if ($user->hasPermission('reviews.moderate')) {
            $stats['pending_reviews'] = DB::table('product_reviews')->where('moderation_status', 'pending')->count();
        }

        return compact('stats', 'queues');
    }

    public function report(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $orders = DB::table('orders')->leftJoin('order_revisions as revisions', function ($join): void {
            $join->on('revisions.id', '=', 'orders.applied_revision_id')->on('revisions.order_id', '=', 'orders.id');
        })->where('orders.placed_at', '>=', $startUtc)->where('orders.placed_at', '<', $endUtc)
            ->select('orders.id', 'orders.currency', 'orders.close_outcome')->get();
        $orderIds = $orders->pluck('id');

        $orderTotals = DB::table('orders')->leftJoin('order_revisions as revisions', function ($join): void {
            $join->on('revisions.id', '=', 'orders.applied_revision_id')->on('revisions.order_id', '=', 'orders.id');
        })->where('orders.placed_at', '>=', $startUtc)->where('orders.placed_at', '<', $endUtc)
            ->groupBy('orders.currency')->select('orders.currency')
            ->selectRaw('COUNT(*) AS order_count')
            ->selectRaw("SUM(CASE WHEN orders.close_outcome = 'success' THEN 1 ELSE 0 END) AS successful_count")
            ->selectRaw("SUM(CASE WHEN orders.close_outcome = 'failure' THEN 1 ELSE 0 END) AS failed_count")
            ->selectRaw('SUM(COALESCE(revisions.subtotal, orders.subtotal)) AS subtotal')
            ->selectRaw('SUM(COALESCE(revisions.discount_total, orders.discount_total)) AS discount_total')
            ->selectRaw('SUM(COALESCE(revisions.customer_shipping_charge, orders.customer_shipping_charge)) AS customer_shipping_charge')
            ->selectRaw('SUM(COALESCE(revisions.tax_total, orders.tax_total, 0)) AS tax_total')
            ->selectRaw('SUM(COALESCE(revisions.grand_total, orders.grand_total)) AS order_value')
            ->selectRaw("SUM(CASE WHEN orders.close_outcome = 'success' THEN COALESCE(revisions.subtotal, orders.subtotal) - COALESCE(revisions.discount_total, orders.discount_total) + COALESCE(revisions.customer_shipping_charge, orders.customer_shipping_charge) ELSE 0 END) AS successful_order_value")
            ->orderBy('orders.currency')->get();

        $costsByCurrency = $this->costsForOrderCohort($orderIds);
        $cohortRefunds = $this->refundTotalsForOrders($orderIds);
        $contribution = $this->contributionRows($orderTotals, $costsByCurrency, $cohortRefunds);

        return [
            'orders' => $orderTotals,
            'direct_costs' => $costsByCurrency,
            'direct_cost_breakdown' => $orderIds->isEmpty() ? collect() : DB::table('order_cost_entries')
                ->whereIn('order_id', $orderIds)->groupBy('currency', 'cost_type', 'basis')
                ->select('currency', 'cost_type', 'basis')->selectRaw('SUM(amount) AS amount')
                ->orderBy('currency')->orderBy('cost_type')->orderBy('basis')->get(),
            'contribution' => $contribution,
            'returns' => DB::table('returns')->where('requested_at', '>=', $startUtc)->where('requested_at', '<', $endUtc)
                ->selectRaw('COUNT(*) AS requested_count')
                ->selectRaw("SUM(CASE WHEN case_type = 'damaged_return' THEN 1 ELSE 0 END) AS damaged_count")->first(),
            'order_events' => (object) [
                'received_orders' => DB::table('order_activity_events')->where('event_type', 'status_changed')->where('current_status', 'received')
                    ->where('occurred_at', '>=', $startUtc)->where('occurred_at', '<', $endUtc)->distinct('order_id')->count('order_id'),
                'failure_closures' => DB::table('order_activity_events')->where('event_type', 'closed')->where('close_outcome', 'failure')
                    ->where('occurred_at', '>=', $startUtc)->where('occurred_at', '<', $endUtc)->count(),
            ],
            'refunds_paid' => DB::table('refunds')->where('status', 'completed')->whereNotNull('refunded_at')
                ->where('refunded_at', '>=', $startUtc)->where('refunded_at', '<', $endUtc)
                ->groupBy('currency')->select('currency')->selectRaw('COUNT(*) AS refund_count')->selectRaw('SUM(amount) AS amount')->orderBy('currency')->get(),
            'cash_on_delivery' => DB::table('shipments')->where('leg_type', 'outbound')->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', $startUtc)->where('delivered_at', '<', $endUtc)
                ->groupBy('currency')->select('currency')->selectRaw('SUM(COALESCE(actual_cod_collected, 0)) AS collected')->orderBy('currency')->get(),
            'overhead' => DB::table('expenses')->whereNull('order_id')->where('status', 'posted')
                ->where('expense_date', '>=', $startUtc->setTimezone('Africa/Cairo')->toDateString())
                ->where('expense_date', '<', $endUtc->setTimezone('Africa/Cairo')->toDateString())
                ->groupBy('currency', 'cost_category')->select('currency', 'cost_category')->selectRaw('COUNT(*) AS expense_count')->selectRaw('SUM(amount) AS amount')->orderBy('currency')->orderBy('cost_category')->get(),
            'current_statuses' => DB::table('orders')->where('placed_at', '>=', $startUtc)->where('placed_at', '<', $endUtc)
                ->groupBy('status')->select('status')->selectRaw('COUNT(*) AS total')->orderBy('status')->get(),
        ];
    }

    private function costsForOrderCohort(Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        $groups = DB::table('order_cost_entries')->whereIn('order_id', $orderIds)
            ->groupBy('order_id', 'currency', 'cost_type', 'order_item_id', 'shipment_id')
            ->select('order_id', 'currency', 'cost_type', 'order_item_id', 'shipment_id')
            ->selectRaw("SUM(CASE WHEN basis = 'actual' THEN amount ELSE 0 END) AS actual_amount")
            ->selectRaw("SUM(CASE WHEN basis = 'estimate' THEN amount ELSE 0 END) AS estimate_amount")
            ->selectRaw("MAX(CASE WHEN basis = 'actual' AND reverses_entry_id IS NULL AND NOT EXISTS (SELECT 1 FROM order_cost_entries AS reversals WHERE reversals.reverses_entry_id = order_cost_entries.id) THEN 1 ELSE 0 END) AS has_actual");

        return DB::query()->fromSub($groups, 'cost_groups')->groupBy('currency')->select('currency')
            ->selectRaw('SUM(actual_amount) AS actual_total')
            ->selectRaw('SUM(estimate_amount) AS estimate_total')
            ->selectRaw('SUM(CASE WHEN has_actual = 1 THEN actual_amount ELSE estimate_amount END) AS selected_total')
            ->orderBy('currency')->get();
    }

    private function refundTotalsForOrders(Collection $orderIds): Collection
    {
        if ($orderIds->isEmpty()) {
            return collect();
        }

        return DB::table('refunds')->whereIn('order_id', $orderIds)->where('status', 'completed')->whereNotNull('refunded_at')
            ->groupBy('currency')->select('currency')->selectRaw('SUM(amount) AS amount')->orderBy('currency')->get();
    }

    private function contributionRows(Collection $orders, Collection $costs, Collection $refunds): Collection
    {
        return $orders->map(function ($order) use ($costs, $refunds): object {
            $directCosts = $costs->firstWhere('currency', $order->currency)?->selected_total ?? '0.0000';
            $paidRefunds = (string) ($refunds->firstWhere('currency', $order->currency)?->amount ?? '0.0000');
            $base = DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) AS amount', [
                (string) $order->successful_order_value, (string) $directCosts, $paidRefunds,
            ])->amount;

            return (object) ['currency' => $order->currency, 'amount' => (string) $base];
        });
    }
}
