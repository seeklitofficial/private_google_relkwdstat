<?php
namespace GBA;

class GoogleBlogAnalyzer {
    private $apiKey;
    private $searchEngineId;
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    
    public function __construct() {
        $this->loadEnv();
    }
    
    private function loadEnv() {
        // 여러 경로 시도
        $possiblePaths = [
            __DIR__ . '/../../.env',
            __DIR__ . '/../.env',
            dirname(__DIR__, 2) . '/.env',
            dirname(__DIR__) . '/.env'
        ];
        
        $envFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envFile = $path;
                break;
            }
        }
        
        if ($envFile && file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    $_ENV[$key] = $value;
                    if (function_exists('putenv')) {
                        putenv("$key=$value");
                    }
                }
            }
        }
        
        $this->apiKey = $_ENV['GOOGLE_CUSTOM_SEARCH_API_KEY'] ?? '';
        $this->searchEngineId = $_ENV['GOOGLE_CUSTOM_SEARCH_ENGINE_ID'] ?? '';
    }
    
    public function searchGoogle(string $keyword, int $count = 10): array {
        // Google Custom Search API 사용
        if (!empty($this->apiKey) && !empty($this->searchEngineId)) {
            return $this->searchWithCustomAPI($keyword, $count);
        }
        
        // API 키가 없으면 시뮬레이션 데이터 사용
        return $this->getSimulationData($keyword);
    }
    
    private function searchWithCustomAPI(string $keyword, int $count): array {
        $apiUrl = "https://www.googleapis.com/customsearch/v1";
        $params = [
            'key' => $this->apiKey,
            'cx' => $this->searchEngineId,
            'q' => $keyword, // 기본 키워드로 검색
            'num' => min($count, 10), // API 최대 10개
            'hl' => 'ko',
            'lr' => 'lang_ko'
        ];
        
        $url = $apiUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: GoogleSearchAnalyzer/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return ['error' => 'Google Custom Search API 요청 실패'];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            return ['error' => 'API 오류: ' . ($data['error']['message'] ?? '알 수 없는 오류')];
        }
        
        return $this->parseCustomSearchResults($data);
    }
    
    private function parseCustomSearchResults(array $data): array {
        $results = [];
        
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $results[] = [
                    'title' => $item['title'] ?? '',
                    'url' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'displayLink' => $item['displayLink'] ?? ''
                ];
            }
        }
        
        return ['items' => $results];
    }
    
    private function parseGoogleResults(string $html, string $keyword): array {
        $results = [];
        
        // 다양한 구글 검색 결과 패턴 시도
        $patterns = [
            // 일반적인 구글 검색 결과
            '/<div[^>]*class="[^"]*g[^"]*"[^>]*>.*?<a[^>]+href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<\/div>/s',
            // 새로운 구글 구조
            '/<div[^>]*class="[^"]*yuRUbf[^"]*"[^>]*>.*?<a[^>]+href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<\/div>/s',
            // 간단한 링크 추출
            '/<a[^>]+href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>/s',
            // 더 넓은 범위
            '/<a[^>]+href="([^"]+)"[^>]*>.*?<h3[^>]*>([^<]+)<\/h3>.*?<\/a>/s'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $url = $match[1];
                    $title = strip_tags($match[2]);
                    
                    // URL 정리
                    if (strpos($url, '/url?q=') === 0) {
                        parse_str(parse_url($url, PHP_URL_QUERY), $params);
                        $url = $params['q'] ?? $url;
                    }
                    
                    // 유효한 외부 링크만 수집
                    if (strpos($url, 'http') === 0 && 
                        strpos($url, 'google.com') === false && 
                        strpos($url, 'youtube.com') === false &&
                        strpos($url, 'wikipedia.org') === false &&
                        !empty($title) && 
                        mb_strlen($title) > 5) {
                        
                        $results[] = [
                            'title' => $title,
                            'url' => $url,
                            'snippet' => $this->extractSnippet($html, $title)
                        ];
                        
                        // 최대 10개까지만
                        if (count($results) >= 10) break 2;
                    }
                }
                if (!empty($results)) break;
            }
        }
        
        // 결과가 없으면 시뮬레이션 데이터 제공
        if (empty($results)) {
            $results = $this->getSimulationData($keyword);
        }
        
        return ['items' => $results];
    }
    
    private function getSimulationData(string $keyword): array {
        return [
            [
                'title' => $keyword . '에 대한 완벽한 가이드',
                'url' => 'https://example.com/guide',
                'snippet' => $keyword . '에 대한 상세한 정보와 활용 방법을 알아보세요.',
                'content' => $this->getSimulationContent($keyword, 'guide')
            ],
            [
                'title' => $keyword . '의 모든 것 - 전문가 분석',
                'url' => 'https://example.com/analysis',
                'snippet' => $keyword . '에 대한 전문가의 깊이 있는 분석과 인사이트를 제공합니다.',
                'content' => $this->getSimulationContent($keyword, 'analysis')
            ],
            [
                'title' => $keyword . ' 활용법과 팁',
                'url' => 'https://example.com/tips',
                'snippet' => $keyword . '을 효과적으로 활용하는 방법과 실용적인 팁을 소개합니다.',
                'content' => $this->getSimulationContent($keyword, 'tips')
            ],
            [
                'title' => $keyword . ' 비교 분석',
                'url' => 'https://example.com/compare',
                'snippet' => $keyword . '의 다양한 옵션들을 비교하고 최적의 선택을 도와드립니다.',
                'content' => $this->getSimulationContent($keyword, 'compare')
            ],
            [
                'title' => $keyword . ' 최신 동향',
                'url' => 'https://example.com/trends',
                'snippet' => $keyword . '의 최신 동향과 미래 전망에 대해 알아보세요.',
                'content' => $this->getSimulationContent($keyword, 'trends')
            ]
        ];
    }
    
    private function getSimulationContent(string $keyword, string $type): string {
        $contents = [
            'guide' => $keyword . '에 대한 완벽한 가이드입니다. ' . $keyword . '의 기본 개념부터 고급 활용법까지 모든 것을 다룹니다. ' . $keyword . '을 처음 접하는 분들도 쉽게 이해할 수 있도록 단계별로 설명합니다. ' . $keyword . '의 핵심 원리와 실제 적용 사례를 통해 실무에 바로 활용할 수 있는 지식을 제공합니다.',
            'analysis' => $keyword . '에 대한 전문가의 깊이 있는 분석입니다. ' . $keyword . '의 현재 상황과 미래 전망을 종합적으로 살펴봅니다. ' . $keyword . '이 우리 사회에 미치는 영향과 변화를 데이터와 함께 제시합니다. ' . $keyword . '의 장단점을 객관적으로 분석하여 균형 잡힌 시각을 제공합니다.',
            'tips' => $keyword . '을 효과적으로 활용하는 실용적인 팁들을 모았습니다. ' . $keyword . '을 사용할 때 주의해야 할 점들과 성공적인 활용을 위한 노하우를 공유합니다. ' . $keyword . '의 다양한 기능과 활용 방법을 실제 사례와 함께 소개합니다. ' . $keyword . '을 통해 더 나은 결과를 얻는 방법을 알려드립니다.',
            'compare' => $keyword . '의 다양한 옵션들을 상세히 비교 분석합니다. ' . $keyword . '의 각각의 특징과 장단점을 명확히 제시하여 최적의 선택을 도와드립니다. ' . $keyword . '의 가격, 성능, 사용성 등 다양한 측면에서 비교합니다. ' . $keyword . '을 선택할 때 고려해야 할 요소들을 체계적으로 정리했습니다.',
            'trends' => $keyword . '의 최신 동향과 미래 전망을 분석합니다. ' . $keyword . '이 현재 어떤 방향으로 발전하고 있는지 살펴봅니다. ' . $keyword . '의 시장 동향과 기술 발전 추세를 파악하여 앞으로의 변화를 예측합니다. ' . $keyword . '이 우리 삶에 미칠 영향과 변화를 미리 준비할 수 있도록 도와드립니다.'
        ];
        
        return $contents[$type] ?? $keyword . '에 대한 정보입니다.';
    }
    
    private function extractSnippet(string $html, string $title): string {
        // 제목 주변의 스니펫 추출
        $titlePos = strpos($html, $title);
        if ($titlePos !== false) {
            $start = max(0, $titlePos - 200);
            $snippet = substr($html, $start, 400);
            $snippet = strip_tags($snippet);
            $snippet = preg_replace('/\s+/', ' ', $snippet);
            return trim($snippet);
        }
        return '';
    }
    
    public function fetchPageContent(string $url): string {
        // 시뮬레이션 데이터인 경우 content 반환
        if (strpos($url, 'example.com') !== false) {
            $keyword = '인공지능'; // 기본값
            $type = 'guide';
            
            if (strpos($url, '/guide') !== false) $type = 'guide';
            elseif (strpos($url, '/analysis') !== false) $type = 'analysis';
            elseif (strpos($url, '/tips') !== false) $type = 'tips';
            elseif (strpos($url, '/compare') !== false) $type = 'compare';
            elseif (strpos($url, '/trends') !== false) $type = 'trends';
            
            $content = $this->getSimulationContent($keyword, $type);
            
            // HTML 형태로 반환 (이미지와 링크 포함)
            return '<html><body><div class="content">' . $content . '</div><img src="image1.jpg" alt="이미지1"><img src="image2.jpg" alt="이미지2"><a href="https://example.com/link1">링크1</a><a href="https://example.com/link2">링크2</a></body></html>';
        }
        
        // 실제 URL인 경우 cURL로 가져오기
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_MAXREDIRS => 3
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return '';
        }
        
        // 블로그 포스트 콘텐츠만 추출
        return $this->extractBlogContent($response);
    }
    
    private function extractBlogContent(string $html): string {
        // 먼저 특정 콘텐츠 영역을 찾아서 추출
        $contentSelectors = [
            // 블로그 플랫폼별
            '#<div[^>]*class="[^"]*se-main-container[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 네이버 블로그
            '#<div[^>]*class="[^"]*se_component_wrap[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 네이버 블로그
            '#<div[^>]*id="postViewArea"[^>]*>([\s\S]*?)</div>#iu', // 네이버 블로그
            '#<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 티스토리
            '#<div[^>]*class="[^"]*post-content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 티스토리
            '#<div[^>]*class="[^"]*wrap_article[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 브런치
            '#<div[^>]*class="[^"]*article_content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 브런치
            '#<div[^>]*class="[^"]*postArticle-content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 미디엄
            '#<div[^>]*class="[^"]*article-content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 미디엄
            
            // 일반적인 글 콘텐츠 영역
            '#<article[^>]*>([\s\S]*?)</article>#iu', // article 태그
            '#<div[^>]*class="[^"]*content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // content 클래스
            '#<div[^>]*class="[^"]*post[^"]*"[^>]*>([\s\S]*?)</div>#iu', // post 클래스
            '#<div[^>]*class="[^"]*entry[^"]*"[^>]*>([\s\S]*?)</div>#iu', // entry 클래스
            '#<div[^>]*class="[^"]*article[^"]*"[^>]*>([\s\S]*?)</div>#iu', // article 클래스
            '#<div[^>]*class="[^"]*text[^"]*"[^>]*>([\s\S]*?)</div>#iu', // text 클래스
            '#<div[^>]*class="[^"]*body[^"]*"[^>]*>([\s\S]*?)</div>#iu', // body 클래스
            '#<div[^>]*class="[^"]*main[^"]*"[^>]*>([\s\S]*?)</div>#iu', // main 클래스
            '#<div[^>]*class="[^"]*story[^"]*"[^>]*>([\s\S]*?)</div>#iu', // story 클래스
            '#<div[^>]*class="[^"]*description[^"]*"[^>]*>([\s\S]*?)</div>#iu', // description 클래스
            
            // 뉴스/기사 사이트
            '#<div[^>]*class="[^"]*news[^"]*"[^>]*>([\s\S]*?)</div>#iu', // news 클래스
            '#<div[^>]*class="[^"]*article[^"]*"[^>]*>([\s\S]*?)</div>#iu', // article 클래스
            '#<div[^>]*class="[^"]*story[^"]*"[^>]*>([\s\S]*?)</div>#iu', // story 클래스
            '#<div[^>]*class="[^"]*text[^"]*"[^>]*>([\s\S]*?)</div>#iu', // text 클래스
            
            // 위키/백과사전
            '#<div[^>]*class="[^"]*mw-content-text[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 위키백과
            '#<div[^>]*class="[^"]*content[^"]*"[^>]*>([\s\S]*?)</div>#iu', // 일반 content
            
            // 포럼/커뮤니티
            '#<div[^>]*class="[^"]*post[^"]*"[^>]*>([\s\S]*?)</div>#iu', // post 클래스
            '#<div[^>]*class="[^"]*message[^"]*"[^>]*>([\s\S]*?)</div>#iu', // message 클래스
            '#<div[^>]*class="[^"]*thread[^"]*"[^>]*>([\s\S]*?)</div>#iu', // thread 클래스
        ];
        
        $extractedContent = '';
        foreach ($contentSelectors as $selector) {
            if (preg_match($selector, $html, $matches)) {
                $extractedContent .= ' ' . $matches[1];
            }
        }
        
        // 콘텐츠를 찾지 못한 경우 더 넓은 범위로 검색
        if (empty($extractedContent)) {
            // body 태그 내의 모든 텍스트 추출
            if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $matches)) {
                $extractedContent = $matches[1];
            } else {
                // body 태그도 없으면 전체 HTML 사용
                $extractedContent = $html;
            }
        }
        
        return $extractedContent;
    }
    
    public function analyzeResults(string $keyword, array $results): array {
        $analyzer = new ContentAnalyzer();
        return $analyzer->analyze($keyword, $results);
    }
}

