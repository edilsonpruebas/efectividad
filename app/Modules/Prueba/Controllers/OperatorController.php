<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Prueba\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class OperatorController extends Controller
{
    // GET /api/operators — todos los operadores
    public function index(): JsonResponse
    {
        return response()->json(
            User::operators()->orderBy('name')->get(['id', 'name', 'email', 'is_active'])
        );
    }

    // GET /api/operators/active — solo activos
    public function active(): JsonResponse
    {
        return response()->json(
            User::activeOperators()->orderBy('name')->get(['id', 'name', 'email'])
        );
    }

    // POST /api/operators — crear
    public function store(Request $request): JsonResponse
{
    // Determinar el rol (por defecto OPERADOR)
    $role = $request->input('role', 'OPERADOR');

    // Reglas base
    $rules = [
        'name' => 'required|string|max:255',
        'role' => 'sometimes|in:OPERADOR,SUPERVISOR,ADMIN',
    ];

    // Solo exigir email/password si el rol es ADMIN
    if ($role === 'ADMIN') {
        $rules['email']    = 'required|email|unique:users,email';
        $rules['password'] = 'required|string|min:6';
    } else {
        $rules['email']    = 'nullable|email|unique:users,email';
        $rules['password'] = 'nullable|string|min:6';
    }

    $validated = $request->validate($rules);

    // Generar email único si no se proporciona (para cumplir con unique)
    if (empty($validated['email'])) {
        $validated['email'] = 'no-login-' . uniqid() . '@placeholder.local';
    }

    // Si no hay password, almacenar hash vacío
    $password = isset($validated['password'])
        ? Hash::make($validated['password'])
        : Hash::make('');

    $operator = User::create([
        'name'      => $validated['name'],
        'email'     => $validated['email'],
        'password'  => $password,
        'role'      => $role,
        'is_active' => true,
    ]);

    return response()->json([
        'message' => 'Operador creado correctamente',
        'data'    => $operator,
    ], 201);
}

    // PUT /api/operators/{id} — editar
    public function update(Request $request, int $id): JsonResponse
{
    $operator = User::operators()->findOrFail($id);
    $role = $operator->role;

    $rules = [
        'name' => 'sometimes|string|max:255',
    ];

    if ($role === 'ADMIN') {
        $rules['email']    = 'sometimes|email|unique:users,email,' . $id;
        $rules['password'] = 'sometimes|string|min:6';
    } else {
        $rules['email']    = 'nullable|email|unique:users,email,' . $id;
        $rules['password'] = 'nullable|string|min:6';
    }

    $validated = $request->validate($rules);

    // Si se envía email vacío, generar placeholder
    if (array_key_exists('email', $validated) && empty($validated['email'])) {
        $validated['email'] = 'no-login-' . uniqid() . '@placeholder.local';
    }

    if (isset($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    }

    $operator->update($validated);

    return response()->json([
        'message' => 'Operador actualizado',
        'data'    => $operator,
    ]);
}

    // PATCH /api/operators/{id}/toggle — activar/desactivar
    public function toggle(int $id): JsonResponse
    {
        $operator = User::operators()->findOrFail($id);
        $operator->is_active = !$operator->is_active;
        $operator->save();

        $estado = $operator->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Operador {$estado} correctamente",
            'data'    => $operator,
        ]);
    }

    // DELETE /api/operators/{id} — eliminar permanente
    public function destroy(int $id): JsonResponse
    {
        $operator = User::operators()->findOrFail($id);

        if ($operator->activitiesAsOperator()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el operador tiene actividades registradas. Desactívalo en su lugar.',
            ], 422);
        }

        $operator->delete();

        return response()->json(['message' => 'Operador eliminado permanentemente']);
    }
}