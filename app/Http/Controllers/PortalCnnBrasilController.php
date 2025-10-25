<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalCnnBrasilController extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para CNN Brasil
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->timeout(30)->get('https://www.cnnbrasil.com.br');

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar o site'], 500);
            }

            $html = $response->body();
            
            // Criar DOM
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $articles = [];

            // Seletores para diferentes tipos de artigos na CNN Brasil
            $selectors = [
                // Artigos principais em manchetes
                '//figure//a[contains(@href, "cnnbrasil.com.br")]',
                
                // Artigos em listas de notícias
                '//article//a[contains(@href, "cnnbrasil.com.br")]',
                
                // Links em divs de notícias
                '//div[contains(@class, "flex") and contains(@class, "gap")]//a[contains(@href, "cnnbrasil.com.br")]',
                
                // Links em seções específicas
                '//section//a[contains(@href, "cnnbrasil.com.br")]',
                
                // Artigos em carrosséis e slideshows
                '//div[contains(@class, "keen-slider")]//a[contains(@href, "cnnbrasil.com.br")]'
            ];

            foreach ($selectors as $selector) {
                $nodes = $xpath->query($selector);
                
                foreach ($nodes as $link) {
                    $article = $this->extractArticleData($link, $xpath);
                    if ($article && !$this->isDuplicate($article, $articles)) {
                        $articles[] = $article;
                    }
                }
            }

            // Buscar artigos em elementos específicos baseados na estrutura observada
            $this->extractSpecificArticles($xpath, $articles);

            // Remover duplicatas e validar dados
            $articles = $this->cleanAndValidateArticles($articles);

            return response()->json([
                'success' => true,
                'total' => count($articles),
                'articles' => $articles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar scraping: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractArticleData($linkNode, $xpath)
    {
        $href = $linkNode->getAttribute('href');
        
        // Validar se é um link válido de artigo
        if (!$href || !$this->isValidArticleUrl($href)) {
            return null;
        }

        $title = '';
        $image = '';
        
        // Buscar título - várias estratégias
        $titleElements = [
            './/h1', './/h2', './/h3', './/h4',
            './/@alt', './/@title',
            './/figcaption//h1', './/figcaption//h2', './/figcaption//h3'
        ];

        foreach ($titleElements as $titleSelector) {
            $titleNodes = $xpath->query($titleSelector, $linkNode);
            if ($titleNodes->length > 0) {
                $title = trim($titleNodes->item(0)->textContent);
                if (!empty($title) && strlen($title) > 10) { // Título mínimo de 10 caracteres
                    break;
                }
            }
        }

        // Se não encontrou título no link, buscar no elemento pai
        if (empty($title)) {
            $parent = $linkNode->parentNode;
            if ($parent) {
                $titleInParent = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4', $parent);
                if ($titleInParent->length > 0) {
                    $title = trim($titleInParent->item(0)->textContent);
                }
            }
        }

        // Buscar imagem
        $imageElements = [
            './/img[@src]',
            './/picture//img[@src]',
            './/source[@srcset]'
        ];

        foreach ($imageElements as $imageSelector) {
            $imageNodes = $xpath->query($imageSelector, $linkNode);
            if ($imageNodes->length > 0) {
                $imgNode = $imageNodes->item(0);
                $imageSrc = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('srcset');
                if ($imageSrc) {
                    $image = $this->normalizeUrl($imageSrc);
                    break;
                }
            }
        }

        // Se não encontrou imagem no link, buscar no elemento pai
        if (empty($image)) {
            $parent = $linkNode->parentNode;
            while ($parent && empty($image)) {
                $imageInParent = $xpath->query('.//img[@src] | .//picture//img[@src]', $parent);
                if ($imageInParent->length > 0) {
                    $imgSrc = $imageInParent->item(0)->getAttribute('src');
                    if ($imgSrc) {
                        $image = $this->normalizeUrl($imgSrc);
                        break;
                    }
                }
                $parent = $parent->parentNode;
            }
        }

        // Validar se tem pelo menos título e link
        if (empty($title) || empty($href)) {
            return null;
        }

        return [
            'title' => $this->cleanTitle($title),
            'link' => $this->normalizeUrl($href),
            'image' => $image ?: null,
            'source' => 'CNN Brasil',
            'check' => 'falso',
            'fonte' => 'cnnbrasil'
        ];
    }

    private function extractSpecificArticles($xpath, &$articles)
    {
        // Extrair de elementos com estruturas específicas observadas no HTML
        $specificSelectors = [
            // Artigos em blocos de manchete
            '//div[@id="manchete-section-one"]//a[contains(@href, "cnnbrasil.com.br")]',
            
            // Artigos em seções de mais lidas
            '//div[contains(@class, "keen-slider__slide")]//a[contains(@href, "cnnbrasil.com.br")]',
            
            // Artigos em listas de vídeos
            '//div[contains(@class, "carouselWrapBlocks")]//a[contains(@href, "cnnbrasil.com.br")]',
            
            // Artigos em seções temáticas
            '//section[contains(@class, "grid")]//a[contains(@href, "cnnbrasil.com.br")]'
        ];

        foreach ($specificSelectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath);
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function isValidArticleUrl($url)
    {
        // Verificar se é uma URL válida de artigo
        if (empty($url)) return false;
        
        // URLs para ignorar
        $ignoredPatterns = [
            '/wp-content/',
            '/wp-admin/',
            '/feed/',
            '.jpg', '.png', '.gif', '.svg',
            'javascript:',
            'mailto:',
            '#',
            '/categoria/',
            '/tag/',
            '/author/'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve conter cnnbrasil.com.br ou ser uma URL relativa válida
        return (strpos($url, 'cnnbrasil.com.br') !== false || strpos($url, '/') === 0);
    }

    private function isDuplicate($article, $articles)
    {
        foreach ($articles as $existing) {
            if ($existing['title'] === $article['title'] || $existing['link'] === $article['link']) {
                return true;
            }
        }
        return false;
    }

    private function cleanAndValidateArticles($articles)
    {
        $cleaned = [];
        
        foreach ($articles as $article) {
            // Validar campos obrigatórios
            if (empty($article['title']) || empty($article['link'])) {
                continue;
            }

            // Limpar e validar título
            $title = $this->cleanTitle($article['title']);
            if (strlen($title) < 10 || strlen($title) > 200) {
                continue;
            }

            // Validar URL
            $link = $this->normalizeUrl($article['link']);
            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            $cleaned[] = [
                'title' => $title,
                'link' => $link,
                'image' => $article['image'],
                'source' => $article['source'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'cnnbrasil'
            ];
        }

        return $cleaned;
    }

    private function cleanTitle($title)
    {
        // Remover caracteres especiais e normalizar
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        
        // Remover prefixos comuns
        $prefixes = ['CNN Brasil:', 'CNN:', 'ASSISTA:', 'VEJA:', 'LEIA:'];
        foreach ($prefixes as $prefix) {
            if (stripos($title, $prefix) === 0) {
                $title = trim(substr($title, strlen($prefix)));
            }
        }

        return $title;
    }

    private function normalizeUrl($url)
    {
        if (empty($url)) return '';
        
        // Normalizar URLs
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        if (strpos($url, '/') === 0) {
            return 'https://www.cnnbrasil.com.br' . $url;
        }

        // Para URLs de imagem com parâmetros, pegar apenas a primeira parte
        if (strpos($url, '?') !== false) {
            $url = explode('?', $url)[0] . '?' . explode('?', $url)[1];
        }
        
        return $url;
    }

    // Método adicional para buscar artigos por categoria específica
    public function scrapByCategory($category = null)
    {
        $baseUrl = 'https://www.cnnbrasil.com.br';
        $url = $category ? $baseUrl . '/' . $category : $baseUrl;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar a categoria'], 500);
            }

            // Usar a mesma lógica de extração
            return $this->scrapCnnBrasil();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar categoria: ' . $e->getMessage()
            ], 500);
        }
    }
}