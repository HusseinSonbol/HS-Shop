<?php

namespace App\Services\Storefront;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerAccountService
{
    /** @param array{name:string,phone:string} $data */
    public function updateProfile(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $customer = Customer::query()->where('user_id', $lockedUser->id)->lockForUpdate()->firstOrFail();
            $name = trim($data['name']);
            $phone = trim($data['phone']);
            $changed = [];

            if ($lockedUser->name !== $name) {
                $lockedUser->name = $name;
                $changed[] = 'name';
            }
            if ($lockedUser->phone !== $phone) {
                $lockedUser->phone = $phone;
                $changed[] = 'phone';
            }
            if ($customer->display_name !== $name) {
                $customer->display_name = $name;
                $changed[] = 'display_name';
            }
            if ($customer->phone !== $phone) {
                $customer->phone = $phone;
                $changed[] = 'customer_phone';
            }

            if ($changed === []) {
                return;
            }

            $lockedUser->save();
            $customer->save();
            $this->log('customer.profile_updated', $user, Customer::class, $customer->id, $changed);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createAddress(Customer $customer, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $data): CustomerAddress {
            $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->addressAttributes($data);

            if ($attributes['is_default']) {
                $lockedCustomer->addresses()->update(['is_default' => false]);
            }

            $address = $lockedCustomer->addresses()->create($attributes);
            $this->log('customer.address_created', auth('customer')->user(), CustomerAddress::class, $address->id, array_keys($attributes));

            return $address;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateAddress(Customer $customer, CustomerAddress $address, array $data): void
    {
        DB::transaction(function () use ($customer, $address, $data): void {
            $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $lockedAddress = $lockedCustomer->addresses()->lockForUpdate()->findOrFail($address->id);
            $attributes = $this->addressAttributes($data);

            if ($attributes['is_default']) {
                $lockedCustomer->addresses()->whereKeyNot($lockedAddress->id)->update(['is_default' => false]);
            }

            $lockedAddress->fill($attributes)->save();
            $this->log('customer.address_updated', auth('customer')->user(), CustomerAddress::class, $lockedAddress->id, array_keys($attributes));
        }, 3);
    }

    public function archiveAddress(Customer $customer, CustomerAddress $address): void
    {
        DB::transaction(function () use ($customer, $address): void {
            $lockedCustomer = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $lockedAddress = $lockedCustomer->addresses()->lockForUpdate()->findOrFail($address->id);
            $lockedAddress->is_default = false;
            $lockedAddress->save();
            $lockedAddress->delete();
            $this->log('customer.address_archived', auth('customer')->user(), CustomerAddress::class, $lockedAddress->id, ['archived']);
        }, 3);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed>
     */
    private function addressAttributes(array $data): array
    {
        $attributes = $data;
        $attributes['country_code'] = mb_strtoupper(trim($data['country_code']));
        $attributes['is_default'] = filter_var($data['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        foreach (['label', 'district', 'building', 'floor', 'apartment', 'landmark', 'postal_code'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $attributes[$field] = $value === '' ? null : $value;
        }
        foreach (['recipient_name', 'phone', 'governorate', 'city', 'street'] as $field) {
            $attributes[$field] = trim($data[$field]);
        }

        return $attributes;
    }

    /** @param list<string> $changedFields */
    private function log(string $event, ?User $actor, string $subjectType, int $subjectId, array $changedFields): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => null,
            'after' => json_encode(['changed_fields' => $changedFields], JSON_THROW_ON_ERROR),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
