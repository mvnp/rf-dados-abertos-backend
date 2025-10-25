<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalR7Controller extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para R7
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1'
            ])->timeout(30)->get('https://www.r7.com');

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar o site R7'], 500);
            }

            $html = $response->body();
            
            // Criar DOM
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $articles = [];

            // Extrair artigos das diferentes seções
            $this->extractMainNews($xpath, $articles);
            $this->extractSportsNews($xpath, $articles);
            $this->extractEntertainmentNews($xpath, $articles);
            $this->extractBrasiliaNews($xpath, $articles);
            $this->extractGeneralNews($xpath, $articles);
            $this->extractMostReadNews($xpath, $articles);

            // Remover duplicatas e validar dados
            $articles = $this->cleanAndValidateArticles($articles);

            return response()->json([
                'success' => true,
                'total' => count($articles),
                'articles' => $articles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar scraping: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    private function extractMainNews($xpath, &$articles)
    {
        // Extrair manchetes principais
        $selectors = [
            // Manchete principal
            '//div[@class="headline-photo"]//article//a[@href]',
            
            // Artigos em cards principais
            '//article[@class="card-relative card-@container"]//a[@href]',
            
            // Links em figures principais
            '//figure//a[@href and contains(@href, "r7.com")]',
            
            // Headlines com imagens
            '//div[contains(@class, "headline")]//a[@href]'
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
    }

    private function extractSportsNews($xpath, &$articles)
    {
        // Extrair notícias de esportes
        $selectors = [
            // Seção de esportes
            '//div[@data-tb-region="Esportes 1"]//a[@href]',
            '//div[@data-tb-region="Esportes 2"]//a[@href]',
            
            // Cards de esportes
            '//div[contains(@class, "sports-")]//a[@href]',
            
            // Agenda esportiva
            '//div[contains(@class, "sports-swiper")]//a[@href]'
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath, 'Esportes');
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractEntertainmentNews($xpath, &$articles)
    {
        // Extrair notícias de entretenimento
        $selectors = [
            // Seção de entretenimento
            '//div[@data-tb-region="Entretenimento"]//a[@href]',
            
            // Domingo Espetacular
            '//div[@data-tb-region="Domingo Espetacular"]//a[@href]',
            
            // Paulo, O Apóstolo
            '//div[contains(@data-tb-region, "Paulo")]//a[@href]'
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath, 'Entretenimento');
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractBrasiliaNews($xpath, &$articles)
    {
        // Extrair notícias de Brasília
        $selectors = [
            // Seção R7 Brasília
            '//div[@data-tb-region="Brasília"]//a[@href]'
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath, 'Brasília');
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractGeneralNews($xpath, &$articles)
    {
        // Extrair notícias gerais e JR 24H
        $selectors = [
            // JR 24H
            '//div[@data-tb-region="JR1"]//a[@href]',
            '//div[@data-tb-region="JR2"]//a[@href]',
            '//div[@data-tb-region="JR3"]//a[@href]',
            
            // Topo 1
            '//div[@data-tb-region="Topo 1"]//a[@href]',
            
            // Oitinho
            '//div[@data-tb-region="Oitinho"]//a[@href]',
            
            // Novezinho
            '//div[@data-tb-region="Novezinho"]//a[@href]',
            
            // Quadrão/Topinho
            '//div[@data-tb-region="QUADRÃO/TOPINHO"]//a[@href]',
            
            // Record
            '//div[@data-tb-region="Record"]//a[@href]',
            
            // Prisma
            '//div[@data-tb-region="Prisma"]//a[@href]'
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath, 'Geral');
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractMostReadNews($xpath, &$articles)
    {
        // Extrair mais lidas
        $selectors = [
            // Seção mais lidas
            '//section[contains(@class, "card-bg-neutral-high-500")]//a[@href]',
            
            // Estrelando
            '//div[@data-tb-region="Estrelando"]//a[@href]'
        ];

        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            
            foreach ($nodes as $link) {
                $article = $this->extractArticleData($link, $xpath, 'Mais Lidas');
                if ($article && !$this->isDuplicate($article, $articles)) {
                    $articles[] = $article;
                }
            }
        }
    }

    private function extractArticleData($linkNode, $xpath, $section = 'Geral')
    {
        $href = $linkNode->getAttribute('href');
        
        // Validar se é um link válido de artigo
        if (!$href || !$this->isValidArticleUrl($href)) {
            return null;
        }

        $title = '';
        $image = '';
        
        // Buscar título - várias estratégias específicas do R7
        $titleElements = [
            './/@title',
            './/@alt',
            './/h1', './/h2', './/h3', './/h4',
            './/span[contains(@class, "video-label")]',
            './/span[@data-tb-title]',
            './/div[@data-tb-title]'
        ];

        foreach ($titleElements as $titleSelector) {
            $titleNodes = $xpath->query($titleSelector, $linkNode);
            if ($titleNodes->length > 0) {
                $titleNode = $titleNodes->item(0);
                if ($titleNode->nodeType === XML_ATTRIBUTE_NODE) {
                    $title = $titleNode->value;
                } else {
                    $title = trim($titleNode->textContent);
                }
                
                if (!empty($title) && strlen($title) > 10) {
                    break;
                }
            }
        }

        // Se não encontrou título no link, buscar no container pai
        if (empty($title)) {
            $parent = $linkNode->parentNode;
            $attempts = 0;
            while ($parent && empty($title) && $attempts < 3) {
                $titleInParent = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[@data-tb-title] | .//span[contains(@class, "video-label")]', $parent);
                if ($titleInParent->length > 0) {
                    $title = trim($titleInParent->item(0)->textContent);
                    if (!empty($title) && strlen($title) > 10) {
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
            './/img[@data-tb-thumbnail]',
            './/picture//img[@src]',
            './/source[@srcset]',
            './/img[@loading="lazy"]'
        ];

        foreach ($imageElements as $imageSelector) {
            $imageNodes = $xpath->query($imageSelector, $linkNode);
            if ($imageNodes->length > 0) {
                $imgNode = $imageNodes->item(0);
                $imageSrc = $imgNode->getAttribute('src') ?: 
                           $imgNode->getAttribute('data-src') ?: 
                           $imgNode->getAttribute('srcset');
                
                if ($imageSrc && $this->isValidImageUrl($imageSrc)) {
                    $image = $this->normalizeUrl($imageSrc);
                    break;
                }
            }
        }

        // Se não encontrou imagem no link, buscar no container pai
        if (empty($image)) {
            $parent = $linkNode->parentNode;
            $attempts = 0;
            while ($parent && empty($image) && $attempts < 3) {
                $imageInParent = $xpath->query('.//img[@src] | .//picture//img[@src] | .//img[@data-tb-thumbnail]', $parent);
                if ($imageInParent->length > 0) {
                    $imgSrc = $imageInParent->item(0)->getAttribute('src') ?: 
                             $imageInParent->item(0)->getAttribute('data-src');
                    
                    if ($imgSrc && $this->isValidImageUrl($imgSrc)) {
                        $image = $this->normalizeUrl($imgSrc);
                        break;
                    }
                }
                $parent = $parent->parentNode;
                $attempts++;
            }
        }

        // Validar se tem título, link e imagem
        if (empty($title) || empty($href) || empty($image)) {
            return null;
        }

        return [
            'title' => $this->cleanTitle($title),
            'link' => $this->normalizeUrl($href),
            'image' => $image,
            'source' => 'R7',
            'section' => $section,
            'check' => 'falso',
            'fonte' => 'r7'
        ];
    }

    private function isValidArticleUrl($url)
    {
        if (empty($url)) return false;
        
        // URLs para ignorar
        $ignoredPatterns = [
            '.jpg', '.png', '.gif', '.svg', '.webp',
            'javascript:',
            'mailto:',
            '#',
            '/wp-content/',
            '/wp-admin/',
            '/feed/',
            'data:image',
            'blob:',
            'ofertas.r7.com',
            'cupons.r7.com',
            'tempo.r7.com'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve ser do R7 ou uma URL relativa válida
        return (strpos($url, 'r7.com') !== false || strpos($url, '/') === 0);
    }

    private function isValidImageUrl($url)
    {
        if (empty($url)) return false;
        
        // Verificar se é uma URL de imagem válida
        $imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'];
        
        foreach ($imageExtensions as $ext) {
            if (strpos(strtolower($url), $ext) !== false) {
                return true;
            }
        }

        // Verificar se contém parâmetros de imagem (como do CDN do R7)
        if (strpos($url, 'resizer') !== false || 
            strpos($url, 'cloudfront') !== false ||
            strpos($url, 'images') !== false) {
            return true;
        }

        return false;
    }

    private function isDuplicate($article, $articles)
    {
        foreach ($articles as $existing) {
            // Verificar duplicata por título ou link
            if ($existing['title'] === $article['title'] || 
                $existing['link'] === $article['link']) {
                return true;
            }
            
            // Verificar títulos muito similares (mais de 80% iguais)
            similar_text($existing['title'], $article['title'], $percent);
            if ($percent > 80) {
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
            if (strlen($title) < 15 || strlen($title) > 300) {
                continue;
            }

            // Validar URL
            $link = $this->normalizeUrl($article['link']);
            if (!filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Validar imagem
            $image = $this->normalizeUrl($article['image']);
            if (!filter_var($image, FILTER_VALIDATE_URL)) {
                continue;
            }

            $cleaned[] = [
                'title' => $title,
                'link' => $link,
                'image' => $image,
                'source' => $article['source'],
                'section' => $article['section'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'r7'
            ];
        }

        // Ordenar por seção e depois por ordem de aparição
        usort($cleaned, function($a, $b) {
            $sectionOrder = [
                'Geral' => 1,
                'Esportes' => 2,
                'Entretenimento' => 3,
                'Brasília' => 4,
                'Mais Lidas' => 5
            ];
            
            $orderA = $sectionOrder[$a['section']] ?? 99;
            $orderB = $sectionOrder[$b['section']] ?? 99;
            
            return $orderA <=> $orderB;
        });

        return $cleaned;
    }

    private function cleanTitle($title)
    {
        // Decodificar entidades HTML
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        // Normalizar espaços
        $title = preg_replace('/\s+/', ' ', $title);
        $title = trim($title);
        
        // Remover prefixos comuns do R7
        $prefixes = [
            'R7:', 'RECORD:', 'ASSISTA:', 'VEJA:', 'LEIA:', 
            'FOTOS:', 'VÍDEO:', 'AO VIVO:', 'EXCLUSIVO:'
        ];
        
        foreach ($prefixes as $prefix) {
            if (stripos($title, $prefix) === 0) {
                $title = trim(substr($title, strlen($prefix)));
            }
        }

        // Remover sufixos comuns
        $suffixes = [' - R7', ' | R7', ' - Record', ' | Record'];
        foreach ($suffixes as $suffix) {
            if (stripos($title, $suffix) !== false) {
                $title = str_ireplace($suffix, '', $title);
            }
        }

        return trim($title);
    }

    private function normalizeUrl($url)
    {
        if (empty($url)) return '';
        
        // URLs já completas
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        // URLs com protocolo omitido
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        // URLs relativas
        if (strpos($url, '/') === 0) {
            return 'https://www.r7.com' . $url;
        }
        
        return $url;
    }

    // Método para buscar artigos por seção específica
    public function scrapBySection($section = null)
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get('https://www.r7.com');

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar o site'], 500);
            }

            $html = $response->body();
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            $articles = [];

            // Filtrar por seção específica
            switch (strtolower($section)) {
                case 'esportes':
                    $this->extractSportsNews($xpath, $articles);
                    break;
                case 'entretenimento':
                    $this->extractEntertainmentNews($xpath, $articles);
                    break;
                case 'brasilia':
                    $this->extractBrasiliaNews($xpath, $articles);
                    break;
                default:
                    return $this->index(); // Retornar todos se seção não especificada
            }

            $articles = $this->cleanAndValidateArticles($articles);

            return response()->json([
                'success' => true,
                'section' => $section,
                'total' => count($articles),
                'articles' => $articles
            ]);

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
            $data = json_decode($result->getContent(), true);
            
            if (!$data['success']) {
                return $result;
            }

            $stats = [
                'total_articles' => $data['total'],
                'sections' => [],
                'sources' => [],
                'with_images' => 0,
                'average_title_length' => 0
            ];

            $titleLengths = [];
            
            foreach ($data['articles'] as $article) {
                // Estatísticas por seção
                $section = $article['section'];
                if (!isset($stats['sections'][$section])) {
                    $stats['sections'][$section] = 0;
                }
                $stats['sections'][$section]++;

                // Contagem de imagens
                if (!empty($article['image'])) {
                    $stats['with_images']++;
                }

                // Comprimento dos títulos
                $titleLengths[] = strlen($article['title']);
            }

            $stats['average_title_length'] = count($titleLengths) > 0 ? 
                round(array_sum($titleLengths) / count($titleLengths), 2) : 0;

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao gerar estatísticas: ' . $e->getMessage()
            ], 500);
        }
    }
}