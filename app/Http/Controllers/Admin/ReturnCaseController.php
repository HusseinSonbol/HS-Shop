<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrderCostRequest;
use App\Http\Requests\Admin\RefundRecordRequest;
use App\Http\Requests\Admin\ReturnCaseRequest;
use App\Http\Requests\Admin\ReturnShipmentRequest;
use App\Http\Requests\Admin\ReturnTransitionRequest;
use App\Http\Requests\Admin\ShipmentEventRequest;
use App\Services\Admin\OrderCostLedgerService;
use App\Services\Admin\ReturnCaseManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReturnCaseController extends Controller
{
    public function index(Request $request): View
    {
        $cases = DB::table('returns')->join('orders', 'orders.id', '=', 'returns.order_id')
            ->join('customers', 'customers.id', '=', 'returns.customer_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'returns.created_by')
            ->select('returns.*', 'orders.order_number', 'customers.display_name as customer_name', 'customers.phone', 'actors.name as created_by_name')
            ->when($request->filled('status'), fn ($query) => $query->where('returns.status', $request->string('status')->toString()))
            ->orderByDesc('returns.requested_at')->paginate(20)->withQueryString();

        return view('admin.returns.index', ['cases' => $cases]);
    }

    public function create(int $order): View
    {
        $record = DB::table('orders')->join('customers', 'customers.id', '=', 'orders.customer_id')->where('orders.id', $order)
            ->select('orders.*', 'customers.display_name as customer_name', 'customers.phone')->first();
        abort_if(! $record, 404);
        abort_unless($record->status === 'received' && $record->close_outcome === 'success', 409, __('admin.returns.validation.order_not_delivered'));
        $items = DB::table('order_revision_items')->where('order_revision_id', $record->applied_revision_id)->orderBy('line_no')->get();
        foreach ($items as $item) {
            $item->already_requested = DB::table('return_items')->join('returns', 'returns.id', '=', 'return_items.return_id')
                ->where('return_items.order_item_id', $item->order_item_id)->where('returns.order_id', $order)
                ->whereNotIn('returns.status', ['rejected', 'cancelled'])->sum('return_items.quantity');
            $item->eligible_quantity = max(0, (float) $item->quantity - (float) $item->already_requested);
        }

        return view('admin.returns.create', compact('record', 'items'));
    }

    public function store(ReturnCaseRequest $request, int $order, ReturnCaseManagementService $service): RedirectResponse
    {
        $id = $service->create($order, $request->user(), $request->validated());

        return redirect()->route('admin.returns.show', $id)->with('status', __('admin.returns.created'));
    }

    public function show(int $return): View
    {
        $case = DB::table('returns')->join('orders', 'orders.id', '=', 'returns.order_id')
            ->join('customers', 'customers.id', '=', 'returns.customer_id')
            ->leftJoin('users as actors', 'actors.id', '=', 'returns.created_by')
            ->where('returns.id', $return)->select('returns.*', 'orders.order_number', 'orders.status as order_status', 'orders.currency', 'orders.applied_revision_id', 'customers.display_name as customer_name', 'customers.phone', 'actors.name as created_by_name')->first();
        abort_if(! $case, 404);
        $items = DB::table('return_items')->join('order_revision_items', function ($join) use ($case): void {
            $join->on('order_revision_items.order_item_id', '=', 'return_items.order_item_id')->where('order_revision_items.order_revision_id', '=', $case->applied_revision_id);
        })->where('return_items.return_id', $return)->select('return_items.*', 'order_revision_items.product_name_snapshot', 'order_revision_items.sku_snapshot', 'order_revision_items.variant_snapshot', 'order_revision_items.variant_id as original_variant_id')->get();
        $shipments = DB::table('shipments')->leftJoin('shipping_companies', 'shipping_companies.id', '=', 'shipments.shipping_company_id')
            ->where('shipments.return_id', $return)->select('shipments.*', 'shipping_companies.name as carrier_name')->orderBy('shipments.id')->get();
        foreach ($shipments as $shipment) {
            $shipment->events = DB::table('shipment_events')->leftJoin('users as actors', 'actors.id', '=', 'shipment_events.actor_user_id')
                ->where('shipment_events.shipment_id', $shipment->id)->select('shipment_events.*', 'actors.name as actor_name')->orderBy('event_at')->orderBy('shipment_events.id')->get();
            $shipment->cost_entries = DB::table('order_cost_entries')->where('shipment_id', $shipment->id)->orderBy('id')->get();
        }
        $refunds = DB::table('refunds')->where('return_id', $return)->orderBy('id')->get();
        $costEntries = DB::table('order_cost_entries')->leftJoin('users as actors', 'actors.id', '=', 'order_cost_entries.recorded_by')
            ->where('order_cost_entries.order_id', $case->order_id)->orderBy('order_cost_entries.id')
            ->select('order_cost_entries.*', 'actors.name as actor_name')->get();
        $companies = DB::table('shipping_companies')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $events = DB::table('activity_logs')->leftJoin('users as actors', 'actors.id', '=', 'activity_logs.actor_user_id')->where('activity_logs.subject_type', 'return')->where('activity_logs.subject_id', $return)->orderByDesc('activity_logs.id')->select('activity_logs.*', 'actors.name as actor_name')->get();
        $costItems = DB::table('order_revision_items')->where('order_revision_id', $case->applied_revision_id)->orderBy('line_no')->get(['order_item_id', 'product_name_snapshot', 'sku_snapshot', 'variant_snapshot']);

        return view('admin.returns.show', compact('case', 'items', 'shipments', 'refunds', 'costEntries', 'companies', 'events', 'costItems'));
    }

    public function transition(ReturnTransitionRequest $request, int $return, ReturnCaseManagementService $service): RedirectResponse
    {
        $service->transition($return, $request->user(), $request->validated());

        return back()->with('status', __('admin.returns.status_saved'));
    }

    public function refund(RefundRecordRequest $request, int $return, ReturnCaseManagementService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('refunds.manage'), 403);
        $service->recordRefund($return, $request->user(), $request->validated());

        return back()->with('status', __('admin.returns.refund_recorded'));
    }

    public function markRefundPaid(Request $request, int $return, int $refund, ReturnCaseManagementService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('refunds.manage'), 403);
        $service->markRefundPaid($return, $request->user(), $refund);

        return back()->with('status', __('admin.returns.refund_completed'));
    }

    public function voidRefund(Request $request, int $return, int $refund, ReturnCaseManagementService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('refunds.manage'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $service->voidPendingRefund($return, $request->user(), $refund, $data['reason']);

        return back()->with('status', __('admin.returns.refund_voided'));
    }

    public function shipment(ReturnShipmentRequest $request, int $return, ReturnCaseManagementService $service): RedirectResponse
    {
        $id = $service->createShipment($return, $request->user(), $request->validated());

        return back()->with('status', __('admin.returns.shipment_created', ['number' => DB::table('shipments')->where('id', $id)->value('shipment_number')]));
    }

    public function shipmentEvent(ShipmentEventRequest $request, int $return, int $shipment, ReturnCaseManagementService $service): RedirectResponse
    {
        $service->recordShipmentEvent($return, $shipment, $request->user(), $request->validated());

        return back()->with('status', __('admin.returns.shipment_event_saved'));
    }

    public function storeCost(OrderCostRequest $request, int $order, ReturnCaseManagementService $service): RedirectResponse
    {
        $created = $service->addCost($order, $request->user(), $request->validated());

        return back()->with('status', __($created ? 'admin.returns.cost_saved' : 'admin.returns.cost_already_saved'));
    }

    public function reverseCost(Request $request, int $order, int $entry, OrderCostLedgerService $costs): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('expenses.manage'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $costs->reverseEntry($order, $entry, $request->user()->id, $data['reason']);

        return back()->with('status', __('admin.returns.cost_reversed'));
    }
}
