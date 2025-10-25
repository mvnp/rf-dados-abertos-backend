<?php

namespace App\Http\Controllers;

use App\Models\Estabelecimento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;
use App\Services\ExcelExportService;

class EstabelecimentosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estabelecimentos = Estabelecimento::query()
            ->where('nome_fantasia', '<>', '')
            ->with(['empresa', 'socio'])
            ->whereHas('empresa')
            ->whereHas('socio');

        $filteredData = array_filter($request->all());
        
        if(count($filteredData) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No filter was entered in the search.'
            ]);    
        }
        
        $estabelecimentos = EstabelecimentoSearchService::search($estabelecimentos, $request);
        $estabelecimentos = $estabelecimentos->get();

        return response()->json([
            'status' => 'success',
            'count' => $estabelecimentos->count(),
            'data' => $estabelecimentos,
            'message' => 'Estabelecimentos retrieved successfully'
        ]);
    }

    public function export(Request $request)
    {
        $filteredData = array_filter($request->all());
        
        if(count($filteredData) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nenhum filtro foi aplicado na pesquisa.'
            ], 400);    
        }

        // Buscar dados usando os mesmos filtros
        $estabelecimentos = Estabelecimento::query()
            ->where('nome_fantasia', '<>', '')
            ->with(['empresa', 'socio'])
            ->whereHas('empresa')
            ->whereHas('socio');

        $estabelecimentos = EstabelecimentoSearchService::search($estabelecimentos, $request);
        $estabelecimentos = $estabelecimentos->get();

        // Definir cabeçalhos
        $headers = [
            'CNPJ',
            'Telefone',
            'E-mail',
            'Razão Social',
            'Nome Fantasia',
            'Situação',
            'CNAE Principal',
            'UF',
            'Município',
            'Capital Social'
        ];

        // Situações cadastrais
        $situacoes = [
            '01' => 'Nula',
            '02' => 'Ativa',
            '03' => 'Suspensa',
            '04' => 'Inapta',
            '08' => 'Baixada'
        ];

        // Definir função de mapeamento
        $rowMapper = function($estabelecimento) use ($situacoes) {
            return [
                ExcelExportService::formatCnpj(
                    $estabelecimento->cnpj_basico,
                    $estabelecimento->cnpj_ordem,
                    $estabelecimento->cnpj_dv
                ),
                ExcelExportService::formatPhone($estabelecimento->ddd_1, $estabelecimento->telefone_1),
                $estabelecimento->email ?? '',
                $estabelecimento->empresa->razao_social ?? '',
                $estabelecimento->nome_fantasia ?? '',
                $situacoes[$estabelecimento->situacao_cadastral] ?? 'Desconhecida',
                $estabelecimento->cnae_principal ?? '',
                $estabelecimento->uf ?? '',
                $estabelecimento->municipio ?? '',
                ExcelExportService::formatCurrency($estabelecimento->empresa->capital_social ?? null)
            ];
        };

        return ExcelExportService::exportToCsv(
            $estabelecimentos,
            'estabelecimentos',
            $headers,
            $rowMapper
        );
    }    

    public function showByCnpj(string $cnpjBasico): JsonResponse
    {
        $cnpjBasico = preg_replace('/\D/', '', $cnpjBasico);

        if (strlen($cnpjBasico) !== 8) {
            return response()->json([
                'status' => 'error',
                'message' => 'O CNPJ basico deve conter exatamente 8 digitos numericos.'
            ], 422);
        }

        $estabelecimentos = Estabelecimento::query()
            ->where('cnpj_basico', $cnpjBasico)
            ->with(['empresa', 'socio'])
            ->get();

        if ($estabelecimentos->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Nenhum estabelecimento encontrado para o CNPJ informado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'count' => $estabelecimentos->count(),
            'data' => $estabelecimentos,
            'message' => 'Estabelecimentos recuperados por CNPJ basico.'
        ]);
    }
}


