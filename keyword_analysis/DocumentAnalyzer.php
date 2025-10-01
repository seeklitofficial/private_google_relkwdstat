<?php
require_once __DIR__.'/NaverSearchClient.php';
require_once __DIR__.'/GoogleSearchClient.php';

class DocumentAnalyzer {
    private $naverClient;
    private $googleClient;
    
    public function __construct($googleApiKey, $googleEngineId) {
        $this->naverClient = new NaverSearchClient();
        $this->googleClient = new GoogleSearchClient($googleApiKey, $googleEngineId);
    }
    
    public function analyze($keyword, $engine = 'both') {
        $engines = [];
        
        // 네이버 분석
        if ($engine === 'naver' || $engine === 'both') {
            $naverResult = $this->naverClient->getDocumentCount($keyword);
            $engines[] = [
                'name' => '네이버',
                'document_count' => $naverResult['document_count'] ?? 0,
                'search_time' => $naverResult['search_time'] ?? 0,
                'error' => $naverResult['error'] ?? null
            ];
        }
        
        // 구글 분석
        if ($engine === 'google' || $engine === 'both') {
            $googleResult = $this->googleClient->getDocumentCount($keyword);
            $engines[] = [
                'name' => '구글',
                'document_count' => $googleResult['document_count'] ?? 0,
                'search_time' => $googleResult['search_time'] ?? 0,
                'error' => $googleResult['error'] ?? null
            ];
        }
        
        // 비교 분석
        $comparison = null;
        if (count($engines) === 2) {
            $naverCount = $engines[0]['document_count'] ?? 0;
            $googleCount = $engines[1]['document_count'] ?? 0;
            
            $comparison = [
                'naver_count' => $naverCount,
                'google_count' => $googleCount,
                'difference' => abs($naverCount - $googleCount),
                'higher_engine' => $naverCount > $googleCount ? '네이버' : '구글',
                'ratio' => $naverCount > 0 && $googleCount > 0 ? 
                    round($naverCount / $googleCount, 2) : 0
            ];
        }
        
        return [
            'keyword' => $keyword,
            'engine' => $engine,
            'engines' => $engines,
            'comparison' => $comparison,
            'total_documents' => array_sum(array_column($engines, 'document_count')),
            'analysis_time' => date('Y-m-d H:i:s')
        ];
    }
}
