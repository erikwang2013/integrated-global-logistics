> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../CLAUDE.md) | [English](CLAUDE.en.md) | [한국어](CLAUDE.ko.md) | [Русский](CLAUDE.ru.md) | [Deutsch](CLAUDE.de.md) | [Français](CLAUDE.fr.md) | [Español](CLAUDE.es.md) | [Português](CLAUDE.pt.md) | [हिन्दी](CLAUDE.hi.md) | [العربية](CLAUDE.ar.md) | [বাংলা](CLAUDE.bn.md) | [Bahasa Indonesia](CLAUDE.id.md) | [日本語](CLAUDE.ja.md)

# 오픈 관리 백엔드 (open-admin)

webman v2 + Flutter 기반의 풀스택 관리 백엔드 시스템.

## 저작권 고지

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **수정 불가, 제거 불가, 되돌릴 수 없음.** 모든 신규 파일은 위 저작권 고지를 파일 헤더 주석으로 포함해야 합니다.

## 기능 목록

| 영역 | 기능 |
|----|------|
| 인증 | 로그인/회원가입/갱신/로그아웃 + 캡차 + 계정 잠금 + 세션 제한 |
| 대시보드 | 실시간 통계/추세/분포/로그 (Redis 5m 캐시) |
| 사용자 | CRUD + 일괄 삭제/활성·비활성화 + Excel 가져오기 |
| 역할·권한 | CRUD + 권한 트리 + RBAC method.path 인가 |
| 시스템 설정 | 키-값 CRUD |
| 작업 감사 | 로그 조회 + 8개 플랫폼 출처 단말 자동 감지 |
| 파일 | 업로드 + Excel/PDF 내보내기 (민감 데이터 마스킹) |
| 보안 | 18계층 심층 방어 (XSS/SQL 주입/CSRF/레이트 리밋/CSP...) |
| 운영 | 헬스 체크/Prometheus 지표/API 문서/security.txt + Docker + CI/CD |

## 기술 스택

### 백엔드
- PHP 8.3+, webman v2 (workerman/webman)
- 데이터베이스: MySQL 8.0+, 테이블 접두사 `logistics_`
- 기본 키: BIGINT 비자동증가, `erikwang2013/snowflake-php`로 생성
- API 계층 ID 암·복호화: `erikwang2013/hashids`
- JWT 인증: `erikwang2013/jwt-webman`
- API 민감 데이터 암·복호화: `erikwang2013/encryption`
- DB 민감 필드 암·복호화: `erikwang2013/encryptable`
- ES 동기화 및 조회: `erikwang2013/webman-scout`
- 국가 국기: `erikwang2013/season`

### 프론트엔드
- Flutter 3.x, 소스 디렉터리 `apps/flutter/`
- 웹은 PC 관리 백엔드 스타일로 설계 (모바일 App 스타일 아님)
- 클라이언트와 관리자 양쪽 지원
- HarmonyOS ArkTS, 소스 디렉터리 `apps/harmonyos/`

## 프로젝트 구조

