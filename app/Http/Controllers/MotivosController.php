<?php

namespace App\Http\Controllers;

use App\Models\Motivo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class MotivosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $motivos = Motivo::query();
        $motivos = $motivos->get();

        return response()->json([
            'status' => 'success',
            'count' => $motivos->count(),
            'data' => $motivos,
            'message' => 'Municipios retrieved successfully'
        ]);
    }
}