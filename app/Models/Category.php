<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['parent_id', 'slug', 'image_path', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function storefrontProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories')
            ->where('products.status', 'published')
            ->where('products.store_availability_status', 'available')
            ->whereNotNull('products.published_at')
            ->where('products.published_at', '<=', now())
            ->whereHas('variants', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('products.published_at')
            ->orderByDesc('products.id');
    }

    public function scopeAvailableForSelection(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
