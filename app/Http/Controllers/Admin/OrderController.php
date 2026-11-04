<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderEventRequest;
use App\Http\Requests\Admin\PaymentProofReviewRequest;
use App\Http\Requests\Admin\PaymentProofUploadRequest;
use App\Http\Requests\Admin\SocialOrderRequest;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Admin\OrderActivityService;
use App\Services\Admin\ShipmentManagementService;
use App\Services\Admin\SocialOrderManagementService;
use App\Services\Storefront\ShippingRateService;
use App\Support\OrderLifecycle;
use App\Support\ShipmentLifecycle;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function createSocial(ShippingRateService $shippingRates): View
    {
        $customers = Customer::query()->orderBy('display_name')->get(['id', 'display_name', 'phone']);
        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->selectRaw('CASE WHEN current_sale_price IS NOT NULL AND current_sale_price < current_regular_price THEN current_sale_price ELSE current_regular_price END AS display_price')
            ->where('is_active', true)
            ->where('currency', 'EGP')
            ->whereHas('product', fn ($query) => $query->where('status', 'published')->where('store_availability_status', 'available'))
            ->with(['product.translations', 'attributes.translations', 'attributes.values.translations'])
            ->orderBy('product_id')->orderBy('id')->get()
            ->map(function (ProductVariant $variant): array {
                $locale = app()->getLocale();
                $product = $variant->product;
                $translation = $product->translations->firstWhere('locale', $locale)
                    ?? $product->translations->firstWhere('locale', 'en')
                    ?? $product->translations->first();
                $optionLabels = $variant->attributes->map(function ($attribute) use ($locale): ?string {
                    $value = $attribute->values->firstWhere('id', $attribute->pivot->attribute_value_id);
                    $attributeName = $attribute->translations->firstWhere('locale', $locale)?->name
                        ?? $attribute->translations->firstWhere('locale', 'en')?->name
                        ?? $attribute->code;
                    $valueName = $value?->translations->firstWhere('locale', $locale)?->value
                        ?? $value?->translations->firstWhere('locale', 'en')?->value
                        ?? $value?->code;

                    return $valueName ? $attributeName.': '.$valueName : null;
                })->filter()->implode(' · ');

                return [
                    'id' => $variant->id,
                    'label' => trim(($translation?->name ?? $product->sku).' · '.($variant->variant_name ?: $optionLabels ?: $variant->sku).' · '.$variant->sku.' · EGP '.$variant->display_price),
                ];
            });

        return view('admin.orders.create-social', [
            'customers' => $customers,
            'variants' => $variants,
            'shippingRates' => $shippingRates->all(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function storeSocial(SocialOrderRequest $request, SocialOrderManagementService $orders): RedirectResponse
    {
        $result = $orders->create($request->user(), $request->validated());
        $message = $result['created'] ? __('admin.social_orders.created') : __('admin.social_orders.already_created');

        return redirect()->route('admin.orders.show', $result['order_id'])->with('status', $message);
    }

    public function reviewPaymentProof(PaymentProofReviewRequest $request, int $order, int $proof, SocialOrderManagementService $orders): RedirectResponse
    {
        $orders->reviewProof($order, $proof, $request->user(), $request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.social_orders.proof_review_saved'));
    }

    public function uploadPaymentProof(PaymentProofUploadRequest $request, int $order, SocialOrderManagementService $orders): RedirectResponse
    {
        $created = $orders->uploadReplacementProof($order, $request->user(), $request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', $created
            ? __('admin.social_orders.proof_uploaded')
            : __('admin.social_orders.proof_already_uploaded'));
    }

    public function showPaymentProof(int $order, int $proof, SocialOrderManagementService $orders): mixed
    {
        $record = $orders->proofForOrder($order, $proof);
        $disk = Storage::disk('local');
        $mimeType = $disk->mimeType($record->storage_path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $path = $disk->path($record->storage_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="payment-proof-'.$proof.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:24'],
            'channel' => ['nullable', 'string', 'max:24'],
            'shipment_status' => ['nullable', 'string', 'max:24'],
        ], [
            'q.string' => __('admin.orders.validation.search_invalid'),
            'q.max' => __('admin.orders.validation.search_invalid'),
            'status.string' => __('admin.orders.validation.filter_invalid'),
            'status.max' => __('admin.orders.validation.filter_invalid'),
            'channel.string' => __('admin.orders.validation.filter_invalid'),
            'channel.max' => __('admin.orders.validation.filter_invalid'),
            'shipment_status.string' => __('admin.orders.validation.filter_invalid'),
            'shipment_status.max' => __('admin.orders.validation.filter_invalid'),
        ]);

        $latestShipmentStatus = DB::table('shipments as s')->select('s.status')->whereColumn('s.order_id', 'orders.id')->orderByDesc('s.id')->limit(1);

        $orders = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'orders.created_by_user_id')
            ->select([
                'orders.id', 'orders.order_number', 'orders.channel', 'orders.status', 'orders.currency',
                'orders.grand_total', 'orders.placed_at', 'orders.close_outcome', 'orders.closure_reason_code', 'customers.display_name as customer_name',
                'customers.phone as customer_phone', 'creators.name as creator_name',
            ])
            ->selectSub($latestShipmentStatus, 'shipment_status')
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(function (Builder $search) use ($like): void {
                    $search->where('orders.order_number', 'like', $like)
                        ->orWhere('customers.display_name', 'like', $like)
                        ->orWhere('customers.phone', 'like', $like)
                        ->orWhere('customers.email', 'like', $like);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('orders.status', $status))
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->where('orders.channel', $channel))
            ->when($filters['shipment_status'] ?? null, fn (Builder $query, string $status) => $query->whereExists(fn (Builder $shipments) => $shipments->selectRaw('1')->from('shipments')->whereColumn('shipments.order_id', 'orders.id')->where('shipments.status', $status)->whereRaw('shipments.id = (SELECT MAX(latest_shipment.id) FROM shipments AS latest_shipment WHERE latest_shipment.order_id = orders.id)')))
            ->orderByDesc('orders.placed_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'orderStatuses' => DB::table('orders')->select('status')->distinct()->orderBy('status')->pluck('status'),
            'channels' => DB::table('orders')->select('channel')->distinct()->orderBy('channel')->pluck('channel'),
            'shipmentStatuses' => DB::table('shipments')->select('status')->distinct()->orderBy('status')->pluck('status'),
        ]);
    }

    public function show(int $order, ShipmentManagementService $shipmentManagement): View
    {
        $record = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'orders.created_by_user_id')
            ->where('orders.id', $order)
            ->select('orders.*', 'customers.display_name as customer_name', 'customers.phone as customer_phone', 'customers.email as customer_email', 'creators.name as creator_name')
            ->first();
        abort_if(! $record, 404);

        $revisions = DB::table('order_revisions')->where('order_id', $order)->orderBy('revision_no')->get();
        foreach ($revisions as $revision) {
            $revision->items = DB::table('order_revision_items')->where('order_revision_id', $revision->id)->orderBy('line_no')->get();
            $revision->address = DB::table('order_revision_addresses')->where('order_revision_id', $revision->id)->where('address_type', 'shipping')->first();
        }

        $paymentMethod = DB::table('payments')->where('order_id', $order)->orderByDesc('id')->value('method');
        $instapayPayment = null;
        $instapayProofs = collect();
        if (in_array($record->channel, ['facebook', 'instagram'], true)) {
            $instapayPayment = DB::table('payments')->where('order_id', $order)->where('method', 'instapay')->orderByDesc('id')->first();
            if ($instapayPayment) {
                $instapayProofs = DB::table('payment_proofs')
                    ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_proofs.payment_transaction_id')
                    ->leftJoin('users as reviewers', 'reviewers.id', '=', 'payment_proofs.reviewed_by')
                    ->where('payment_transactions.payment_id', $instapayPayment->id)
                    ->orderByDesc('payment_proofs.id')
                    ->select('payment_proofs.*', 'reviewers.name as reviewer_name')
                    ->get();
            }
        }

        $shipments = DB::table('shipments')
            ->leftJoin('shipping_companies', 'shipping_companies.id', '=', 'shipments.shipping_company_id')
            ->where('shipments.order_id', $order)
            ->orderBy('shipments.id')
            ->select('shipments.*', 'shipping_companies.name as carrier_name')
            ->get();
        foreach ($shipments as $shipment) {
            $shipment->events = DB::table('shipment_events')
                ->leftJoin('users as actors', 'actors.id', '=', 'shipment_events.actor_user_id')
                ->where('shipment_id', $shipment->id)
                ->orderBy('event_at')->orderBy('shipment_events.id')
                ->select('shipment_events.*', 'actors.name as actor_name')->get();
            $creationEvent = $shipment->events->first(fn ($event) => $event->payload && isset(json_decode($event->payload, true)['shipping_company_name']));
            $creationPayload = $creationEvent ? json_decode($creationEvent->payload, true) : [];
            $shipment->carrier_name_snapshot = $creationPayload['shipping_company_name'] ?? null;
            $shipment->next_statuses = ShipmentLifecycle::TRANSITIONS[$shipment->status] ?? [];
            $shipment->collection_variance = $shipment->actual_cod_collected === null ? null : DB::selectOne(
                'SELECT CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) AS amount',
                [(string) $shipment->actual_cod_collected, (string) $shipment->expected_cod_collection],
            )->amount;
        }
        $shipmentCreation = auth()->user()->hasPermission('shipments.manage') ? $shipmentManagement->creationContext($order) : null;

        $events = DB::table('order_activity_events')
            ->leftJoin('users as actors', 'actors.id', '=', 'order_activity_events.actor_user_id')
            ->where('order_activity_events.order_id', $order)
            ->orderByDesc('order_activity_events.occurred_at')
            ->orderByDesc('order_activity_events.id')
            ->select('order_activity_events.*', 'actors.name as actor_name')
            ->get();

        $assignmentEvent = $events->first(fn ($event) => $event->event_type === 'assigned');
        $assignmentDetails = $assignmentEvent?->details ? json_decode($assignmentEvent->details, true) : [];
        $assignedTo = $assignmentDetails['new_assignee'] ?? null;
        $lastClosure = $events->first(fn ($event) => $event->event_type === 'closed');
        $nextStatuses = ($record->close_outcome === null && $record->closed_at === null)
            ? (OrderLifecycle::TRANSITIONS[$record->status] ?? [])
            : [];
        $canCloseOrder = $record->close_outcome === null && $record->status !== 'received';
        $canReopenOrder = $record->close_outcome === 'failure'
            && $lastClosure
            && $lastClosure->event_type === 'closed';
        $staff = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.code', ['super_admin', 'manager', 'operator']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.orders.show', compact(
            'record', 'revisions', 'shipments', 'events', 'assignedTo', 'staff', 'paymentMethod', 'instapayPayment', 'instapayProofs',
            'nextStatuses', 'canCloseOrder', 'canReopenOrder', 'lastClosure', 'shipmentCreation',
        ));
    }

    public function storeEvent(OrderEventRequest $request, int $order, OrderActivityService $activity): RedirectResponse
    {
        $activity->record($order, $request->user(), $request->validated());

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.orders.event_saved'));
    }
}