class ContentAnalyzer {
    public function analyze(string $keyword, array $results): array {
        $totalDocs = count($results);
        if ($totalDocs === 0) {
            return ['error' => '분석할 결과가 없습니다'];
        }
        
        $analyzedResults = [];
        $totalChars = 0;
        $totalImages = 0;
        $totalLinks = 0;
        $totalNumbers = 0;
        $totalExclamations = 0;
        $allTitles = [];
        $coKeywords = [];
        
        foreach ($results as $result) {
            $content = $this->fetchAndAnalyzeContent($result['url']);
            $analysis = $this->analyzeContent($keyword, $content, $result['title']);
            
            $analyzedResults[] = array_merge($result, $analysis);
            
            $totalChars += $analysis['charCount'];
            $totalImages += $analysis['imageCount'];
            $totalLinks += $analysis['linkCount'];
            $totalNumbers += $analysis['numberCount'];
            $totalExclamations += $analysis['exclamationCount'];
            $allTitles[] = $result['title'];
            
            // 공동 키워드 수집
            $this->collectCoKeywords($content, $coKeywords, $keyword);
        }
        
        // 공동 키워드를 배열 형태로 변환
        $coKeywordsArray = [];
        foreach ($coKeywords as $keyword => $count) {
            $coKeywordsArray[] = ['keyword' => $keyword, 'count' => $count];
        }
        usort($coKeywordsArray, function($a, $b) { return $b['count'] - $a['count']; });
        
        return [
            'summary' => [
                'totalDocs' => $totalDocs,
                'avgChars' => round($totalChars / $totalDocs),
                'avgImages' => round($totalImages / $totalDocs, 1),
                'avgLinks' => round($totalLinks / $totalDocs, 1),
                'avgNumbers' => round($totalNumbers / $totalDocs, 1),
                'avgExclamations' => round($totalExclamations / $totalDocs, 1)
            ],
            'titleAnalysis' => $this->analyzeTitles($allTitles),
            'coKeywords' => array_slice($coKeywordsArray, 0, 20),
            'topResults' => array_slice($analyzedResults, 0, 10)
        ];
    }
    
