<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerOrderController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer')->customer;
        abort_unless($customer, 403);

        $itemCounts = DB::table('order_revision_items')
            ->select('order_revision_id')
            ->selectRaw('COUNT(*) AS item_count')
            ->groupBy('order_revision_id');

        $orders = DB::table('orders')
            ->leftJoin('order_revisions as revisions', 'revisions.id', '=', 'orders.applied_revision_id')
            ->leftJoinSub($itemCounts, 'revision_item_counts', fn ($join) => $join->on('revision_item_counts.order_revision_id', '=', 'revisions.id'))
            ->where('orders.customer_id', $customer->id)
            ->select([
                'orders.public_id', 'orders.order_number', 'orders.status', 'orders.currency', 'orders.placed_at',
                'revisions.revision_no', 'revisions.grand_total',
                'revision_item_counts.item_count',
            ])
            ->orderByDesc('orders.placed_at')
            ->orderByDesc('orders.id')
            ->paginate(10)
            ->withQueryString();

        $orders->getCollection()->transform(function ($order): object {
            $key = 'store.customer_orders.statuses.'.$order->status;
            $order->status_label = Lang::has($key) ? __($key) : $order->status;

            return $order;
        });

        return view('storefront.account.orders.index', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, string $publicId): View
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1, 404);

        $customer = $request->user('customer')->customer;
        abort_unless($customer, 403);

        $order = DB::table('orders')
            ->leftJoin('order_revisions as revisions', 'revisions.id', '=', 'orders.applied_revision_id')
            ->leftJoin('order_revision_addresses as addresses', function ($join): void {
                $join->on('addresses.order_revision_id', '=', 'revisions.id')
                    ->where('addresses.address_type', '=', 'shipping');
            })
            ->where('orders.public_id', $publicId)
            ->where('orders.customer_id', $customer->id)
            ->select([
                'orders.id', 'orders.public_id', 'orders.order_number', 'orders.status', 'orders.placed_at',
                'orders.currency as order_currency', 'orders.grand_total as order_grand_total',
                'revisions.id as revision_id', 'revisions.revision_no', 'revisions.currency',
                'revisions.subtotal', 'revisions.discount_total', 'revisions.customer_shipping_charge',
                'revisions.tax_total', 'revisions.grand_total',
                'addresses.recipient_name', 'addresses.phone', 'addresses.country_code', 'addresses.governorate',
                'addresses.city', 'addresses.district', 'addresses.street', 'addresses.building',
                'addresses.floor', 'addresses.apartment', 'addresses.landmark', 'addresses.postal_code',
            ])
            ->firstOrFail();

        $items = $order->revision_id
            ? DB::table('order_revision_items')
                ->where('order_revision_id', $order->revision_id)
                ->orderBy('line_no')
                ->get()
            : collect();

        $statusKey = 'store.customer_orders.statuses.'.$order->status;
        $order->status_label = Lang::has($statusKey) ? __($statusKey) : $order->status;

        return view('storefront.account.orders.show', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'order' => $order,
            'items' => $items,
        ]);
    }
}
