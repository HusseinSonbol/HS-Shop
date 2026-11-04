<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShippingCompanyManagementService
{
    public function create(string $name, User $actor): int
    {
        return DB::transaction(function () use ($name, $actor): int {
            $now = now();
            $companyId = (int) DB::table('shipping_companies')->insertGetId([
                'code' => 'carrier-'.Str::lower((string) Str::ulid()),
                'name' => trim($name),
                'integration_driver' => null,
                'config' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->log($actor->id, $companyId, 'shipping_company.created', null, ['name' => trim($name), 'is_active' => true]);

            return $companyId;
        }, attempts: 3);
    }

    public function update(int $companyId, string $name, User $actor): void
    {
        DB::transaction(function () use ($companyId, $name, $actor): void {
            $company = DB::table('shipping_companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_if(! $company, 404);
            $name = trim($name);
            if ($company->name === $name) {
                return;
            }
            DB::table('shipping_companies')->where('id', $companyId)->update(['name' => $name, 'updated_at' => now()]);
            $this->log($actor->id, $companyId, 'shipping_company.updated', ['name' => $company->name], ['name' => $name]);
        }, attempts: 3);
    }

    public function toggleActive(int $companyId, User $actor): bool
    {
        return DB::transaction(function () use ($companyId, $actor): bool {
            $company = DB::table('shipping_companies')->where('id', $companyId)->lockForUpdate()->first();
            abort_if(! $company, 404);
            $isActive = ! (bool) $company->is_active;
            DB::table('shipping_companies')->where('id', $companyId)->update(['is_active' => $isActive, 'updated_at' => now()]);
            $this->log($actor->id, $companyId, $isActive ? 'shipping_company.activated' : 'shipping_company.deactivated',
                ['is_active' => (bool) $company->is_active], ['is_active' => $isActive]);

            return $isActive;
        }, attempts: 3);
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function log(int $actorId, int $companyId, string $event, ?array $before, array $after): void
    {
        DB::table('activity_logs')->insert([
            'actor_user_id' => $actorId,
            'event' => $event,
            'subject_type' => 'shipping_company',
            'subject_id' => $companyId,
            'before' => $before ? json_encode($before, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'after' => json_encode($after, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 500),
            'correlation_id' => request()->attributes->get('correlation_id'),
            'created_at' => now(),
        ]);
    }
}