    private function fetchAndAnalyzeContent(string $url): string {
        $analyzer = new GoogleBlogAnalyzer();
        return $analyzer->fetchPageContent($url);
    }
    
    private function analyzeContent(string $keyword, string $content, string $title): array {
        $cleanContent = $this->extractTextFromHtml($content);
        
        return [
            'charCount' => mb_strlen($cleanContent),
            'keywordCount' => substr_count(mb_strtolower($cleanContent), mb_strtolower($keyword)),
            'keywordDensity' => $this->calculateDensity($cleanContent, $keyword),
            'imageCount' => $this->countImages($content),
            'linkCount' => $this->countLinks($content),
            'numberCount' => $this->countNumbers($cleanContent),
            'exclamationCount' => substr_count($cleanContent, '!'),
            'titleType' => $this->classifyTitle($title),
            'contentPreview' => mb_substr($cleanContent, 0, 200) . '...'
        ];
    }
    
    private function extractTextFromHtml(string $html): string {
        if (empty($html)) return '';
        
        // 스크립트와 스타일 태그 제거
        $html = preg_replace('#<script[\s\S]*?</script>#iu', ' ', $html);
        $html = preg_replace('#<style[\s\S]*?</style>#iu', ' ', $html);
        $html = preg_replace('#<noscript[\s\S]*?</noscript>#iu', ' ', $html);
        $html = preg_replace('#<nav[\s\S]*?</nav>#iu', ' ', $html);
        $html = preg_replace('#<header[\s\S]*?</header>#iu', ' ', $html);
        $html = preg_replace('#<footer[\s\S]*?</footer>#iu', ' ', $html);
        
        // 특정 콘텐츠 영역만 추출 시도
        $contentSelectors = [
            '#<main[^>]*>([\s\S]*?)</main>#iu',
            '#<article[^>]*>([\s\S]*?)</article>#iu',
            '#<section[^>]*>([\s\S]*?)</section>#iu',
            '#<div[^>]*class="[^"]*content[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*main[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*body[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*text[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*post[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*entry[^"]*"[^>]*>([\s\S]*?)</div>#iu',
            '#<div[^>]*class="[^"]*article[^"]*"[^>]*>([\s\S]*?)</div>#iu'
        ];
        
        $extractedContent = '';
        foreach ($contentSelectors as $selector) {
            if (preg_match($selector, $html, $matches)) {
                $extractedContent .= ' ' . $matches[1];
            }
        }
        
        // 콘텐츠 영역을 찾지 못한 경우 body 태그 사용
        if (empty($extractedContent)) {
            if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $matches)) {
                $extractedContent = $matches[1];
            } else {
                // body 태그도 없으면 전체 HTML 사용
                $extractedContent = $html;
            }
        }
        
        // HTML 태그 제거
        $text = strip_tags($extractedContent);
        // HTML 엔티티 디코딩
        $text = html_entity_decode($text, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        // 제어 문자 제거
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
        // 연속 공백 정리
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function calculateDensity(string $content, string $keyword): float {
        $totalWords = str_word_count($content);
        $keywordCount = substr_count(mb_strtolower($content), mb_strtolower($keyword));
        return $totalWords > 0 ? round(($keywordCount / $totalWords) * 100, 2) : 0;
    }
    
    private function countImages(string $html): int {
        return preg_match_all('/<img[^>]*>/i', $html);
    }
    
    private function countLinks(string $html): int {
        return preg_match_all('/<a[^>]*href[^>]*>/i', $html);
    }
    
    private function countNumbers(string $content): int {
        return preg_match_all('/\d+/', $content);
    }
    
    private function classifyTitle(string $title): string {
        $title = mb_strtolower($title);
        
        // 방법/가이드형
        if (strpos($title, '방법') !== false || strpos($title, '하는법') !== false || 
            strpos($title, '가이드') !== false || strpos($title, '팁') !== false) return '방법형';
        
        // 후기/리뷰형
        if (strpos($title, '후기') !== false || strpos($title, '리뷰') !== false || 
            strpos($title, '경험') !== false || strpos($title, '사용') !== false) return '후기형';
        
        // 비교형
        if (strpos($title, '비교') !== false || strpos($title, 'vs') !== false || 
            strpos($title, '차이') !== false || strpos($title, '대비') !== false) return '비교형';
        
        // 추천형
        if (strpos($title, '추천') !== false || strpos($title, '베스트') !== false || 
            strpos($title, '인기') !== false || strpos($title, '좋은') !== false) return '추천형';
        
        // 가격형
        if (strpos($title, '가격') !== false || strpos($title, '비용') !== false || 
            strpos($title, '요금') !== false || strpos($title, '돈') !== false) return '가격형';
        
        // 질문형
        if (strpos($title, '?') !== false || strpos($title, '어떻게') !== false || 
            strpos($title, '무엇') !== false || strpos($title, '왜') !== false) return '질문형';
        
        // 서비스형
        if (strpos($title, '서비스') !== false || strpos($title, '사이트') !== false || 
            strpos($title, '앱') !== false || strpos($title, '프로그램') !== false) return '서비스형';
        
        // 이벤트형
        if (strpos($title, '이벤트') !== false || strpos($title, '행사') !== false || 
            strpos($title, '캘린더') !== false || strpos($title, '일정') !== false) return '이벤트형';
        
        // 숫자형
        if (preg_match('/\d+/', $title)) return '숫자형';
        
        return '일반형';
    }
    
    private function analyzeTitles(array $titles): array {
        $patterns = [];
        foreach ($titles as $title) {
            $type = $this->classifyTitle($title);
            $patterns[$type] = ($patterns[$type] ?? 0) + 1;
        }
        arsort($patterns);
        return $patterns;
    }
    
    private function collectCoKeywords(string $content, array &$coKeywords, string $mainKeyword): void {
        // 실제 콘텐츠 영역만 추출 (제목, 본문, 섹션 등)
        $contentSelectors = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', // 제목들
            'p', 'div', 'span', 'article', 'section', 'main', // 본문 영역
            'li', 'td', 'th', // 리스트, 테이블
            'blockquote', 'cite', 'em', 'strong', 'b', 'i' // 강조 텍스트
        ];
        
        $cleanContent = '';
        foreach ($contentSelectors as $selector) {
            // 각 선택자에 해당하는 내용 추출
            if (preg_match_all('/<' . $selector . '[^>]*>([^<]*)<\/' . $selector . '>/i', $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $cleanContent .= ' ' . $match;
                }
            }
        }
        
        // HTML 엔티티 디코딩
        $cleanContent = html_entity_decode($cleanContent, ENT_QUOTES, 'UTF-8');
        $cleanContent = preg_replace('/\s+/', ' ', $cleanContent);
        
        $words = preg_split('/\s+/', mb_strtolower($cleanContent));
        
        // 실제 콘텐츠에 맞는 불용어 리스트
        $stopWords = [
            // 기본 불용어
            '그리고', '그런데', '하지만', '그러나', '또한', '또는', '그래서', '따라서', '그러므로', 
            '그런', '이런', '저런', '그', '이', '저', '것', '수', '있', '하', '되', '되다', '하다', 
            '있다', '없다', '이다', '아니다', '입니다', '합니다', '됩니다',
            // 일반적인 웹 용어
            'http', 'https', 'www', 'com', 'org', 'net', 'kr', 'co',
            // 숫자와 특수문자
            'nbsp', 'amp', 'lt', 'gt', 'quot', 'apos', 'copy', 'reg', 'trade',
            // HTML 엔티티
            'u0026', 'u003c', 'u003e', 'u0022', 'u0027', 'u0020', 'u00a0'
        ];
        
        foreach ($words as $word) {
            $word = trim($word);
            
            // 필터링 조건 (실제 콘텐츠에 맞게 간소화)
            if (mb_strlen($word) > 1 && 
                !in_array($word, $stopWords) && 
                $word !== mb_strtolower($mainKeyword) &&
                !preg_match('/^[0-9]+$/', $word) && // 순수 숫자 제외
                !preg_match('/^[^가-힣a-zA-Z]+$/', $word) && // 한글/영문이 포함된 것만
                mb_strlen($word) <= 20 // 너무 긴 단어 제외
            ) {
                $coKeywords[$word] = ($coKeywords[$word] ?? 0) + 1;
            }
        }
    }
}
