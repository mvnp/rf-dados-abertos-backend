<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalTerraController extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para Terra.com.br
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])->timeout(30)->get('https://www.terra.com.br');

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar o site Terra'], 500);
            }

            $html = $response->body();
            
            // Criar DOM
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $articles = [];

            // Seletores para diferentes tipos de artigos no Terra
            $selectors = [
                // Cards principais com imagem
                '//div[contains(@class, "card-news") and contains(@class, "card-has-image")]',
                
                // Cards premium com imagem
                '//div[contains(@class, "card-premium") and contains(@class, "card-has-image")]',
                
                // Cards horizontais com imagem
                '//div[contains(@class, "card-h") and contains(@class, "card-has-image")]',
                
                // Cards pequenos com imagem
                '//div[contains(@class, "card-h-small") and contains(@class, "card-has-image")]',
                
                // Cards extra large com imagem
                '//div[contains(@class, "card-hxl") and contains(@class, "card-has-image")]',
                
                // Stories do Terra
                '//a[contains(@class, "t360-stories--story")]'
            ];

            foreach ($selectors as $selector) {
                $nodes = $xpath->query($selector);
                
                foreach ($nodes as $cardNode) {
                    $article = $this->extractArticleData($cardNode, $xpath);
                    if ($article && !$this->isDuplicate($article, $articles)) {
                        $articles[] = $article;
                    }
                }
            }

            // Buscar artigos em seções específicas
            $this->extractSpecificSections($xpath, $articles);

            // Extrair stories em formato especial
            $this->extractStories($xpath, $articles);

            // Remover duplicatas e validar dados
            $articles = $this->cleanAndValidateArticles($articles);

            return response()->json([
                'success' => true,
                'total' => count($articles),
                'articles' => array_slice($articles, 0, 50), // Limitar a 50 artigos
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'terra'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar scraping: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractArticleData($cardNode, $xpath)
    {
        $title = '';
        $link = '';
        $image = '';
        $category = '';

        // Estratégia 1: Buscar em cards padrão
        if ($cardNode->nodeName === 'div') {
            // Buscar título - link principal
            $titleLink = $xpath->query('.//a[contains(@class, "main-url") or contains(@class, "card-news__url")]', $cardNode)->item(0);
            
            if (!$titleLink) {
                // Fallback: qualquer link com título
                $titleLink = $xpath->query('.//a[@title and @href]', $cardNode)->item(0);
            }

            if ($titleLink) {
                $link = $titleLink->getAttribute('href');
                $title = $titleLink->getAttribute('title');
                
                // Se não tem title, buscar no h3
                if (empty($title)) {
                    $h3 = $xpath->query('.//h3', $titleLink)->item(0);
                    if ($h3) {
                        $title = trim($h3->textContent);
                    }
                }
            }

            // Buscar imagem
            $imgNode = $xpath->query('.//img[@src]', $cardNode)->item(0);
            if ($imgNode) {
                $image = $imgNode->getAttribute('src');
                // Se não tem title ainda, tentar pegar do alt da imagem
                if (empty($title)) {
                    $title = $imgNode->getAttribute('alt');
                }
            }

            // Buscar categoria/chapéu
            $categoryNode = $xpath->query('.//a[contains(@class, "card-news__text--hat")]', $cardNode)->item(0);
            if ($categoryNode) {
                $category = trim($categoryNode->textContent);
            }
        }
        
        // Estratégia 2: Para stories
        if ($cardNode->nodeName === 'a' && strpos($cardNode->getAttribute('class'), 't360-stories--story') !== false) {
            $link = $cardNode->getAttribute('href');
            $title = $cardNode->getAttribute('title');
            
            // Buscar imagem na story
            $imgNode = $xpath->query('.//img[@src]', $cardNode)->item(0);
            if ($imgNode) {
                $image = $imgNode->getAttribute('src');
            }
            
            $category = 'Stories';
        }

        // Validar dados extraídos
        if (empty($title) || empty($link)) {
            return null;
        }

        // Normalizar URLs
        $link = $this->normalizeUrl($link);
        $image = $this->normalizeUrl($image);

        // Validar se é um artigo válido
        if (!$this->isValidArticleUrl($link)) {
            return null;
        }

        return [
            'title' => $this->cleanTitle($title),
            'link' => $link,
            'image' => $image,
            'category' => $category,
            'source' => 'Terra',
            'check' => 'falso',
            'fonte' => 'terra'
        ];
    }

    private function extractSpecificSections($xpath, &$articles)
    {
        // Seções específicas do Terra
        $specificSelectors = [
            // Tabela editorial
            '//div[@id="table-editorial-table"]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Seções de assuntos
            '//div[contains(@class, "app-t360-subject-table")]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Mesa do usuário
            '//div[contains(@class, "app-t360-user-table")]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Cobertura especial
            '//div[contains(@class, "special-coverage")]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Seção de entretenimento
            '//div[@id="table-1649742"]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Seção de esportes
            '//div[@id="table-1649678"]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Seção de notícias
            '//div[@id="table-1649676"]//div[contains(@class, "card") and contains(@class, "card-has-image")]',
            
            // Seção vida e estilo
            '//div[@id="table-1649744"]//div[contains(@class, "card") and contains(@class, "card-has-image")]'
        ];

        foreach ($specificSelectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $cardNode) {
                $article = $this->extractArticleData($cardNode, $xpath);
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractStories($xpath, &$articles)
    {
        // Extrair stories específicas do carousel
        $storyNodes = $xpath->query('//a[contains(@class, "t360-stories--story")]');
        
        foreach ($storyNodes as $storyNode) {
            $link = $storyNode->getAttribute('href');
            $title = $storyNode->getAttribute('title');
            
            // Buscar imagem
            $image = '';
            $imgNode = $xpath->query('.//img[contains(@class, "t360-stories--story__image")]', $storyNode)->item(0);
            if ($imgNode) {
                $image = $imgNode->getAttribute('src');
            }

            if (!empty($title) && !empty($link) && !empty($image)) {
                $article = [
                    'title' => $this->cleanTitle($title),
                    'link' => $this->normalizeUrl($link),
                    'image' => $this->normalizeUrl($image),
                    'category' => 'Stories',
                    'source' => 'Terra'
                ];

                if (!$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function isValidArticleUrl($url)
    {
        if (empty($url)) return false;
        
        // URLs para ignorar
        $ignoredPatterns = [
            'javascript:',
            'mailto:',
            '#',
            '.jpg', '.png', '.gif', '.svg', '.jpeg',
            '/wp-content/',
            '/wp-admin/',
            'terra.com.br/categoria/',
            'terra.com.br/tag/',
            'servicos.terra.com.br'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve ser uma URL do Terra válida
        return (strpos($url, 'terra.com.br') !== false || strpos($url, '/') === 0);
    }

    private function isDuplicate($article, $articles)
    {
        foreach ($articles as $existing) {
            // Verificar duplicata por título ou link
            if ($existing['title'] === $article['title'] || 
                $existing['link'] === $article['link']) {
                return true;
            }
            
            // Verificar similaridade de título (85% similar)
            $similarity = 0;
            similar_text($existing['title'], $article['title'], $similarity);
            if ($similarity > 85) {
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
            if (empty($article['title']) || empty($article['link']) || empty($article['image'])) {
                continue;
            }

            // Limpar e validar título
            $title = $this->cleanTitle($article['title']);
            if (strlen($title) < 10 || strlen($title) > 300) {
                continue;
            }

            // Validar URLs
            $link = $this->normalizeUrl($article['link']);
            $image = $this->normalizeUrl($article['image']);
            
            if (!filter_var($link, FILTER_VALIDATE_URL) || !filter_var($image, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Verificar se a imagem não é muito pequena (provavelmente ícone)
            if (strpos($image, 'icon') !== false || 
                strpos($image, '16x') !== false || 
                strpos($image, '24x') !== false) {
                continue;
            }

            $cleaned[] = [
                'title' => $title,
                'link' => $link,
                'image' => $image,
                'category' => $article['category'] ?? 'Geral',
                'source' => $article['source'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'terra'
            ];
        }

        // Remover duplicatas finais e ordenar por relevância
        $cleaned = $this->removeFinalDuplicates($cleaned);
        
        return $cleaned;
    }

    private function removeFinalDuplicates($articles)
    {
        $unique = [];
        $seen = [];

        foreach ($articles as $article) {
            $key = md5($article['title'] . $article['link']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $article;
            }
        }

        return $unique;
    }

    private function cleanTitle($title)
    {
        // Decodificar entidades HTML
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        // Normalizar espaços
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        
        // Remover caracteres especiais desnecessários
        $title = preg_replace('/[^\p{L}\p{N}\s\-\.\,\;\:\!\?\(\)]/u', '', $title);
        
        // Remover prefixos comuns do Terra
        $prefixes = [
            'Terra:', 'TERRA:', 'Veja:', 'VEJA:', 'Assista:', 'ASSISTA:',
            'Saiba:', 'SAIBA:', 'Entenda:', 'ENTENDA:', 'Confira:', 'CONFIRA:'
        ];
        
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
        
        // Se já é uma URL completa
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        // Se começa com //
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        // Se é uma URL relativa
        if (strpos($url, '/') === 0) {
            return 'https://www.terra.com.br' . $url;
        }

        // Para URLs com parâmetros de imagem do Terra
        if (strpos($url, 'trrsf.com') !== false || strpos($url, 'terra.com') !== false) {
            return $url;
        }
        
        return $url;
    }

    // Método para buscar artigos por seção específica
    public function scrapBySection($section = null)
    {
        $validSections = [
            'noticias', 'esportes', 'diversao', 'vida-e-estilo', 
            'economia', 'tecnologia', 'planeta'
        ];

        if ($section && !in_array($section, $validSections)) {
            return response()->json(['error' => 'Seção inválida'], 400);
        }

        $url = $section ? "https://www.terra.com.br/{$section}/" : 'https://www.terra.com.br';

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar a seção'], 500);
            }

            // Usar a mesma lógica de extração da home
            return $this->index();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar seção: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para obter estatísticas do scraping
    public function getStats()
    {
        try {
            $result = $this->index();
            $data = $result->getData(true);
            
            if (!$data['success']) {
                return $result;
            }

            $articles = $data['articles'];
            
            // Agrupar por categoria
            $byCategory = [];
            foreach ($articles as $article) {
                $category = $article['category'] ?? 'Sem categoria';
                $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
            }

            return response()->json([
                'success' => true,
                'total_articles' => count($articles),
                'by_category' => $byCategory,
                'has_image' => count(array_filter($articles, fn($a) => !empty($a['image']))),
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'terra'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao gerar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}