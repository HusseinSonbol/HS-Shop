<?php

namespace App\Services\Admin;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    /** @param array<string, mixed> $data */
    public function create(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $attributes = $this->attributes($data);
            if ($attributes['is_default']) {
                $lockedCustomer->addresses()->update(['is_default' => false]);
            }

            $address = $lockedCustomer->addresses()->create($attributes);
            $this->log('customer_address.created', $lockedCustomer, $address);

            return $address;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Customer $customer, CustomerAddress $address, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $data): CustomerAddress {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $lockedAddress = $lockedCustomer->addresses()->lockForUpdate()->findOrFail($address->id);
            $attributes = $this->attributes($data);
            if ($attributes['is_default']) {
                $lockedCustomer->addresses()->whereKeyNot($lockedAddress->id)->update(['is_default' => false]);
            }

            $lockedAddress->fill($attributes)->save();
            $this->log('customer_address.updated', $lockedCustomer, $lockedAddress);

            return $lockedAddress->refresh();
        }, 3);
    }

    public function archive(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $lockedAddress = $lockedCustomer->addresses()->lockForUpdate()->findOrFail($address->id);
            $lockedAddress->delete();
            $this->log('customer_address.archived', $lockedCustomer, $lockedAddress);
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = $data;
        $attributes['country_code'] = mb_strtoupper(trim($data['country_code']));
        $attributes['is_default'] = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach (['label', 'district', 'building', 'floor', 'apartment', 'landmark', 'postal_code'] as $optionalField) {
            if (! isset($attributes[$optionalField]) || trim((string) $attributes[$optionalField]) === '') {
                $attributes[$optionalField] = null;
            } else {
                $attributes[$optionalField] = trim((string) $attributes[$optionalField]);
            }
        }

        foreach (['recipient_name', 'phone', 'governorate', 'city', 'street'] as $requiredField) {
            $attributes[$requiredField] = trim($attributes[$requiredField]);
        }

        return $attributes;
    }

    private function log(string $event, Customer $customer, CustomerAddress $address): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => auth()->id(),
            'event' => $event,
            'subject_type' => CustomerAddress::class,
            'subject_id' => $address->id,
            'before' => null,
            'after' => json_encode([
                'customer_id' => $customer->id,
                'is_default' => $address->is_default,
                'archived' => $address->trashed(),
            ], JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
