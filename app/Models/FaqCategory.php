<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaqCategory extends Model
{
    use SoftDeletes;

    protected $table = 'faq_categories';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'audience',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class, 'faq_category_id')->orderBy('sort_order', 'asc');
    }
}
