<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PlaceWebsiteOrderRequest;
use App\Services\Storefront\WebsiteOrderPlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WebsiteOrderController extends Controller
{
    public function place(PlaceWebsiteOrderRequest $request, WebsiteOrderPlacementService $orders): RedirectResponse
    {
        $result = $orders->place($request->user('customer'), $request->validated(), app()->getLocale());
        $request->session()->forget('checkout.idempotency_key');

        return redirect()->route('store.orders.confirmation', $result['public_id']);
    }

    public function confirmation(Request $request, string $publicId): View
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $publicId) === 1, 404);

        $customer = $request->user('customer')->customer;
        abort_unless($customer, 403);

        $order = DB::table('orders')
            ->join('order_revisions', 'order_revisions.id', '=', 'orders.applied_revision_id')
            ->leftJoin('order_revision_addresses', function ($join): void {
                $join->on('order_revision_addresses.order_revision_id', '=', 'order_revisions.id')
                    ->where('order_revision_addresses.address_type', '=', 'shipping');
            })
            ->where('orders.public_id', $publicId)
            ->where('orders.customer_id', $customer->id)
            ->select([
                'orders.id', 'orders.order_number', 'orders.status', 'orders.currency',
                'order_revisions.id as revision_id',
                'order_revisions.subtotal', 'order_revisions.discount_total', 'order_revisions.customer_shipping_charge', 'order_revisions.grand_total',
                'order_revision_addresses.recipient_name', 'order_revision_addresses.phone', 'order_revision_addresses.governorate',
                'order_revision_addresses.city', 'order_revision_addresses.street', 'order_revision_addresses.building',
            ])
            ->firstOrFail();
        $items = DB::table('order_revision_items')
            ->where('order_revision_id', $order->revision_id)
            ->orderBy('line_no')
            ->get();

        return view('storefront.orders.confirmation', [
            'locale' => app()->getLocale(),
            'isRtl' => app()->getLocale() === 'ar',
            'order' => $order,
            'items' => $items,
        ]);
    }
}
