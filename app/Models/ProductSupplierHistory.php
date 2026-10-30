<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplierHistory extends Model
{
    protected $table = 'product_supplier_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:4', 'is_active' => 'boolean', 'changed_at' => 'immutable_datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductSupplier::class, 'product_supplier_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