```
open-admin/
├── app/
│   ├── admin/controller/       # 관리자 컨트롤러 (14개)
│   │   ├── BaseController.php      # 기본 컨트롤러
│   │   ├── DashboardController.php # 대시보드 (Redis 캐시)
│   │   ├── UserController.php      # 사용자 CRUD + 일괄 작업
│   │   ├── RoleController.php      # 역할 CRUD
│   │   ├── PermissionController.php# 권한 CRUD
│   │   ├── ConfigController.php    # 시스템 설정 CRUD
│   │   ├── LogController.php       # 작업 로그 조회
│   │   ├── ProfileController.php   # 개인 센터 + 로그아웃
│   │   ├── ExportController.php    # Excel/PDF 내보내기
│   │   ├── ImportController.php    # Excel 사용자 가져오기
│   │   ├── UploadController.php    # 파일 업로드
│   │   ├── HealthController.php    # 헬스 체크
│   │   ├── DocsController.php      # OpenAPI 문서
│   │   └── MetricsController.php   # Prometheus 모니터링 지표
│   ├── api/v1/controller/      # API v1 컨트롤러 (버전 헤더 제어)
│   │   ├── CaptchaController.php
│   │   └── AuthController.php
│   ├── common/                 # 공용 유틸리티 클래스
│   │   ├── HashidsService.php
│   │   ├── SnowflakeService.php
│   │   └── EncryptionService.php
│   ├── common/                 # 공용 정의 (Apidoc Definitions 포함)
│   ├── middleware/             # 미들웨어 (8개)
│   │   ├── Cors.php            # 크로스 도메인 (전역)
│   │   ├── SecurityFilter.php  # 공격 차단 (전역: XSS/SQL 주입/경로 탐색/명령 주입/CSRF)
│   │   ├── RateLimit.php       # Redis 레이트 리밋 (전역, Lua 원자화)
│   │   ├── AdminAuth.php       # JWT 인증 + 블랙리스트
│   │   ├── AdminPermission.php # RBAC 권한 검증 (Redis 60s 캐시)
│   │   └── OperationLog.php    # 작업 로그 자동 기록 (출처 단말 감지 포함)
│   ├── model/                  # 데이터 모델
│   ├── queue/                  # 큐 작업
│   └── process/                # 프로세스 (Http, Monitor)
├── apps/
│   ├── flutter/                # Flutter Web 관리 백엔드
│   │   └── lib/app/
│   │       ├── pages/          # 6개 완성 페이지
│   │       │   ├── dashboard/  # 대시보드
│   │       │   ├── login/      # 로그인
│   │       │   ├── user/       # 사용자 관리
│   │       │   ├── role/       # 역할·권한
│   │       │   ├── config/     # 시스템 설정
│   │       │   ├── log/        # 작업 로그
│   │       │   └── profile/    # 개인 센터
│   │       ├── services/       # ApiService + AuthService
│   │       ├── layouts/        # 반응형 레이아웃
│   │       └── theme/          # Material 3 테마
│   └── harmonyos/              # HarmonyOS 클라이언트
├── config/                     # 설정 파일
│   ├── route.php               # 라우트 + API 버전 정책
│   └── middleware.php           # 전역 미들웨어 등록
├── database/
│   ├── install.sql             # 전체 설치 스크립트 (모든 SQL 병합)
│   └── backup/                 # DB 백업 스크립트
│       ├── backup.sh           # mysqldump+gzip, 30일 보존
│       └── restore.sh          # 대화형 복구
├── docs/                       # 문서
│   ├── ARCHITECTURE.md         # Mermaid 아키텍처 다이어그램
│   ├── DESIGN.md               # 설계 문서
│   ├── SECURITY.md             # 보안 아키텍처 설계
│   ├── API.md                  # API 참조 문서
│   ├── nginx-security.conf     # Nginx 보안 참조 설정
│   ├── diagrams/               # 분해 아키텍처 다이어그램
│   └── superpowers/            # 규범과 계획
│       ├── specs/              # 설계 규범
│       └── plans/              # 구현 계획
├── public/                     # 공용 진입점
├── runtime/                    # 런타임 파일
├── tests/                      # 테스트
├── vendor/                     # Composer 의존성
├── CLAUDE.md                   # 본 파일
├── README.md                   # 중국어 설명
├── README.en.md                # 영어 설명
├── README.ko.md ... README.ja.md  # 다국어 설명 (한/러/독/프/서/포/힌디/아랍/벵골/인니/일)
├── .env                        # 환경 변수 (버전 관리에 포함하지 않음)
├── .env.example                # 환경 변수 템플릿
├── .env.docker                 # Docker 환경 변수
├── composer.json               # PHP 의존성
├── Dockerfile                  # Docker 빌드
├── docker-compose.yml          # Docker 오케스트레이션
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 파이프라인 (PHP 문법+PHPUnit+Flutter analyze)
```

## 미들웨어 실행 체인

```
전역:  Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → {라우트 미들웨어}
/admin: Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → AdminAuth → AdminPermission → OperationLog → Controller
/api:   Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → Controller
/health: Cors → Locale(Accept-Language) → SecurityMiddleware(erikwang2013/security-php) → RateLimit → Controller
```

> **주의**: 권한 검증이 필요 없는 관리자 인터페이스(예: 개인 센터 조회)는 `/admin` 그룹 밖에 별도로 등록하고 `AdminAuth` 미들웨어만 추가하세요. 그룹 내 라우트는 `AdminPermission`이 `method.path` 형식의 권한 식별자를 검증합니다.
> 
> **Redis 접두사**: 모든 key에 자동으로 `open-admin:` 접두사가 추가되며, `.env`의 `REDIS_PREFIX`로 사용자 정의할 수 있습니다.

## 보안 강화

- **공격 탐지**: erikwang2013/security-php 패키지 (31종 탐지기: XSS/SQL 주입/명령 주입/경로 탐색/SSRF/XXE/JNDI/역직렬화/JWT 공격/CSRF/민감 데이터 유출 등 + HTTP 메서드 검증/요청 본문 크기 제한/Content-Type 검증 + IP 공격 단계 상향 블랙리스트)
- **CSP 헤더**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies를 모든 응답에 주입
- **계정 잠금**: 연속 5회 로그인 실패 시 계정 15분 잠금
- **동시 세션 제한**: 동일 사용자 최대 3개 유효 Token, 초과 시 가장 오래된 Token 블랙리스트
- **security.txt**: `/.well-known/security.txt` RFC 9116 엔드포인트
- **Nginx 보안 설정**: `docs/nginx-security.conf` 리버스 프록시 보안 강화 참조

## API 버전 정책

버전은 요청 헤더 `API-Version`으로 제어 (기본 `v1`), URL에 나타나지 않습니다:

```bash
curl http://localhost:8787/api/v1/auth/login
```

새 버전 추가는 `app/api/{version}/controller/` 디렉터리를 만들고 `ApiVersion` 미들웨어에 등록하기만 하면 됩니다.

## 레이트 리밋 정책

