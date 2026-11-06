<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupportTicketNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $ticketId,
        private readonly string $ticketNumber,
        private readonly string $event,
        private readonly string $summary,
        private readonly bool $forCustomer = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'support_ticket',
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'event' => $this->event,
            'summary' => $this->summary,
            'url' => $this->forCustomer
                ? route('store.account.tickets.show', $this->ticketId, false)
                : route('admin.support-tickets.show', $this->ticketId, false),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
