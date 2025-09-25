# Google Relkwdstat Backend Services

Google Ads API V21을 활용한 키워드 분석 도구의 백엔드 서비스입니다.

## 🚀 주요 기능

- **Google Ads API 연동**: REST API V21을 통한 키워드 데이터 수집
- **OAuth 2.0 인증**: 안전한 API 인증 및 토큰 관리
- **모듈화된 구조**: 재사용 가능한 유틸리티 모듈
- **환경 변수 관리**: 안전한 설정 관리
- **에러 처리**: 포괄적인 에러 처리 및 로깅

## 📁 프로젝트 구조

```
private_html/
├── google_relkwdstat/
│   ├── google_relkwdstat_config/
│   │   ├── google_relkwdstat_google_ads_config.php    # API 설정
│   │   └── google_relkwdstat_env_loader.php          # 환경 변수 로더
│   ├── google_relkwdstat_services/
│   │   └── google_relkwdstat_KeywordIdeaService.php  # 키워드 서비스
│   └── google_relkwdstat_utils/
│       ├── google_relkwdstat_response_helper.php     # 응답 헬퍼
│       ├── google_relkwdstat_validator.php           # 데이터 검증
│       └── google_relkwdstat_logger.php               # 로깅 유틸리티
├── .env                    # 환경 변수 (민감한 정보)
├── .env.example           # 환경 변수 예시
└── README.md              # 이 파일
```

## 🛠 기술 스택

- **Backend**: PHP 8+, Google Ads API V21
- **Authentication**: OAuth 2.0
- **Configuration**: Environment Variables
- **Logging**: Custom Logger Class

## 🔧 설치 및 설정

1. **저장소 클론**
   ```bash
   git clone git@github.com:seeklitofficial/private_google_relkwdstat.git
   ```

2. **환경 변수 설정**
   ```bash
   cp .env.example .env
   # .env 파일을 편집하여 실제 값 입력
   ```

3. **필수 환경 변수**
   ```env
   GOOGLE_ADS_DEVELOPER_TOKEN=your_developer_token
   GOOGLE_ADS_CLIENT_ID=your_client_id
   GOOGLE_ADS_CLIENT_SECRET=your_client_secret
   GOOGLE_ADS_REFRESH_TOKEN=your_refresh_token
   GOOGLE_ADS_CUSTOMER_ID=your_customer_id
   GOOGLE_ADS_API_KEY=your_api_key
   GOOGLE_ADS_LOGIN_CUSTOMER_ID=your_login_customer_id
   ```

## 📊 서비스 모듈

### 1. KeywordIdeaService
- **기능**: Google Ads API 키워드 아이디어 생성
- **특징**: OAuth 2.0 토큰 자동 갱신
- **메서드**: `generateKeywordIdeas()`

### 2. ResponseHelper
- **기능**: 표준화된 API 응답 처리
- **특징**: CORS 헤더 설정, 에러 응답 표준화
- **메서드**: `setCorsHeaders()`, `success()`, `error()`

### 3. DataValidator
- **기능**: 입력 데이터 검증
- **특징**: 타입 안전성, 입력 검증
- **메서드**: `validateKeywords()`, `validateUrl()`, `validateLocationIds()`

### 4. Logger
- **기능**: 애플리케이션 로깅
- **특징**: 레벨별 로깅, 컨텍스트 정보 포함
- **메서드**: `info()`, `warning()`, `error()`, `debug()`

## 🔒 보안 고려사항

- **환경 변수**: 민감한 정보는 `.env` 파일에 저장
- **Git 무시**: `.env` 파일은 Git에서 제외
- **토큰 관리**: OAuth 2.0 토큰 자동 갱신
- **입력 검증**: 모든 사용자 입력에 대한 엄격한 검증

## 📈 성능 최적화

- **토큰 캐싱**: OAuth 토큰 캐싱으로 API 호출 최적화
- **에러 처리**: 효율적인 에러 처리 및 복구
- **로깅 최적화**: 필요한 경우에만 로깅 수행

## 🧪 테스트

```bash
# API 엔드포인트 테스트
curl -X POST -H "Content-Type: application/json" \
  -d '{"keywords":["테스트"],"location_ids":[2840],"language_id":1004,"network_type":"GOOGLE_SEARCH_AND_PARTNERS"}' \
  http://localhost:8000/google_relkwdstat/api/google_relkwdstat_api/google_relkwdstat_keyword_ideas.php
```

## 📝 로깅

로그는 `logs/app.log` 파일에 저장됩니다:
- **INFO**: 일반적인 정보 로그
- **WARNING**: 경고 메시지
- **ERROR**: 에러 메시지
- **DEBUG**: 디버깅 정보

## 🔄 API 토큰 관리

- **자동 갱신**: 토큰 만료 전 자동 갱신
- **에러 처리**: 토큰 갱신 실패 시 적절한 에러 처리
- **캐싱**: 유효한 토큰은 메모리에 캐싱

## 🤝 기여하기

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 라이선스

이 프로젝트는 MIT 라이선스 하에 배포됩니다. 자세한 내용은 `LICENSE` 파일을 참조하세요.

## 📞 지원

문제가 발생하거나 질문이 있으시면 GitHub Issues를 통해 문의해주세요.

---

**Google Relkwdstat Backend Services** - Google Ads API V21을 활용한 키워드 분석 백엔드 서비스
