<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $fillable = ['customer_id', 'guest_token', 'status', 'currency', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
