<?php

namespace App\Modules\Prueba\Support;

class RolePermissions
{
    const PERMISSIONS = [
        'VP'          => ['*'],   // todo
        'RRHH'        => [
            'reports.efectividad',
            'reports.manual',
            'reports.data',       // ← nuevo
            'admin.operators',
            'admin.audit',
        ],
        'FABRICA'     => [
            'reports.efectividad',
            'reports.manual',
            'reports.data',  
        ],
        'OPERACIONES' => [],      // futuro
        'ADMIN'       => ['*'],   // todo (compatibilidad)
        'SUPERVISOR'  => [
            'dashboard.activities',
            'reports.history',
            'reports.data', 
        ],
    ];

    public static function can(string $role, string $permission): bool
    {
        $perms = self::PERMISSIONS[$role] ?? [];
        return in_array('*', $perms) || in_array($permission, $perms);
    }

    public static function getPermissions(string $role): array
    {
        return self::PERMISSIONS[$role] ?? [];
    }
}