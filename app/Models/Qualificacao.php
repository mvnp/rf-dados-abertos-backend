<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Qualificacao extends Model
{
    use HasFactory;

    protected $table = 'qualificacoes';

    protected $fillable = [
        'descricao'
    ];
}