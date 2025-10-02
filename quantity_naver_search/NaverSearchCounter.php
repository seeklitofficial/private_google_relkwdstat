<?php
class NaverSearchCounter {
    private $clientId;
    private $clientSecret;
    
    public function __construct() {
        $this->loadEnvConfig();
    }
    
    private function loadEnvConfig() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    $_ENV[$key] = $value;
                    if (function_exists('putenv')) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }
        
        $this->clientId = $_ENV['QUANTITY_NAVER_CLIENT_ID'] ?? getenv('QUANTITY_NAVER_CLIENT_ID');
        $this->clientSecret = $_ENV['QUANTITY_NAVER_CLIENT_SECRET'] ?? getenv('QUANTITY_NAVER_CLIENT_SECRET');
    }
    
    public function checkApiStatus() {
        return [
            'client_id_set' => !empty($this->clientId),
            'client_secret_set' => !empty($this->clientSecret)
        ];
    }
    
    public function getSearchCount($keyword, $searchType = 'webkr') {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return [
                'success' => false,
                'error' => 'API 키가 설정되지 않았습니다.'
            ];
        }
        
        $url = 'https://openapi.naver.com/v1/search/' . $searchType . '?' . http_build_query([
            'query' => $keyword,
            'display' => 1
        ]);
        
        $response = $this->makeRequest($url);
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'API 요청에 실패했습니다.'
            ];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['total'])) {
            return [
                'success' => true,
                'count' => number_format($data['total']),
                'total' => $data['total']
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Naver-Client-Id: ' . $this->clientId,
            'X-Naver-Client-Secret: ' . $this->clientSecret
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
