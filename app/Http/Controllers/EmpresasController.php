<?php

namespace App\Http\Controllers;

use App\Models\Empresas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class EmpresasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresas = Empresas::query();
        $empresas = $empresas->get();

        return response()->json([
            'status' => 'success',
            'count' => $empresas->count(),
            'data' => $empresas,
            'message' => 'Empresas retrieved successfully'
        ]);
    }
}