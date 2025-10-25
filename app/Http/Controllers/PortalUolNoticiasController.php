<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalUolNoticiasController extends Controller
{
    public function index()
    {
        // Fazer requisição para o UOL Notícias
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1'
        ])->timeout(30)->get('https://noticias.uol.com.br');

        if (!$response->successful()) {
            return response()->json(['error' => 'Falha ao acessar o site UOL Notícias'], 500);
        }

        $html = $response->body();
        
        // Criar DOM
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $articles = [];

        // Buscar artigos com imagem na estrutura thumbnails (baseado no HTML fornecido)
        $thumbnailNodes = $xpath->query('//div[contains(@class, "thumbnails-item") and contains(@class, "grid") and not(contains(@class, "no-image"))]');

        foreach ($thumbnailNodes as $node) {
            $article = $this->extractArticleFromThumbnail($xpath, $node);
            if ($article) {
                $articles[] = $article;
            }
        }

        // Buscar artigos em outras estruturas da página
        $additionalNodes = $xpath->query('//article[.//img[@src or @data-src] and .//a[@href] and (.//h1 or .//h2 or .//h3 or .//h4)]');

        foreach ($additionalNodes as $node) {
            $article = $this->extractArticleFromGeneric($xpath, $node);
            if ($article) {
                $articles[] = $article;
            }
        }

        // Buscar notícias em listas ou feeds
        $feedNodes = $xpath->query('//div[contains(@class, "feed") or contains(@class, "news-list")]//div[.//img and .//a[@href]]');

        foreach ($feedNodes as $node) {
            $article = $this->extractArticleFromGeneric($xpath, $node);
            if ($article) {
                $articles[] = $article;
            }
        }

        // Remover duplicatas e filtrar
        $unique = $this->filterAndDeduplicateArticles($articles);

        return response()->json([
            'success' => true,
            'total' => count($unique),
            'source' => 'noticias.uol.com.br',
            'articles' => $unique
        ]);
    }

    private function extractArticleFromThumbnail($xpath, $node)
    {
        // Link principal (dentro de thumbnails-wrapper > a)
        $linkElement = $xpath->query('.//div[contains(@class, "thumbnails-wrapper")]//a[@href]', $node)->item(0);
        
        // Título (h3 com classe thumb-title)
        $titleElement = $xpath->query('.//h3[contains(@class, "thumb-title")]', $node)->item(0);
        
        // Imagem (dentro de thumb-image)
        $imageElement = $xpath->query('.//figure[contains(@class, "thumb-image")]//img[@src or @data-src]', $node)->item(0);
        
        if ($linkElement && $titleElement && $imageElement) {
            // Priorizar data-src se existir (lazy loading)
            $imageUrl = $imageElement->getAttribute('data-src') ?: $imageElement->getAttribute('src');
            
            return [
                'title' => trim($titleElement->textContent),
                'link' => $this->normalizeUrl($linkElement->getAttribute('href')),
                'image' => $this->normalizeUrl($imageUrl),
                'type' => 'thumbnail'
            ];
        }

        return null;
    }

    private function extractArticleFromGeneric($xpath, $node)
    {
        // Link
        $linkElement = $xpath->query('.//a[@href]', $node)->item(0);
        
        // Título (buscar em diferentes tags de heading)
        $titleElement = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[contains(@class, "title") or contains(@class, "headline")]', $node)->item(0);
        
        // Imagem
        $imageElement = $xpath->query('.//img[@src or @data-src]', $node)->item(0);
        
        if ($linkElement && $titleElement && $imageElement) {
            $imageUrl = $imageElement->getAttribute('data-src') ?: $imageElement->getAttribute('src');
            
            return [
                'title' => trim($titleElement->textContent),
                'link' => $this->normalizeUrl($linkElement->getAttribute('href')),
                'image' => $this->normalizeUrl($imageUrl),
                'type' => 'generic',
                'check' => 'falso',
                'fonte' => 'uol'
            ];
        }

        return null;
    }

    private function filterAndDeduplicateArticles($articles)
    {
        $unique = [];
        $seen = [];
        
        foreach ($articles as $article) {
            // Verificar se todos os campos estão preenchidos
            if (empty($article['title']) || empty($article['link']) || empty($article['image'])) {
                continue;
            }

            // Filtrar títulos muito curtos ou suspeitos
            if (strlen($article['title']) < 15) {
                continue;
            }

            // Verificar se a imagem é válida (não é placeholder ou icon)
            if (!$this->isValidImage($article['image'])) {
                continue;
            }

            // Criar chave única baseada no título e link
            $key = md5(strtolower(trim($article['title'])) . $article['link']);
            
            if (!isset($seen[$key])) {
                // Remover campo 'type' antes de adicionar ao resultado final
                unset($article['type']);
                $unique[] = $article;
                $seen[$key] = true;
            }
        }

        // Ordenar por relevância
        usort($unique, function($a, $b) {
            // Priorizar artigos com imagens do UOL
            $aIsUol = strpos($a['image'], 'uol.com') !== false;
            $bIsUol = strpos($b['image'], 'uol.com') !== false;
            
            if ($aIsUol && !$bIsUol) return -1;
            if (!$aIsUol && $bIsUol) return 1;
            
            // Secundariamente, ordenar por tamanho do título (mais completos primeiro)
            return strlen($b['title']) - strlen($a['title']);
        });

        return $unique;
    }

    private function isValidImage($imageUrl)
    {
        // Filtrar imagens inválidas
        $invalidPatterns = [
            'placeholder',
            'loading',
            'icon',
            'sprite',
            'logo-small',
            '.svg',
            'data:image'
        ];

        $imageUrl = strtolower($imageUrl);
        
        foreach ($invalidPatterns as $pattern) {
            if (strpos($imageUrl, $pattern) !== false) {
                return false;
            }
        }

        // Verificar se tem extensão de imagem válida ou é do CDN do UOL
        return strpos($imageUrl, 'imguol.com.br') !== false || 
               preg_match('/\.(jpg|jpeg|png|webp)(\?|$)/i', $imageUrl);
    }

    private function normalizeUrl($url)
    {
        if (empty($url)) return null;
        
        // URL já completa
        if (str_starts_with($url, 'http')) return $url;
        
        // Protocolo relativo
        if (str_starts_with($url, '//')) return 'https:' . $url;
        
        // Caminho absoluto
        if (str_starts_with($url, '/')) {
            return 'https://noticias.uol.com.br' . $url;
        }
        
        // Caminho relativo
        return 'https://noticias.uol.com.br/' . $url;
    }

    // Método para buscar a home page principal do UOL (caso necessário)
    public function uolHome()
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->timeout(30)->get('https://www.uol.com.br');

        if (!$response->successful()) {
            return response()->json(['error' => 'Falha ao acessar UOL'], 500);
        }

        // Reutilizar a mesma lógica adaptando as URLs
        // ... (implementação similar adaptada para www.uol.com.br)
        
        return response()->json([
            'success' => true,
            'message' => 'Método para home page principal do UOL'
        ]);
    }
}