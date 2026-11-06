<?php

namespace App\Support;

final class ReturnCaseLifecycle
{
    public const STATUSES = [
        'requested', 'approved', 'pickup_in_transit', 'item_received',
        'inspection_accepted', 'inspection_rejected', 'exchange_preparing',
        'replacement_in_transit', 'completed', 'refund_pending', 'refunded', 'rejected', 'cancelled',
    ];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['pickup_in_transit', 'cancelled'],
        'pickup_in_transit' => ['item_received'],
        'item_received' => ['inspection_accepted', 'inspection_rejected'],
        'inspection_accepted' => ['exchange_preparing'],
        'inspection_rejected' => ['rejected'],
        'exchange_preparing' => ['replacement_in_transit'],
        'replacement_in_transit' => ['completed'],
        'refund_pending' => ['refunded'],
        'completed' => [], 'refunded' => [], 'rejected' => [], 'cancelled' => [],
    ];
}
