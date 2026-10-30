<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    public $timestamps = false;

    protected $table = 'product_price_history';

    protected $fillable = [
        'product_variant_id', 'version_no', 'factory_unit_cost', 'inbound_delivery_unit_cost',
        'packaging_unit_cost', 'advertising_unit_cost', 'pricing_cost_total', 'profit_rate_percent',
        'calculated_regular_price', 'official_regular_price', 'sale_price', 'currency', 'recorded_at',
        'recorded_by', 'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'factory_unit_cost' => 'decimal:4', 'inbound_delivery_unit_cost' => 'decimal:4',
            'packaging_unit_cost' => 'decimal:4', 'advertising_unit_cost' => 'decimal:4',
            'pricing_cost_total' => 'decimal:4', 'profit_rate_percent' => 'decimal:4',
            'calculated_regular_price' => 'decimal:4', 'official_regular_price' => 'decimal:4', 'sale_price' => 'decimal:4',
            'recorded_at' => 'immutable_datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
