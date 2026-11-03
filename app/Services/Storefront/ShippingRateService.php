<?php

namespace App\Services\Storefront;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShippingRateService
{
    /** @return list<array{code:string,governorate_ar:string,governorate_en:string,charge:string}> */
    public function all(bool $lock = false): array
    {
        $query = DB::table('settings')->where('group', 'shipping')->where('key', 'governorate_rates');
        if ($lock) {
            $query->lockForUpdate();
        }

        $stored = $query->value('value');
        if (! is_string($stored)) {
            return [];
        }

        $rates = json_decode($stored, true);
        if (! is_array($rates)) {
            return [];
        }

        return array_values(array_filter($rates, fn ($rate): bool => is_array($rate)
            && isset($rate['code'], $rate['governorate_ar'], $rate['governorate_en'], $rate['charge'])
            && Str::isUuid((string) $rate['code'])));
    }

    /** @return array{code:string,governorate_ar:string,governorate_en:string,charge:string}|null */
    public function find(string $code, bool $lock = false): ?array
    {
        foreach ($this->all($lock) as $rate) {
            if (hash_equals($rate['code'], $code)) {
                return $rate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $address
     *  @param list<array{code:string,governorate_ar:string,governorate_en:string,charge:string}> $rates
     */
    public function matchAddress(array $address, array $rates): ?array
    {
        if (mb_strtoupper((string) ($address['country_code'] ?? '')) !== 'EG') {
            return null;
        }

        $governorate = $this->normalize((string) ($address['governorate'] ?? ''));
        foreach ($rates as $rate) {
            if ($governorate === $this->normalize($rate['governorate_ar'])
                || $governorate === $this->normalize($rate['governorate_en'])) {
                return $rate;
            }
        }

        return null;
    }

    /** @param list<array{code?:string|null,governorate_ar:string,governorate_en:string,charge:string}> $rates */
    public function save(array $rates, int $actorUserId): void
    {
        $usedCodes = [];
        $prepared = array_map(function (array $rate) use (&$usedCodes): array {
            $code = isset($rate['code']) && Str::isUuid((string) $rate['code'])
                ? (string) $rate['code']
                : (string) Str::uuid();
            if (isset($usedCodes[$code])) {
                $code = (string) Str::uuid();
            }
            $usedCodes[$code] = true;

            return [
                'code' => $code,
                'governorate_ar' => trim($rate['governorate_ar']),
                'governorate_en' => trim($rate['governorate_en']),
                'charge' => $this->normalizeAmount($rate['charge']),
            ];
        }, $rates);

        DB::transaction(function () use ($prepared, $actorUserId): void {
            $setting = DB::table('settings')
                ->where('group', 'shipping')
                ->where('key', 'governorate_rates')
                ->lockForUpdate()
                ->first();
            $now = now();

            if ($setting) {
                DB::table('settings')->where('id', $setting->id)->update([
                    'value' => json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);
                $settingId = (int) $setting->id;
            } else {
                $settingId = (int) DB::table('settings')->insertGetId([
                    'group' => 'shipping',
                    'key' => 'governorate_rates',
                    'value' => json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'is_public' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('activity_logs')->insert([
                'actor_user_id' => $actorUserId,
                'event' => 'settings.shipping_rates_updated',
                'subject_type' => 'settings',
                'subject_id' => $settingId,
                'before' => $setting?->value,
                'after' => json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'ip_address' => request()->ip(),
                'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
                'correlation_id' => request()->attributes->get('correlation_id'),
                'created_at' => $now,
            ]);
        }, 3);
    }

    public function formatAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', ltrim($whole, '0') ?: '0');
        $fraction = rtrim(str_pad($fraction, 4, '0'), '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }

    private function normalizeAmount(string $amount): string
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ltrim($whole, '0') === '' ? '0.'.str_pad($fraction, 4, '0') : ltrim($whole, '0').'.'.str_pad($fraction, 4, '0');
    }
}
