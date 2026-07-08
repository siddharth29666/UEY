<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'admin_id',
        'admin_name',
        'ip_address',
        'user_agent',
        'module',
        'action',
        'affected_table',
        'affected_record_id',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'admin_id' => 'integer',
        'affected_record_id' => 'integer',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
