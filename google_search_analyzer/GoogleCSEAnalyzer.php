<?php
require_once __DIR__.'/CSEClient.php';

class GoogleCSEAnalyzer {
    private $client;

    public function __construct(string $apiKey, string $engineId) {
        $this->client = new CSEClient($apiKey, $engineId);
    }

    public function analyze(string $keyword, string $lang = 'ko'): array {
        $hl = ($lang === 'en') ? 'en' : 'ko';
        $sections = [];
        $order = 1;

        // Web results only (minimal schema)
        $web = $this->client->searchWeb($keyword, $hl, 10);
        if (isset($web['error'])) {
            return ['error' => $web['error'], 'detail' => isset($web['detail']) ? $web['detail'] : null];
        }
        if (isset($web['items']) && is_array($web['items'])) {
            foreach ($web['items'] as $item) {
                $sections[] = [
                    'order' => $order++,
                    'type' => 'web',
                    'title' => isset($item['title']) ? $item['title'] : '-',
                    'item_count' => null,
                    'url' => isset($item['link']) ? $item['link'] : null,
                ];
            }
        }

        return [
            'keyword' => $keyword,
            'sections' => $sections,
            'total_sections' => count($sections),
        ];
    }
}