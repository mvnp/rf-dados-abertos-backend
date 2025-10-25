<?php

namespace App\Http\Controllers;

use App\Models\Cnae;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class CnaesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cnae = Cnae::query();
        $cnae = $cnae->get();

        return response()->json([
            'status' => 'success',
            'count' => $cnae->count(),
            'data' => $cnae,
            'message' => 'Socios retrieved successfully'
        ]);
    }
}