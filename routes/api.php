<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SociosController;
use App\Http\Controllers\EmpresasController;
use App\Http\Controllers\MunicipiosController;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\QualificacoesController;
use App\Http\Controllers\EstabelecimentosController;
use App\Http\Controllers\NaturezasController;
use App\Http\Controllers\MotivosController;
use App\Http\Controllers\PaisesController;
use App\Http\Controllers\CnaesController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Method 1: Full resource routes (recommended)
Route::apiResource('socios', SociosController::class);
Route::apiResource('empresas', EmpresasController::class);
Route::apiResource('municipios', MunicipiosController::class);
Route::apiResource('estados', EstadosController::class);
Route::apiResource('qualificacoes', QualificacoesController::class);
Route::apiResource('estabelecimentos', EstabelecimentosController::class)->only(['index', 'export']);
Route::apiResource('naturezas', NaturezasController::class);
Route::apiResource('motivos', MotivosController::class);
Route::apiResource('paises', PaisesController::class);
Route::apiResource('cnaes', CnaesController::class);