<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'variant_name', 'is_default', 'is_active',
        'current_regular_price', 'current_sale_price', 'currency', 'weight_g',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean', 'is_active' => 'boolean',
            'current_regular_price' => 'decimal:4', 'current_sale_price' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'variant_attribute_values', 'variant_id', 'attribute_id')
            ->withPivot('attribute_value_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')->orderBy('sort_order')->orderBy('id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderByDesc('version_no');
    }

    public function currentPriceVersion(): BelongsTo
    {
        return $this->belongsTo(ProductPriceHistory::class, 'current_price_version_id');
    }
}
