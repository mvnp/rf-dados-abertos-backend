<?php

namespace App\Http\Controllers;

use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class PaisesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paises = Pais::query();
        $paises = $paises->get();

        return response()->json([
            'status' => 'success',
            'count' => $paises->count(),
            'data' => $paises,
            'message' => 'Paises retrieved successfully'
        ]);
    }
}