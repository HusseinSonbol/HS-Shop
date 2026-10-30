<?php

namespace App\Services\Admin;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerManagementService
{
    /** @param array{display_name:string,phone:string,email?:?string,notes?:?string} $data */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create([
                'customer_number' => 'CUS-'.Str::upper((string) Str::ulid()),
                ...$this->attributes($data),
            ]);
            $this->log('customer.created', $customer, null, ['fields' => array_keys($this->attributes($data))]);

            return $customer;
        }, 3);
    }

    /** @param array{display_name:string,phone:string,email?:?string,notes?:?string} $data */
    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data): Customer {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $attributes = $this->attributes($data);
            $changedFields = array_keys(array_filter($attributes, fn (mixed $value, string $key): bool => $lockedCustomer->{$key} !== $value, ARRAY_FILTER_USE_BOTH));
            $lockedCustomer->fill($attributes)->save();
            $this->log('customer.updated', $lockedCustomer, ['fields' => $changedFields], ['fields' => $changedFields]);

            return $lockedCustomer->refresh();
        }, 3);
    }

    public function archive(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $lockedCustomer->delete();
            $this->log('customer.archived', $lockedCustomer, null, ['archived' => true]);
        }, 3);
    }

    public function restore(Customer $customer): void
    {
        DB::transaction(function () use ($customer): void {
            $lockedCustomer = Customer::withTrashed()->lockForUpdate()->findOrFail($customer->id);
            if ($lockedCustomer->trashed()) {
                $lockedCustomer->restore();
                $this->log('customer.restored', $lockedCustomer, ['archived' => true], ['archived' => false]);
            }
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'display_name' => trim($data['display_name']),
            'phone' => trim($data['phone']),
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? mb_strtolower(trim((string) $data['email'])) : null,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];
    }

    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(string $event, Customer $customer, ?array $before, ?array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
