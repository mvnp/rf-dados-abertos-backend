<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalMetropolesController extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para Metrópoles
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->timeout(30)->get('https://www.metropoles.com');

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

            // Seletores baseados na estrutura do Metrópoles
            $selectors = [
                // Manchetes principais
                '//article[@class="NoticiaWrapper__Article-sc-1vgx9gu-1 lhgZVZ"]//a[contains(@href, "metropoles.com")]',
                '//article[@class="NoticiaWrapper__Article-sc-1vgx9gu-1 IyqpT"]//a[contains(@href, "metropoles.com")]',
                
                // Carrossel de manchetes
                '//div[contains(@class, "CarrosselManchetesWrapper")]//a[contains(@href, "metropoles.com")]',
                '//div[contains(@class, "manchete-carousel")]//a[contains(@href, "metropoles.com")]',
                
                // Colunas e blogs
                '//div[contains(@class, "HomeColunasWrapper")]//a[contains(@href, "metropoles.com")]',
                
                // Notícias relacionadas
                '//article[contains(@class, "NoticiaRelacionadaWrapper")]//a[contains(@href, "metropoles.com")]',
                
                // Widget mais lidas
                '//div[contains(@class, "WidgetMaisLidasWrapper")]//a[contains(@href, "metropoles.com")]',
                
                // Slider home
                '//div[contains(@class, "swiper-slide")]//a[contains(@href, "metropoles.com")]'
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

            // Buscar artigos em estruturas específicas do Metrópoles
            $this->extractSpecificMetropolesArticles($xpath, $articles);

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
        $category = '';
        
        // Buscar título - estratégias específicas do Metrópoles
        $titleElements = [
            './/h2[contains(@class, "noticia__titulo")]',
            './/h4[contains(@class, "noticia__titulo")]',
            './/h4[contains(@class, "manchete-carousel__title")]',
            './/h5[contains(@class, "bloco-maisLidas__titulo")]',
            './/h1', './/h2', './/h3', './/h4', './/h5',
            './/@title', './/@aria-label'
        ];

        foreach ($titleElements as $titleSelector) {
            $titleNodes = $xpath->query($titleSelector, $linkNode);
            if ($titleNodes->length > 0) {
                $titleText = trim($titleNodes->item(0)->textContent);
                if (!empty($titleText) && strlen($titleText) > 10) {
                    $title = $titleText;
                    break;
                }
            }
        }

        // Se não encontrou título no link, buscar no elemento pai
        if (empty($title)) {
            $parent = $linkNode->parentNode;
            $attempts = 0;
            while ($parent && empty($title) && $attempts < 3) {
                $titleInParent = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//h5', $parent);
                if ($titleInParent->length > 0) {
                    $titleText = trim($titleInParent->item(0)->textContent);
                    if (!empty($titleText) && strlen($titleText) > 10) {
                        $title = $titleText;
                        break;
                    }
                }
                $parent = $parent->parentNode;
                $attempts++;
            }
        }

        // Buscar imagem
        $imageElements = [
            './/img[@src]',
            './/picture//img[@src]',
            './/figure//img[@src]',
            './/div[contains(@class, "bloco-noticia__figure-imagem")]',
            './/img[contains(@class, "manchete-carousel__image")]',
            './/img[contains(@class, "bloco-noticia__figure-imagem")]'
        ];

        foreach ($imageElements as $imageSelector) {
            $imageNodes = $xpath->query($imageSelector, $linkNode);
            if ($imageNodes->length > 0) {
                $imgNode = $imageNodes->item(0);
                $imageSrc = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src');
                if ($imageSrc && !$this->isInvalidImage($imageSrc)) {
                    $image = $this->normalizeUrl($imageSrc);
                    break;
                }
            }
        }

        // Se não encontrou imagem no link, buscar no elemento pai
        if (empty($image)) {
            $parent = $linkNode->parentNode;
            $attempts = 0;
            while ($parent && empty($image) && $attempts < 3) {
                $imageInParent = $xpath->query('.//img[@src] | .//picture//img[@src]', $parent);
                if ($imageInParent->length > 0) {
                    $imgSrc = $imageInParent->item(0)->getAttribute('src');
                    if ($imgSrc && !$this->isInvalidImage($imgSrc)) {
                        $image = $this->normalizeUrl($imgSrc);
                        break;
                    }
                }
                $parent = $parent->parentNode;
                $attempts++;
            }
        }

        // Buscar categoria
        $categoryElements = [
            './/div[contains(@class, "noticia__categoria")]',
            './/a[contains(@class, "manchete-carousel__categoria")]',
            './/div[contains(@class, "TextLabel")]'
        ];

        foreach ($categoryElements as $categorySelector) {
            $categoryNodes = $xpath->query($categorySelector, $linkNode->parentNode);
            if ($categoryNodes->length > 0) {
                $categoryText = trim($categoryNodes->item(0)->textContent);
                if (!empty($categoryText) && strlen($categoryText) < 50) {
                    $category = $categoryText;
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
            'category' => $category ?: 'Sem categoria',
            'source' => 'Metrópoles',
            'check' => 'falso',
            'fonte' => 'metropoles'
        ];
    }

    private function extractSpecificMetropolesArticles($xpath, &$articles)
    {
        // Extrair de elementos específicos do Metrópoles
        $specificSelectors = [
            // Grid de notícias
            '//div[contains(@class, "Grid__Container")]//a[contains(@href, "metropoles.com")]',
            
            // Swiper slides
            '//div[contains(@class, "swiper-slide")]//a[contains(@href, "metropoles.com")]',
            
            // Artigos em colunas
            '//div[contains(@class, "categorias-slider__link")]',
            
            // Notícias em destaque
            '//div[contains(@id, "manchete-section")]//a[contains(@href, "metropoles.com")]'
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
            '.jpg', '.png', '.gif', '.svg', '.webp',
            'javascript:',
            'mailto:',
            '#',
            '/categoria/',
            '/tag/',
            '/author/',
            'youtube.com',
            'instagram.com',
            'facebook.com',
            'twitter.com'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve conter metropoles.com ou ser uma URL relativa válida
        return (strpos($url, 'metropoles.com') !== false || strpos($url, '/') === 0);
    }

    private function isInvalidImage($imageSrc)
    {
        $invalidPatterns = [
            'avatar',
            'logo',
            'placeholder',
            'loading',
            'sprite',
            'icon',
            '1x1',
            'blank'
        ];

        foreach ($invalidPatterns as $pattern) {
            if (stripos($imageSrc, $pattern) !== false) {
                return true;
            }
        }

        return false;
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
            if (strlen($title) < 10 || strlen($title) > 300) {
                continue;
            }

            // Validar URL
            $link = $this->normalizeUrl($article['link']);
            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Validar imagem se existir
            $image = $article['image'];
            if ($image && !filter_var($image, FILTER_VALIDATE_URL)) {
                $image = null;
            }

            $cleaned[] = [
                'title' => $title,
                'link' => $link,
                'image' => $image,
                'category' => $article['category'],
                'source' => $article['source'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'metropoles'
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
        
        // Remover prefixos comuns do Metrópoles
        $prefixes = [
            'Metrópoles:', 
            'ASSISTA:', 
            'VEJA:', 
            'LEIA:', 
            'ACOMPANHE:',
            'Veja mais detalhes da Notícia:',
            'Acompanhe'
        ];
        
        foreach ($prefixes as $prefix) {
            if (stripos($title, $prefix) === 0) {
                $title = trim(substr($title, strlen($prefix)));
            }
        }

        // Remover textos de aria-label
        $title = preg_replace('/^Veja mais detalhes da Notícia:\s*/i', '', $title);
        
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
            return 'https://www.metropoles.com' . $url;
        }

        return $url;
    }

    // Método para buscar artigos por categoria específica
    public function scrapByCategory($category = null)
    {
        $baseUrl = 'https://www.metropoles.com';
        $url = $category ? $baseUrl . '/' . $category : $baseUrl;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar a categoria'], 500);
            }

            // Usar a mesma lógica de extração
            return $this->index();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para obter estatísticas do scraping
    public function getStats()
    {
        try {
            $result = $this->index();
            $data = $result->getData(true);
            
            if ($data['success']) {
                $articles = $data['articles'];
                $categories = array_count_values(array_column($articles, 'category'));
                $withImages = count(array_filter($articles, function($article) {
                    return !empty($article['image']);
                }));
                
                return response()->json([
                    'total_articles' => $data['total'],
                    'articles_with_images' => $withImages,
                    'articles_without_images' => $data['total'] - $withImages,
                    'categories' => $categories,
                    'scraped_at' => now()->toISOString(),
                    'check' => 'falso',
                    'fonte' => 'metropoles'
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao obter estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}