<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalGloboPontoComController extends Controller
{
    public function index()
    {
        // Fazer requisição para o G1
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->get('https://g1.globo.com');

        $html = $response->body();
        
        // Criar DOM
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $articles = [];

        // Buscar artigos
        $nodes = $xpath->query('//div[contains(@class, "feed-post")] | //article[contains(@class, "feed-post")]');

        foreach ($nodes as $node) {
            // Título e link
            $titleLink = $xpath->query('.//h2//a | .//h3//a | .//a[contains(@class, "feed-post-link")]', $node)->item(0);
            
            // Imagem
            $image = $xpath->query('.//img[@src]', $node)->item(0);
            
            if ($titleLink) {
                $articles[] = [
                    'title' => trim($titleLink->textContent),
                    'link' => $this->normalizeUrl($titleLink->getAttribute('href')),
                    'image' => $image ? $this->normalizeUrl($image->getAttribute('src')) : null,
                    'check' => 'falso',
                    'fonte' => 'globocom'
                ];
            }
        }

        // Remover duplicatas por título
        $unique = [];
        $titles = [];
        
        foreach ($articles as $article) {
            if (!in_array($article['title'], $titles)) {
                $unique[] = $article;
                $titles[] = $article['title'];
            }
        }

        return response()->json($unique);
    }

    private function normalizeUrl($url)
    {
        if (str_starts_with($url, 'http')) return $url;
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if (str_starts_with($url, '/')) return 'https://g1.globo.com' . $url;
        return $url;
    }
}