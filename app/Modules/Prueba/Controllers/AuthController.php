<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Prueba\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Modules\Prueba\Support\RolePermissions;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)
            ->whereIn('role', ['ADMIN', 'SUPERVISOR', 'RRHH', 'FABRICA', 'OPERACIONES', 'VP'])
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Usuario desactivado'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $permissions = RolePermissions::getPermissions($user->role);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}