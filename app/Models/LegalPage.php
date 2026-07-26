<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalPage extends Model
{
    use SoftDeletes;

    protected $table = 'legal_pages';

    protected $fillable = [
        'slug',
        'title',
        'content',
        'version',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];
}
