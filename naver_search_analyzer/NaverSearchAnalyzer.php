<?php
class NaverSearchAnalyzer {
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    private $mobileUserAgent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15A372 Safari/604.1';
    
    public function analyzeSearchResults($keyword, $mode = 'pc') {
        $searchUrl = $this->buildSearchUrl($keyword, $mode);
        $ua = ($mode === 'mobile' || $mode === 'mo') ? $this->mobileUserAgent : $this->userAgent;
        $html = $this->fetchSearchPage($searchUrl, $ua);
        
        if (!$html) {
            return ['error' => '검색 결과를 가져올 수 없습니다.'];
        }
        
        $sections = $this->parseSearchSections($html);
        
        return [
            'keyword' => $keyword,
            'search_url' => $searchUrl,
            'sections' => $sections,
            'total_sections' => count($sections),
            'analysis_time' => date('Y-m-d H:i:s')
        ];
    }
    
    private function buildSearchUrl($keyword, $mode = 'pc') {
        $encodedKeyword = urlencode($keyword);
        if ($mode === 'mobile' || $mode === 'mo') {
            return "https://m.search.naver.com/search.naver?where=m&sm=mtp_hty.top&ie=utf8&query={$encodedKeyword}";
        }
        return "https://search.naver.com/search.naver?where=nexearch&sm=top_hty&fbm=0&ie=utf8&query={$encodedKeyword}";
    }
    
