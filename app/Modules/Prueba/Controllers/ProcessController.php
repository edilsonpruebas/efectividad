<?php

namespace App\Modules\Prueba\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Prueba\Models\Process;

class ProcessController extends Controller
{
    public function index()
    {
        return response()->json(
            Process::active()->orderBy('name')->get()
        );
    }
}