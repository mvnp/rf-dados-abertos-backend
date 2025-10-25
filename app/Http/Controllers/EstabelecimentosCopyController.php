<?php

namespace App\Http\Controllers;

use App\Models\Estabelecimento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\EstabelecimentoSearchService;

class EstabelecimentoController extends Controller
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

    /**
     * Store a newly created estabelecimento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'cnpj_basico' => 'required|string|max:8',
                'cnpj_ordem' => 'required|string|max:4',
                'cnpj_dv' => 'required|string|max:2',
                'matriz_filial' => 'required|integer|in:1,2',
                'nome_fantasia' => 'nullable|string|max:255',
                'situacao_cadastral' => 'nullable|integer',
                'data_situacao_cadastral' => 'nullable|date',
                'uf' => 'nullable|string|max:2',
                'municipio' => 'nullable|integer',
                'bairro' => 'nullable|string|max:100',
                'logradouro' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:20',
                'cep' => 'nullable|string|max:8',
                'correio_eletronico' => 'nullable|email|max:255',
                // Add other fields as needed
            ]);

            $estabelecimento = Estabelecimento::create($validatedData);

            return response()->json([
                'status' => 'success',
                'data' => $estabelecimento,
                'message' => 'Estabelecimento created successfully'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified estabelecimento.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $estabelecimento = Estabelecimento::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $estabelecimento,
                'message' => 'Estabelecimento retrieved successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estabelecimento not found'
            ], 404);
        }
    }

    /**
     * Update the specified estabelecimento.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $estabelecimento = Estabelecimento::findOrFail($id);

            $validatedData = $request->validate([
                'cnpj_basico' => 'sometimes|string|max:8',
                'cnpj_ordem' => 'sometimes|string|max:4',
                'cnpj_dv' => 'sometimes|string|max:2',
                'matriz_filial' => 'sometimes|integer|in:1,2',
                'nome_fantasia' => 'nullable|string|max:255',
                'situacao_cadastral' => 'nullable|integer',
                'data_situacao_cadastral' => 'nullable|date',
                'uf' => 'nullable|string|max:2',
                'municipio' => 'nullable|integer',
                'bairro' => 'nullable|string|max:100',
                'logradouro' => 'nullable|string|max:255',
                'numero' => 'nullable|string|max:20',
                'cep' => 'nullable|string|max:8',
                'correio_eletronico' => 'nullable|email|max:255',
                // Add other fields as needed
            ]);

            $estabelecimento->update($validatedData);

            return response()->json([
                'status' => 'success',
                'data' => $estabelecimento->fresh(),
                'message' => 'Estabelecimento updated successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estabelecimento not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified estabelecimento.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $estabelecimento = Estabelecimento::findOrFail($id);
            $estabelecimento->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Estabelecimento deleted successfully'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estabelecimento not found'
            ], 404);
        }
    }

    /**
     * Search estabelecimentos by name.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $term
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request, $term): JsonResponse
    {
        $estabelecimentos = Estabelecimento::searchByName($term)
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => 'Search results retrieved successfully'
        ]);
    }

    /**
     * Find estabelecimento by CNPJ.
     *
     * @param  string  $cnpj
     * @return \Illuminate\Http\JsonResponse
     */
    public function findByCnpj($cnpj): JsonResponse
    {
        $estabelecimentos = Estabelecimento::byCnpj($cnpj)->get();

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => 'Estabelecimentos found by CNPJ'
        ]);
    }

    /**
     * Find estabelecimentos by UF.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $uf
     * @return \Illuminate\Http\JsonResponse
     */
    public function findByUf(Request $request, $uf): JsonResponse
    {
        $estabelecimentos = Estabelecimento::byUf($uf)
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => "Estabelecimentos found in {$uf}"
        ]);
    }

    /**
     * Get only matriz establishments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMatriz(Request $request): JsonResponse
    {
        $estabelecimentos = Estabelecimento::matriz()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => 'Matriz establishments retrieved successfully'
        ]);
    }

    /**
     * Get only filial establishments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFiliais(Request $request): JsonResponse
    {
        $estabelecimentos = Estabelecimento::filial()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => 'Filial establishments retrieved successfully'
        ]);
    }

    /**
     * Get only active establishments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAtivos(Request $request): JsonResponse
    {
        $estabelecimentos = Estabelecimento::ativo()
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $estabelecimentos,
            'message' => 'Active establishments retrieved successfully'
        ]);
    }

    /**
     * Get statistics about establishments.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats(): JsonResponse
    {
        $stats = [
            'total' => Estabelecimento::count(),
            'matriz' => Estabelecimento::matriz()->count(),
            'filiais' => Estabelecimento::filial()->count(),
            'ativos' => Estabelecimento::ativo()->count(),
            'by_uf' => Estabelecimento::selectRaw('uf, COUNT(*) as count')
                ->groupBy('uf')
                ->orderBy('count', 'desc')
                ->get()
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'message' => 'Statistics retrieved successfully'
        ]);
    }
}