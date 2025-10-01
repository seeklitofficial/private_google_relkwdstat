<?php
class GoogleSearchClient {
    private $apiKey;
    private $engineId;
    private $endpoint = 'https://www.googleapis.com/customsearch/v1';
    
    public function __construct($apiKey, $engineId) {
        $this->apiKey = $apiKey;
        $this->engineId = $engineId;
    }
    
    public function getDocumentCount($keyword) {
        $startTime = microtime(true);
        
        $params = [
            'key' => $this->apiKey,
            'cx' => $this->engineId,
            'q' => $keyword,
            'hl' => 'ko',
            'num' => 1  // 최소한의 결과만 요청
        ];
        
        $url = $this->endpoint . '?' . http_build_query($params);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        
        $searchTime = round((microtime(true) - $startTime) * 1000);
        
        if ($res === false) {
            return [
                'error' => '구글 검색 실패',
                'detail' => $err,
                'status' => $code,
                'search_time' => $searchTime
            ];
        }
        
        $json = json_decode($res, true);
        if (!$json) {
            return [
                'error' => '구글 응답 파싱 실패',
                'raw' => mb_substr($res, 0, 1000),
                'status' => $code,
                'search_time' => $searchTime
            ];
        }
        
        if (isset($json['error'])) {
            return [
                'error' => '구글 API 오류',
                'detail' => $json['error'],
                'status' => $code,
                'search_time' => $searchTime
            ];
        }
        
        // Google CSE는 정확한 문서 수를 제공하지 않으므로 검색 결과 수를 반환
        $documentCount = isset($json['searchInformation']['totalResults']) ? 
            (int) $json['searchInformation']['totalResults'] : 0;
        
        return [
            'document_count' => $documentCount,
            'search_time' => $searchTime,
            'status' => $code
        ];
    }
}
