<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EstabelecimentoSearchService
{
    protected Builder $query;

    public function __construct(Builder $estabelecimentosQuery)
    {
        $this->query = $estabelecimentosQuery;
    }

    /**
     * Apply all search filters based on request parameters
     */
    public function applyFilters(Request $request): Builder
    {
        $this->applyCnpjFilter($request)
             ->applySituacaoFilter($request)
             ->applyCnaePrincipalFilter($request)
             ->applyCnaeSecundariaFilter($request)
             ->applyFantasiaFilter($request)
             ->applyLocationFilters($request)
             ->applyCepFilter($request)
             ->applyCapitalFilter($request)
             ->applyLimit($request);

        return $this->query;
    }

    /**
     * Filter by CNPJ básico (8 digits)
     */
    protected function applyCnpjFilter(Request $request): self
    {
        if ($request->filled('cnpj') && strlen($request->cnpj) == 8) {
            $this->query->where('estabelecimentos.cnpj_basico', $request->cnpj);
        }

        return $this;
    }

    /**
     * Filter by cadastral situation
     */
    protected function applySituacaoFilter(Request $request): self
    {
        if ($request->filled('situacao')) {
            $this->query->where('situacao_cadastral', (string) $request->situacao);
        }

        return $this;
    }

    /**
     * Filter by primary CNAE
     */
    protected function applyCnaePrincipalFilter(Request $request): self
    {
        if ($request->filled('cnae_principal')) {
            $cnae = str_pad($request->cnae_principal, 7, '0', STR_PAD_LEFT);
            $this->query->where('cnae_principal', $cnae);
        }

        return $this;
    }

    /**
     * Filter by secondary CNAE
     */
    protected function applyCnaeSecundariaFilter(Request $request): self
    {
        if ($request->filled('cnae_secundaria')) {
            $this->query->where('cnae_secundaria', (string) $request->cnae_secundaria);
        }

        return $this;
    }

    /**
     * Filter by fantasy name (partial match)
     */
    protected function applyFantasiaFilter(Request $request): self
    {
        if ($request->filled('fantasia')) {
            $this->query->where('nome_fantasia', 'LIKE', "{$request->fantasia}%");
        }

        return $this;
    }

    /**
     * Filter by state and municipality
     */
    protected function applyLocationFilters(Request $request): self
    {
        if ($request->filled('uf')) {
            $uf = strtoupper($request->uf);
            $this->query->where('uf', $uf);

            // If municipality is also provided
            if ($request->filled('municipio')) {
                $municipio = strtoupper($request->municipio);
                $this->query->where('municipio', $municipio);
            }
        }

        return $this;
    }

    /**
     * Filter by CEP (partial match)
     */
    protected function applyCepFilter(Request $request): self
    {
        if ($request->filled('cep')) {
            $this->query->where('cep', 'LIKE', "%{$request->cep}%");
        }

        return $this;
    }

    /**
     * Filter by capital range
     */
    protected function applyCapitalFilter(Request $request): self
    {
        if ($request->filled('capital_entre')) {
            $capitalRange = explode(',', $request->capital_entre);
            
            if (count($capitalRange) === 2) {
                $capitalInicial = (float) $capitalRange[0];
                $capitalFinal = (float) $capitalRange[1];
                
                if ($capitalInicial > 0 && $capitalFinal > 0) {
                    $this->query->whereHas('empresa', function ($query) use ($capitalInicial, $capitalFinal) {
                        $query->whereBetween('capital_social', [$capitalInicial, $capitalFinal]);
                    });
                }
            }
        }

        return $this;
    }

    /**
     * Apply result limit
     */
    protected function applyLimit(Request $request): self
    {
        $limit = $request->filled('limit') ? (int) $request->limit : 50;
        $this->query->limit($limit);

        return $this;
    }

    /**
     * Static method to create and use the service in one call
     */
    public static function search(Builder $estabelecimentosQuery, Request $request): Builder
    {
        $service = new self($estabelecimentosQuery);
        return $service->applyFilters($request);
    }
}