<?php

namespace App\Http\Controllers;

use App\Models\Natureza;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class NaturezasController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $natureza = Natureza::query();
        $natureza = $natureza->get();

        return response()->json([
            'status' => 'success',
            'count' => $natureza->count(),
            'data' => $natureza,
            'message' => 'Naturezas retrieved successfully'
        ]);
    }
}