<?php

namespace App\Services\Admin;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierManagementService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, int $actorId): Supplier
    {
        return DB::transaction(function () use ($data, $actorId): Supplier {
            $supplier = Supplier::create($this->attributes($data));
            $this->log($supplier, 'supplier.created', $actorId, null, $this->snapshot($supplier));

            return $supplier;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Supplier $supplier, array $data, int $actorId): void
    {
        DB::transaction(function () use ($supplier, $data, $actorId): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($supplier);
            $supplier->update($this->attributes($data, $supplier));
            $this->log($supplier, 'supplier.updated', $actorId, $before, $this->snapshot($supplier->fresh()));
        });
    }

    public function archive(Supplier $supplier, int $actorId): void
    {
        DB::transaction(function () use ($supplier, $actorId): void {
            $supplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($supplier);
            $supplier->update(['is_active' => false]);
            $supplier->delete();
            $this->log($supplier, 'supplier.archived', $actorId, $before, null);
        });
    }

    public function restore(Supplier $supplier, int $actorId): void
    {
        DB::transaction(function () use ($supplier, $actorId): void {
            $supplier = Supplier::onlyTrashed()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $supplier->restore();
            $supplier->update(['is_active' => true]);
            $this->log($supplier, 'supplier.restored', $actorId, null, $this->snapshot($supplier->fresh()));
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data, ?Supplier $supplier = null): array
    {
        return [
            'code' => strtoupper(trim($data['code'])),
            'supplier_type' => $data['supplier_type'],
            'name' => trim($data['name']),
            'owner_name' => filled($data['owner_name'] ?? null) ? trim($data['owner_name']) : null,
            'owner_phone' => filled($data['owner_phone'] ?? null) ? trim($data['owner_phone']) : null,
            'contact_name' => filled($data['contact_name'] ?? null) ? trim($data['contact_name']) : null,
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address_json' => $this->address($data['address'] ?? [], $supplier),
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'is_active' => (bool) $data['is_active'],
        ];
    }

    /** @param array<string, mixed> $address @return array<string, mixed>|null */
    private function address(array $address, ?Supplier $supplier): ?array
    {
        $fields = ['governorate', 'city', 'district', 'street', 'building', 'floor', 'landmark', 'postal_code'];
        $normalized = [];
        foreach ($fields as $field) {
            if (filled($address[$field] ?? null)) {
                $normalized[$field] = trim((string) $address[$field]);
            }
        }

        $existing = $supplier?->address_json ?? [];
        $unmapped = array_diff_key($existing, array_fill_keys($fields, true));
        $result = array_merge($unmapped, $normalized);

        return $result === [] ? null : $result;
    }

    /** @return array<string, mixed> */
    private function snapshot(Supplier $supplier): array
    {
        return $supplier->only(['code', 'supplier_type', 'name', 'owner_name', 'owner_phone', 'contact_name', 'email', 'phone', 'address_json', 'notes', 'is_active']);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function log(Supplier $supplier, string $event, int $actorId, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => Supplier::class,
            'subject_id' => $supplier->id,
            'before' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
