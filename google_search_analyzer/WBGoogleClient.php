<?php
class WBGoogleClient {
    private $apiKey;
    private $endpoint = 'https://serpapi.webscrapingapi.com/v2';

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function search($keyword, $device = 'desktop', $gl = 'kr', $hl = 'ko') {
        $query = http_build_query([
            'engine' => 'google',
            'api_key' => $this->apiKey,
            'q' => $keyword,
            'device' => $device, // desktop|mobile|tablet
            'gl' => $gl,
            'hl' => $hl
        ]);
        $url = $this->endpoint.'?'.$query;
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5]);
        $res = curl_exec($ch); $err = curl_error($ch); $code=curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($res===false){ return ['error'=>'WB 요청 실패','detail'=>$err,'status'=>$code]; }
        $json = json_decode($res, true);
        if(!$json){ return ['error'=>'WB 응답 파싱 실패','raw'=>$res]; }
        if(isset($json['error']) && $json['error']){ return ['error'=>'WB 오류','detail'=>$json['error']]; }
        return $json;
    }
}


