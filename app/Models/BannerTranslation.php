<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannerTranslation extends Model
{
    protected $fillable = ['locale', 'title', 'alt_text'];

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Banner::class);
    }
}
