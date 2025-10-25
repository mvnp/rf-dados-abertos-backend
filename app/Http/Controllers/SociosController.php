<?php

namespace App\Http\Controllers;

use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class SociosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $socios = Socio::query();
        $filteredData = array_filter($request->all());
        
        if(count($filteredData) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No filter was entered in the search.'
            ]);    
        }
        
        $socios = EstabelecimentoSearchService::search($socios, $request);
        $socios = $socios->get();

        return response()->json([
            'status' => 'success',
            'count' => $socios->count(),
            'data' => $socios,
            'message' => 'Socios retrieved successfully'
        ]);
    }
}