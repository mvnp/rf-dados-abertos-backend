<?php

namespace App\Http\Controllers;

use App\Models\Qualificacao;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class QualificacoesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $qualificacoes = Qualificacao::query();
        $qualificacoes = $qualificacoes->get();

        return response()->json([
            'status' => 'success',
            'count' => $qualificacoes->count(),
            'data' => $qualificacoes,
            'message' => 'Qualificacoes retrieved successfully'
        ]);
    }
}