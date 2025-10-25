<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class PortalGloboController extends Controller
{
    public function index()
    {
        try {
            // Fazer requisição para Globo.com
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1'
            ])->timeout(30)->get('https://globo.com');

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

            // Seletores baseados na estrutura típica do Globo.com
            $selectors = [
                // Artigos principais em manchetes
                '//article//a[contains(@href, "globo.com")]',
                
                // Links em divs de notícias
                '//div[contains(@class, "feed")]//a[contains(@href, "globo.com")]',
                '//div[contains(@class, "manchete")]//a[contains(@href, "globo.com")]',
                '//div[contains(@class, "destaque")]//a[contains(@href, "globo.com")]',
                
                // Seções específicas do Globo
                '//section[contains(@class, "noticias")]//a[contains(@href, "globo.com")]',
                '//div[contains(@class, "barra-edicoes")]//a[contains(@href, "globo.com")]',
                
                // Links em carrosséis e listas
                '//div[contains(@class, "carousel")]//a[contains(@href, "globo.com")]',
                '//ul[contains(@class, "lista")]//a[contains(@href, "globo.com")]',
                
                // G1 específicos (portal de notícias do Globo)
                '//a[contains(@href, "g1.globo.com")]',
                
                // GloboEsporte
                '//a[contains(@href, "globoesporte.globo.com")]',
                
                // Outros portais Globo
                '//a[contains(@href, "gshow.globo.com")]',
                '//a[contains(@href, "extra.globo.com")]',
                '//a[contains(@href, "oglobo.globo.com")]'
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
        $category = '';
        
        // Buscar título - várias estratégias específicas para Globo
        $titleElements = [
            './/h1', './/h2', './/h3', './/h4',
            './/@alt', './/@title',
            './/figcaption//h1', './/figcaption//h2', './/figcaption//h3',
            './/div[contains(@class, "titulo")]',
            './/span[contains(@class, "titulo")]',
            './/p[contains(@class, "titulo")]'
        ];

        foreach ($titleElements as $titleSelector) {
            $titleNodes = $xpath->query($titleSelector, $linkNode);
            if ($titleNodes->length > 0) {
                $title = trim($titleNodes->item(0)->textContent);
                if (!empty($title) && strlen($title) > 10) {
                    break;
                }
            }
        }

        // Se não encontrou título no link, buscar no elemento pai
        if (empty($title)) {
            $parent = $linkNode->parentNode;
            $searchDepth = 0;
            while ($parent && $searchDepth < 3) {
                $titleInParent = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[contains(@class, "titulo")]', $parent);
                if ($titleInParent->length > 0) {
                    $title = trim($titleInParent->item(0)->textContent);
                    if (!empty($title) && strlen($title) > 10) {
                        break;
                    }
                }
                $parent = $parent->parentNode;
                $searchDepth++;
            }
        }

        // Buscar imagem com estratégias específicas para Globo
        $imageElements = [
            './/img[@src]',
            './/picture//img[@src]',
            './/source[@srcset]',
            './/div[contains(@class, "foto")]//img[@src]',
            './/figure//img[@src]'
        ];

        foreach ($imageElements as $imageSelector) {
            $imageNodes = $xpath->query($imageSelector, $linkNode);
            if ($imageNodes->length > 0) {
                $imgNode = $imageNodes->item(0);
                $imageSrc = $imgNode->getAttribute('src') ?: $imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('srcset');
                if ($imageSrc && $this->isValidImageUrl($imageSrc)) {
                    $image = $this->normalizeUrl($imageSrc);
                    break;
                }
            }
        }

        // Se não encontrou imagem no link, buscar no elemento pai
        if (empty($image)) {
            $parent = $linkNode->parentNode;
            $searchDepth = 0;
            while ($parent && empty($image) && $searchDepth < 3) {
                $imageInParent = $xpath->query('.//img[@src] | .//picture//img[@src] | .//*[contains(@class, "foto")]//img[@src]', $parent);
                if ($imageInParent->length > 0) {
                    $imgSrc = $imageInParent->item(0)->getAttribute('src') ?: $imageInParent->item(0)->getAttribute('data-src');
                    if ($imgSrc && $this->isValidImageUrl($imgSrc)) {
                        $image = $this->normalizeUrl($imgSrc);
                        break;
                    }
                }
                $parent = $parent->parentNode;
                $searchDepth++;
            }
        }

        // Tentar extrair categoria da URL
        $category = $this->extractCategoryFromUrl($href);

        // Validar se tem pelo menos título e link
        if (empty($title) || empty($href)) {
            return null;
        }

        return [
            'title' => $this->cleanTitle($title),
            'link' => $this->normalizeUrl($href),
            'image' => $image ?: null,
            'category' => $category,
            'source' => $this->determineSource($href),
            'check' => 'falso',
            'fonte' => 'g1globo'
        ];
    }

    private function extractSpecificArticles($xpath, &$articles)
    {
        // Extrair de elementos com estruturas específicas do Globo
        $specificSelectors = [
            // Seção de manchetes principais
            '//div[@id="home-manchetes"]//a[contains(@href, "globo.com")]',
            
            // Feed de notícias
            '//div[contains(@class, "feed-home")]//a[contains(@href, "globo.com")]',
            
            // Destaques da home
            '//section[contains(@class, "destaques")]//a[contains(@href, "globo.com")]',
            
            // Últimas notícias
            '//div[contains(@class, "ultimas")]//a[contains(@href, "globo.com")]',
            
            // Widget de mais lidas
            '//div[contains(@class, "mais-lidas")]//a[contains(@href, "globo.com")]',
            
            // Seções temáticas
            '//section[contains(@class, "secao")]//a[contains(@href, "globo.com")]',
            
            // Cards de notícias
            '//div[contains(@class, "card-noticia")]//a[contains(@href, "globo.com")]'
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
        if (empty($url)) return false;
        
        // URLs para ignorar
        $ignoredPatterns = [
            '/wp-content/',
            '/wp-admin/',
            '/feed/',
            '.jpg', '.png', '.gif', '.svg', '.jpeg', '.webp',
            'javascript:',
            'mailto:',
            '#',
            '/categoria/',
            '/tag/',
            '/author/',
            '/busca/',
            '/search/',
            'globoplay.globo.com/configuracoes',
            'accounts.globo.com'
        ];

        foreach ($ignoredPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        // Deve conter globo.com ou ser uma URL relativa válida
        $validDomains = [
            'globo.com',
            'g1.globo.com',
            'globoesporte.globo.com',
            'gshow.globo.com',
            'extra.globo.com',
            'oglobo.globo.com'
        ];

        foreach ($validDomains as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }

        return strpos($url, '/') === 0; // URL relativa
    }

    private function isValidImageUrl($url)
    {
        if (empty($url)) return false;
        
        $validExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        $hasValidExtension = false;
        
        foreach ($validExtensions as $ext) {
            if (strpos(strtolower($url), $ext) !== false) {
                $hasValidExtension = true;
                break;
            }
        }
        
        // Também aceitar URLs que contenham padrões típicos de imagens do Globo
        $globoImagePatterns = [
            's3.glbimg.com',
            'g1.globo.com/media',
            'globoesporte.globo.com/media',
            'extra.globo.com/incoming'
        ];
        
        foreach ($globoImagePatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                $hasValidExtension = true;
                break;
            }
        }
        
        return $hasValidExtension;
    }

    private function extractCategoryFromUrl($url)
    {
        // Extrair categoria da URL baseado nos padrões do Globo
        if (strpos($url, 'g1.globo.com') !== false) {
            preg_match('/g1\.globo\.com\/([^\/]+)/', $url, $matches);
            return isset($matches[1]) ? ucfirst($matches[1]) : 'G1';
        }
        
        if (strpos($url, 'globoesporte.globo.com') !== false) {
            return 'Esporte';
        }
        
        if (strpos($url, 'gshow.globo.com') !== false) {
            return 'Entretenimento';
        }
        
        if (strpos($url, 'extra.globo.com') !== false) {
            return 'Extra';
        }
        
        if (strpos($url, 'oglobo.globo.com') !== false) {
            return 'O Globo';
        }
        
        return 'Globo';
    }

    private function determineSource($url)
    {
        if (strpos($url, 'g1.globo.com') !== false) {
            return 'G1';
        }
        
        if (strpos($url, 'globoesporte.globo.com') !== false) {
            return 'Globo Esporte';
        }
        
        if (strpos($url, 'gshow.globo.com') !== false) {
            return 'Gshow';
        }
        
        if (strpos($url, 'extra.globo.com') !== false) {
            return 'Extra';
        }
        
        if (strpos($url, 'oglobo.globo.com') !== false) {
            return 'O Globo';
        }
        
        return 'Globo.com';
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

            // Só incluir artigos que tenham imagem
            if (empty($article['image'])) {
                continue;
            }

            $cleaned[] = [
                'title' => $title,
                'link' => $link,
                'image' => $article['image'],
                'category' => $article['category'] ?? 'Geral',
                'source' => $article['source'],
                'scraped_at' => now()->toISOString(),
                'check' => 'falso',
                'fonte' => 'g1globo'
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
        
        // Remover prefixos comuns do Globo
        $prefixes = [
            'G1:', 'G1 -', 'Globo:', 'Globo -', 
            'ASSISTA:', 'VEJA:', 'LEIA:', 'SAIBA:',
            'GloboEsporte.com:', 'Gshow:',
            'Extra Online:', 'O Globo:'
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
        
        // Normalizar URLs
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        if (strpos($url, '/') === 0) {
            return 'https://globo.com' . $url;
        }

        // Para URLs de imagem, limpar parâmetros desnecessários mas manter essenciais
        if (strpos($url, '?') !== false && $this->isValidImageUrl($url)) {
            $parts = explode('?', $url);
            $base = $parts[0];
            $queryString = $parts[1];
            
            // Manter apenas parâmetros importantes para imagens
            parse_str($queryString, $params);
            $importantParams = [];
            
            foreach ($params as $key => $value) {
                if (in_array($key, ['w', 'h', 'width', 'height', 'crop', 'quality', 'format'])) {
                    $importantParams[$key] = $value;
                }
            }
            
            if (!empty($importantParams)) {
                $url = $base . '?' . http_build_query($importantParams);
            } else {
                $url = $base;
            }
        }
        
        return $url;
    }

    // Método para buscar artigos por categoria específica
    public function scrapByCategory($category = null)
    {
        $baseUrls = [
            'geral' => 'https://globo.com',
            'noticias' => 'https://g1.globo.com',
            'esporte' => 'https://globoesporte.globo.com',
            'entretenimento' => 'https://gshow.globo.com',
            'economia' => 'https://g1.globo.com/economia',
            'politica' => 'https://g1.globo.com/politica',
            'mundo' => 'https://g1.globo.com/mundo',
            'tecnologia' => 'https://g1.globo.com/tecnologia'
        ];

        $url = isset($baseUrls[$category]) ? $baseUrls[$category] : $baseUrls['geral'];

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(30)->get($url);

            if (!$response->successful()) {
                return response()->json(['error' => 'Falha ao acessar a categoria'], 500);
            }

            // Adaptar a lógica de extração para a categoria específica
            return $this->index();

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erro ao processar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    // Método para buscar apenas artigos com imagens de alta qualidade
    public function scrapHighQualityImages()
    {
        // Implementar lógica específica para filtrar apenas imagens de alta qualidade
        $articles = $this->index()->getData(true);
        
        if (isset($articles['articles'])) {
            $filteredArticles = array_filter($articles['articles'], function($article) {
                if (empty($article['image'])) {
                    return false;
                }
                
                // Verificar se a imagem tem parâmetros de qualidade
                return strpos($article['image'], 'quality') !== false || 
                       strpos($article['image'], 'w=') !== false ||
                       strpos($article['image'], 's3.glbimg.com') !== false;
            });
            
            return response()->json([
                'success' => true,
                'total' => count($filteredArticles),
                'articles' => array_values($filteredArticles)
            ]);
        }
        
        return response()->json(['error' => 'Erro ao filtrar imagens'], 500);
    }
}