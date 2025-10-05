document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('google-search-analyzer-form');
    const keywordInput = document.getElementById('google-search-analyzer-keyword');
    const resultsContainer = document.getElementById('google-search-analyzer-results');
    const submitBtn = form.querySelector('button');
    const btnText = submitBtn.querySelector('.google-search-analyzer-btn-text');
    const spinner = submitBtn.querySelector('.google-search-analyzer-spinner');
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const keyword = keywordInput.value.trim();
        if (!keyword) {
            keywordInput.focus();
            return;
        }
        
        // UI 상태 변경
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        spinner.style.display = 'inline-flex';
        resultsContainer.innerHTML = '<div class="google-search-analyzer-loading">구글 검색 및 분석 중...</div>';
        
        try {
            const response = await fetch('/google_blog_analyzer/google_blog_analyzer_analyze.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ keyword: keyword })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || '분석 실패');
            }
            
            displayResults(data, keyword);
            
        } catch (error) {
            displayError(error.message);
        } finally {
            // UI 상태 복원
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            spinner.style.display = 'none';
        }
    });
    
    function displayResults(data, keyword) {
        const { summary, titleAnalysis, coKeywords, topResults } = data;
        
        let html = '';
        
        // 요약 통계
        html += `
            <div class="google-search-analyzer-card">
                <h3>📊 분석 요약</h3>
                <div class="google-search-analyzer-stats">
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">총 검색 결과</div>
                        <div class="google-search-analyzer-stat-value">${summary.totalDocs}개</div>
                    </div>
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">평균 글자 수</div>
                        <div class="google-search-analyzer-stat-value">${summary.avgChars.toLocaleString()}자</div>
                    </div>
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">평균 이미지 수</div>
                        <div class="google-search-analyzer-stat-value">${summary.avgImages}개</div>
                    </div>
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">평균 링크 수</div>
                        <div class="google-search-analyzer-stat-value">${summary.avgLinks}개</div>
                    </div>
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">평균 숫자 수</div>
                        <div class="google-search-analyzer-stat-value">${summary.avgNumbers}개</div>
                    </div>
                    <div class="google-search-analyzer-stat">
                        <div class="google-search-analyzer-stat-label">평균 감탄부호</div>
                        <div class="google-search-analyzer-stat-value">${summary.avgExclamations}개</div>
                    </div>
                </div>
            </div>
        `;
        
        // 제목 분석
        if (titleAnalysis && Object.keys(titleAnalysis).length > 0) {
            html += `
                <div class="google-search-analyzer-card">
                    <h3>📝 제목 유형 분석</h3>
                    <div>
                        ${Object.entries(titleAnalysis).map(([type, count]) => 
                            `<span class="google-search-analyzer-badge">${type} (${count}개)</span>`
                        ).join('')}
                    </div>
                </div>
            `;
        }
        
        // 공동 키워드
        if (coKeywords && coKeywords.length > 0) {
            html += `
                <div class="google-search-analyzer-card">
                    <h3>🔗 함께 사용된 키워드</h3>
                    <div>
                        ${coKeywords.slice(0, 15).map(item => 
                            `<span class="google-search-analyzer-badge">${item.keyword} (${item.count}회)</span>`
                        ).join('')}
                    </div>
                    <div style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                        * HTML 태그와 불용어는 제외된 의미 있는 키워드만 표시됩니다.
                    </div>
                </div>
            `;
        }
        
        // 상위 검색 결과
        if (topResults && topResults.length > 0) {
            html += `
                <div class="google-search-analyzer-card">
                    <h3>🔍 상위 검색 결과</h3>
                    <div>
                        ${topResults.map(result => `
                            <div class="google-search-analyzer-result-item">
                                <a href="${result.url}" target="_blank">${escapeHtml(result.title)}</a>
                                <div class="google-search-analyzer-result-meta">
                                    <span class="google-search-analyzer-badge">글자수: ${result.charCount.toLocaleString()}</span>
                                    <span class="google-search-analyzer-badge">키워드: ${result.keywordCount}회</span>
                                    <span class="google-search-analyzer-badge">밀도: ${result.keywordDensity}%</span>
                                    <span class="google-search-analyzer-badge">이미지: ${result.imageCount}개</span>
                                    <span class="google-search-analyzer-badge">링크: ${result.linkCount}개</span>
                                    <span class="google-search-analyzer-badge">${result.titleType}</span>
                                </div>
                                ${result.contentPreview ? `<div style="margin-top: 8px; color: #666; font-size: 0.9rem;">${escapeHtml(result.contentPreview)}</div>` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        resultsContainer.innerHTML = html;
    }
    
    function displayError(message) {
        resultsContainer.innerHTML = `
            <div class="google-search-analyzer-error">
                <h3>❌ 오류 발생</h3>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
