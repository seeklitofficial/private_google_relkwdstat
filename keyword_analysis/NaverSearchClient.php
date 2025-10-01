<?php
class NaverSearchClient {
    private $endpoint = 'https://search.naver.com/search.naver';
    
    public function getDocumentCount($keyword) {
        $startTime = microtime(true);
        
        $params = [
            'query' => $keyword,
            'where' => 'web',
            'sm' => 'tab_hty.top',
            'ie' => 'utf8'
        ];
        
        $url = $this->endpoint . '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $html = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        
        $searchTime = round((microtime(true) - $startTime) * 1000);
        
        if ($html === false) {
            return [
                'error' => '네이버 검색 실패',
                'detail' => $err,
                'status' => $code,
                'search_time' => $searchTime
            ];
        }
        
        // 네이버 검색 결과에서 문서 수 추출
        $documentCount = $this->extractDocumentCount($html);
        
        return [
            'document_count' => $documentCount,
            'search_time' => $searchTime,
            'status' => $code
        ];
    }
    
    private function extractDocumentCount($html) {
        // 네이버 검색 결과에서 "약 X,XXX개" 패턴 찾기
        $patterns = [
            '/약\s*([0-9,]+)개/',
            '/([0-9,]+)개의\s*결과/',
            '/총\s*([0-9,]+)개/'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return (int) str_replace(',', '', $matches[1]);
            }
        }
        
        // 패턴을 찾지 못한 경우 기본값
        return 0;
    }
}
