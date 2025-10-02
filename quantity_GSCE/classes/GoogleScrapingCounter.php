<?php

class GoogleScrapingCounter {
    
    /**
     * 키워드의 구글 검색 결과 수를 웹 스크래핑으로 가져옵니다
     * 
     * @param string $keyword 검색할 키워드
     * @param string $language 검색 언어 (기본값: 'ko')
     * @param string $country 검색 국가 (기본값: 'kr')
     * @return array 결과 배열 (success, count, error)
     */
    public function getSearchCount($keyword, $language = 'ko', $country = 'kr') {
        try {
            // 구글 검색 URL 생성
            $searchUrl = $this->buildSearchUrl($keyword, $language, $country);
            
            // 웹 페이지 가져오기
            $html = $this->fetchPage($searchUrl);
            
            if (!$html) {
                return [
                    'success' => false,
                    'count' => '-',
                    'error' => '웹 페이지를 가져올 수 없습니다.'
                ];
            }
            
            // 검색 결과 수 추출
            $count = $this->extractSearchCount($html);
            
            if ($count === null) {
                return [
                    'success' => false,
                    'count' => '-',
                    'error' => '검색 결과 수를 찾을 수 없습니다.'
                ];
            }
            
            return [
                'success' => true,
                'count' => $this->formatNumber($count),
                'error' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'count' => '-',
                'error' => '오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 구글 검색 URL을 생성합니다
     */
    private function buildSearchUrl($keyword, $language, $country) {
        $baseUrl = 'https://www.google.com/search';
        $params = [
            'q' => $keyword,
            'hl' => $language,
            'gl' => $country,
            'num' => 10
        ];
        
        return $baseUrl . '?' . http_build_query($params);
    }
    
    /**
     * 웹 페이지를 가져옵니다
     */
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            return false;
        }
        
        return $response;
    }
    
    /**
     * HTML에서 검색 결과 수를 추출합니다
     */
    private function extractSearchCount($html) {
        // 여러 패턴으로 검색 결과 수 찾기
        $patterns = [
            '/약 ([0-9,]+)개 결과/',
            '/About ([0-9,]+) results/',
            '/([0-9,]+) results/',
            '/약 ([0-9,]+)개/',
            '/([0-9,]+) results \(/',
            '/Results: ([0-9,]+)/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return str_replace(',', '', $matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * 숫자를 한국어 형식으로 포맷팅합니다
     */
    private function formatNumber($number) {
        if ($number === '-') {
            return '-';
        }
        
        $num = intval($number);
        
        if ($num >= 1000000000) {
            return round($num / 1000000000, 1) . 'B';
        } elseif ($num >= 1000000) {
            return round($num / 1000000, 1) . 'M';
        } elseif ($num >= 1000) {
            return round($num / 1000, 1) . 'K';
        }
        
        return number_format($num);
    }
    
    /**
     * 여러 키워드의 검색 결과 수를 일괄 조회합니다
     */
    public function getMultipleSearchCounts($keywords, $language = 'ko', $country = 'kr') {
        $results = [];
        
        foreach ($keywords as $keyword) {
            $results[$keyword] = $this->getSearchCount($keyword, $language, $country);
            
            // 요청 간 지연 (구글 차단 방지)
            sleep(2);
        }
        
        return $results;
    }
}
