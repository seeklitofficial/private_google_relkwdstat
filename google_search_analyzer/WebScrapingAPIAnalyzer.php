<?php
require_once __DIR__.'/WBGoogleClient.php';
class WebScrapingAPIAnalyzer {
    private $client;
    public function __construct($apiKey){ $this->client = new WBGoogleClient($apiKey); }
    public function analyze($keyword, $device='desktop', $lang='ko'){
        $hl = ($lang==='en')?'en':'ko';
        $gl = ($lang==='en')?'us':'kr';
        $data = $this->client->search($keyword, $device, $gl, $hl);
        if(isset($data['error'])) return $data;
        $sections = []; $order = 1;
        // Map organic results if present
        if (isset($data['organic_results']) && is_array($data['organic_results'])){
            foreach ($data['organic_results'] as $item){
                $sections[] = [
                    'order'=>$order++, 'type'=>'web',
                    'title'=> isset($item['title']) ? $item['title'] : '-',
                    'item_count'=> null,
                    'url'=> isset($item['link']) ? $item['link'] : null
                ];
            }
        }
        // Images block (if present)
        if (isset($data['images_results']) && is_array($data['images_results'])){
            foreach ($data['images_results'] as $item){
                $sections[] = [
                    'order'=>$order++, 'type'=>'image',
                    'title'=> isset($item['title']) ? $item['title'] : '-',
                    'item_count'=> null,
                    'url'=> isset($item['original']) ? $item['original'] : (isset($item['link'])?$item['link']:null)
                ];
            }
        }
        return [
            'keyword'=>$keyword,
            'device'=>$device,
            'sections'=>$sections,
            'total_sections'=>count($sections)
        ];
    }
}


