<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Support\OrderLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderActivityService
{
    /** @param array<string, mixed> $data */
    public function record(int $orderId, User $actor, array $data): void
    {
        DB::transaction(function () use ($orderId, $actor, $data): void {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_if(! $order, 404);

            $eventType = $data['event_type'];
            $previousStatus = $order->status;
            $currentStatus = $previousStatus;

            if ($eventType === 'follow_up_update') {
                $this->recordFollowUpUpdates($orderId, $actor, $previousStatus, $data);

                return;
            }

            $closeOutcome = null;
            $reasonCode = null;
            $followUpAt = null;
            $details = [];

            if ($eventType === 'status_changed') {
                $currentStatus = $data['new_status'];
                if ($order->close_outcome !== null || $order->closed_at !== null
                    || ! OrderLifecycle::canTransition($previousStatus, $currentStatus)) {
                    throw ValidationException::withMessages(['new_status' => __('admin.orders.validation.transition_invalid')]);
                }

                $closeOutcome = $currentStatus === 'received' ? 'success' : null;
                $details = [
                    'previous_value' => $previousStatus,
                    'new_value' => $currentStatus,
                    'customer_received' => $closeOutcome === 'success',
                ];
                DB::table('orders')->where('id', $orderId)->update([
                    'status' => $currentStatus,
                    'closed_at' => $closeOutcome === 'success' ? now() : null,
                    'close_outcome' => $closeOutcome,
                    'closure_reason_code' => null,
                    'updated_at' => now(),
                ]);
            } elseif ($eventType === 'closed') {
                if ($order->close_outcome !== null || $order->closed_at !== null || $previousStatus === 'received') {
                    throw ValidationException::withMessages(['event_type' => __('admin.orders.validation.already_closed')]);
                }

                $closeOutcome = 'failure';
                $reasonCode = $data['reason_code'];
                $details = [
                    'previous_close_outcome' => $order->close_outcome,
                    'new_close_outcome' => $closeOutcome,
                    'previous_reason_code' => $order->closure_reason_code,
                    'new_reason_code' => $reasonCode,
                    'reason_details' => $data['reason_details'] ?? null,
                ];
                DB::table('orders')->where('id', $orderId)->update([
                    'closed_at' => now(),
                    'close_outcome' => $closeOutcome,
                    'closure_reason_code' => $reasonCode,
                    'updated_at' => now(),
                ]);
            } else {
                if ($order->close_outcome !== 'failure' || $order->closed_at === null) {
                    throw ValidationException::withMessages(['event_type' => __('admin.orders.validation.not_closed')]);
                }

                $closure = DB::table('order_activity_events')
                    ->where('order_id', $orderId)
                    ->where('event_type', 'closed')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();
                if (! $closure) {
                    throw ValidationException::withMessages(['event_type' => __('admin.orders.validation.reopen_unavailable')]);
                }

                $details = [
                    'previous_close_outcome' => $order->close_outcome,
                    'new_close_outcome' => null,
                    'previous_reason_code' => $order->closure_reason_code,
                    'new_reason_code' => null,
                    'restored_from_event_id' => $closure->id,
                ];
                DB::table('orders')->where('id', $orderId)->update([
                    'closed_at' => null,
                    'close_outcome' => null,
                    'closure_reason_code' => null,
                    'updated_at' => now(),
                ]);
            }

            $this->appendEvent(
                orderId: $orderId,
                actor: $actor,
                eventType: $eventType,
                previousStatus: $previousStatus,
                currentStatus: $currentStatus,
                closeOutcome: $closeOutcome,
                reasonCode: $reasonCode,
                details: $details,
                nextFollowUpAt: $followUpAt,
            );
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    private function recordFollowUpUpdates(int $orderId, User $actor, string $status, array $data): void
    {
        if (isset($data['assigned_user_id']) && $data['assigned_user_id'] !== '') {
            $assignee = DB::table('users')
                ->join('user_role', 'users.id', '=', 'user_role.user_id')
                ->join('roles', 'user_role.role_id', '=', 'roles.id')
                ->where('users.id', $data['assigned_user_id'])
                ->where('users.is_active', true)
                ->whereNull('users.deleted_at')
                ->whereIn('roles.code', ['super_admin', 'manager', 'operator'])
                ->select('users.id', 'users.name')
                ->first();
            if (! $assignee) {
                throw ValidationException::withMessages(['assigned_user_id' => __('admin.orders.validation.assignee_invalid')]);
            }

            $previousEvent = DB::table('order_activity_events')
                ->where('order_id', $orderId)
                ->where('event_type', 'assigned')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $previousDetails = $previousEvent?->details ? json_decode($previousEvent->details, true) : [];
            $this->appendEvent($orderId, $actor, 'assigned', $status, $status, null, null, [
                'previous_assignee' => $previousDetails['new_assignee'] ?? null,
                'new_assignee' => ['id' => (int) $assignee->id, 'name' => $assignee->name],
            ]);
        }

        if (isset($data['next_follow_up_at']) && $data['next_follow_up_at'] !== '') {
            $previousEvent = DB::table('order_activity_events')
                ->where('order_id', $orderId)
                ->whereNotNull('next_follow_up_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            $followUpAt = Carbon::parse($data['next_follow_up_at'], 'Africa/Cairo')->utc()->format('Y-m-d H:i:s');
            $this->appendEvent($orderId, $actor, 'follow_up', $status, $status, null, null, [
                'previous_follow_up_at' => $previousEvent?->next_follow_up_at,
                'new_follow_up_at' => $followUpAt,
            ], $followUpAt);
        }

        if (isset($data['note']) && trim($data['note']) !== '') {
            $this->appendEvent($orderId, $actor, 'note_added', $status, $status, null, null, ['note' => trim($data['note'])]);
        }
    }

    /** @param array<string, mixed> $details */
    private function appendEvent(
        int $orderId,
        User $actor,
        string $eventType,
        string $previousStatus,
        string $currentStatus,
        ?string $closeOutcome,
        ?string $reasonCode,
        array $details,
        ?string $nextFollowUpAt = null,
    ): void {
        DB::table('order_activity_events')->insert([
            'order_id' => $orderId,
            'actor_user_id' => $actor->id,
            'event_type' => $eventType,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'close_outcome' => $closeOutcome,
            'reason_code' => $reasonCode,
            'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'next_follow_up_at' => $nextFollowUpAt,
            'occurred_at' => now(),
            'idempotency_key' => 'order-event:'.Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
