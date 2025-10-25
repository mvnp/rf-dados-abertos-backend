<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class EstadaoScraperController extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para Estadão
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive'
            ])->timeout(30)->get('https://www.estadao.com.br');

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

            // Seletores baseados na estrutura HTML observada
            $selectors = [
                // Artigos principais com imagem na home
                '//div[contains(@class, "noticia-single-block")]//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Manchetes principais
                '//div[contains(@class, "manchete")]//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Artigos em seções de destaque
                '//div[contains(@class, "news-block")]//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Cards de notícias
                '//div[contains(@class, "card")]//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Artigos em carrosséis
                '//div[contains(@class, "carousel")]//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Links em figura/imagem
                '//figure//a[contains(@href, "estadao.com.br") and @title and .//img]',
                
                // Artigos com headline
                '//h1//a[contains(@href, "estadao.com.br") and @title]',
                '//h2//a[contains(@href, "estadao.com.br") and @title]',
                '//h3//a[contains(@href, "estadao.com.br") and @title]'
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

            // Buscar artigos específicos na estrutura do Estadão
            $this->extractSpecificEstadaoArticles($xpath, $articles);

            // Limpar e validar dados
            $articles = $this->cleanAndValidateArticles($articles);

            return response()->json([
                'success' => true,
                'total' => count($articles),
                'articles' => $articles,
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'estadao'
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
        $title = $linkNode->getAttribute('title');
        
        // Se não tem título no atributo, buscar no texto do link
        if (empty($title)) {
            $title = trim($linkNode->textContent);
        }
        
        // Validar se é um link válido de artigo
        if (!$href || !$this->isValidArticleUrl($href)) {
            return null;
        }

        $image = '';
        $subtitle = '';
        $category = '';
        
        // Buscar imagem dentro do link ou em elementos próximos
        $imageElements = [
            './/img[@src]',
            './/img[@data-src]',
            '..//img[@src]',
            '..//img[@data-src]',
            './ancestor::div[1]//img[@src]',
            './ancestor::div[1]//img[@data-src]',
            './ancestor::article[1]//img[@src]',
            './ancestor::article[1]//img[@data-src]'
        ];

        foreach ($imageElements as $imageSelector) {
            $imageNodes = $xpath->query($imageSelector, $linkNode);
            if ($imageNodes->length > 0) {
                $imgNode = $imageNodes->item(0);
                $imageSrc = $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src');
                if ($imageSrc) {
                    $image = $this->normalizeUrl($imageSrc);
                    break;
                }
            }
        }

        // Buscar subtítulo/descrição
        $subtitleElements = [
            './ancestor::div[1]//p[contains(@class, "subheadline")]',
            './ancestor::div[1]//div[contains(@class, "subheadline")]',
            './ancestor::div[1]//p[not(contains(@class, "headline"))]',
            './following-sibling::p[1]',
            '..//p[contains(@class, "description")]'
        ];

        foreach ($subtitleElements as $subtitleSelector) {
            $subtitleNodes = $xpath->query($subtitleSelector, $linkNode);
            if ($subtitleNodes->length > 0) {
                $subtitle = trim($subtitleNodes->item(0)->textContent);
                if (!empty($subtitle) && strlen($subtitle) > 20) {
                    break;
                }
            }
        }

        // Buscar categoria/chapéu
        $categoryElements = [
            './ancestor::div[1]//div[contains(@class, "chapeu")]//span',
            './ancestor::div[1]//div[contains(@class, "category")]',
            './ancestor::div[1]//a[contains(@class, "chapeu")]',
            './ancestor::div[1]//span[contains(@class, "tag")]'
        ];

        foreach ($categoryElements as $categorySelector) {
            $categoryNodes = $xpath->query($categorySelector, $linkNode);
            if ($categoryNodes->length > 0) {
                $category = trim($categoryNodes->item(0)->textContent);
                if (!empty($category) && strlen($category) < 50) {
                    break;
                }
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
            'subtitle' => $subtitle ?: null,
            'category' => $category ?: null,
            'source' => 'Estadão',
            'has_image' => !empty($image),
            'check' => 'falso',
            'fonte' => 'estadao'
        ];
    }

    private function extractSpecificEstadaoArticles($xpath, &$articles)
    {
        // Extrair artigos de seções específicas do Estadão baseado na estrutura observada
        $specificSelectors = [
            // Seção de manchetes principais
            '//div[contains(@class, "manchete-dia-a-dia")]//a[contains(@href, "estadao.com.br")]',
            
            // Artigos em destaque com imagem
            '//div[contains(@class, "container-image")]//a[contains(@href, "estadao.com.br")]',
            
            // Notícias simples com imagem
            '//div[contains(@class, "NoticiaSimplesContainer")]//a[contains(@href, "estadao.com.br")]',
            
            // Carrossel de notícias
            '//div[contains(@class, "carousel-item")]//a[contains(@href, "estadao.com.br")]',
            
            // Artigos em colunas
            '//div[contains(@class, "coluna")]//a[contains(@href, "estadao.com.br")]',
            
            // Seções temáticas (150 anos, Blue Studio, etc)
            '//div[contains(@class, "faixa-tematica")]//a[contains(@href, "estadao.com.br")]',
            
            // Cards de notícias especiais
            '//div[contains(@class, "CardFaixaTematica")]//a[contains(@href, "estadao.com.br")]'
        ];

        foreach ($specificSelectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                // Verificar se o link tem imagem associada
                $hasImage = $this->linkHasImage($link, $xpath);
                
                if ($hasImage) {
                    $article = $this->extractArticleData($link, $xpath);
                    if ($article && !$this->isDuplicate($article, $articles)) {
                        $articles[] = $article;
                    }
                }
            }
        }
    }

    private function linkHasImage($linkNode, $xpath)
    {
        $imageSelectors = [
            './/img',
            '..//img',
            './ancestor::div[1]//img',
            './ancestor::article[1]//img',
            './ancestor::*[contains(@class, "image")]//img'
        ];

        foreach ($imageSelectors as $selector) {
            $images = $xpath->query($selector, $linkNode);
            if ($images->length > 0) {
                return true;
            }
        }
        return false;
    }

    private function isValidArticleUrl($url)
    {
        if (empty($url)) return false;
        
        // URLs para ignorar
        $ignoredPatterns = [
            '/wp-content/',
            '/wp-admin/',
            '/feed/',
            '.jpg', '.png', '.gif', '.svg', '.webp',
            'javascript:',
            'mailto:',
            '#',
            '/newsletter/',
            '/termo-de-uso',
            '/politica-privacidade'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve ser um artigo do Estadão
        return (strpos($url, 'estadao.com.br') !== false || strpos($url, '/') === 0);
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

            // Filtrar apenas artigos com imagem
            if (empty($article['image'])) {
                continue;
            }

            // Limpar e validar título
            $title = $this->cleanTitle($article['title']);
            if (strlen($title) < 10 || strlen($title) > 300) {
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
                'subtitle' => $article['subtitle'],
                'category' => $article['category'],
                'source' => $article['source'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'estadao'
            ];
        }

        // Remover duplicatas por URL
        $uniqueArticles = [];
        $seenUrls = [];
        
        foreach ($cleaned as $article) {
            if (!in_array($article['link'], $seenUrls)) {
                $uniqueArticles[] = $article;
                $seenUrls[] = $article['link'];
            }
        }

        return $uniqueArticles;
    }

    private function cleanTitle($title)
    {
        // Remover caracteres especiais e normalizar
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        
        // Remover prefixos comuns do Estadão
        $prefixes = ['Estadão:', 'ASSISTA:', 'VEJA:', 'LEIA:', 'EXCLUSIVO:'];
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
            return 'https://www.estadao.com.br' . $url;
        }
        
        return $url;
    }

    // Método para salvar artigos no banco de dados (opcional)
    public function scrapeAndSave()
    {
        $response = $this->scrapeHomePage();
        $data = $response->getData(true);
        
        if ($data['success']) {
            foreach ($data['articles'] as $articleData) {
                // Assumindo que você tem um model Article
                // Article::updateOrCreate(
                //     ['link' => $articleData['link']],
                //     $articleData
                // );
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Artigos salvos com sucesso',
                'total_saved' => count($data['articles'])
            ]);
        }
        
        return $response;
    }

    // Método para buscar artigos por categoria específica
    public function scrapeByCategory($category = null)
    {
        $baseUrl = 'https://www.estadao.com.br';
        $url = $category ? $baseUrl . '/' . $category : $baseUrl;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar a categoria'], 500);
            }

            // Usar a mesma lógica de extração
            return $this->scrapeHomePage();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar categoria: ' . $e->getMessage()
            ], 500);
        }
    }
}