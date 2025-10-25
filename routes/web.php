<?php

use App\Http\Controllers\HomeController;
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

use App\Http\Controllers\PortalGloboPontoComController;
use App\Http\Controllers\PortalUolNoticiasController;
use App\Http\Controllers\PortalMetropolesController;
use App\Http\Controllers\PortalCnnBrasilController;
use App\Http\Controllers\PortalTerraController;
use App\Http\Controllers\EstadaoScraperController;
use App\Http\Controllers\PortalGloboController;
use App\Http\Controllers\PortalR7Controller;

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', [HomeController::class, 'index']);

// Method 1: Full resource routes (recommended)
Route::resource('socios', SociosController::class);
Route::resource('empresas', EmpresasController::class);
Route::resource('municipios', MunicipiosController::class);
Route::resource('estados', EstadosController::class);
Route::resource('qualificacoes', QualificacoesController::class);
Route::resource('estabelecimentos', EstabelecimentosController::class)->only(['index']);
Route::resource('naturezas', NaturezasController::class);
Route::resource('motivos', MotivosController::class);
Route::resource('paises', PaisesController::class);
Route::resource('cnaes', CnaesController::class);

Route::get('cnnbrasil', [PortalCnnBrasilController::class, 'index']);
Route::get('metropoles', [PortalMetropolesController::class, 'index']);
Route::get('g1com', [PortalGloboPontoComController::class, 'index']);
Route::get('uolnoticias', [PortalUolNoticiasController::class, 'index']);
Route::get('portalterra', [PortalTerraController::class, 'index']);
Route::get('portalr7', [PortalR7Controller::class, 'index']);
Route::get('estadao', [EstadaoScraperController::class, 'index']);
Route::get('globocom', [PortalGloboController::class, 'index']);

// Rota para gerar e fazer download do arquivo
Route::get('/estabelecimentos/export', [EstabelecimentosController::class, 'export'])->name('estabelecimentos.export');