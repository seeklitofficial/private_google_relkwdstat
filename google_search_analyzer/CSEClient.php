<?php
class CSEClient {
    private $apiKey;
    private $engineId;
    private $endpoint = 'https://www.googleapis.com/customsearch/v1';
    public function __construct($apiKey, $engineId) {
        $this->apiKey = $apiKey; $this->engineId = $engineId;
    }
    public function search($q, $hl='ko', $num=10) {
        $params = http_build_query([
            'key'=>$this->apiKey, 'cx'=>$this->engineId, 'q'=>$q, 'hl'=>$hl, 'num'=>$num
        ]);
        $url = $this->endpoint.'?'.$params;
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5]);
        $res = curl_exec($ch); $err = curl_error($ch); $code=curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($res===false){ return ['error'=>'CSE 요청 실패','detail'=>$err,'status'=>$code]; }
        $json = json_decode($res, true);
        if(!$json){ return ['error'=>'CSE 응답 파싱 실패','raw'=>$res]; }
        if(isset($json['error'])){ return ['error'=>'CSE 오류','detail'=>$json['error']]; }
        return $json;
    }

    public function searchImage($q, $hl='ko', $num=10) {
        $params = http_build_query([
            'key'=>$this->apiKey, 'cx'=>$this->engineId, 'q'=>$q, 'hl'=>$hl, 'num'=>$num, 'searchType'=>'image'
        ]);
        $url = $this->endpoint.'?'.$params;
        $ch = curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_CONNECTTIMEOUT=>5]);
        $res = curl_exec($ch); $err = curl_error($ch); $code=curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($res===false){ return ['error'=>'CSE 이미지 요청 실패','detail'=>$err,'status'=>$code]; }
        $json = json_decode($res, true);
        if(!$json){ return ['error'=>'CSE 이미지 응답 파싱 실패','raw'=>$res]; }
        if(isset($json['error'])){ return ['error'=>'CSE 이미지 오류','detail'=>$json['error']]; }
        return $json;
    }
}


