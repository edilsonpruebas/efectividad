<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Prueba\Models\Process;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProcessController extends Controller
{
    // GET /api/processes — solo activos (comportamiento original intacto)
    public function index(): JsonResponse
    {
        return response()->json(
            Process::active()->orderBy('name')->get()
        );
    }

    // GET /api/processes/all — todos (activos e inactivos)
    public function all(): JsonResponse
    {
        return response()->json(
            Process::orderBy('name')->get()
        );
    }

    // POST /api/processes — crear
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255|unique:processes,name',
            'description'   => 'nullable|string',
            'base_per_hour' => 'required|numeric|min:0',
        ]);

        $process = Process::create([
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Proceso creado correctamente',
            'data'    => $process,
        ], 201);
    }

    // PUT /api/processes/{id} — editar
    public function update(Request $request, int $id): JsonResponse
    {
        $process = Process::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255|unique:processes,name,' . $id,
            'description'   => 'nullable|string',
            'base_per_hour' => 'sometimes|numeric|min:0',
        ]);

        $process->update($validated);

        return response()->json([
            'message' => 'Proceso actualizado',
            'data'    => $process,
        ]);
    }

    // PATCH /api/processes/{id}/toggle — activar/desactivar
    public function toggle(int $id): JsonResponse
    {
        $process = Process::findOrFail($id);
        $process->is_active = !$process->is_active;
        $process->save();

        $estado = $process->is_active ? 'activado' : 'desactivado';

        return response()->json([
            'message' => "Proceso {$estado} correctamente",
            'data'    => $process,
        ]);
    }

    // DELETE /api/processes/{id} — eliminar permanente
    public function destroy(int $id): JsonResponse
    {
        $process = Process::findOrFail($id);

        if ($process->activities()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el proceso tiene actividades registradas. Desactívalo en su lugar.',
            ], 422);
        }

        $process->delete();

        return response()->json(['message' => 'Proceso eliminado permanentemente']);
    }
}