<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\ReturnCaseLifecycle;
use App\Support\ShipmentLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReturnCaseManagementService
{
    public function __construct(private readonly OrderCostLedgerService $costs) {}

    /** @param array<string, mixed> $data */
    public function create(int $orderId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($orderId, $actor, $data): int {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);
            if ($order->status !== 'received' || $order->close_outcome !== 'success') {
                throw ValidationException::withMessages(['order' => __('admin.returns.validation.order_not_delivered')]);
            }
            if (! in_array($data['reason_code'], ['damaged', 'manufacturing_defect'], true)) {
                throw ValidationException::withMessages(['reason_code' => __('admin.returns.validation.reason_invalid')]);
            }

            $revision = DB::table('order_revisions')->where('id', $order->applied_revision_id)->where('order_id', $orderId)->lockForUpdate()->first();
            if (! $revision) {
                throw ValidationException::withMessages(['order' => __('admin.returns.validation.snapshot_missing')]);
            }
            $requested = collect($data['items'])->filter(fn (array $line): bool => (int) DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(18,3)) > 0 THEN 1 ELSE 0 END AS positive', [(string) $line['quantity']])->positive === 1)->keyBy(fn (array $line): int => (int) $line['order_item_id']);
            if ($requested->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('admin.returns.validation.item_required')]);
            }
            $lines = DB::table('order_revision_items')->where('order_revision_id', $revision->id)
                ->whereIn('order_item_id', $requested->keys())->orderBy('order_item_id')->lockForUpdate()->get()->keyBy('order_item_id');
            if ($lines->count() !== $requested->count()) {
                throw ValidationException::withMessages(['items' => __('admin.returns.validation.item_invalid')]);
            }
            foreach ($requested as $lineId => $entry) {
                $alreadyReturned = (string) DB::table('return_items')->join('returns', 'returns.id', '=', 'return_items.return_id')
                    ->where('return_items.order_item_id', $lineId)
                    ->where('returns.order_id', $orderId)
                    ->whereNotIn('returns.status', ['rejected', 'cancelled'])
                    ->sum('return_items.quantity');
                $validQuantity = DB::selectOne(
                    'SELECT CASE WHEN CAST(? AS DECIMAL(18,3)) > 0 AND CAST(? AS DECIMAL(18,3)) <= CAST(? AS DECIMAL(18,3)) - CAST(? AS DECIMAL(18,3)) THEN 1 ELSE 0 END AS valid',
                    [(string) $entry['quantity'], (string) $entry['quantity'], (string) $lines[$lineId]->quantity, $alreadyReturned],
                )->valid;
                if (! $validQuantity) {
                    throw ValidationException::withMessages(['items' => __('admin.returns.validation.quantity_exceeds_eligible')]);
                }
            }

            $now = now();
            $returnId = (int) DB::table('returns')->insertGetId([
                'return_number' => 'RET-'.(string) Str::ulid(), 'order_id' => $orderId,
                'customer_id' => $order->customer_id, 'case_type' => 'damaged_return', 'status' => 'requested',
                'reason_code' => $data['reason_code'], 'reason' => trim($data['reason']), 'requested_at' => $now,
                'received_at' => null, 'resolution' => null, 'notes' => null, 'created_by' => $actor->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($requested as $lineId => $entry) {
                DB::table('return_items')->insert([
                    'return_id' => $returnId, 'order_item_id' => $lineId, 'quantity' => $entry['quantity'],
                    'condition' => 'unused_claimed', 'disposition' => null, 'refund_amount' => 0,
                    'replacement_order_item_id' => null, 'replacement_variant_id' => null,
                    'replacement_sku_snapshot' => null, 'replacement_product_name_snapshot' => null,
                    'replacement_variant_snapshot' => null, 'replacement_quantity' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('orders')->where('id', $orderId)->update(['status' => 'damaged_return', 'updated_at' => $now]);
            $this->orderEvent($orderId, $actor->id, 'damaged_return_opened', 'received', 'damaged_return', ['return_id' => $returnId, 'return_number' => 'RET-'.(string) DB::table('returns')->where('id', $returnId)->value('return_number')], $now);
            $this->audit($actor->id, 'return.case_created', 'return', $returnId, null, ['order_id' => $orderId, 'reason_code' => $data['reason_code'], 'items' => $requested->all()]);

            return $returnId;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function transition(int $returnId, User $actor, array $data): void
    {
        DB::transaction(function () use ($returnId, $actor, $data): void {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            $next = $data['status'];
            if (! in_array($next, ReturnCaseLifecycle::TRANSITIONS[$case->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => __('admin.returns.validation.transition_invalid')]);
            }
            if ($next === 'refund_pending') {
                throw ValidationException::withMessages(['status' => __('admin.returns.validation.refund_record_action_required')]);
            }
            if ($next === 'item_received') {
                $deliveredPickup = DB::table('shipments')->where('return_id', $returnId)->where('leg_type', 'customer_return')->where('status', 'delivered')->exists();
                if (! $deliveredPickup) {
                    throw ValidationException::withMessages(['status' => __('admin.returns.validation.pickup_not_delivered')]);
                }
            }
            if ($next === 'inspection_accepted') {
                if (! filter_var($data['unused_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    throw ValidationException::withMessages(['unused_confirmed' => __('admin.returns.validation.unused_required')]);
                }
                if (trim((string) ($data['inspection_note'] ?? '')) === '') {
                    throw ValidationException::withMessages(['inspection_note' => __('admin.returns.validation.inspection_note_required')]);
                }
                DB::table('return_items')->where('return_id', $returnId)->update(['condition' => 'unused_confirmed', 'updated_at' => now()]);
            }
            if ($next === 'inspection_rejected' && trim((string) ($data['inspection_note'] ?? '')) === '') {
                throw ValidationException::withMessages(['inspection_note' => __('admin.returns.validation.inspection_rejection_note_required')]);
            }
            if ($next === 'exchange_preparing') {
                $replacements = collect($data['replacements'] ?? [])->keyBy(fn (array $row): int => (int) $row['return_item_id']);
                $items = DB::table('return_items')->where('return_id', $returnId)->orderBy('id')->lockForUpdate()->get();
                if ($replacements->count() !== $items->count()) {
                    throw ValidationException::withMessages(['replacements' => __('admin.returns.validation.replacement_required')]);
                }
                foreach ($items as $item) {
                    $choice = $replacements->get($item->id);
                    if (! $choice) {
                        throw ValidationException::withMessages(['replacements' => __('admin.returns.validation.replacement_required')]);
                    }
                    $order = DB::table('orders')->where('id', $case->order_id)->first();
                    $originalLine = DB::table('order_revision_items')->where('order_revision_id', $order->applied_revision_id)->where('order_item_id', $item->order_item_id)->first();
                    $sameQuantity = $originalLine ? DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(18,3)) = CAST(? AS DECIMAL(18,3)) THEN 1 ELSE 0 END AS same', [(string) $choice['quantity'], (string) $item->quantity])->same : 0;
                    $availableVariant = $originalLine ? DB::table('product_variants as variants')
                        ->join('products', 'products.id', '=', 'variants.product_id')
                        ->where('variants.id', $originalLine->variant_id)->where('variants.is_active', true)
                        ->whereNull('variants.deleted_at')->whereNull('products.deleted_at')
                        ->where('products.status', 'published')->where('products.store_availability_status', 'available')
                        ->lockForUpdate()->exists() : false;
                    if (! $originalLine || ! $availableVariant || (int) $originalLine->variant_id !== (int) $choice['variant_id'] || (int) $sameQuantity !== 1) {
                        throw ValidationException::withMessages(['replacements' => __('admin.returns.validation.replacement_invalid')]);
                    }
                    DB::table('return_items')->where('id', $item->id)->update([
                        'replacement_variant_id' => $originalLine->variant_id,
                        'replacement_sku_snapshot' => $originalLine->sku_snapshot,
                        'replacement_product_name_snapshot' => $originalLine->product_name_snapshot,
                        'replacement_variant_snapshot' => $originalLine->variant_snapshot,
                        'replacement_quantity' => $choice['quantity'], 'updated_at' => now(),
                    ]);
                }
            }
            if ($next === 'replacement_in_transit' && ! DB::table('shipments')->where('return_id', $returnId)->where('leg_type', 'exchange')->exists()) {
                throw ValidationException::withMessages(['status' => __('admin.returns.validation.exchange_shipment_required')]);
            }
            if ($next === 'completed') {
                $delivered = DB::table('shipments')->where('return_id', $returnId)->where('leg_type', 'exchange')->where('status', 'delivered')->exists();
                if (! $delivered) {
                    throw ValidationException::withMessages(['status' => __('admin.returns.validation.exchange_not_delivered')]);
                }
            }
            if ($next === 'refunded') {
                throw ValidationException::withMessages(['status' => __('admin.returns.validation.refund_paid_action_required')]);
            }

            $now = now();
            DB::table('returns')->where('id', $returnId)->update([
                'status' => $next,
                'received_at' => $next === 'item_received' ? $now : $case->received_at,
                'resolution' => in_array($next, ['completed', 'refunded'], true) ? ($next === 'completed' ? 'exchange' : 'refund') : $case->resolution,
                'notes' => $data['inspection_note'] ?? $case->notes,
                'updated_at' => $now,
            ]);
            $auditAfter = ['status' => $next, 'note' => $data['inspection_note'] ?? null];
            if ($next === 'exchange_preparing') {
                $auditAfter['replacement_items'] = DB::table('return_items')->where('return_id', $returnId)
                    ->orderBy('id')->get(['id', 'order_item_id', 'replacement_variant_id', 'replacement_sku_snapshot', 'replacement_product_name_snapshot', 'replacement_variant_snapshot', 'replacement_quantity'])
                    ->map(fn (object $item): array => (array) $item)->all();
            }
            if ($next === 'inspection_accepted') {
                $auditAfter['unused_confirmed'] = true;
            }
            $this->audit($actor->id, 'return.status_changed', 'return', $returnId, ['status' => $case->status], $auditAfter);
            $this->orderEvent($case->order_id, $actor->id, 'return_status_changed', null, null, ['return_id' => $returnId, 'previous_status' => $case->status, 'status' => $next], $now);

            if (in_array($next, ['rejected', 'cancelled'], true)) {
                $order = DB::table('orders')->where('id', $case->order_id)->lockForUpdate()->first();
                if ($order && $order->status === 'damaged_return') {
                    DB::table('orders')->where('id', $case->order_id)->update(['status' => 'received', 'updated_at' => $now]);
                    $this->orderEvent($case->order_id, $actor->id, 'damaged_return_closed', 'damaged_return', 'received', ['return_id' => $returnId, 'resolution' => $next], $now);
                }
            }

            if ($next === 'completed') {
                $order = DB::table('orders')->where('id', $case->order_id)->lockForUpdate()->first();
                if ($order && $order->status === 'damaged_return') {
                    DB::table('orders')->where('id', $case->order_id)->update(['status' => 'received', 'updated_at' => $now]);
                    $this->orderEvent($case->order_id, $actor->id, 'damaged_return_closed', 'damaged_return', 'received', ['return_id' => $returnId, 'resolution' => $next], $now);
                }
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function recordRefund(int $returnId, User $actor, array $data): void
    {
        DB::transaction(function () use ($returnId, $actor, $data): void {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            if ($case->status !== 'inspection_accepted') {
                throw ValidationException::withMessages(['return' => __('admin.returns.validation.refund_not_allowed')]);
            }
            $order = DB::table('orders')->where('id', $case->order_id)->lockForUpdate()->first();
            abort_if(! $order, 404);
            $orderTotal = (string) $order->grand_total;
            $alreadyRefunded = (string) DB::table('refunds')->where('order_id', $case->order_id)->whereNotIn('status', ['voided', 'rejected'])->sum('amount');
            $fitsRefundCap = DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(19,4)) <= CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) THEN 1 ELSE 0 END AS fits', [(string) $data['amount'], $orderTotal, $alreadyRefunded])->fits;
            if ((int) $fitsRefundCap !== 1) {
                throw ValidationException::withMessages(['amount' => __('admin.returns.validation.refund_exceeds_order_total')]);
            }
            $payment = DB::table('payments')->where('order_id', $case->order_id)->orderByDesc('id')->first();
            $now = now();
            $refundId = DB::table('refunds')->insertGetId([
                'refund_number' => 'RFD-'.(string) Str::ulid(), 'return_id' => $returnId,
                'payment_id' => $payment?->id, 'order_id' => $case->order_id, 'status' => 'pending',
                'amount' => $data['amount'], 'currency' => DB::table('orders')->where('id', $case->order_id)->value('currency'),
                'reason' => trim($data['reason']), 'refunded_at' => null, 'created_by' => $actor->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit($actor->id, 'return.refund_recorded', 'refund', $refundId, null, ['return_id' => $returnId, 'amount' => $data['amount'], 'reason' => $data['reason']]);
            DB::table('returns')->where('id', $returnId)->update(['status' => 'refund_pending', 'resolution' => 'refund', 'updated_at' => $now]);
        }, attempts: 3);
    }

    public function markRefundPaid(int $returnId, User $actor, int $refundId): void
    {
        DB::transaction(function () use ($returnId, $actor, $refundId): void {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            $refund = DB::table('refunds')->where('id', $refundId)->where('return_id', $returnId)->lockForUpdate()->first();
            abort_if(! $refund, 404);
            if ($case->status !== 'refund_pending' || $refund->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => __('admin.returns.validation.refund_not_allowed')]);
            }
            $now = now();
            DB::table('refunds')->where('id', $refundId)->update(['status' => 'completed', 'refunded_at' => $now, 'updated_at' => $now]);
            DB::table('returns')->where('id', $returnId)->update(['status' => 'refunded', 'updated_at' => $now]);
            DB::table('orders')->where('id', $case->order_id)->where('status', 'damaged_return')->update(['status' => 'received', 'updated_at' => $now]);
            $this->audit($actor->id, 'return.refund_completed', 'refund', $refundId, ['status' => 'pending'], ['status' => 'completed', 'amount' => $refund->amount]);
            $this->orderEvent($case->order_id, $actor->id, 'damaged_return_closed', 'damaged_return', 'received', ['return_id' => $returnId, 'resolution' => 'refund'], $now);
        }, attempts: 3);
    }

    public function voidPendingRefund(int $returnId, User $actor, int $refundId, string $reason): void
    {
        DB::transaction(function () use ($returnId, $actor, $refundId, $reason): void {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            $refund = DB::table('refunds')->where('id', $refundId)->where('return_id', $returnId)->lockForUpdate()->first();
            abort_if(! $refund, 404);
            if ($case->status !== 'refund_pending' || $refund->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => __('admin.returns.validation.refund_not_allowed')]);
            }
            DB::table('refunds')->where('id', $refundId)->update(['status' => 'voided', 'reason' => mb_substr($refund->reason.' · void: '.$reason, 0, 255), 'updated_at' => now()]);
            DB::table('returns')->where('id', $returnId)->update(['status' => 'inspection_accepted', 'resolution' => null, 'updated_at' => now()]);
            $this->audit($actor->id, 'return.refund_voided', 'refund', $refundId, ['status' => 'pending'], ['status' => 'voided', 'reason' => $reason]);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function recordShipmentEvent(int $returnId, int $shipmentId, User $actor, array $data): void
    {
        DB::transaction(function () use ($returnId, $shipmentId, $actor, $data): void {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            $shipment = DB::table('shipments')->where('id', $shipmentId)->where('return_id', $returnId)->lockForUpdate()->first();
            abort_if(! $shipment, 404);
            $eventKey = 'return-event:'.$shipmentId.':'.$actor->id.':'.$data['idempotency_key'];
            if (DB::table('shipment_events')->where('source', 'manual')->where('provider_event_id', $eventKey)->exists()) {
                return;
            }
            if ($shipment->status !== $data['status'] && ! ShipmentLifecycle::canTransition($shipment->status, $data['status'])) {
                throw ValidationException::withMessages(['status' => __('admin.shipments.validation.transition_invalid')]);
            }
            $now = Carbon::parse($data['event_at'], 'Africa/Cairo')->utc();
            $carrierCost = $data['carrier_cost_actual'] ?? $shipment->carrier_cost_actual;
            $deliveredAt = $data['status'] === 'delivered' ? $now : $shipment->delivered_at;
            DB::table('shipments')->where('id', $shipmentId)->update([
                'status' => $data['status'], 'carrier_cost_actual' => $carrierCost,
                'actual_cod_collected' => $data['status'] === 'delivered' ? '0.0000' : $shipment->actual_cod_collected,
                'shipped_at' => $shipment->shipped_at ?? (in_array($data['status'], ['picked_up', 'in_transit', 'out_for_delivery', 'delivered'], true) ? $now : null),
                'delivered_at' => $deliveredAt, 'updated_at' => now(),
            ]);
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipmentId, 'status' => $data['status'], 'event_at' => $now,
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'location' => trim((string) ($data['location'] ?? '')) ?: null, 'source' => 'manual',
                'provider_event_id' => $eventKey, 'payload' => json_encode(['previous_status' => $shipment->status, 'new_status' => $data['status'], 'carrier_cost_actual' => $carrierCost], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actor_user_id' => $actor->id, 'created_at' => now(),
            ]);
            if ($carrierCost !== null && (string) $carrierCost !== (string) $shipment->carrier_cost_actual) {
                $type = $shipment->leg_type === 'customer_return' ? 'return_shipping' : 'exchange_shipping';
                $this->costs->syncShipmentActual($case->order_id, $shipmentId, $actor->id, $type, (string) $carrierCost);
            }
            $this->audit($actor->id, 'return.shipment_event', 'shipment', $shipmentId, ['status' => $shipment->status], ['status' => $data['status'], 'return_id' => $returnId]);

            if ($shipment->leg_type === 'customer_return' && $data['status'] === 'delivered' && $case->status === 'pickup_in_transit') {
                DB::table('returns')->where('id', $returnId)->update(['status' => 'item_received', 'received_at' => $now, 'updated_at' => now()]);
                $this->orderEvent($case->order_id, $actor->id, 'return_status_changed', null, null, ['return_id' => $returnId, 'status' => 'item_received'], $now);
            }
            if ($shipment->leg_type === 'exchange' && $data['status'] === 'delivered' && $case->status === 'replacement_in_transit') {
                DB::table('returns')->where('id', $returnId)->update(['status' => 'completed', 'resolution' => 'exchange', 'updated_at' => now()]);
                DB::table('orders')->where('id', $case->order_id)->where('status', 'damaged_return')->update(['status' => 'received', 'updated_at' => now()]);
                $this->orderEvent($case->order_id, $actor->id, 'damaged_return_closed', 'damaged_return', 'received', ['return_id' => $returnId, 'resolution' => 'exchange'], $now);
            }
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function createShipment(int $returnId, User $actor, array $data): int
    {
        return DB::transaction(function () use ($returnId, $actor, $data): int {
            $case = DB::table('returns')->where('id', $returnId)->lockForUpdate()->first();
            abort_if(! $case, 404);
            $legType = $data['leg_type'];
            $latestLeg = DB::table('shipments')->where('return_id', $returnId)->where('leg_type', $legType)->orderByDesc('id')->first();
            $retryReady = $latestLeg && ShipmentLifecycle::canStartAnother($latestLeg->status);
            $allowed = $legType === 'customer_return'
                ? ($case->status === 'approved' || ($case->status === 'pickup_in_transit' && $retryReady))
                : ($case->status === 'exchange_preparing' || ($case->status === 'replacement_in_transit' && $retryReady));
            $idempotencyKey = 'return-shipment:'.$returnId.':'.$legType.':'.$data['idempotency_key'];
            $existingEvent = DB::table('shipment_events')->where('source', 'manual')->where('provider_event_id', $idempotencyKey)->first();
            if ($existingEvent) {
                return (int) $existingEvent->shipment_id;
            }
            if (! $allowed) {
                throw ValidationException::withMessages(['leg_type' => __('admin.returns.validation.shipment_not_allowed')]);
            }
            $order = DB::table('orders')->where('id', $case->order_id)->lockForUpdate()->first();
            $revision = DB::table('order_revisions')->where('id', $order->applied_revision_id)->first();
            $address = DB::table('order_revision_addresses')->where('order_revision_id', $revision->id)->where('address_type', 'shipping')->first();
            $company = DB::table('shipping_companies')->where('id', $data['shipping_company_id'])->where('is_active', true)->first();
            if (! $address || ! $company) {
                throw ValidationException::withMessages(['shipment' => __('admin.returns.validation.shipment_data_invalid')]);
            }
            $items = DB::table('return_items')->where('return_id', $returnId)->get();
            $now = now();
            $shipmentId = (int) DB::table('shipments')->insertGetId([
                'shipment_number' => 'SHP-'.(string) Str::ulid(), 'order_id' => $case->order_id, 'return_id' => $returnId,
                'shipping_company_id' => $company->id, 'leg_type' => $legType, 'status' => 'awaiting_pickup',
                'tracking_number' => trim((string) ($data['tracking_number'] ?? '')) ?: null,
                'customer_shipping_charge' => '0.0000', 'carrier_cost_actual' => $data['carrier_cost_actual'] ?? null,
                'expected_cod_collection' => '0.0000', 'actual_cod_collected' => null, 'currency' => $order->currency,
                'shipped_at' => null, 'delivered_at' => null,
                'recipient_snapshot' => json_encode(['recipient_name' => $address->recipient_name, 'phone' => $address->phone, 'governorate' => $address->governorate, 'city' => $address->city, 'district' => $address->district, 'street' => $address->street, 'building' => $address->building, 'floor' => $address->floor, 'apartment' => $address->apartment], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_by' => $actor->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($items as $item) {
                DB::table('shipment_items')->insert(['shipment_id' => $shipmentId, 'order_item_id' => $item->order_item_id, 'quantity' => $legType === 'exchange' ? ($item->replacement_quantity ?: $item->quantity) : $item->quantity]);
            }
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipmentId, 'status' => 'awaiting_pickup', 'event_at' => $now,
                'description' => trim((string) ($data['description'] ?? '')) ?: null, 'location' => null,
                'source' => 'manual', 'provider_event_id' => $idempotencyKey,
                'payload' => json_encode(['return_id' => $returnId, 'leg_type' => $legType, 'shipping_company' => $company->name], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actor_user_id' => $actor->id, 'created_at' => $now,
            ]);
            DB::table('returns')->where('id', $returnId)->update(['status' => $legType === 'customer_return' ? 'pickup_in_transit' : 'replacement_in_transit', 'updated_at' => $now]);
            $this->audit($actor->id, 'return.shipment_created', 'shipment', $shipmentId, null, ['return_id' => $returnId, 'leg_type' => $legType, 'carrier_cost_actual' => $data['carrier_cost_actual'] ?? null]);
            if (! empty($data['carrier_cost_actual'])) {
                $this->costs->syncShipmentActual($case->order_id, $shipmentId, $actor->id, $legType === 'customer_return' ? 'return_shipping' : 'exchange_shipping', (string) $data['carrier_cost_actual']);
            }

            return $shipmentId;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function addCost(int $orderId, User $actor, array $data): bool
    {
        return DB::transaction(function () use ($orderId, $actor, $data): bool {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);
            $key = (string) $data['idempotency_key'];
            $alreadyRecorded = DB::table('activity_logs')->where('event', 'order.cost_recorded')->where('subject_id', $orderId)->where('after', 'like', '%"idempotency_key":"'.$key.'"%')->exists();
            if ($alreadyRecorded) {
                return false;
            }
            $shipmentId = $data['shipment_id'] ?? null;
            if ($shipmentId && ! DB::table('shipments')->where('id', $shipmentId)->where('order_id', $orderId)->exists()) {
                throw ValidationException::withMessages(['shipment_id' => __('admin.returns.validation.shipment_invalid')]);
            }
            $orderItemId = $data['order_item_id'] ?? null;
            if ($data['cost_type'] === 'product' && (! $orderItemId || ! DB::table('order_items')->where('id', $orderItemId)->where('order_id', $orderId)->exists())) {
                throw ValidationException::withMessages(['order_item_id' => __('admin.returns.validation.item_invalid')]);
            }
            if ($data['cost_type'] !== 'product' && $orderItemId !== null) {
                throw ValidationException::withMessages(['order_item_id' => __('admin.returns.validation.item_invalid')]);
            }
            if ($data['cost_type'] === 'product' && DB::table('order_cost_entries')->where('order_item_id', $orderItemId)->where('cost_type', 'product')->where('basis', $data['basis'])->whereNull('reverses_entry_id')->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_cost_entries as reversals')->whereColumn('reversals.reverses_entry_id', 'order_cost_entries.id'))->exists()) {
                throw ValidationException::withMessages(['order_item_id' => __('admin.returns.validation.product_cost_exists')]);
            }
            $entryId = $this->costs->record($orderId, $actor->id, $data['cost_type'], $data['basis'], (string) $data['amount'], trim($data['description']), $shipmentId, $orderItemId);
            $this->audit($actor->id, 'order.cost_recorded', 'order', $orderId, null, ['entry_id' => $entryId, 'cost_type' => $data['cost_type'], 'basis' => $data['basis'], 'amount' => $data['amount'], 'description' => trim($data['description']), 'order_item_id' => $orderItemId, 'idempotency_key' => $key]);

            return true;
        }, attempts: 3);
    }

    private function orderEvent(int $orderId, int $actorId, string $type, ?string $previous, ?string $current, array $details, mixed $now): void
    {
        DB::table('order_activity_events')->insert([
            'order_id' => $orderId, 'actor_user_id' => $actorId, 'event_type' => $type,
            'previous_status' => $previous, 'current_status' => $current, 'close_outcome' => null,
            'reason_code' => null, 'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'next_follow_up_at' => null, 'occurred_at' => $now, 'idempotency_key' => null, 'created_at' => $now,
        ]);
    }

    private function audit(int $actorId, string $event, string $subject, int $subjectId, ?array $before, array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId, 'event' => $event, 'subject_type' => $subject, 'subject_id' => $subjectId,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'), 'created_at' => now(),
        ]);
    }
}
