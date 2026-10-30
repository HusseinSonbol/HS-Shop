<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSupplier extends Model
{
    protected $fillable = [
        'product_id', 'supplier_id', 'supplier_product_code', 'unit_cost', 'currency',
        'supplier_availability_status', 'valid_from', 'valid_until', 'is_active', 'updated_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:4', 'valid_from' => 'immutable_datetime', 'valid_until' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ProductSupplierHistory::class)->orderByDesc('version_no');
    }
}
