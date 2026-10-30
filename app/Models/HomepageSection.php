<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomepageSection extends Model
{
    protected $fillable = ['code', 'section_type', 'product_collection_id', 'banner_placement', 'sort_order', 'is_enabled', 'settings', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'settings' => 'array'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(HomepageSectionTranslation::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ProductCollection::class, 'product_collection_id');
    }
}
