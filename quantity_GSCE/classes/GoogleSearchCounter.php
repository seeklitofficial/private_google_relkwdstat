<?php
class GoogleSearchCounter {
    private $apiKey;
    private $searchEngineId;
    
    public function __construct() {
        $this->loadEnvConfig();
    }
    
    private function loadEnvConfig() {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // $_ENV 배열에 직접 설정
                    $_ENV[$key] = $value;
                    
                    // putenv가 사용 가능한 경우에만 사용
                    if (function_exists('putenv')) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }
        
        // $_ENV 우선, 그 다음 getenv 사용
        $this->apiKey = $_ENV['QUANTITY_GOOGLE_CUSTOM_SEARCH_API_KEY'] ?? getenv('QUANTITY_GOOGLE_CUSTOM_SEARCH_API_KEY');
        $this->searchEngineId = $_ENV['QUANTITY_GOOGLE_CUSTOM_SEARCH_ENGINE_ID'] ?? getenv('QUANTITY_GOOGLE_CUSTOM_SEARCH_ENGINE_ID');
    }
    
    public function checkApiStatus() {
        return [
            'api_key_set' => !empty($this->apiKey),
            'search_engine_id_set' => !empty($this->searchEngineId)
        ];
    }
    
    public function getSearchCount($keyword, $language = 'ko', $country = 'kr') {
        if (empty($this->apiKey) || empty($this->searchEngineId)) {
            return [
                'success' => false,
                'error' => 'API 키 또는 검색 엔진 ID가 설정되지 않았습니다.'
            ];
        }
        
        $url = 'https://www.googleapis.com/customsearch/v1?' . http_build_query([
            'key' => $this->apiKey,
            'cx' => $this->searchEngineId,
            'q' => $keyword,
            'lr' => 'lang_' . $language,
            'cr' => 'country' . $country,
            'num' => 1
        ]);
        
        $response = $this->makeRequest($url);
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'API 요청에 실패했습니다.'
            ];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['searchInformation']['totalResults'])) {
            return [
                'success' => true,
                'count' => number_format($data['searchInformation']['totalResults'])
            ];
        } else {
            return [
                'success' => false,
                'error' => '검색 결과를 가져올 수 없습니다.'
            ];
        }
    }
    
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return $response;
    }
}
?>