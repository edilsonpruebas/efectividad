<?php

namespace App\Http\Middleware;

use App\Modules\Prueba\Support\RolePermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPermission
{
   public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['debug' => 'NO HAY USUARIO'], 403);
        }

        foreach ($permissions as $permission) {
            if (RolePermissions::can($user->role, $permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'debug'      => 'PERMISO DENEGADO',
            'role'       => $user->role,
            'permission' => implode('|', $permissions),
            'perms'      => RolePermissions::getPermissions($user->role),
        ], 403);
    }
}