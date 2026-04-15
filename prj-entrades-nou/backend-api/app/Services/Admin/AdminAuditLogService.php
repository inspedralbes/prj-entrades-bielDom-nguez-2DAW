<?php

namespace App\Services\Admin;

use App\Models\AdminLog;

/**
 * Registre d’accions d’administrador sobre la BD (auditoria llegible).
 */
class AdminAuditLogService
{
    public function record (
        int $adminUserId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $summary,
        ?string $ipAddress,
    ): AdminLog {
        $log = new AdminLog();
        $log->admin_user_id = $adminUserId;
        $log->action = $action;
        $log->entity_type = $entityType;
        $log->entity_id = $entityId;
        $log->summary = $summary;
        $log->ip_address = $ipAddress;
        $log->save();

        return $log;
    }
}
