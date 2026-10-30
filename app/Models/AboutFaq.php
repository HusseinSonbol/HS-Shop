<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AboutFaq extends Model
{
    use SoftDeletes;

    protected $fillable = ['is_active', 'sort_order', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AboutFaqTranslation::class, 'about_faq_id');
    }
}
