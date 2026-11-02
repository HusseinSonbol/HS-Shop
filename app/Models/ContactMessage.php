<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'customer_id', 'sender_name', 'email', 'phone', 'subject', 'message', 'status',
        'received_at', 'handled_by', 'handled_at', 'converted_ticket_id',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'handled_at' => 'immutable_datetime'];
    }
}
