<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Create an audit log record.
     */
    public function log(
        User $admin,
        string $module,
        string $action,
        ?string $affectedTable = null,
        ?int $affectedRecordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        return AuditLog::create([
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'module' => $module,
            'action' => $action,
            'affected_table' => $affectedTable,
            'affected_record_id' => $affectedRecordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
