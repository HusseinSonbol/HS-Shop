<?php

namespace App\Support;

final class ShipmentLifecycle
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'awaiting_pickup' => ['picked_up', 'cancelled'],
        'picked_up' => ['in_transit', 'out_for_delivery', 'delivery_attempt_failed', 'returning_to_sender'],
        'in_transit' => ['out_for_delivery', 'delivery_attempt_failed', 'returning_to_sender'],
        'out_for_delivery' => ['delivered', 'delivery_attempt_failed', 'returning_to_sender'],
        'delivery_attempt_failed' => ['out_for_delivery', 'returning_to_sender', 'cancelled'],
        'returning_to_sender' => ['returned_to_sender'],
        'returned_to_sender' => [],
        'delivered' => [],
        'cancelled' => [],
    ];

    /** @var list<string> */
    public const STATUSES = [
        'awaiting_pickup', 'picked_up', 'in_transit', 'out_for_delivery',
        'delivery_attempt_failed', 'returning_to_sender', 'returned_to_sender', 'delivered', 'cancelled',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function canStartAnother(string $status): bool
    {
        return in_array($status, ['returned_to_sender', 'cancelled'], true);
    }
}
