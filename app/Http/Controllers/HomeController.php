<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\{Municipio, Estado, Qualificacao, Natureza, Motivo, Pais, Cnae, Estabelecimento};
use App\Services\EstabelecimentoSearchService;

class HomeController extends Controller
{
    /**
     * Display the search form
     */
    public function index()
    {
        // Load all reference data for dropdowns
        $estados = Estado::orderBy('sigla')->get();
        $qualificacoes = Qualificacao::orderBy('descricao')->get();
        $naturezas = Natureza::orderBy('descricao')->get();
        $motivos = Motivo::orderBy('descricao')->get();
        $municipios = Municipio::orderBy('descricao')->get();
        $paises = Pais::orderBy('descricao')->get();
        $cnaes = Cnae::orderBy('descricao')->get();

        return view('home', compact(
            'estados',
            'qualificacoes', 
            'naturezas',
            'municipios',
            'motivos',
            'paises',
            'cnaes'
        ));
    }
}