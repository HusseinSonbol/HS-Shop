<?php

namespace App\Services\Admin;

use App\Models\ProductSupplier;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductSupplierManagementService
{
    /** @param array<string, mixed> $data */
    public function create(Supplier $supplier, array $data, int $actorId): ProductSupplier
    {
        return DB::transaction(function () use ($supplier, $data, $actorId): ProductSupplier {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if (! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => __('admin.suppliers.errors.supplier_inactive')]);
            }
            $existing = ProductSupplier::query()->where('product_id', $data['product_id'])->where('supplier_id', $supplier->id)->lockForUpdate()->exists();
            if ($existing) {
                throw ValidationException::withMessages(['product_id' => __('admin.suppliers.validation.duplicate_offer')]);
            }

            $offer = ProductSupplier::create($this->attributes($supplier, $data, $actorId));
            $this->writeHistory($offer, 1, $data, $actorId);
            $this->log($offer, 'supplier_offer.created', $actorId, null, $this->snapshot($offer));

            return $offer;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(ProductSupplier $offer, Supplier $supplier, array $data, int $actorId): void
    {
        DB::transaction(function () use ($offer, $supplier, $data, $actorId): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $offer = ProductSupplier::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ((int) $offer->supplier_id !== (int) $supplier->id) {
                abort(404);
            }
            $before = $this->snapshot($offer);
            $attributes = $this->attributes($supplier, $data, $actorId);
            unset($attributes['product_id'], $attributes['supplier_id'], $attributes['currency']);
            $offer->fill($attributes);
            if ($offer->isDirty(['unit_cost', 'supplier_availability_status', 'valid_from', 'valid_until', 'is_active'])) {
                $offer->save();
                $version = (int) ProductSupplier::query()->findOrFail($offer->id)->history()->max('version_no') + 1;
                $this->writeHistory($offer, $version, $data, $actorId);
                $this->log($offer, 'supplier_offer.updated', $actorId, $before, $this->snapshot($offer->fresh()));
            }
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(Supplier $supplier, array $data, int $actorId): array
    {
        return [
            'supplier_id' => $supplier->id,
            'product_id' => (int) $data['product_id'],
            'unit_cost' => $this->decimal($data['unit_cost']),
            'currency' => 'EGP',
            'supplier_availability_status' => $data['supplier_availability_status'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_until' => $data['valid_until'] ?: null,
            'is_active' => (bool) $data['is_active'],
            'updated_by' => $actorId,
        ];
    }

    /** @param array<string, mixed> $data */
    private function writeHistory(ProductSupplier $offer, int $version, array $data, int $actorId): void
    {
        DB::table('product_supplier_history')->insert([
            'product_supplier_id' => $offer->id,
            'version_no' => $version,
            'unit_cost' => $this->decimal($data['unit_cost']),
            'currency' => 'EGP',
            'supplier_availability_status' => $data['supplier_availability_status'],
            'valid_from' => $data['valid_from'] ?: null,
            'valid_until' => $data['valid_until'] ?: null,
            'is_active' => (bool) $data['is_active'],
            'changed_at' => now(),
            'changed_by' => $actorId,
            'change_reason' => filled($data['change_reason'] ?? null) ? trim($data['change_reason']) : null,
            'created_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function snapshot(ProductSupplier $offer): array
    {
        return $offer->only(['id', 'product_id', 'supplier_id', 'unit_cost', 'currency', 'supplier_availability_status', 'valid_from', 'valid_until', 'is_active', 'updated_by']);
    }

    private function decimal(string|int $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', trim((string) $value), 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        return $whole.'.'.str_pad($fraction, 4, '0');
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(ProductSupplier $offer, string $event, int $actorId, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => ProductSupplier::class,
            'subject_id' => $offer->id,
            'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
