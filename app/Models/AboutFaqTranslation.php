<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AboutFaqTranslation extends Model
{
    protected $fillable = ['locale', 'question', 'answer'];

    public function faq(): BelongsTo
    {
        return $this->belongsTo(AboutFaq::class, 'about_faq_id');
    }
}
