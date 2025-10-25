<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Http\Response;

class ExcelExportService
{
    /**
     * Exportar dados para CSV
     */
    public static function exportToCsv(Collection $data, string $filename, array $headers, callable $rowMapper)
    {
        $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
            'Pragma' => 'public',
        ];

        $callback = function() use ($data, $headers, $rowMapper) {
            $file = fopen('php://output', 'w');
            
            // Adicionar BOM para UTF-8 (para Excel reconhecer acentos)
            fwrite($file, "\xEF\xBB\xBF");
            
            // Cabeçalhos das colunas
            fputcsv($file, $headers, ';');
            
            // Dados
            foreach ($data as $item) {
                $row = $rowMapper($item);
                fputcsv($file, $row, ';');
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $responseHeaders);
    }

    /**
     * Formatar CNPJ
     */
    public static function formatCnpj(string $cnpjBasico, ?string $cnpjOrdem = null, ?string $cnpjDv = null): string
    {
        $cnpj = $cnpjBasico . 
                str_pad($cnpjOrdem ?? '', 4, '0', STR_PAD_LEFT) . 
                str_pad($cnpjDv ?? '', 2, '0', STR_PAD_LEFT);
        
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }

    /**
     * Formatar telefone
     */
    public static function formatPhone(?string $ddd, ?string $telefone): string
    {
        if (!$ddd || !$telefone) {
            return '';
        }

        $telefoneClean = preg_replace('/\D/', '', $telefone);
        
        if (strlen($telefoneClean) === 8) {
            $telefoneFormatado = substr($telefoneClean, 0, 4) . '-' . substr($telefoneClean, 4);
        } elseif (strlen($telefoneClean) === 9) {
            $telefoneFormatado = substr($telefoneClean, 0, 5) . '-' . substr($telefoneClean, 5);
        } else {
            $telefoneFormatado = $telefoneClean;
        }
        
        return "({$ddd}) {$telefoneFormatado}";
    }

    /**
     * Formatar valor monetário
     */
    public static function formatCurrency(?float $value): string
    {
        if (!$value) {
            return '';
        }
        
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}