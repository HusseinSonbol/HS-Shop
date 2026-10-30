<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'supplier_type', 'name', 'owner_name', 'owner_phone', 'contact_name', 'email', 'phone', 'address_json', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['address_json' => 'array', 'is_active' => 'boolean'];
    }

    public function productSuppliers(): HasMany
    {
        return $this->hasMany(ProductSupplier::class);
    }
}
