<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_id', 'product_type', 'sku', 'slug', 'status', 'store_availability_status',
        'availability_checked_at', 'availability_checked_by', 'requires_shipping', 'tax_class', 'weight_g', 'published_at',
    ];

    protected function casts(): array
    {
        return ['requires_shipping' => 'boolean', 'availability_checked_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function supplierOptions(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')->withPivot('is_primary');
    }

    public function availabilityHistory(): HasMany
    {
        return $this->hasMany(ProductAvailabilityHistory::class)->orderByDesc('checked_at');
    }
}
