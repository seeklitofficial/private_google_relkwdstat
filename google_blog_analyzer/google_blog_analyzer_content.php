<?php
?>
<main class="google-search-analyzer-container">
    <h1>구글 검색 결과 분석기</h1>
    <p>키워드로 구글 검색을 실행하고 상위 결과들을 분석합니다.</p>
    
    <form id="google-search-analyzer-form" class="google-search-analyzer-form">
        <div class="google-search-analyzer-input-group">
            <label for="google-search-analyzer-keyword">검색 키워드</label>
            <input 
                id="google-search-analyzer-keyword" 
                name="keyword" 
                type="text" 
                placeholder="예: 인공지능, 마케팅, 블로그" 
                required
            >
        </div>
        <button type="submit" class="google-search-analyzer-btn">
            <span class="google-search-analyzer-btn-text">분석 시작</span>
            <span class="google-search-analyzer-spinner" style="display: none;">
                <span class="spinner-icon"></span>분석 중...
            </span>
        </button>
    </form>
    
    <section id="google-search-analyzer-results" class="google-search-analyzer-results"></section>
</main>
