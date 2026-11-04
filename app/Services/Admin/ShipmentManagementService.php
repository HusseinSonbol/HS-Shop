<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\ShipmentLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ShipmentManagementService
{
    /** @return array<string, mixed> */
    public function creationContext(int $orderId): array
    {
        $order = DB::table('orders')->where('id', $orderId)->first();
        abort_if(! $order, 404);
        $payment = DB::table('payments')->where('order_id', $orderId)->orderByDesc('id')->first();
        $shipments = DB::table('shipments')->where('order_id', $orderId)->orderBy('id')->get();

        return $this->buildCreationContext($order, $payment, $shipments);
    }

    /** @param array<string, mixed> $data */
    public function create(int $orderId, User $actor, array $data): bool
    {
        $idempotencyKey = 'manual-shipment-create:'.$actor->id.':'.$data['idempotency_key'];

        return DB::transaction(function () use ($orderId, $actor, $data, $idempotencyKey): bool {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);
            $existingEvent = DB::table('shipment_events')
                ->where('source', 'manual')
                ->where('provider_event_id', $idempotencyKey)
                ->first();
            if ($existingEvent) {
                return false;
            }

            $payment = DB::table('payments')->where('order_id', $orderId)->orderByDesc('id')->lockForUpdate()->first();
            $shipments = DB::table('shipments')->where('order_id', $orderId)->orderBy('id')->lockForUpdate()->get();
            $context = $this->buildCreationContext($order, $payment, $shipments);
            if (! $context['can_create']) {
                throw ValidationException::withMessages(['shipment' => $context['block_message']]);
            }

            $company = DB::table('shipping_companies')
                ->where('id', $data['shipping_company_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $company) {
                throw ValidationException::withMessages(['shipping_company_id' => __('admin.shipments.validation.company_invalid')]);
            }

            if ($this->compareMoney((string) $data['customer_shipping_charge'], $context['remaining_customer_charge']) > 0) {
                throw ValidationException::withMessages(['customer_shipping_charge' => __('admin.shipments.validation.customer_charge_exceeds_remaining')]);
            }

            $revision = DB::table('order_revisions')
                ->where('id', $order->applied_revision_id)
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first();
            if (! $revision) {
                throw ValidationException::withMessages(['shipment' => __('admin.shipments.validation.order_snapshot_missing')]);
            }
            $address = DB::table('order_revision_addresses')
                ->where('order_revision_id', $revision->id)
                ->where('address_type', 'shipping')
                ->first();
            $items = DB::table('order_revision_items')
                ->where('order_revision_id', $revision->id)
                ->orderBy('line_no')
                ->get();
            if (! $address || $items->isEmpty()) {
                throw ValidationException::withMessages(['shipment' => __('admin.shipments.validation.order_snapshot_missing')]);
            }

            $now = now();
            $shipmentId = (int) DB::table('shipments')->insertGetId([
                'shipment_number' => 'SHP-'.(string) Str::ulid(),
                'order_id' => $orderId,
                'shipping_company_id' => $company->id,
                'leg_type' => 'outbound',
                'status' => 'picked_up',
                'tracking_number' => $this->nullableTrim($data['tracking_number'] ?? null),
                'customer_shipping_charge' => (string) $data['customer_shipping_charge'],
                'carrier_cost_actual' => $this->nullableTrim($data['carrier_cost_actual'] ?? null),
                'expected_cod_collection' => $context['expected_cod_collection'],
                'actual_cod_collected' => null,
                'currency' => $order->currency,
                'shipped_at' => $now,
                'delivered_at' => null,
                'recipient_snapshot' => json_encode([
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'country_code' => $address->country_code,
                    'governorate' => $address->governorate,
                    'city' => $address->city,
                    'district' => $address->district,
                    'street' => $address->street,
                    'building' => $address->building,
                    'floor' => $address->floor,
                    'apartment' => $address->apartment,
                    'landmark' => $address->landmark,
                    'postal_code' => $address->postal_code,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_by' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($items as $item) {
                DB::table('shipment_items')->insert([
                    'shipment_id' => $shipmentId,
                    'order_item_id' => $item->order_item_id,
                    'quantity' => $item->quantity,
                ]);
            }

            $description = $this->nullableTrim($data['description'] ?? null) ?? __('admin.shipments.events.handed_to_carrier');
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipmentId,
                'status' => 'picked_up',
                'event_at' => $now,
                'description' => $description,
                'location' => null,
                'source' => 'manual',
                'provider_event_id' => $idempotencyKey,
                'payload' => json_encode([
                    'shipping_company_id' => $company->id,
                    'shipping_company_name' => $company->name,
                    'customer_shipping_charge' => (string) $data['customer_shipping_charge'],
                    'carrier_cost_actual' => $this->nullableTrim($data['carrier_cost_actual'] ?? null),
                    'expected_cod_collection' => $context['expected_cod_collection'],
                    'actor_user_id' => $actor->id,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actor_user_id' => $actor->id,
                'created_at' => $now,
            ]);
            $this->logActivity($actor->id, $orderId, $shipmentId, 'shipment.created', null, [
                'shipment_number' => DB::table('shipments')->where('id', $shipmentId)->value('shipment_number'),
                'shipping_company' => $company->name,
                'customer_shipping_charge' => (string) $data['customer_shipping_charge'],
                'carrier_cost_actual' => $this->nullableTrim($data['carrier_cost_actual'] ?? null),
                'expected_cod_collection' => $context['expected_cod_collection'],
            ]);

            return true;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function recordEvent(int $orderId, int $shipmentId, User $actor, array $data): bool
    {
        $idempotencyKey = 'manual-shipment-event:'.$actor->id.':'.$data['idempotency_key'];

        return DB::transaction(function () use ($orderId, $shipmentId, $actor, $data, $idempotencyKey): bool {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);
            $shipment = DB::table('shipments')
                ->where('id', $shipmentId)
                ->where('order_id', $orderId)
                ->lockForUpdate()
                ->first();
            abort_if(! $shipment, 404);

            if (DB::table('shipment_events')->where('source', 'manual')->where('provider_event_id', $idempotencyKey)->exists()) {
                return false;
            }

            if ($order->status !== 'shipped' || $order->close_outcome !== null || $order->closed_at !== null) {
                throw ValidationException::withMessages(['shipment' => __('admin.shipments.validation.order_not_ready')]);
            }

            $newStatus = $data['status'];
            if ($newStatus !== $shipment->status && ! ShipmentLifecycle::canTransition($shipment->status, $newStatus)) {
                throw ValidationException::withMessages(['status' => __('admin.shipments.validation.transition_invalid')]);
            }

            $eventAt = Carbon::parse($data['event_at'], 'Africa/Cairo')->utc();
            $carrierCost = array_key_exists('carrier_cost_actual', $data) && $data['carrier_cost_actual'] !== null
                ? (string) $data['carrier_cost_actual']
                : $shipment->carrier_cost_actual;
            $actualCod = $newStatus === 'delivered'
                ? (string) $data['actual_cod_collected']
                : $shipment->actual_cod_collected;
            $shippedAt = $shipment->shipped_at;
            if ($shippedAt === null && in_array($newStatus, ['picked_up', 'in_transit', 'out_for_delivery', 'delivery_attempt_failed', 'returning_to_sender', 'returned_to_sender', 'delivered'], true)) {
                $shippedAt = $eventAt;
            }
            $deliveredAt = $newStatus === 'delivered' ? $eventAt : $shipment->delivered_at;

            DB::table('shipments')->where('id', $shipmentId)->update([
                'status' => $newStatus,
                'carrier_cost_actual' => $carrierCost,
                'actual_cod_collected' => $actualCod,
                'shipped_at' => $shippedAt,
                'delivered_at' => $deliveredAt,
                'updated_at' => now(),
            ]);

            $details = [
                'previous_status' => $shipment->status,
                'new_status' => $newStatus,
                'previous_carrier_cost_actual' => $shipment->carrier_cost_actual,
                'new_carrier_cost_actual' => $carrierCost,
                'previous_actual_cod_collected' => $shipment->actual_cod_collected,
                'new_actual_cod_collected' => $actualCod,
            ];
            DB::table('shipment_events')->insert([
                'shipment_id' => $shipmentId,
                'status' => $newStatus,
                'event_at' => $eventAt,
                'description' => $this->nullableTrim($data['description'] ?? null),
                'location' => $this->nullableTrim($data['location'] ?? null),
                'source' => 'manual',
                'provider_event_id' => $idempotencyKey,
                'payload' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'actor_user_id' => $actor->id,
                'created_at' => now(),
            ]);
            $this->logActivity($actor->id, $orderId, $shipmentId, 'shipment.event_recorded', [
                'status' => $shipment->status,
                'carrier_cost_actual' => $shipment->carrier_cost_actual,
                'actual_cod_collected' => $shipment->actual_cod_collected,
            ], [
                ...$details,
                'description' => $this->nullableTrim($data['description'] ?? null),
                'location' => $this->nullableTrim($data['location'] ?? null),
                'event_at' => $eventAt->toIso8601String(),
            ]);

            return true;
        }, attempts: 3);
    }

    /** @param iterable<object> $shipments @return array<string, mixed> */
    private function buildCreationContext(object $order, ?object $payment, iterable $shipments): array
    {
        $shipments = collect($shipments);
        $allocatedCustomerCharge = '0.0000';
        $alreadyCollected = '0.0000';
        $blockMessage = null;

        foreach ($shipments as $shipment) {
            $allocatedCustomerCharge = $this->addMoney($allocatedCustomerCharge, (string) $shipment->customer_shipping_charge);
            if ($shipment->actual_cod_collected !== null) {
                $alreadyCollected = $this->addMoney($alreadyCollected, (string) $shipment->actual_cod_collected);
            }
        }

        if ($order->status !== 'shipped' || $order->close_outcome !== null || $order->closed_at !== null) {
            $blockMessage = __('admin.shipments.validation.order_not_ready');
        } elseif ($payment?->method === 'instapay' && $payment->status !== 'paid') {
            $blockMessage = __('admin.shipments.validation.instapay_not_verified');
        } elseif ($shipments->contains(fn ($shipment): bool => $shipment->status === 'delivered')) {
            $blockMessage = __('admin.shipments.validation.already_delivered');
        } elseif ($shipments->contains(fn ($shipment): bool => ! ShipmentLifecycle::canStartAnother($shipment->status))) {
            $blockMessage = __('admin.shipments.validation.previous_attempt_open');
        }

        $remainingCustomerCharge = $this->subtractFloorZero((string) $order->customer_shipping_charge, $allocatedCustomerCharge);
        $expectedCod = $payment?->status === 'paid'
            ? '0.0000'
            : $this->subtractFloorZero((string) $order->grand_total, $alreadyCollected);
        $companies = DB::table('shipping_companies')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        if ($blockMessage === null && $companies->isEmpty()) {
            $blockMessage = __('admin.shipments.validation.no_active_company');
        }

        return [
            'can_create' => $blockMessage === null,
            'block_message' => $blockMessage,
            'companies' => $companies,
            'remaining_customer_charge' => $remainingCustomerCharge,
            'expected_cod_collection' => $expectedCod,
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function logActivity(int $actorId, int $orderId, int $shipmentId, string $event, ?array $before, array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => 'shipment',
            'subject_id' => $shipmentId,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after' => json_encode(['order_id' => $orderId, ...$after], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }

    private function addMoney(string $left, string $right): string
    {
        return (string) DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) + CAST(? AS DECIMAL(19,4)) AS amount', [$left, $right])->amount;
    }

    private function compareMoney(string $left, string $right): int
    {
        return (int) DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(19,4)) < CAST(? AS DECIMAL(19,4)) THEN -1 WHEN CAST(? AS DECIMAL(19,4)) > CAST(? AS DECIMAL(19,4)) THEN 1 ELSE 0 END AS comparison', [$left, $right, $left, $right])->comparison;
    }

    private function subtractFloorZero(string $left, string $right): string
    {
        if ($this->compareMoney($left, $right) <= 0) {
            return '0.0000';
        }

        return (string) DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) - CAST(? AS DECIMAL(19,4)) AS amount', [$left, $right])->amount;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
