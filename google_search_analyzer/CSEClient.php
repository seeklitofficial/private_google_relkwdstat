<?php
class CSEClient {
    private $apiKey;
    private $engineId;
    private $endpoint = 'https://www.googleapis.com/customsearch/v1';

    public function __construct($apiKey, $engineId) {
        $this->apiKey = $apiKey;
        $this->engineId = $engineId;
    }

    public function searchWeb(string $query, string $hl = 'ko', int $num = 10): array {
        $num = max(1, min(10, $num));
        $params = [
            'key' => $this->apiKey,
            'cx' => $this->engineId,
            'q' => $query,
            'hl' => $hl,
            'num' => $num,
        ];
        $url = $this->endpoint.'?'.http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($res === false) {
            return ['error' => 'CSE 요청 실패', 'detail' => $err, 'status' => $code, 'url' => $url];
        }
        $json = json_decode($res, true);
        if (!$json) {
            return ['error' => 'CSE 응답 파싱 실패', 'raw' => mb_substr($res, 0, 1000), 'status' => $code, 'url' => $url];
        }
        if (isset($json['error'])) {
            return ['error' => 'CSE 오류', 'detail' => $json['error'], 'status' => $code, 'url' => $url];
        }
        return $json;
    }
}