    private function fetchSearchPage($url, $userAgent = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent ?: $this->userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]);
        
        // gzip 압축 자동 해제
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("cURL Error: " . $error);
            return false;
        }
        
        if ($httpCode !== 200 || !$html) {
            error_log("HTTP Error: " . $httpCode);
            return false;
        }
        
        // 디버깅을 위한 HTML 저장 (개발용)
        if (defined('DEBUG') && DEBUG) {
            file_put_contents(__DIR__ . '/debug_search_result.html', $html);
        }
        
        return $html;
    }
    
    private function parseSearchSections($html) {
        $sections = [];
        
        // DOMDocument를 사용하여 HTML 파싱
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        // DOM 순서 기반 섹션 감지 (실제 검색 결과 순서와 일치)
        $this->detectSectionsInDOMOrder($dom, $xpath, $sections);
        
        // 섹션 타입 정규화 (unknown/info를 의미있는 대 섹션으로 매핑)
        $this->normalizeSections($sections);
        
        return $sections;
    }

    // 섹션 리스트의 타입을 제목 기반으로 정규화
    private function normalizeSections(&$sections) {
        foreach ($sections as &$section) {
            $rawType = isset($section['type']) ? (string)$section['type'] : '';
            $title = isset($section['title']) ? (string)$section['title'] : '';
            $section['type'] = $this->normalizeType($rawType, $title);
        }
    }

    // unknown/info 타입을 제목 키워드로 의미있는 대 섹션으로 변환
    private function normalizeType($type, $title) {
        $t = strtolower(trim($type ?? ''));
        $ttl = trim($title ?? '');
        
        // 이미 의미 있는 타입이면 그대로 반환
        $meaningful = [
            'powerlink','web','news','blog','cafe','kin','image','video','shopping','dictionary','related','tool'
        ];
        if (in_array($t, $meaningful, true)) {
            return $t;
        }
        
        // 제목 기반 키워드 매핑 (대소문자/한글 포함 비포괄적 검색)
        $has = function($needle) use ($ttl) {
            if ($ttl === '') return false;
            if (function_exists('mb_stripos')) {
                return mb_stripos($ttl, $needle) !== false;
            }
            return stripos($ttl, $needle) !== false;
        };
        
        if ($has('어학사전') || $has('사전')) return 'dictionary';
        if ($has('웹문서') || $has('웹 검색 결과') || $has('검색 결과')) return 'web';
        if ($has('뉴스') || $has('언론사') || $has('연합뉴스') || $has('뉴스1') || $has('뉴시스')) return 'news';
        if ($has('블로그')) return 'blog';
        if ($has('카페')) return 'cafe';
        if ($has('지식iN') || $has('지식인')) return 'kin';
        if ($has('이미지') || $has('사진')) return 'image';
        if ($has('동영상') || $has('영상') || $has('YouTube') || $has('유튜브')) return 'video';
        if ($has('쇼핑') || $has('가격비교')) return 'shopping';
        if ($has('함께 많이 찾는') || $has('연관 검색어') || $has('함께보면 좋은')) return 'related';
        if ($has('도구') || $has('툴')) return 'tool';
        if ($has('기본정보')) return 'info';
        
        // 기본값
        return $t ?: 'info';
    }
    
    // DOM 순서 기반 섹션 감지 (실제 검색 결과 순서와 일치)
    private function detectSectionsInDOMOrder($dom, $xpath, &$sections) {
        $order = 1;
        
        // 1. 파워링크/광고 섹션 우선 감지 (실제 네이버 첫 페이지 순서)
        $this->detectPowerLinksFirst($dom, $xpath, $sections, $order);
        
        // 2. 웹 검색 결과 섹션 감지
        $this->detectWebResults($dom, $xpath, $sections, $order);
        
        // 3. api_subject_bx 선택자를 사용한 섹션 감지 (네이버의 실제 섹션 구조)
        $apiSubjectElements = $xpath->query('//div[contains(@class, "api_subject_bx")]');
        
        foreach ($apiSubjectElements as $element) {
            $sectionInfo = $this->analyzeApiSubjectSection($element, $order);
            if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                $sections[] = $sectionInfo;
                $order++;
            }
        }
        
        // 4. 추가 섹션 감지 (api_subject_bx로 감지되지 않은 섹션들)
        $this->detectAdditionalSectionsInDOM($dom, $xpath, $sections, $order);
        
        // 5. api_subject_bx가 없는 경우 기존 방식으로 폴백
        if (empty($sections)) {
            $this->detectSectionsFallback($dom, $xpath, $sections, $order);
        }
    }
    
    // 파워링크/광고 섹션 우선 감지 (실제 네이버 첫 페이지 순서)
    private function detectPowerLinksFirst($dom, $xpath, &$sections, &$order) {
        $powerLinkSelectors = [
            '//div[contains(@class, "power_link")]',
            '//div[contains(@class, "ad_area")]',
            '//div[contains(@class, "sponsor")]',
            '//div[contains(@class, "advertisement")]',
            '//div[contains(@class, "ad")]',
            '//div[contains(@class, "promotion")]',
            '//div[contains(@class, "sponsored")]',
            '//div[contains(@id, "power_link")]',
            '//div[contains(@id, "ad_area")]',
            '//div[contains(@id, "sponsor")]'
        ];
        
        foreach ($powerLinkSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzePowerLinkSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 웹 검색 결과 섹션 감지
    private function detectWebResults($dom, $xpath, &$sections, &$order) {
        $webResultSelectors = [
            '//div[contains(@class, "web")]',
            '//div[contains(@class, "total_wrap")]',
            '//div[contains(@class, "total_area")]',
            '//div[contains(@class, "web_result")]',
            '//div[contains(@class, "search_result")]'
        ];
        
        foreach ($webResultSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeWebResultSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 파워링크 섹션 분석
    private function analyzePowerLinkSection($element, $order) {
        $title = $this->extractPowerLinkTitle($element);
        $itemCount = $this->countPowerLinkItems($element);
        $feeds = $this->extractPowerLinkFeeds($element);
        $url = $this->extractPowerLinkUrl($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'powerlink',
                'title' => $title,
                'item_count' => $itemCount,
                'url' => $url,
                'feeds' => $feeds,
                'description' => '네이버 파워링크 광고 섹션',
                'seo_insight' => $this->getSEOInsight('powerlink', $order),
                'priority' => $this->getSectionPriority('powerlink', $order)
            ];
        }
        
        return null;
    }
    
    // 파워링크 URL 추출
    private function extractPowerLinkUrl($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        // 실제 외부 사이트 링크 우선 찾기
        $linkSelectors = [
            './/a[contains(@href, "http") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "https://") and not(contains(@href, "search.naver.com"))]',
            './/div[contains(@class, "title")]//a[@href]',
            './/h3//a[@href]',
            './/h4//a[@href]',
            './/a[@href]'
        ];
        
        foreach ($linkSelectors as $selector) {
            $links = $xpath->query($selector, $element);
            if ($links->length > 0) {
                $url = $links->item(0)->getAttribute('href');
                if (!empty($url) && $url !== '#' && $url !== 'javascript:void(0)') {
                    // 절대 URL인 경우 그대로 사용
                    if (strpos($url, 'http') === 0) {
                        return $url;
                    } else {
                        // 상대 URL을 절대 URL로 변환
                        if (strpos($url, '/') === 0) {
                            return 'https://search.naver.com' . $url;
                        } else {
                            return 'https://' . $url;
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    // 파워링크 제목 추출
    private function extractPowerLinkTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '파워링크';
    }
    
    // 파워링크 아이템 수 계산
    private function countPowerLinkItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 파워링크 피드 추출
    private function extractPowerLinkFeeds($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        $feeds = [];
        
        // 파워링크 피드 아이템 선택자들
        $feedSelectors = [
            './/li[contains(@class, "power_link")]',
            './/div[contains(@class, "power_link")]',
            './/a[contains(@class, "power_link")]',
            './/div[contains(@class, "ad_item")]',
            './/li[contains(@class, "ad_item")]'
        ];
        
        foreach ($feedSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            foreach ($items as $item) {
                $feed = $this->extractFeedFromItem($item);
                if ($feed && !$this->feedExists($feeds, $feed)) {
                    $feeds[] = $feed;
                }
            }
        }
        
        return array_slice($feeds, 0, 10);
    }
    
    // 웹 검색 결과 섹션 분석
    private function analyzeWebResultSection($element, $order) {
        $title = $this->extractWebResultTitle($element);
        $itemCount = $this->countWebResultItems($element);
        $url = $this->extractWebResultUrl($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'web',
                'title' => $title,
                'item_count' => $itemCount,
                'url' => $url,
                'description' => '네이버 웹 검색 결과 섹션',
                'seo_insight' => $this->getSEOInsight('web', $order),
                'priority' => $this->getSectionPriority('web', $order)
            ];
        }
        
        return null;
    }
    
    // 웹 검색 결과 URL 추출
    private function extractWebResultUrl($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        // 실제 외부 사이트 링크 우선 찾기
        $linkSelectors = [
            './/a[contains(@href, "http") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "https://") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "blog.naver.com")]',
            './/a[contains(@href, "cafe.naver.com")]',
            './/a[contains(@href, "news.naver.com")]',
            './/a[contains(@href, "kin.naver.com")]',
            './/h3//a[@href]',
            './/h4//a[@href]',
            './/div[contains(@class, "title")]//a[@href]',
            './/a[@href]'
        ];
        
        foreach ($linkSelectors as $selector) {
            $links = $xpath->query($selector, $element);
            if ($links->length > 0) {
                $url = $links->item(0)->getAttribute('href');
                if (!empty($url) && $url !== '#' && $url !== 'javascript:void(0)') {
                    // 절대 URL인 경우 그대로 사용
                    if (strpos($url, 'http') === 0) {
                        return $url;
                    } else {
                        // 상대 URL을 절대 URL로 변환
                        if (strpos($url, '/') === 0) {
                            return 'https://search.naver.com' . $url;
                        } else {
                            return 'https://' . $url;
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    // 웹 검색 결과 제목 추출
    private function extractWebResultTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '웹 검색 결과';
    }
    
    // 웹 검색 결과 아이템 수 계산
    private function countWebResultItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 추가 섹션 감지 (api_subject_bx로 감지되지 않은 섹션들)
    private function detectAdditionalSectionsInDOM($dom, $xpath, &$sections, &$order) {
        // 네이버 특별 섹션 감지 (상자로 묶여 있는 섹션들)
        $this->detectNaverSpecialSections($dom, $xpath, $sections, $order);
        
        // 네이버 주요 섹션들 감지 (실제 네이버 검색 결과의 마지막 섹션들)
        $this->detectNaverMainSections($dom, $xpath, $sections, $order);
    }
    
    // 네이버 주요 섹션들 감지 (실제 네이버 검색 결과의 마지막 섹션들)
    private function detectNaverMainSections($dom, $xpath, &$sections, &$order) {
        // 뉴스 섹션 감지
        $newsSelectors = [
            '//div[contains(@class, "news")]',
            '//div[contains(@id, "news")]',
            '//div[contains(@class, "뉴스")]'
        ];
        
        foreach ($newsSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeNewsSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 블로그 섹션 감지
        $blogSelectors = [
            '//div[contains(@class, "blog")]',
            '//div[contains(@id, "blog")]',
            '//div[contains(@class, "블로그")]'
        ];
        
        foreach ($blogSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeBlogSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 카페 섹션 감지
        $cafeSelectors = [
            '//div[contains(@class, "cafe")]',
            '//div[contains(@id, "cafe")]',
            '//div[contains(@class, "카페")]'
        ];
        
        foreach ($cafeSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeCafeSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 지식iN 섹션 감지
        $kinSelectors = [
            '//div[contains(@class, "kin")]',
            '//div[contains(@id, "kin")]',
            '//div[contains(@class, "지식")]'
        ];
        
        foreach ($kinSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeKinSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 쇼핑 섹션 감지
        $shoppingSelectors = [
            '//div[contains(@class, "shopping")]',
            '//div[contains(@id, "shopping")]',
            '//div[contains(@class, "쇼핑")]'
        ];
        
        foreach ($shoppingSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeShoppingSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 이미지 섹션 감지
        $imageSelectors = [
            '//div[contains(@class, "image")]',
            '//div[contains(@id, "image")]',
            '//div[contains(@class, "이미지")]'
        ];
        
        foreach ($imageSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeImageSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 동영상 섹션 감지
        $videoSelectors = [
            '//div[contains(@class, "video")]',
            '//div[contains(@id, "video")]',
            '//div[contains(@class, "동영상")]'
        ];
        
        foreach ($videoSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeVideoSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
        
        // 함께 많이 찾는 섹션 감지
        $relatedSelectors = [
            '//div[contains(@class, "related")]',
            '//div[contains(@class, "함께")]',
            '//div[contains(@class, "추천")]'
        ];
        
        foreach ($relatedSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeRelatedSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 뉴스 섹션 분석
    private function analyzeNewsSection($element, $order) {
        $title = $this->extractNewsTitle($element);
        $itemCount = $this->countNewsItems($element);
        $url = $this->extractNewsUrl($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'news',
                'title' => $title,
                'item_count' => $itemCount,
                'url' => $url,
                'description' => '네이버 뉴스 섹션',
                'seo_insight' => $this->getSEOInsight('news', $order),
                'priority' => $this->getSectionPriority('news', $order)
            ];
        }
        
        return null;
    }
    
    // 뉴스 URL 추출
    private function extractNewsUrl($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        // 실제 외부 사이트 링크 우선 찾기
        $linkSelectors = [
            './/a[contains(@href, "http") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "https://") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "news.naver.com")]',
            './/div[contains(@class, "title")]//a[@href]',
            './/h3//a[@href]',
            './/h4//a[@href]',
            './/a[@href]'
        ];
        
        foreach ($linkSelectors as $selector) {
            $links = $xpath->query($selector, $element);
            if ($links->length > 0) {
                $url = $links->item(0)->getAttribute('href');
                if (!empty($url) && $url !== '#' && $url !== 'javascript:void(0)') {
                    // 절대 URL인 경우 그대로 사용
                    if (strpos($url, 'http') === 0) {
                        return $url;
                    } else {
                        // 상대 URL을 절대 URL로 변환
                        if (strpos($url, '/') === 0) {
                            return 'https://search.naver.com' . $url;
                        } else {
                            return 'https://' . $url;
                        }
                    }
                }
            }
        }
        
        return null;
    }
    
    // 뉴스 제목 추출
    private function extractNewsTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '뉴스';
    }
    
    // 뉴스 아이템 수 계산
    private function countNewsItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 지식iN 섹션 분석
    private function analyzeKinSection($element, $order) {
        $title = $this->extractKinTitle($element);
        $itemCount = $this->countKinItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'kin',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 지식iN 섹션',
                'seo_insight' => $this->getSEOInsight('kin', $order),
                'priority' => $this->getSectionPriority('kin', $order)
            ];
        }
        
        return null;
    }
    
    // 지식iN 제목 추출
    private function extractKinTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '지식iN';
    }
    
    // 지식iN 아이템 수 계산
    private function countKinItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 쇼핑 섹션 분석
    private function analyzeShoppingSection($element, $order) {
        $title = $this->extractShoppingTitle($element);
        $itemCount = $this->countShoppingItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'shopping',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 쇼핑 섹션',
                'seo_insight' => $this->getSEOInsight('shopping', $order),
                'priority' => $this->getSectionPriority('shopping', $order)
            ];
        }
        
        return null;
    }
    
    // 쇼핑 제목 추출
    private function extractShoppingTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '쇼핑';
    }
    
    // 쇼핑 아이템 수 계산
    private function countShoppingItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 이미지 섹션 분석
    private function analyzeImageSection($element, $order) {
        $title = $this->extractImageTitle($element);
        $itemCount = $this->countImageItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'image',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 이미지 섹션',
                'seo_insight' => $this->getSEOInsight('image', $order),
                'priority' => $this->getSectionPriority('image', $order)
            ];
        }
        
        return null;
    }
    
    // 이미지 제목 추출
    private function extractImageTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '이미지';
    }
    
    // 이미지 아이템 수 계산
    private function countImageItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 함께 많이 찾는 섹션 분석
    private function analyzeRelatedSection($element, $order) {
        $title = $this->extractRelatedTitle($element);
        $itemCount = $this->countRelatedItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'related',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 함께 많이 찾는 섹션',
                'seo_insight' => $this->getSEOInsight('related', $order),
                'priority' => $this->getSectionPriority('related', $order)
            ];
        }
        
        return null;
    }
    
    // 함께 많이 찾는 제목 추출
    private function extractRelatedTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '함께 많이 찾는';
    }
    
    // 함께 많이 찾는 아이템 수 계산
    private function countRelatedItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 네이버 특별 섹션 감지 (실제 네이버 검색 결과에 맞는 섹션들)
    private function detectNaverSpecialSections($dom, $xpath, &$sections, &$order) {
        // 실제 네이버 검색 결과의 주요 섹션들만 감지
        $mainSectionSelectors = [
            // 네이버 서비스 섹션 (통합)
            '//div[contains(@class, "naver_service") or contains(@class, "service")]',
            
            // 네이버 증권 섹션
            '//div[contains(@class, "stock") or contains(@class, "finance") or contains(@class, "증권")]',
            
            // 네이버 뉴스 섹션
            '//div[contains(@class, "news") or contains(@class, "뉴스")]',
            
            // 네이버 정보 섹션 (통합)
            '//div[contains(@class, "info") or contains(@class, "summary") or contains(@class, "정보")]',
            
            // 네이버 서비스 링크 섹션
            '//div[contains(@class, "service_link") or contains(@class, "service_list")]',
            
            // 네이버 위젯 섹션
            '//div[contains(@class, "widget") or contains(@class, "위젯")]',
            
            // 네이버 카드 섹션
            '//div[contains(@class, "card") or contains(@class, "카드")]',
            
            // 네이버 도구 섹션
            '//div[contains(@class, "tool") or contains(@class, "utility") or contains(@class, "도구")]'
        ];
        
        foreach ($mainSectionSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeNaverMainSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 네이버 주요 섹션 분석 (실제 검색 결과에 맞는 섹션들)
    private function analyzeNaverMainSection($element, $order) {
        $title = $this->extractNaverMainTitle($element);
        $itemCount = $this->countNaverMainItems($element);
        $sectionType = $this->determineNaverMainType($element, $title);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getNaverMainDescription($sectionType, $itemCount),
                'seo_insight' => $this->getSEOInsight($sectionType, $order),
                'priority' => $this->getSectionPriority($sectionType, $order)
            ];
        }
        
        return null;
    }
    
    // 네이버 주요 섹션 제목 추출
    private function extractNaverMainTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h1',
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/div[contains(@class, "tit")]',
            './/div[contains(@class, "head")]',
            './/span[contains(@class, "title")]',
            './/a[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        // 클래스명에서 제목 추출
        $class = $element->getAttribute('class');
        if (strpos($class, 'service') !== false) return '네이버 서비스';
        if (strpos($class, 'stock') !== false || strpos($class, 'finance') !== false) return '증권정보';
        if (strpos($class, 'news') !== false) return '뉴스';
        if (strpos($class, 'info') !== false) return '기본정보';
        if (strpos($class, 'widget') !== false) return '네이버 위젯';
        if (strpos($class, 'card') !== false) return '네이버 카드';
        if (strpos($class, 'tool') !== false) return '네이버 도구';
        
        return '네이버 섹션';
    }
    
    // 네이버 주요 섹션 아이템 수 계산
    private function countNaverMainItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "card")]',
            './/a[contains(@class, "link")]',
            './/div[contains(@class, "list")]//div'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 네이버 주요 섹션 타입 결정
    private function determineNaverMainType($element, $title) {
        $class = strtolower($element->getAttribute('class'));
        $title = strtolower($title);
        
        if (strpos($class, 'service') !== false || strpos($title, '서비스') !== false) {
            return 'service';
        }
        if (strpos($class, 'stock') !== false || strpos($class, 'finance') !== false || strpos($title, '증권') !== false) {
            return 'stock';
        }
        if (strpos($class, 'news') !== false || strpos($title, '뉴스') !== false) {
            return 'news';
        }
        if (strpos($class, 'info') !== false || strpos($title, '정보') !== false) {
            return 'info';
        }
        if (strpos($class, 'widget') !== false || strpos($title, '위젯') !== false) {
            return 'widget';
        }
        if (strpos($class, 'card') !== false || strpos($title, '카드') !== false) {
            return 'card';
        }
        if (strpos($class, 'tool') !== false || strpos($title, '도구') !== false) {
            return 'tool';
        }
        
        return 'naver_main';
    }
    
    // 네이버 주요 섹션 설명 생성
    private function getNaverMainDescription($sectionType, $itemCount) {
        $descriptions = [
            'service' => '네이버 서비스 섹션',
            'stock' => '네이버 증권정보 섹션',
            'news' => '네이버 뉴스 섹션',
            'info' => '네이버 정보 섹션',
            'widget' => '네이버 위젯 섹션',
            'card' => '네이버 카드 섹션',
            'tool' => '네이버 도구 섹션'
        ];
        
        $baseDescription = $descriptions[$sectionType] ?? '네이버 주요 섹션';
        return $baseDescription . " ({$itemCount}개 항목)";
    }
    
    // 네이버 특별 섹션 분석
    private function analyzeNaverSpecialSection($element, $order) {
        $title = $this->extractNaverSpecialTitle($element);
        $itemCount = $this->countNaverSpecialItems($element);
        $sectionType = $this->determineNaverSpecialType($element, $title);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getNaverSpecialDescription($sectionType, $itemCount),
                'seo_insight' => $this->getSEOInsight($sectionType, $order),
                'priority' => $this->getSectionPriority($sectionType, $order)
            ];
        }
        
        return null;
    }
    
    // 네이버 특별 섹션 제목 추출
    private function extractNaverSpecialTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h1',
            './/h2',
            './/h3',
            './/h4',
            './/div[contains(@class, "title")]',
            './/div[contains(@class, "tit")]',
            './/div[contains(@class, "head")]',
            './/div[contains(@class, "header")]',
            './/span[contains(@class, "title")]',
            './/span[contains(@class, "tit")]',
            './/a[contains(@class, "title")]',
            './/a[contains(@class, "tit")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        // 클래스명에서 제목 추출
        $class = $element->getAttribute('class');
        if (strpos($class, 'service') !== false) return '네이버 서비스';
        if (strpos($class, 'tool') !== false) return '네이버 도구';
        if (strpos($class, 'info') !== false) return '네이버 정보';
        if (strpos($class, 'widget') !== false) return '네이버 위젯';
        if (strpos($class, 'card') !== false) return '네이버 카드';
        if (strpos($class, 'panel') !== false) return '네이버 패널';
        if (strpos($class, 'container') !== false) return '네이버 컨테이너';
        if (strpos($class, 'box') !== false) return '네이버 박스';
        if (strpos($class, 'group') !== false) return '네이버 그룹';
        if (strpos($class, 'section') !== false) return '네이버 섹션';
        if (strpos($class, 'area') !== false) return '네이버 영역';
        if (strpos($class, 'wrap') !== false) return '네이버 래퍼';
        if (strpos($class, 'block') !== false) return '네이버 블록';
        if (strpos($class, 'module') !== false) return '네이버 모듈';
        if (strpos($class, 'component') !== false) return '네이버 컴포넌트';
        if (strpos($class, 'unit') !== false) return '네이버 유닛';
        if (strpos($class, 'item') !== false) return '네이버 아이템';
        if (strpos($class, 'element') !== false) return '네이버 엘리먼트';
        
        return '네이버 섹션';
    }
    
    // 네이버 특별 섹션 아이템 수 계산
    private function countNaverSpecialItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "card")]',
            './/div[contains(@class, "box")]',
            './/div[contains(@class, "panel")]',
            './/div[contains(@class, "widget")]',
            './/a[contains(@class, "link")]',
            './/a[contains(@class, "item")]',
            './/span[contains(@class, "item")]',
            './/div[contains(@class, "list")]//div',
            './/div[contains(@class, "menu")]//li',
            './/div[contains(@class, "nav")]//li'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 네이버 특별 섹션 타입 결정
    private function determineNaverSpecialType($element, $title) {
        $class = strtolower($element->getAttribute('class'));
        $title = strtolower($title);
        
        if (strpos($class, 'service') !== false || strpos($title, '서비스') !== false) {
            return 'service';
        }
        if (strpos($class, 'tool') !== false || strpos($title, '도구') !== false) {
            return 'tool';
        }
        if (strpos($class, 'info') !== false || strpos($title, '정보') !== false) {
            return 'info';
        }
        if (strpos($class, 'widget') !== false || strpos($title, '위젯') !== false) {
            return 'widget';
        }
        if (strpos($class, 'card') !== false || strpos($title, '카드') !== false) {
            return 'card';
        }
        if (strpos($class, 'panel') !== false || strpos($title, '패널') !== false) {
            return 'panel';
        }
        if (strpos($class, 'container') !== false || strpos($title, '컨테이너') !== false) {
            return 'container';
        }
        if (strpos($class, 'box') !== false || strpos($title, '박스') !== false) {
            return 'box';
        }
        if (strpos($class, 'group') !== false || strpos($title, '그룹') !== false) {
            return 'group';
        }
        if (strpos($class, 'section') !== false || strpos($title, '섹션') !== false) {
            return 'section';
        }
        if (strpos($class, 'area') !== false || strpos($title, '영역') !== false) {
            return 'area';
        }
        if (strpos($class, 'wrap') !== false || strpos($title, '래퍼') !== false) {
            return 'wrap';
        }
        if (strpos($class, 'block') !== false || strpos($title, '블록') !== false) {
            return 'block';
        }
        if (strpos($class, 'module') !== false || strpos($title, '모듈') !== false) {
            return 'module';
        }
        if (strpos($class, 'component') !== false || strpos($title, '컴포넌트') !== false) {
            return 'component';
        }
        if (strpos($class, 'unit') !== false || strpos($title, '유닛') !== false) {
            return 'unit';
        }
        if (strpos($class, 'item') !== false || strpos($title, '아이템') !== false) {
            return 'item';
        }
        if (strpos($class, 'element') !== false || strpos($title, '엘리먼트') !== false) {
            return 'element';
        }
        
        return 'naver_special';
    }
    
    // 네이버 특별 섹션 설명 생성
    private function getNaverSpecialDescription($sectionType, $itemCount) {
        $descriptions = [
            'service' => '네이버 서비스 섹션',
            'tool' => '네이버 도구 섹션',
            'info' => '네이버 정보 섹션',
            'widget' => '네이버 위젯 섹션',
            'card' => '네이버 카드 섹션',
            'panel' => '네이버 패널 섹션',
            'container' => '네이버 컨테이너 섹션',
            'box' => '네이버 박스 섹션',
            'group' => '네이버 그룹 섹션',
            'section' => '네이버 섹션',
            'area' => '네이버 영역 섹션',
            'wrap' => '네이버 래퍼 섹션',
            'block' => '네이버 블록 섹션',
            'module' => '네이버 모듈 섹션',
            'component' => '네이버 컴포넌트 섹션',
            'unit' => '네이버 유닛 섹션',
            'item' => '네이버 아이템 섹션',
            'element' => '네이버 엘리먼트 섹션'
        ];
        
        $baseDescription = $descriptions[$sectionType] ?? '네이버 특별 섹션';
        return $baseDescription . " ({$itemCount}개 항목)";
    }
    
    // 동영상 섹션 분석
    private function analyzeVideoSection($element, $order) {
        $title = $this->extractVideoTitle($element);
        $itemCount = $this->countVideoItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'video',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 동영상 섹션',
                'seo_insight' => $this->getSEOInsight('video', $order),
                'priority' => $this->getSectionPriority('video', $order)
            ];
        }
        
        return null;
    }
    
    // 동영상 제목 추출
    private function extractVideoTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]',
            './/div[contains(@class, "video")]//h2',
            './/div[contains(@class, "video")]//h3'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '동영상';
    }
    
    // 동영상 아이템 수 계산
    private function countVideoItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "video")]//li',
            './/div[contains(@class, "video")]//div[contains(@class, "item")]',
            './/a[contains(@class, "link")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 블로그 섹션 분석
    private function analyzeBlogSection($element, $order) {
        $title = $this->extractBlogTitle($element);
        $itemCount = $this->countBlogItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'blog',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 블로그 섹션',
                'seo_insight' => $this->getSEOInsight('blog', $order),
                'priority' => $this->getSectionPriority('blog', $order)
            ];
        }
        
        return null;
    }
    
    // 블로그 제목 추출
    private function extractBlogTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '블로그';
    }
    
    // 블로그 아이템 수 계산
    private function countBlogItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "blog")]//li',
            './/div[contains(@class, "blog")]//div[contains(@class, "item")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 카페 섹션 분석
    private function analyzeCafeSection($element, $order) {
        $title = $this->extractCafeTitle($element);
        $itemCount = $this->countCafeItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => 'cafe',
                'title' => $title,
                'item_count' => $itemCount,
                'description' => '네이버 카페 섹션',
                'seo_insight' => $this->getSEOInsight('cafe', $order),
                'priority' => $this->getSectionPriority('cafe', $order)
            ];
        }
        
        return null;
    }
    
    // 카페 제목 추출
    private function extractCafeTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $titlePatterns = [
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        return '카페';
    }
    
    // 카페 아이템 수 계산
    private function countCafeItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "cafe")]//li',
            './/div[contains(@class, "cafe")]//div[contains(@class, "item")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // api_subject_bx 피드 추출
    private function extractApiSubjectFeeds($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        $feeds = [];
        
        // 더 포괄적인 피드 아이템 선택자들
        $feedSelectors = [
            './/li[contains(@class, "api_item")]',
            './/div[contains(@class, "api_item")]',
            './/div[contains(@class, "item")]',
            './/a[contains(@class, "link")]',
            './/div[contains(@class, "list")]//div[contains(@class, "item")]',
            './/ul//li',
            './/div[contains(@class, "list")]//li',
            './/div[contains(@class, "api")]//li',
            './/div[contains(@class, "api")]//div[contains(@class, "item")]',
            './/a[@href]',
            './/li',
            './/div[contains(@class, "link")]',
            './/span[contains(@class, "link")]'
        ];
        
        foreach ($feedSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            foreach ($items as $item) {
                $feed = $this->extractFeedFromItem($item);
                if ($feed && !$this->feedExists($feeds, $feed)) {
                    $feeds[] = $feed;
                }
            }
        }
        
        return array_slice($feeds, 0, 10); // 최대 10개 피드만 반환
    }
    
    // 개별 피드 아이템에서 데이터 추출
    private function extractFeedFromItem($item) {
        $xpath = new DOMXPath($item->ownerDocument);
        
        // 제목 추출 - 더 포괄적인 선택자
        $titleSelectors = [
            './/a[contains(@class, "title")]',
            './/span[contains(@class, "title")]',
            './/div[contains(@class, "title")]',
            './/h3',
            './/h4',
            './/a',
            './/span[contains(@class, "link")]',
            './/div[contains(@class, "link")]',
            './/span[contains(@class, "text")]',
            './/div[contains(@class, "text")]'
        ];
        
        $title = '';
        foreach ($titleSelectors as $selector) {
            $titleElements = $xpath->query($selector, $item);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 200 && strlen($title) > 2) {
                    break;
                }
            }
        }
        
        // 제목이 없으면 전체 텍스트에서 추출
        if (empty($title)) {
            $title = trim($item->textContent);
            if (strlen($title) > 200) {
                $title = substr($title, 0, 200) . '...';
            }
        }
        
        // 제목 정리 (너무 긴 텍스트 정리)
        $title = trim($title);
        if (strlen($title) > 100) {
            $title = substr($title, 0, 100) . '...';
        }
        
        // 제목이 여전히 없으면 스킵
        if (empty($title) || strlen($title) < 3) {
            return null;
        }
        
        // URL 추출 - 네이버 특화 링크 찾기
        $url = '';
        
        // 1. 실제 외부 사이트 링크 우선 찾기
        $linkSelectors = [
            './/a[contains(@href, "http") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "https://") and not(contains(@href, "search.naver.com"))]',
            './/a[contains(@href, "blog.naver.com")]',
            './/a[contains(@href, "cafe.naver.com")]',
            './/a[contains(@href, "news.naver.com")]',
            './/a[contains(@href, "kin.naver.com")]',
            './/a[@href and not(@href="#") and not(@href="javascript:void(0)") and not(contains(@href, "search.naver.com"))]',
            './/a[@href and not(@href="#") and not(@href="javascript:void(0)")]'
        ];
        
        foreach ($linkSelectors as $selector) {
            $linkElements = $xpath->query($selector, $item);
            if ($linkElements->length > 0) {
                $href = $linkElements->item(0)->getAttribute('href');
                if (!empty($href) && $href !== '#' && $href !== 'javascript:void(0)') {
                    // 절대 URL인 경우 그대로 사용
                    if (strpos($href, 'http') === 0) {
                        $url = $href;
                    } else {
                        // 상대 URL인 경우 절대 URL로 변환
                        if (strpos($href, '/') === 0) {
                            $url = 'https://search.naver.com' . $href;
                        } else {
                            $url = 'https://' . $href;
                        }
                    }
                    break;
                }
            }
        }
        
        // 2. 네이버 내부 링크인 경우 처리
        if (empty($url)) {
            // 네이버 블로그/카페 링크 생성
            $title = trim($item->textContent);
            if (!empty($title)) {
                $encodedTitle = urlencode($title);
                $url = "https://search.naver.com/search.naver?where=web&sm=tab_jum&query=" . $encodedTitle;
            }
        }
        
        // 3. URL이 여전히 없으면 스킵
        if (empty($url)) {
            return null;
        }
        
        // 설명 추출
        $description = '';
        $descSelectors = [
            './/div[contains(@class, "desc")]',
            './/span[contains(@class, "desc")]',
            './/p[contains(@class, "desc")]',
            './/div[contains(@class, "summary")]'
        ];
        
        foreach ($descSelectors as $selector) {
            $descElements = $xpath->query($selector, $item);
            if ($descElements->length > 0) {
                $description = trim($descElements->item(0)->textContent);
                if (!empty($description) && strlen($description) < 300) {
                    break;
                }
            }
        }
        
        // 날짜 추출
        $publishDate = '';
        $dateSelectors = [
            './/span[contains(@class, "date")]',
            './/div[contains(@class, "date")]',
            './/time'
        ];
        
        foreach ($dateSelectors as $selector) {
            $dateElements = $xpath->query($selector, $item);
            if ($dateElements->length > 0) {
                $publishDate = trim($dateElements->item(0)->textContent);
                if (!empty($publishDate)) {
                    break;
                }
            }
        }
        
        // 작성자 추출
        $author = '';
        $authorSelectors = [
            './/span[contains(@class, "author")]',
            './/div[contains(@class, "author")]',
            './/span[contains(@class, "writer")]'
        ];
        
        foreach ($authorSelectors as $selector) {
            $authorElements = $xpath->query($selector, $item);
            if ($authorElements->length > 0) {
                $author = trim($authorElements->item(0)->textContent);
                if (!empty($author)) {
                    break;
                }
            }
        }
        
        // 썸네일 추출
        $thumbnail = '';
        $imgElements = $xpath->query('.//img[@src]', $item);
        if ($imgElements->length > 0) {
            $thumbnail = $imgElements->item(0)->getAttribute('src');
            if (!empty($thumbnail) && !str_starts_with($thumbnail, 'http')) {
                $thumbnail = 'https://search.naver.com' . $thumbnail;
            }
        }
        
        if (!empty($title)) {
            return [
                'title' => $title,
                'url' => $url ?: '#',
                'description' => $description ?: null,
                'publish_date' => $publishDate ?: null,
                'author' => $author ?: null,
                'thumbnail' => $thumbnail ?: null
            ];
        }
        
        return null;
    }
    
    // 피드 중복 확인
    private function feedExists($feeds, $newFeed) {
        foreach ($feeds as $feed) {
            if ($feed['title'] === $newFeed['title'] && $feed['url'] === $newFeed['url']) {
                return true;
            }
        }
        return false;
    }
    
    // api_subject_bx 섹션 분석
    private function analyzeApiSubjectSection($element, $order) {
        $title = $this->extractApiSubjectTitle($element);
        $itemCount = $this->countApiSubjectItems($element);
        $sectionType = $this->determineApiSubjectType($element, $title);
        $feeds = $this->extractApiSubjectFeeds($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'feeds' => $feeds,
                'description' => $this->getApiSubjectDescription($sectionType, $itemCount),
                'seo_insight' => $this->getSEOInsight($sectionType, $order),
                'priority' => $this->getSectionPriority($sectionType, $order)
            ];
        }
        
        return null;
    }
    
    // api_subject_bx 제목 추출
    private function extractApiSubjectTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        // api_subject_bx 내부의 제목 패턴들
        $titlePatterns = [
            './/h2[contains(@class, "api_subject_txt")]',
            './/h3[contains(@class, "api_subject_txt")]',
            './/div[contains(@class, "api_subject_txt")]',
            './/span[contains(@class, "api_subject_txt")]',
            './/h2',
            './/h3',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) {
                    return $title;
                }
            }
        }
        
        // 클래스명에서 제목 추출
        $class = $element->getAttribute('class');
        if (strpos($class, 'news') !== false) return '뉴스';
        if (strpos($class, 'blog') !== false) return '블로그';
        if (strpos($class, 'cafe') !== false) return '카페';
        if (strpos($class, 'kin') !== false) return '지식iN';
        if (strpos($class, 'shopping') !== false) return '쇼핑';
        if (strpos($class, 'image') !== false) return '이미지';
        if (strpos($class, 'video') !== false) return '동영상';
        if (strpos($class, 'book') !== false) return '도서';
        if (strpos($class, 'dict') !== false) return '사전';
        if (strpos($class, 'power') !== false || strpos($class, 'ad') !== false) return '파워링크';
        
        return '검색 결과';
    }
    
    // api_subject_bx 아이템 수 계산
    private function countApiSubjectItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "card")]',
            './/a[contains(@class, "link")]',
            './/div[contains(@class, "list")]//div',
            './/div[contains(@class, "api_item")]',
            './/div[contains(@class, "api_list")]//div'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // api_subject_bx 타입 결정
    private function determineApiSubjectType($element, $title) {
        $class = strtolower($element->getAttribute('class'));
        $title = strtolower($title);
        
        if (strpos($class, 'power') !== false || strpos($class, 'ad') !== false || strpos($title, '파워링크') !== false) {
            return 'powerlink';
        }
        if (strpos($class, 'news') !== false || strpos($title, '뉴스') !== false) {
            return 'news';
        }
        if (strpos($class, 'blog') !== false || strpos($title, '블로그') !== false) {
            return 'blog';
        }
        if (strpos($class, 'cafe') !== false || strpos($title, '카페') !== false) {
            return 'cafe';
        }
        if (strpos($class, 'kin') !== false || strpos($title, '지식') !== false) {
            return 'kin';
        }
        if (strpos($class, 'shopping') !== false || strpos($title, '쇼핑') !== false) {
            return 'shopping';
        }
        if (strpos($class, 'image') !== false || strpos($title, '이미지') !== false) {
            return 'image';
        }
        if (strpos($class, 'video') !== false || strpos($title, '동영상') !== false) {
            return 'video';
        }
        if (strpos($class, 'book') !== false || strpos($title, '도서') !== false) {
            return 'book';
        }
        if (strpos($class, 'dict') !== false || strpos($title, '사전') !== false) {
            return 'dictionary';
        }
        
        return 'unknown';
    }
    
    // api_subject_bx 설명 생성
    private function getApiSubjectDescription($sectionType, $itemCount) {
        $descriptions = [
            'powerlink' => '네이버 파워링크 광고 섹션',
            'news' => '네이버 뉴스 섹션',
            'blog' => '네이버 블로그 섹션',
            'cafe' => '네이버 카페 섹션',
            'kin' => '네이버 지식iN 섹션',
            'shopping' => '네이버 쇼핑 섹션',
            'image' => '네이버 이미지 섹션',
            'video' => '네이버 동영상 섹션',
            'book' => '네이버 도서 섹션',
            'dictionary' => '네이버 사전 섹션'
        ];
        
        $baseDescription = $descriptions[$sectionType] ?? '네이버 검색 섹션';
        return $baseDescription . " ({$itemCount}개 항목)";
    }
    
    // 폴백 섹션 감지 (기존 방식)
    private function detectSectionsFallback($dom, $xpath, &$sections, &$order) {
        // 기존 방식으로 섹션 감지
        $meaningfulSelectors = [
            '//div[contains(@class, "power") or contains(@class, "ad") or contains(@id, "power")]',
            '//div[contains(@class, "news") or contains(@id, "news")]',
            '//div[contains(@class, "blog") or contains(@id, "blog")]',
            '//div[contains(@class, "cafe") or contains(@id, "cafe")]',
            '//div[contains(@class, "kin") or contains(@id, "kin")]',
            '//div[contains(@class, "shopping") or contains(@id, "shopping")]',
            '//div[contains(@class, "image") or contains(@id, "image")]',
            '//div[contains(@class, "video") or contains(@id, "video")]',
            '//div[contains(@class, "book") or contains(@id, "book")]',
            '//div[contains(@class, "dict") or contains(@id, "dict")]',
            '//div[contains(@class, "related") or contains(@class, "함께")]'
        ];
        
        foreach ($meaningfulSelectors as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeSectionInDOMOrder($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title']) && 
                    !$this->isUIElement($sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // DOM 순서 기반 섹션 분석
    private function analyzeSectionInDOMOrder($element, $order, $predefinedType = null) {
        $title = $this->extractSectionTitleFromElement($element);
        $itemCount = $this->countItemsInElement($element);
        
        // 미리 정의된 타입이 있으면 사용, 없으면 자동 감지
        $sectionType = $predefinedType ?: $this->determineSectionType($element, $title);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getElementDescription($element),
                'seo_insight' => $this->getSEOInsight($sectionType, $order),
                'priority' => $this->getSectionPriority($sectionType, $order)
            ];
        }
        
        return null;
    }
    
    private function extractSectionInfo($element, $sectionType, $order) {
        $title = $this->getSectionTitle($element, $sectionType);
        $itemCount = $this->countSectionItems($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getSectionDescription($element)
            ];
        }
        
        return null;
    }
    
    private function getSectionTitle($element, $sectionType) {
        $titles = [
            'web' => '웹',
            'news' => '뉴스',
            'blog' => '블로그',
            'cafe' => '카페',
            'image' => '이미지',
            'video' => '동영상',
            'shopping' => '쇼핑',
            'encyclopedia' => '백과사전',
            'knowledge' => '지식iN',
            'book' => '도서',
            'movie' => '영화',
            'music' => '음악',
            'local' => '지역정보',
            'real_estate' => '부동산',
            'stock' => '주식',
            'weather' => '날씨',
            'dictionary' => '사전',
            'translate' => '번역',
            'calculator' => '계산기',
            'unit_converter' => '단위변환'
        ];
        
        return $titles[$sectionType] ?? ucfirst($sectionType);
    }
    
    private function countSectionItems($element) {
        // XPath를 사용하여 더 정확한 아이템 수 계산
        $xpath = new DOMXPath($element->ownerDocument);
        
        // 각 섹션별 특화된 셀렉터 사용
        $itemSelectors = [
            '//div[contains(@class, "total_tit")]',
            '//div[contains(@class, "news_tit")]',
            '//div[contains(@class, "blog_tit")]',
            '//div[contains(@class, "cafe_tit")]',
            '//div[contains(@class, "image_item")]',
            '//div[contains(@class, "video_item")]',
            '//div[contains(@class, "shopping_item")]',
            '//div[contains(@class, "encyclopedia_item")]',
            '//div[contains(@class, "knowledge_item")]',
            '//div[contains(@class, "book_item")]',
            '//div[contains(@class, "movie_item")]',
            '//div[contains(@class, "music_item")]',
            '//div[contains(@class, "local_item")]',
            '//div[contains(@class, "real_estate_item")]',
            '//div[contains(@class, "stock_item")]',
            '//div[contains(@class, "weather_item")]',
            '//div[contains(@class, "dictionary_item")]',
            '//div[contains(@class, "translate_item")]',
            '//div[contains(@class, "calculator_item")]',
            '//div[contains(@class, "unit_converter_item")]'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count); // 최소 1개는 있다고 가정
    }
    
    private function getSectionDescription($element) {
        // 섹션의 설명이나 추가 정보 추출
        $descElements = $element->getElementsByTagName('p');
        if ($descElements->length > 0) {
            return trim($descElements->item(0)->textContent);
        }
        return '-';
    }
    
    private function detectAdditionalSections($html, &$sections, &$order) {
        // 특별한 섹션들 감지
        $specialSections = [
            'featured_snippet' => '특성 스니펫',
            'people_also_ask' => '사용자들이 묻는 질문',
            'related_searches' => '관련 검색어',
            'advertisement' => '광고'
        ];
        
        foreach ($specialSections as $pattern => $title) {
            if (strpos($html, $pattern) !== false || 
                preg_match('/' . str_replace('_', '.*', $pattern) . '/i', $html)) {
                $sections[] = [
                    'order' => $order,
                    'type' => $pattern,
                    'title' => $title,
                    'item_count' => 1,
                    'description' => '특별 섹션'
                ];
                $order++;
            }
        }
    }
    
    
    private function detectNaverSections($html, &$sections, &$order) {
        // 실제 네이버 검색 결과에서 섹션 감지
        $naverSections = [
            '함께 많이 찾는' => ['함께 많이 찾는', '함께 많이 찾은', 'related searches', 'title":"함께 많이 찾는'],
            '키워드 뜻' => ['키워드 뜻', '키워드 의미', 'keyword meaning', 'content":"키워드 뜻'],
            '네이버 키워드' => ['네이버 키워드', 'naver keyword', 'content":"네이버 키워드'],
            '블로그' => ['blog.naver.com', '네이버 블로그', 'fds-article-simple-box'],
            '카페' => ['cafe.naver.com', '네이버 카페'],
            '지식iN' => ['kin.naver.com', '지식iN', '지식인', 'in.naver.com'],
            '뉴스' => ['news.naver.com', '네이버 뉴스'],
            '쇼핑' => ['shopping.naver.com', '네이버 쇼핑'],
            '이미지' => ['image.naver.com', '네이버 이미지'],
            '동영상' => ['video.naver.com', '네이버 동영상'],
            '백과사전' => ['100.naver.com', '네이버 백과사전'],
            '도서' => ['book.naver.com', '네이버 도서'],
            '영화' => ['movie.naver.com', '네이버 영화'],
            '음악' => ['music.naver.com', '네이버 음악'],
            '지역정보' => ['local.naver.com', '네이버 지역정보'],
            '부동산' => ['land.naver.com', '네이버 부동산'],
            '주식' => ['finance.naver.com', '네이버 주식'],
            '날씨' => ['weather.naver.com', '네이버 날씨'],
            '사전' => ['dict.naver.com', '네이버 사전'],
            '번역' => ['translate.naver.com', '네이버 번역'],
            '계산기' => ['calculator.naver.com', '네이버 계산기'],
            '단위변환' => ['unit.naver.com', '네이버 단위변환']
        ];
        
        foreach ($naverSections as $sectionName => $patterns) {
            $found = false;
            $itemCount = 0;
            
            foreach ($patterns as $pattern) {
                if (stripos($html, $pattern) !== false) {
                    $found = true;
                    $itemCount++;
                }
            }
            
            if ($found) {
                // 이미 존재하는 섹션인지 확인
                $exists = false;
                foreach ($sections as $existingSection) {
                    if ($existingSection['title'] === $sectionName) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $sections[] = [
                        'order' => $order,
                        'type' => strtolower(str_replace([' ', 'iN', 'i'], ['_', 'in', 'i'], $sectionName)),
                        'title' => $sectionName,
                        'item_count' => max(1, $itemCount),
                        'description' => '실제 네이버 섹션'
                    ];
                    $order++;
                }
            }
        }
    }
    
    private function extractSectionTitles($html, &$sections, &$order) {
        // 실제 네이버 검색 결과에서 섹션 제목을 직접 추출
        $sectionTitles = [
            '함께 많이 찾는',
            '키워드 뜻', 
            '네이버 키워드',
            '블로그',
            '카페',
            '지식iN',
            '뉴스',
            '쇼핑',
            '이미지',
            '동영상',
            '백과사전',
            '도서',
            '영화',
            '음악',
            '지역정보',
            '부동산',
            '주식',
            '날씨',
            '사전',
            '번역',
            '계산기',
            '단위변환'
        ];
        
        foreach ($sectionTitles as $title) {
            if (stripos($html, $title) !== false) {
                // 이미 존재하는 섹션인지 확인
                $exists = false;
                foreach ($sections as $existingSection) {
                    if ($existingSection['title'] === $title) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $sections[] = [
                        'order' => $order,
                        'type' => strtolower(str_replace([' ', 'iN', 'i'], ['_', 'in', 'i'], $title)),
                        'title' => $title,
                        'item_count' => 1,
                        'description' => '실제 네이버 섹션'
                    ];
                    $order++;
                }
            }
        }
    }
    
    // 파워링크/광고 섹션 감지 (1순위)
    private function detectPowerLinks($html, &$sections, &$order) {
        $powerLinkPatterns = [
            '//div[contains(@class, "power_link")]',
            '//div[contains(@class, "ad_area")]',
            '//div[contains(@class, "sponsor")]',
            '//div[contains(@class, "advertisement")]',
            '//div[contains(@class, "ad")]',
            '//div[contains(@class, "promotion")]',
            '//div[contains(@class, "sponsored")]',
            '//div[contains(@id, "power_link")]',
            '//div[contains(@id, "ad_area")]',
            '//div[contains(@id, "sponsor")]'
        ];
        
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        
        foreach ($powerLinkPatterns as $pattern) {
            $elements = $xpath->query($pattern);
            if ($elements->length > 0) {
                $sections[] = [
                    'order' => $order,
                    'type' => 'powerlink',
                    'title' => '파워링크',
                    'item_count' => $elements->length,
                    'description' => '네이버 파워링크 광고 섹션'
                ];
                $order++;
                break; // 첫 번째 파워링크만 감지
            }
        }
    }
    
    // 스마트 섹션 감지 - 사용자에게 유용한 섹션만 선별
    private function detectDynamicSections($dom, $xpath, &$sections, &$order) {
        // 1단계: 메인 섹션만 우선 감지
        $mainSectionPatterns = [
            // 파워링크 (광고)
            '//div[contains(@class, "power") or contains(@class, "ad") or contains(@id, "power")]',
            // 뉴스
            '//div[contains(@class, "news") or contains(@id, "news")]',
            // 블로그
            '//div[contains(@class, "blog") or contains(@id, "blog")]',
            // 카페
            '//div[contains(@class, "cafe") or contains(@id, "cafe")]',
            // 지식iN
            '//div[contains(@class, "kin") or contains(@id, "kin")]',
            // 쇼핑
            '//div[contains(@class, "shopping") or contains(@id, "shopping")]',
            // 이미지
            '//div[contains(@class, "image") or contains(@id, "image")]',
            // 동영상
            '//div[contains(@class, "video") or contains(@id, "video")]',
            // 도서
            '//div[contains(@class, "book") or contains(@id, "book")]',
            // 사전
            '//div[contains(@class, "dict") or contains(@id, "dict")]',
            // 함께 많이 찾는
            '//div[contains(@class, "related") or contains(@class, "함께")]'
        ];
        
        foreach ($mainSectionPatterns as $pattern) {
            $elements = $xpath->query($pattern);
            foreach ($elements as $element) {
                $sectionInfo = $this->analyzeMainSection($element, $order);
                if ($sectionInfo && !$this->sectionExists($sections, $sectionInfo['title'])) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 메인 섹션 분석 (사용자에게 유용한 정보 제공)
    private function analyzeMainSection($element, $order) {
        $title = $this->extractMainSectionTitle($element);
        $itemCount = $this->countMainSectionItems($element);
        $sectionType = $this->determineMainSectionType($element, $title);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getMainSectionDescription($sectionType, $itemCount),
                'seo_insight' => $this->getSEOInsight($sectionType, $order),
                'priority' => $this->getSectionPriority($sectionType, $order)
            ];
        }
        
        return null;
    }
    
    // 메인 섹션 제목 추출
    private function extractMainSectionTitle($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        // 네이버 섹션별 제목 패턴
        $titlePatterns = [
            './/h2[contains(@class, "title")]',
            './/h3[contains(@class, "title")]',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]',
            './/h2',
            './/h3'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $titleElements = $xpath->query($pattern, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 50) {
                    return $title;
                }
            }
        }
        
        // 클래스명에서 제목 추출
        $class = $element->getAttribute('class');
        if (strpos($class, 'news') !== false) return '뉴스';
        if (strpos($class, 'blog') !== false) return '블로그';
        if (strpos($class, 'cafe') !== false) return '카페';
        if (strpos($class, 'kin') !== false) return '지식iN';
        if (strpos($class, 'shopping') !== false) return '쇼핑';
        if (strpos($class, 'image') !== false) return '이미지';
        if (strpos($class, 'video') !== false) return '동영상';
        if (strpos($class, 'book') !== false) return '도서';
        if (strpos($class, 'dict') !== false) return '사전';
        if (strpos($class, 'power') !== false || strpos($class, 'ad') !== false) return '파워링크';
        
        return '검색 결과';
    }
    
    // 메인 섹션 아이템 수 계산
    private function countMainSectionItems($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "card")]',
            './/a[contains(@class, "link")]',
            './/div[contains(@class, "list")]//div'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 메인 섹션 타입 결정
    private function determineMainSectionType($element, $title) {
        $class = strtolower($element->getAttribute('class'));
        $title = strtolower($title);
        
        if (strpos($class, 'power') !== false || strpos($class, 'ad') !== false || strpos($title, '파워링크') !== false) {
            return 'powerlink';
        }
        if (strpos($class, 'news') !== false || strpos($title, '뉴스') !== false) {
            return 'news';
        }
        if (strpos($class, 'blog') !== false || strpos($title, '블로그') !== false) {
            return 'blog';
        }
        if (strpos($class, 'cafe') !== false || strpos($title, '카페') !== false) {
            return 'cafe';
        }
        if (strpos($class, 'kin') !== false || strpos($title, '지식') !== false) {
            return 'kin';
        }
        if (strpos($class, 'shopping') !== false || strpos($title, '쇼핑') !== false) {
            return 'shopping';
        }
        if (strpos($class, 'image') !== false || strpos($title, '이미지') !== false) {
            return 'image';
        }
        if (strpos($class, 'video') !== false || strpos($title, '동영상') !== false) {
            return 'video';
        }
        if (strpos($class, 'book') !== false || strpos($title, '도서') !== false) {
            return 'book';
        }
        if (strpos($class, 'dict') !== false || strpos($title, '사전') !== false) {
            return 'dictionary';
        }
        
        return 'unknown';
    }
    
    // 메인 섹션 설명 생성
    private function getMainSectionDescription($sectionType, $itemCount) {
        $descriptions = [
            'powerlink' => '네이버 파워링크 광고 섹션',
            'news' => '네이버 뉴스 섹션',
            'blog' => '네이버 블로그 섹션',
            'cafe' => '네이버 카페 섹션',
            'kin' => '네이버 지식iN 섹션',
            'shopping' => '네이버 쇼핑 섹션',
            'image' => '네이버 이미지 섹션',
            'video' => '네이버 동영상 섹션',
            'book' => '네이버 도서 섹션',
            'dictionary' => '네이버 사전 섹션'
        ];
        
        $baseDescription = $descriptions[$sectionType] ?? '네이버 검색 섹션';
        return $baseDescription . " ({$itemCount}개 항목)";
    }
    
    // SEO 인사이트 제공
    private function getSEOInsight($sectionType, $order) {
        $insights = [
            'powerlink' => [
                'high' => '광고가 1순위로 노출되어 경쟁이 치열합니다.',
                'medium' => '광고 섹션이 상위에 노출되어 있습니다.',
                'low' => '광고 섹션이 노출되어 있습니다.'
            ],
            'news' => [
                'high' => '뉴스 섹션이 상위에 노출되어 신뢰도가 높습니다.',
                'medium' => '뉴스 섹션이 노출되어 있습니다.',
                'low' => '뉴스 섹션이 하위에 있습니다.'
            ],
            'blog' => [
                'high' => '블로그 섹션이 상위에 노출되어 콘텐츠 마케팅이 효과적입니다.',
                'medium' => '블로그 섹션이 노출되어 있습니다.',
                'low' => '블로그 섹션이 하위에 있습니다.'
            ]
        ];
        
        $priority = $this->getSectionPriority($sectionType, $order);
        return $insights[$sectionType][$priority] ?? '해당 섹션이 노출되어 있습니다.';
    }
    
    // 섹션 우선순위 계산
    private function getSectionPriority($sectionType, $order) {
        if ($order <= 3) return 'high';
        if ($order <= 6) return 'medium';
        return 'low';
    }
    
    // 섹션 요소 분석 (계층 구조 고려)
    private function analyzeSectionElement($element, $order) {
        $title = $this->extractSectionTitleFromElement($element);
        $itemCount = $this->countItemsInElement($element);
        $sectionType = $this->determineSectionType($element, $title);
        
        // 계층 구조 분석
        $hierarchy = $this->analyzeHierarchy($element);
        
        if ($title && $itemCount > 0) {
            return [
                'order' => $order,
                'type' => $sectionType,
                'title' => $title,
                'item_count' => $itemCount,
                'description' => $this->getElementDescription($element),
                'hierarchy' => $hierarchy,
                'is_main_section' => $this->isMainSection($element, $title, $itemCount),
                'parent_section' => $this->getParentSection($element)
            ];
        }
        
        return null;
    }
    
    // 요소에서 섹션 제목 추출
    private function extractSectionTitleFromElement($element) {
        // 다양한 제목 패턴 시도
        $titleSelectors = [
            './/h2',
            './/h3',
            './/h4',
            './/div[contains(@class, "title")]',
            './/div[contains(@class, "tit")]',
            './/div[contains(@class, "head")]',
            './/div[contains(@class, "header")]',
            './/span[contains(@class, "title")]',
            './/span[contains(@class, "tit")]'
        ];
        
        $xpath = new DOMXPath($element->ownerDocument);
        
        foreach ($titleSelectors as $selector) {
            $titleElements = $xpath->query($selector, $element);
            if ($titleElements->length > 0) {
                $title = trim($titleElements->item(0)->textContent);
                if (!empty($title) && strlen($title) < 100) { // 너무 긴 텍스트는 제외
                    return $title;
                }
            }
        }
        
        // 클래스명에서 제목 추출
        $class = $element->getAttribute('class');
        if ($class) {
            $classParts = explode(' ', $class);
            foreach ($classParts as $part) {
                if (strpos($part, '_area') !== false || strpos($part, '_wrap') !== false) {
                    $title = str_replace(['_area', '_wrap', '_section'], '', $part);
                    $title = str_replace('_', ' ', $title);
                    return ucfirst($title);
                }
            }
        }
        
        return null;
    }
    
    // 요소 내 아이템 수 계산
    private function countItemsInElement($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        
        $itemSelectors = [
            './/li',
            './/div[contains(@class, "item")]',
            './/div[contains(@class, "card")]',
            './/div[contains(@class, "box")]',
            './/a[contains(@class, "link")]',
            './/div[contains(@class, "list")]//div'
        ];
        
        $count = 0;
        foreach ($itemSelectors as $selector) {
            $items = $xpath->query($selector, $element);
            $count += $items->length;
        }
        
        return max(1, $count);
    }
    
    // 섹션 타입 결정
    private function determineSectionType($element, $title) {
        $class = strtolower($element->getAttribute('class'));
        $title = strtolower($title);
        
        // 클래스 기반 타입 결정
        if (strpos($class, 'news') !== false) return 'news';
        if (strpos($class, 'blog') !== false) return 'blog';
        if (strpos($class, 'cafe') !== false) return 'cafe';
        if (strpos($class, 'image') !== false) return 'image';
        if (strpos($class, 'video') !== false) return 'video';
        if (strpos($class, 'shopping') !== false) return 'shopping';
        if (strpos($class, 'encyclopedia') !== false) return 'encyclopedia';
        if (strpos($class, 'knowledge') !== false) return 'knowledge';
        if (strpos($class, 'book') !== false) return 'book';
        if (strpos($class, 'movie') !== false) return 'movie';
        if (strpos($class, 'music') !== false) return 'music';
        if (strpos($class, 'local') !== false) return 'local';
        if (strpos($class, 'real_estate') !== false) return 'real_estate';
        if (strpos($class, 'stock') !== false) return 'stock';
        if (strpos($class, 'weather') !== false) return 'weather';
        if (strpos($class, 'dictionary') !== false) return 'dictionary';
        if (strpos($class, 'translate') !== false) return 'translate';
        if (strpos($class, 'calculator') !== false) return 'calculator';
        if (strpos($class, 'unit_converter') !== false) return 'unit_converter';
        
        // 제목 기반 타입 결정
        if (strpos($title, '뉴스') !== false) return 'news';
        if (strpos($title, '블로그') !== false) return 'blog';
        if (strpos($title, '카페') !== false) return 'cafe';
        if (strpos($title, '이미지') !== false) return 'image';
        if (strpos($title, '동영상') !== false) return 'video';
        if (strpos($title, '쇼핑') !== false) return 'shopping';
        if (strpos($title, '백과') !== false) return 'encyclopedia';
        if (strpos($title, '지식') !== false) return 'knowledge';
        if (strpos($title, '도서') !== false) return 'book';
        if (strpos($title, '영화') !== false) return 'movie';
        if (strpos($title, '음악') !== false) return 'music';
        if (strpos($title, '지역') !== false) return 'local';
        if (strpos($title, '부동산') !== false) return 'real_estate';
        if (strpos($title, '주식') !== false) return 'stock';
        if (strpos($title, '날씨') !== false) return 'weather';
        if (strpos($title, '사전') !== false) return 'dictionary';
        if (strpos($title, '번역') !== false) return 'translate';
        if (strpos($title, '계산') !== false) return 'calculator';
        if (strpos($title, '단위') !== false) return 'unit_converter';
        
        return 'unknown';
    }
    
    // 요소 설명 추출
    private function getElementDescription($element) {
        $xpath = new DOMXPath($element->ownerDocument);
        $descElements = $xpath->query('.//p | .//div[contains(@class, "desc")] | .//div[contains(@class, "summary")]', $element);
        
        if ($descElements->length > 0) {
            return trim($descElements->item(0)->textContent);
        }
        
        return '-';
    }
    
    // 섹션 중복 확인
    private function sectionExists($sections, $title) {
        foreach ($sections as $section) {
            if ($section['title'] === $title) {
                return true;
            }
        }
        return false;
    }
    
    // UI 요소인지 확인
    private function isUIElement($title) {
        $uiElements = [
            'search option detail', 'select', 'btn', 'button', 'link', 'url', 'desc', 
            'mod more', 'keep', 'api', 'ct feed', 'main', 'option', 'search',
            'link button list', 'sc page', 'feed', 'more', 'detail', 'option',
            'search option', 'button list', 'api sc', 'ct feed', 'mod more',
            'search detail', 'option detail', 'button detail', 'link detail'
        ];
        
        $title = strtolower(trim($title));
        foreach ($uiElements as $uiElement) {
            if (strpos($title, $uiElement) !== false) {
                return true;
            }
        }
        
        // 너무 짧은 제목 (3글자 이하) 제외
        if (strlen($title) <= 3) {
            return true;
        }
        
        // 특수문자만 있는 제목 제외
        if (preg_match('/^[^a-zA-Z가-힣0-9\s]+$/', $title)) {
            return true;
        }
        
        return false;
    }
    
    // 계층 구조 분석
    private function analyzeHierarchy($element) {
        $hierarchy = [];
        
        // 부모 요소들 추적
        $parent = $element->parentNode;
        $level = 0;
        
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE && $level < 5) {
            $class = $parent->getAttribute('class');
            $id = $parent->getAttribute('id');
            
            if ($class || $id) {
                $hierarchy[] = [
                    'level' => $level,
                    'tag' => $parent->tagName,
                    'class' => $class,
                    'id' => $id
                ];
            }
            
            $parent = $parent->parentNode;
            $level++;
        }
        
        return $hierarchy;
    }
    
    // 메인 섹션인지 판단
    private function isMainSection($element, $title, $itemCount) {
        // 파워링크는 메인 섹션
        if (strpos(strtolower($title), '파워링크') !== false) {
            return true;
        }
        
        // 네이버 주요 섹션들
        $mainSections = ['뉴스', '블로그', '카페', '지식iN', '쇼핑', '이미지', '동영상', '도서', '사전'];
        foreach ($mainSections as $section) {
            if (strpos($title, $section) !== false) {
                return true;
            }
        }
        
        // 아이템 수가 많은 섹션 (5개 이상)
        if ($itemCount >= 5) {
            return true;
        }
        
        // 클래스명으로 판단
        $class = strtolower($element->getAttribute('class'));
        if (strpos($class, 'section') !== false || 
            strpos($class, 'area') !== false || 
            strpos($class, 'group') !== false) {
            return true;
        }
        
        return false;
    }
    
    // 부모 섹션 찾기
    private function getParentSection($element) {
        $parent = $element->parentNode;
        
        while ($parent && $parent->nodeType === XML_ELEMENT_NODE) {
            $class = $parent->getAttribute('class');
            $id = $parent->getAttribute('id');
            
            // 파워링크 섹션 내부인지 확인
            if (strpos($class, 'power') !== false || 
                strpos($class, 'ad') !== false ||
                strpos($id, 'power') !== false) {
                return 'powerlink';
            }
            
            // 뉴스 섹션 내부인지 확인
            if (strpos($class, 'news') !== false || 
                strpos($id, 'news') !== false) {
                return 'news';
            }
            
            // 블로그 섹션 내부인지 확인
            if (strpos($class, 'blog') !== false || 
                strpos($id, 'blog') !== false) {
                return 'blog';
            }
            
            $parent = $parent->parentNode;
        }
        
        return null;
    }
    
    // 기존 고정 섹션 감지 (백업용)
    private function detectFixedSections($xpath, &$sections, &$order) {
        $sectionSelectors = [
            'web' => '//div[@id="main_pack"]//div[contains(@class, "total_wrap")] | //div[@id="main_pack"]//div[contains(@class, "total_area")]',
            'news' => '//div[@id="main_pack"]//div[contains(@class, "news_area")] | //div[@id="main_pack"]//div[contains(@class, "news")]',
            'blog' => '//div[@id="main_pack"]//div[contains(@class, "blog_area")] | //div[@id="main_pack"]//div[contains(@class, "blog")]',
            'cafe' => '//div[@id="main_pack"]//div[contains(@class, "cafe_area")] | //div[@id="main_pack"]//div[contains(@class, "cafe")]',
            'image' => '//div[@id="main_pack"]//div[contains(@class, "image_area")] | //div[@id="main_pack"]//div[contains(@class, "image")]',
            'video' => '//div[@id="main_pack"]//div[contains(@class, "video_area")] | //div[@id="main_pack"]//div[contains(@class, "video")]',
            'shopping' => '//div[@id="main_pack"]//div[contains(@class, "shopping_area")] | //div[@id="main_pack"]//div[contains(@class, "shopping")]',
            'encyclopedia' => '//div[@id="main_pack"]//div[contains(@class, "encyclopedia_area")] | //div[@id="main_pack"]//div[contains(@class, "encyclopedia")]',
            'knowledge' => '//div[@id="main_pack"]//div[contains(@class, "knowledge_area")] | //div[@id="main_pack"]//div[contains(@class, "knowledge")]',
            'book' => '//div[@id="main_pack"]//div[contains(@class, "book_area")] | //div[@id="main_pack"]//div[contains(@class, "book")]',
            'movie' => '//div[@id="main_pack"]//div[contains(@class, "movie_area")] | //div[@id="main_pack"]//div[contains(@class, "movie")]',
            'music' => '//div[@id="main_pack"]//div[contains(@class, "music_area")] | //div[@id="main_pack"]//div[contains(@class, "music")]',
            'local' => '//div[@id="main_pack"]//div[contains(@class, "local_area")] | //div[@id="main_pack"]//div[contains(@class, "local")]',
            'real_estate' => '//div[@id="main_pack"]//div[contains(@class, "real_estate_area")] | //div[@id="main_pack"]//div[contains(@class, "real_estate")]',
            'stock' => '//div[@id="main_pack"]//div[contains(@class, "stock_area")] | //div[@id="main_pack"]//div[contains(@class, "stock")]',
            'weather' => '//div[@id="main_pack"]//div[contains(@class, "weather_area")] | //div[@id="main_pack"]//div[contains(@class, "weather")]',
            'dictionary' => '//div[@id="main_pack"]//div[contains(@class, "dictionary_area")] | //div[@id="main_pack"]//div[contains(@class, "dictionary")]',
            'translate' => '//div[@id="main_pack"]//div[contains(@class, "translate_area")] | //div[@id="main_pack"]//div[contains(@class, "translate")]',
            'calculator' => '//div[@id="main_pack"]//div[contains(@class, "calculator_area")] | //div[@id="main_pack"]//div[contains(@class, "calculator")]',
            'unit_converter' => '//div[@id="main_pack"]//div[contains(@class, "unit_converter_area")] | //div[@id="main_pack"]//div[contains(@class, "unit_converter")]'
        ];
        
        foreach ($sectionSelectors as $sectionType => $selector) {
            $elements = $xpath->query($selector);
            if ($elements->length > 0 && !$this->sectionExists($sections, $this->getSectionTitle(null, $sectionType))) {
                $sectionInfo = $this->extractSectionInfo($elements->item(0), $sectionType, $order);
                if ($sectionInfo) {
                    $sections[] = $sectionInfo;
                    $order++;
                }
            }
        }
    }
    
    // 저장 기능 제거됨 - 데이터베이스 저장 로직 삭제
    
    // 히스토리 기능 제거됨 - 데이터베이스 조회 로직 삭제
}
?>
