<?php
require_once __DIR__.'/CSEClient.php';
class GoogleCSEAnalyzer {
    private $cse;
    public function __construct($apiKey, $engineId){
        $this->cse = new CSEClient($apiKey, $engineId);
    }
    public function analyze($keyword, $lang='ko'){
        $hl = ($lang==='en')?'en':'ko';
        $sections=[]; $order=1;
        // Web
        $dataWeb = $this->cse->search($keyword, $hl, 10);
        if(isset($dataWeb['error'])) return $dataWeb;
        if(isset($dataWeb['items']) && is_array($dataWeb['items'])){
            foreach($dataWeb['items'] as $it){
                $sections[] = ['order'=>$order++, 'type'=>'web', 'title'=>isset($it['title'])?$it['title']:'-', 'item_count'=>null, 'url'=>isset($it['link'])?$it['link']:null];
            }
        }
        // Image
        $dataImg = $this->cse->searchImage($keyword, $hl, 8);
        if(!isset($dataImg['error']) && isset($dataImg['items']) && is_array($dataImg['items'])){
            foreach($dataImg['items'] as $it){
                $sections[] = ['order'=>$order++, 'type'=>'image', 'title'=>isset($it['title'])?$it['title']:'-', 'item_count'=>null, 'url'=>isset($it['link'])?$it['link']:null];
            }
        }
        return [
            'keyword'=>$keyword, 'mode'=>'pc', 'sections'=>$sections, 'total_sections'=>count($sections),
            'search_url'=>'https://www.google.com/search?q='.urlencode($keyword).'&hl='.$hl
        ];
    }
}


