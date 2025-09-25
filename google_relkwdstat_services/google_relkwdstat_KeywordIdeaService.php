<?php

require_once __DIR__ . '/../google_relkwdstat_config/google_relkwdstat_google_ads_config.php';

class KeywordIdeaService
{
    private $config;
    private $accessToken;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../google_relkwdstat_config/google_relkwdstat_google_ads_config.php';
        $this->accessToken = $this->getAccessToken();
    }

    /**
     * OAuth 2.0 액세스 토큰 가져오기
     */
    private function getAccessToken()
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        
        $postData = [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $this->config['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('OAuth 토큰 갱신 실패: ' . $response);
        }

        $tokenData = json_decode($response, true);
        return $tokenData['access_token'];
    }

    /**
     * REST API를 통한 키워드 아이디어 생성
     */
    public function generateKeywordIdeas(array $keywords, ?string $pageUrl, array $locationIds, int $languageId): array
    {
        $endpoint = "https://googleads.googleapis.com/v21/customers/{$this->config['customer_id']}:generateKeywordIdeas";
        
        // 요청 데이터 구성
        $requestData = [
            'language' => "languageConstants/{$languageId}",
            'geoTargetConstants' => array_map(function($id) {
                return "geoTargetConstants/{$id}";
            }, $locationIds),
            'keywordPlanNetwork' => 'GOOGLE_SEARCH',
            'includeAdultKeywords' => false,
            'historicalMetricsOptions' => [
                'yearMonthRange' => [
                    'start' => [
                        'year' => (int)date('Y') - 1,
                        'month' => (int)date('n')
                    ],
                    'end' => [
                        'year' => (int)date('Y'),
                        'month' => (int)date('n')
                    ]
                ]
            ]
        ];

        // 키워드 시드 설정
        if (empty($keywords) && is_null($pageUrl)) {
            throw new Exception('키워드 또는 페이지 URL을 제공해주세요.');
        } elseif (empty($keywords)) {
            $requestData['urlSeed'] = ['url' => $pageUrl];
        } elseif (is_null($pageUrl)) {
            $requestData['keywordSeed'] = ['keywords' => $keywords];
        } else {
            $requestData['keywordAndUrlSeed'] = [
                'url' => $pageUrl,
                'keywords' => $keywords
            ];
        }

        // REST API 호출
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'developer-token: ' . $this->config['developer_token'],
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL 오류: ' . $error);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['error']['message']) 
                ? $errorData['error']['message'] 
                : '알 수 없는 오류가 발생했습니다.';
            throw new Exception('API 오류 (HTTP ' . $httpCode . '): ' . $errorMessage);
        }

        $responseData = json_decode($response, true);
        
        if (!isset($responseData['results'])) {
            return [];
        }

        // 결과 처리
        $results = [];
        foreach ($responseData['results'] as $result) {
            $keywordText = $result['text'] ?? '';
            $metrics = $result['keywordIdeaMetrics'] ?? null;
            
            // 실제 데이터가 있는지 확인
            $avgSearches = $metrics['avgMonthlySearches'] ?? 0;
            $competitionIndex = $metrics['competitionIndex'] ?? 0;
            $lowBid = $metrics['lowTopOfPageBidMicros'] ?? 0;
            $highBid = $metrics['highTopOfPageBidMicros'] ?? 0;
            $avgCpc = $metrics['averageCpcMicros'] ?? 0;
            
            // 경쟁도 계산 (competition_index 기반)
            $competition = $this->calculateCompetitionLevel($competitionIndex);
            
            $results[] = [
                'keyword' => $keywordText,
                'avg_monthly_searches' => $avgSearches,
                'competition' => $competition,
                'competition_index' => $competitionIndex,
                'low_top_of_page_bid_micros' => $lowBid,
                'high_top_of_page_bid_micros' => $highBid,
                'cpc_bid_micros' => $avgCpc,
                'data_source' => 'Google Ads API V21' // 데이터 출처 명시
            ];
        }

        return $results;
    }

    /**
     * 경쟁 수준 계산 (competition_index 기반)
     */
    private function calculateCompetitionLevel($competitionIndex): string
    {
        if ($competitionIndex == 0) {
            return 'UNKNOWN';
        } elseif ($competitionIndex <= 33) {
            return 'LOW';
        } elseif ($competitionIndex <= 66) {
            return 'MEDIUM';
        } else {
            return 'HIGH';
        }
    }
}
