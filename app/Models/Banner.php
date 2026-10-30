<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banner extends Model
{
    protected $fillable = ['placement', 'image_path', 'target_url', 'sort_order', 'starts_at', 'ends_at', 'is_active', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BannerTranslation::class);
    }
}
