<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasMany as HasManyRelation;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }

    public function products(): HasManyRelation
    {
        return $this->hasMany(Product::class);
    }
}
