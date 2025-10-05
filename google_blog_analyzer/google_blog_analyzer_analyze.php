<?php
use GBA\GoogleBlogAnalyzer;

require_once __DIR__ . '/../../private_html/google_blog_analyzer/GoogleBlogAnalyzer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST 요청만 허용됩니다']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$keyword = trim($input['keyword'] ?? '');

if (empty($keyword)) {
    http_response_code(400);
    echo json_encode(['error' => '키워드를 입력하세요']);
    exit;
}

try {
    $analyzer = new GoogleBlogAnalyzer();
    
    // 구글 검색 실행
    $searchResults = $analyzer->searchGoogle($keyword, 10);
    
    if (isset($searchResults['error'])) {
        http_response_code(502);
        echo json_encode(['error' => '구글 검색 실패', 'detail' => $searchResults['error']]);
        exit;
    }
    
    // 검색 결과 분석
    $analysis = $analyzer->analyzeResults($keyword, $searchResults['items'] ?? []);
    
    echo json_encode($analysis, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '서버 오류: ' . $e->getMessage()]);
}
