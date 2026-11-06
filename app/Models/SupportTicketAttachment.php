<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = ['support_ticket_message_id', 'storage_path', 'original_name', 'mime_type', 'byte_size', 'file_sha256', 'uploaded_at'];

    protected function casts(): array
    {
        return ['uploaded_at' => 'immutable_datetime'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessage::class, 'support_ticket_message_id');
    }
}
