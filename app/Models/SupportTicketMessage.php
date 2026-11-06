<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicketMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['support_ticket_id', 'sender_type', 'customer_id', 'staff_user_id', 'visibility', 'body', 'sent_at', 'edited_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime', 'edited_at' => 'immutable_datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'support_ticket_message_id');
    }
}
