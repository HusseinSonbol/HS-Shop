<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCostLedgerService
{
    public function reverseEntry(int $orderId, int $entryId, int $actorId, string $reason): void
    {
        DB::transaction(function () use ($orderId, $entryId, $actorId, $reason): void {
            $entry = DB::table('order_cost_entries')->where('id', $entryId)->where('order_id', $orderId)->lockForUpdate()->first();
            abort_if(! $entry, 404);
            $alreadyReversed = DB::table('order_cost_entries')->where('reverses_entry_id', $entryId)->exists();
            if ($alreadyReversed) {
                throw ValidationException::withMessages(['cost' => __('admin.returns.validation.cost_already_reversed')]);
            }
            $negative = DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) * -1 AS amount', [(string) $entry->amount])->amount;
            $this->appendReversal($entry, $actorId, (string) $negative, trim($reason));
        }, attempts: 3);
    }

    public function record(int $orderId, int $actorId, string $type, string $basis, string $amount, string $description, ?int $shipmentId = null, ?int $orderItemId = null): int
    {
        $currency = DB::table('orders')->where('id', $orderId)->value('currency');
        $now = now();
        $expenseId = null;
        if ($type !== 'product') {
            $category = match ($type) {
                'carrier_shipping', 'return_shipping', 'exchange_shipping' => 'carrier_shipping',
                'packaging' => 'packaging', 'advertising' => 'advertising', default => 'other',
            };
            $expenseId = DB::table('expenses')->insertGetId([
                'expense_number' => 'EXP-'.(string) Str::ulid(), 'expense_type' => 'order_operating', 'cost_category' => $category,
                'description' => $description, 'amount' => $amount, 'currency' => $currency, 'expense_date' => $now->toDateString(),
                'order_id' => $orderId, 'status' => 'posted', 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        return (int) DB::table('order_cost_entries')->insertGetId([
            'order_id' => $orderId, 'order_item_id' => $orderItemId, 'shipment_id' => $shipmentId, 'cost_type' => $type,
            'amount' => $amount, 'currency' => $currency, 'basis' => $basis, 'expense_id' => $expenseId,
            'recorded_at' => $now, 'recorded_by' => $actorId, 'reverses_entry_id' => null, 'notes' => $description, 'created_at' => $now,
        ]);
    }

    public function syncShipmentActual(int $orderId, int $shipmentId, int $actorId, string $type, ?string $amount): void
    {
        $current = DB::table('order_cost_entries as entries')
            ->where('entries.order_id', $orderId)->where('entries.shipment_id', $shipmentId)
            ->where('entries.cost_type', $type)->where('entries.basis', 'actual')->whereNull('entries.reverses_entry_id')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_cost_entries as reversals')->whereColumn('reversals.reverses_entry_id', 'entries.id'))
            ->orderByDesc('entries.id')->first();
        if (($current?->amount === null && $amount === null)
            || ($current && $amount !== null && DB::selectOne('SELECT CASE WHEN CAST(? AS DECIMAL(19,4)) = CAST(? AS DECIMAL(19,4)) THEN 1 ELSE 0 END AS same', [(string) $current->amount, $amount])->same)) {
            return;
        }

        if ($current) {
            $negative = DB::selectOne('SELECT CAST(? AS DECIMAL(19,4)) * -1 AS amount', [(string) $current->amount])->amount;
            $this->appendReversal($current, $actorId, (string) $negative, 'Reversal of cost entry '.$current->id);
        }
        if ($amount !== null) {
            $this->record($orderId, $actorId, $type, 'actual', $amount, 'Carrier actual cost for shipment '.$shipmentId, $shipmentId);
        }
    }

    private function appendReversal(object $original, int $actorId, string $amount, string $description): void
    {
        $now = now();
        $expenseId = null;
        if ($original->expense_id !== null) {
            $expenseId = DB::table('expenses')->insertGetId([
                'expense_number' => 'EXP-'.(string) Str::ulid(), 'expense_type' => 'order_operating', 'cost_category' => DB::table('expenses')->where('id', $original->expense_id)->value('cost_category'),
                'description' => $description, 'amount' => $amount, 'currency' => $original->currency, 'expense_date' => $now->toDateString(),
                'order_id' => $original->order_id, 'status' => 'posted', 'created_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('order_cost_entries')->insert([
            'order_id' => $original->order_id, 'order_item_id' => $original->order_item_id, 'shipment_id' => $original->shipment_id,
            'cost_type' => $original->cost_type, 'amount' => $amount, 'currency' => $original->currency, 'basis' => $original->basis,
            'expense_id' => $expenseId, 'recorded_at' => $now, 'recorded_by' => $actorId, 'reverses_entry_id' => $original->id,
            'notes' => $description, 'created_at' => $now,
        ]);
    }
}
