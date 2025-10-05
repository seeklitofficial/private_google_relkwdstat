<?php
?>
<main class="google-blog-analyzer-container">
    <h1>구글 블로그 분석기</h1>
    <p>키워드로 구글 검색을 실행하고 블로그 콘텐츠를 분석합니다.</p>
    
    <form id="google-blog-analyzer-form" class="google-blog-analyzer-form">
        <div class="google-blog-analyzer-input-group">
            <label for="google-blog-analyzer-keyword">검색 키워드</label>
            <input 
                id="google-blog-analyzer-keyword" 
                name="keyword" 
                type="text" 
                placeholder="예: 인공지능, 마케팅, 블로그" 
                required
            >
        </div>
        <button type="submit" class="google-blog-analyzer-btn">
            <span class="google-blog-analyzer-btn-text">분석 시작</span>
            <span class="google-blog-analyzer-spinner" style="display: none;">
                <span class="google-blog-analyzer-spinner-icon"></span>분석 중...
            </span>
        </button>
    </form>
    
    <section id="google-blog-analyzer-results" class="google-blog-analyzer-results"></section>
</main>
