<?php

namespace App\Http\Controllers;

use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class MunicipiosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $municipios = Municipio::query();
        $municipios = $municipios->get();

        return response()->json([
            'status' => 'success',
            'count' => $municipios->count(),
            'data' => $municipios,
            'message' => 'Municipios retrieved successfully'
        ]);
    }
}