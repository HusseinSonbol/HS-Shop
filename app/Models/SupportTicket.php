<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'customer_id', 'order_id', 'subject', 'status', 'priority', 'category',
        'assigned_to', 'requester_name', 'requester_email', 'requester_phone', 'opened_at',
        'first_response_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }
}