Redis 슬라이딩 윈도우 (Lua 원자화), 기본 60회/분/IP/라우트:
- 로그인: 10회/분
- 회원가입: 5회/분
- 응답 헤더: `X-RateLimit-Limit/Remaining/Reset`, 초과 시 `Retry-After` 추가

## 코드 규칙

### PHP
- 전역 함수/클래스 참조에 선행 `\`를 붙이지 않고 `use`로 import
- 설정 파일에 각 설정 항목의 의미를 설명하는 중국어 주석 포함 필수
- 모든 신규 `.php` 파일 헤더에 저작권 고지 포함 필수
- **Redis는 `support\Redis` 유틸리티 클래스로 접근** (싱글턴 연결 풀, `REDIS_HOST/PORT/PASSWORD/DB` 환경 변수 자동 읽기), 모든 key에 자동 접두사 (기본 `open-admin:`, `REDIS_PREFIX` 환경 변수로 설정 가능)
- **라우트 권한**: `/admin` 그룹 내 라우트는 `method.path` 형식의 권한 (예: `get.admin/dashboard`) 필요, 권한 검증이 필요 없는 라우트는 그룹 밖에 두고 `AdminAuth` 미들웨어만 추가
- **CORS**: 새 요청 헤더 추가 시 `Cors.php` 미들웨어와 `route.php` fallback의 `Access-Control-Allow-Headers`를 함께 갱신
- **슈퍼 관리자 보호**: `RoleController`의 `update`/`destroy` 메서드는 `slug == 'super_admin'` 역할을 조작할 수 없음
- webman은 PHP Warning을 예외로 변환하므로, 정의되지 않은 속성/변수는 500 오류를 유발

### 데이터베이스
- 테이블 접두사: `logistics_`
- 기본 키 `id`: BIGINT 타입, 비자동증가, snowflake로 생성
- 민감 필드는 `erikwang2013/encryptable` trait로 자동 암·복호화
- 마이그레이션 파일은 SQL 형식 사용

### Flutter
- 웹 레이아웃은 PC 관리 백엔드 스타일 (사이드바 + 상단바 + 콘텐츠 영역)
- GetX 상태 관리, **모든 API 요청은 `ApiService` 싱글턴을 통해야 함** (Dio + JWT 인터셉터), 독립 Dio 인스턴스 생성 또는 baseUrl 하드코딩 금지
- Token 영속화는 `shared_preferences` 사용
- 반응형 중단점: 모바일 (< 768px)과 데스크톱 (>= 768px)
- **페이지 헤더 Row는 반드시 `Wrap` 사용**하여 사이드바 펼침 시 넘침 방지; 필터 ChoiceChip은 `Obx` 안에 감싸야 반응형 갱신 가능
- **DataTable은 반드시 `SingleChildScrollView(scrollDirection: Axis.horizontal)`로 감싸기** 열 넘침 방지
- 독립 페이지 (예: ProfilePage)는 반드시 `Scaffold` 포함, 그렇지 않으면 `TextField` 등 Material 컴포넌트가 "No Material widget found" 오류 발생
- 사이드바 펼침/접힘 시 `_showCollapsedContent`로 콘텐츠 전환을 지연하여 애니메이션 중 RenderFlex 넘침 방지

### HarmonyOS
- `@ohos.net.http` 네이티브 HTTP 클라이언트 사용
- Token 무감지 갱신: 401 시 자동으로 `/api/v1/auth/refresh` 호출
- 갱신 실패 시 자동으로 로그인 페이지 리다이렉트

## 배포

### Docker Compose (프로덕션 환경 권장)

프로젝트 루트 `docker-compose.yml`이 5개 서비스를 오케스트레이션:

| 서비스 | 설명 |
|------|------|
| `nginx` | Nginx 리버스 프록시 (80/443), 정적 파일 서비스 |
| `app` | webman PHP 8.3 애플리케이션, `Dockerfile` 빌드 (OPcache 포함) |
| `mysql` | MySQL 8.0, 데이터 볼륨 영속화 |
| `redis` | Redis 7 Alpine, 캐시/레이트 리밋/Session |
| `elasticsearch` | Elasticsearch 8.x, 전문 검색 |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml`이 GitHub Actions 파이프라인 정의:

- PHP 문법 검사 (`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석 (`flutter analyze`)

### 데이터베이스 백업

`database/backup/backup.sh` — mysqldump + gzip, 30일 전 이전 백업 자동 정리.
`database/backup/restore.sh` — 대화형 복구, 사용 가능한 백업을 나열해 선택.

### 모니터링

`GET /metrics` 엔드포인트 (`MetricsController`)가 Prometheus text format으로 5개 gauge 지표를 출력:
- `openadmin_http_requests_total` — 요청 총수
- `openadmin_active_users` — 활성 사용자 수
- `openadmin_db_connection_status` — DB 연결 상태 (0/1)
- `openadmin_redis_connection_status` — Redis 연결 상태 (0/1)
- `openadmin_memory_usage_bytes` — 메모리 사용량
