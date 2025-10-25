<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Socio extends Model
{
    use HasFactory;

    protected $table = 'socios';

    protected $fillable = [
        'cnpj_basico',
        'identificador',
        'nome',
        'documento',
        'qualificacao',
        'data_sociedade',
        'pais',
        'representante_documento',
        'representante_nome',
        'representante_qualificacao',
        'faixa_etaria'
    ];
}