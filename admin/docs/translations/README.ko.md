> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> [中文](../README.md) | [English](README.en.md) | [한국어](README.ko.md) | [Русский](README.ru.md) | [Deutsch](README.de.md) | [Français](README.fr.md) | [Español](README.es.md) | [Português](README.pt.md) | [हिन्दी](README.hi.md) | [العربية](README.ar.md) | [বাংলা](README.bn.md) | [Bahasa Indonesia](README.id.md) | [日本語](README.ja.md)

# 오픈 관리 백엔드 (open-admin)

webman v2 + Flutter 기반의 풀스택 관리 백엔드 시스템입니다.

> [아키텍처 다이어그램](docs/ARCHITECTURE.ko.md) | [설계 문서](docs/DESIGN.ko.md) | [보안 아키텍처](docs/SECURITY.ko.md) | [API 참조](docs/API.ko.md)

## 기능 목록

| 업무 영역 | 기능 | 설명 |
|--------|------|------|
| 🔐 인증 | 로그인/토큰 갱신/로그아웃 | 클릭 캡차 + JWT + 블랙리스트 |
| | 계정 잠금 | 5회 실패 시 15분 잠금 |
| | 동시 세션 제한 | 동일 사용자 최대 3개의 유효 Token |
| 📊 대시보드 | 실시간 통계/추세 차트/분포 차트/최근 작업 | Redis 5분 캐시 |
| 👥 사용자 관리 | CRUD + 일괄 삭제/활성·비활성화 | 소프트 삭제 + 비밀번호 재확인 |
| | Excel 일괄 가져오기 | 행 단위 검증 + 오류 보고 |
| 🔒 역할·권한 | 역할 CRUD + 권한 트리 | RBAC method.path 단위 인가 |
| ⚙ 시스템 설정 | 키-값 CRUD | 그룹 관리 |
| 📋 작업 감사 | 로그 조회 + 출처 단말 감지 | 8개 플랫폼 자동 인식 |
| 📁 파일 관리 | 업로드/Excel 내보내기/PDF 내보내기 | 민감 데이터 자동 마스킹 |
| 🛡 보안 방어 | 18계층 심층 방어 | XSS/SQL 주입/경로 탐색/명령 주입/CSRF/레이트 리밋/CSP... |
| 🏥 운영 | 헬스 체크/metrics/API 문서/security.txt | Prometheus + OpenAPI 3.0 + hg/apidoc 대화형 문서 |
| 🌐 국제화 | 중·영문 전환 | Accept-Language 헤더 / ?lang= 파라미터 |

## 기술 스택

| 계층 | 기술 | 설명 |
|---|------|------|
| 백엔드 프레임워크 | webman v2 (workerman) | 초고성능 PHP 상주 프로세스 프레임워크 |
| PHP 버전 | 8.3+ | |
| 데이터베이스 | MySQL 8.0+ | 테이블 접두사 `logistics_`, BIGINT 비자동증가 기본 키 |
| 검색 엔진 | Elasticsearch | `webman-scout`로 동기화 및 조회 |
| 관리자 프론트엔드 | Flutter 3.x | 웹은 PC 관리 백엔드 스타일(`apps/flutter/`) |
| 모바일 | HarmonyOS ArkTS | HarmonyOS 네이티브 클라이언트(`apps/harmonyos/`), 휴대폰/태블릿/2in1 지원 |

## 핵심 의존성

| 패키지 | 용도 |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake 알고리즘으로 전역 고유 BIGINT 기본 키 생성 |
| `erikwang2013/hashids` | API 계층 ID 암·복호화로 실제 DB ID 숨김 |
| `erikwang2013/jwt-webman` | JWT 인증 토큰 발급과 검증 |
| `erikwang2013/encryption` | 인터페이스 전송 계층 민감 데이터 암·복호화 |
| `erikwang2013/encryptable` | DB 저장 계층 민감 필드 자동 암·복호화 |
| `erikwang2013/webman-scout` | Elasticsearch 데이터 동기화 및 전문 검색 |
| `erikwang2013/season` | 국가 국기 데이터 |
| `erikwang2013/poster-php` | 클릭 캡차 생성·검증 + 포스터 생성 |
| `phpoffice/phpspreadsheet` | Excel 내보내기 |
| `barryvdh/laravel-dompdf` | PDF 내보내기 (Dompdf 기반) |

## 프로젝트 구조

```
open-admin/
├── app/
│   ├── admin/controller/       # 관리자 컨트롤러
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
│   │   └── BaseController.php      # 기본 컨트롤러
│   ├── api/
│   │   └── v1/controller/          # API v1 컨트롤러 (버전은 요청 헤더 API-Version으로 제어)
│   │       ├── CaptchaController.php # 클릭 캡차
│   │       └── AuthController.php    # 로그인/토큰 갱신
│   ├── common/                 # 공용 유틸리티 클래스
│   │   ├── HashidsService.php  # ID 인코딩/디코딩
│   │   ├── SnowflakeService.php# Snowflake ID 생성
│   │   └── EncryptionService.php # 데이터 암·복호화 + 마스킹
│   ├── middleware/             # 미들웨어
│   │   ├── Cors.php            # 크로스 도메인
│   │   ├── SecurityFilter.php  # 공격 탐지 차단 (HTTP 메서드 제한/XSS/SQL 주입/경로 탐색/명령 주입/CSRF)
│   │   ├── RateLimit.php       # Redis 레이트 리밋 (슬라이딩 윈도우 + 응답 헤더)
│   │   ├── ApiVersion.php      # API 버전 검증
│   │   ├── AdminAuth.php       # JWT 인증 + 블랙리스트
│   │   ├── AdminPermission.php # RBAC 권한 검증
│   │   └── OperationLog.php    # 작업 로그 자동 기록 (출처 단말 감지 포함)
│   └── model/                  # 데이터 모델
├── apps/
│   ├── flutter/                # Flutter Web 관리 백엔드 (PC 스타일)
│   │   └── lib/app/
│   │       ├── pages/          # 6개 완성 페이지 (대시보드/사용자/역할/설정/로그/개인 센터)
│   │       ├── services/       # ApiService (JWT 인터셉터) + AuthService (Token 영속화)
│   │       └── layouts/        # 반응형 관리 백엔드 레이아웃 (사이드바+상단바+콘텐츠 영역)
│   └── harmonyos/              # HarmonyOS 네이티브 클라이언트 (Token 무감지 갱신)
├── config/                     # 설정 파일 (중국어 주석 포함)
│   ├── route.php               # 라우트 + API 버전 정책
│   ├── middleware.php           # 전역 미들웨어 등록
│   └── ...                     # 각 구성 요소 설정
├── database/install.sql        # SQL 설치 스크립트 (권한 시드 데이터 포함)
├── public/                     # 공용 진입점
├── runtime/                    # 런타임 파일
└── vendor/                     # Composer 의존성
```

## 환경 요구 사항

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (프론트엔드 개발에만 필요)
- Elasticsearch >= 7.x (선택, 검색 기능에 필요)

## 빠른 시작

### 1. 의존성 설치

```bash
composer install
```

### 2. 환경 변수 설정

환경 변수를 복사하고 수정합니다 (선택 사항, 설정하지 않으면 `config/*.php`의 기본값 사용):

```bash
cp .env.example .env
```

핵심 설정 항목:

| 환경 변수 | 설명 | 기본값 |
|---------|------|--------|
| `JWT_SECRET` | JWT 서명 키 | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Hashids 솔트 값 | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | API 암호화 키 | 32바이트 기본값 |
| `SNOWFLAKE_DATACENTER_ID` | 데이터센터 ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | 워커 노드 ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES 주소 | `http://localhost:9200` |

**프로덕션 환경에서는 반드시 모든 키를 임의의 문자열로 변경하세요.**

### 3. 원클릭 설치

서비스 시작 후 브라우저에서 설치 마법사에 접속하여 데이터베이스 초기화와 관리자 생성을 완료합니다:

```bash
php start.php start
```

기본적으로 `http://0.0.0.0:8787`에서 수신합니다 (포트는 `config/server.php`에서 수정 가능).

브라우저에서 **`http://localhost:8787/install`**을 열고 마법사에 따라 입력합니다:

| 단계 | 내용 |
|------|------|
| ① 데이터베이스 설정 | 호스트 주소, 포트, 데이터베이스 이름, 사용자 이름, 비밀번호 |
| ② 관리자 설정 | 관리자 사용자 이름, 비밀번호 (기본 admin / admin888) |

「설치 시작」을 클릭하면 테이블 생성, 권한 데이터 시드, 관리자 계정 생성이 자동으로 완료되고 `.env`에 데이터베이스 설정이 기록됩니다.

> 설치 완료 후 `runtime/install.lock` 잠금 파일이 생성됩니다. 재설치가 필요하면 이 파일을 삭제하면 됩니다.

### 4. 로그인

`http://localhost:8787`에 접속하여 설치 시 설정한 관리자 계정과 비밀번호로 로그인합니다.

### 5. 프론트엔드 실행 (선택)

**Flutter 관리 백엔드 (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC 관리 백엔드 스타일)
```

**HarmonyOS 클라이언트 (모바일):**

DevEco Studio로 `apps/harmonyos/` 디렉터리를 열고 실기기 또는 에뮬레이터에서 실행합니다.

### 6. Docker Compose 원클릭 배포 (프로덕션 환경 권장)

프로젝트는 완전한 Docker 오케스트레이션을 제공하며 Nginx, PHP (webman app), MySQL, Redis, Elasticsearch 5개 서비스를 포함합니다.

```bash
# 1. Docker 환경 변수 설정
cp .env.docker .env

# 2. 모든 서비스 시작
docker-compose up -d

# 3. 브라우저에서 설치 마법사 접속 후 초기화
# http://localhost:8787/install  (데이터베이스 및 관리자 정보 입력)
# 또는 수동으로 SQL 마이그레이션 실행 (app 컨테이너 진입):
# docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. 접속
# http://localhost:8787  (webman)
# http://localhost:8080  (Nginx 리버스 프록시)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, `php:8.3-cli` 기반
- `docker-compose.yml`: 5개 서비스 오케스트레이션, 네트워크 격리, 데이터 볼륨 영속화
- `.env.docker`: Docker 환경 전용 환경 변수


## 데이터베이스 규칙

- **테이블 접두사**: `logistics_`
- **기본 키**: 모든 테이블의 기본 키는 `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT 금지**
- **ID 생성**: 기본 키 ID는 애플리케이션 계층의 `SnowflakeService::generate()`로 생성, 분산 환경에서 고유
- **필수 필드**: 모든 테이블은 `id`, `created_at`, `updated_at`을 포함해야 함
- **소프트 삭제**: 소프트 삭제가 필요한 테이블은 `deleted_at DATETIME DEFAULT NULL` 추가
- **민감 필드**: 휴대폰 번호, 이메일, 주민등록번호 등은 `encryptable` 플러그인으로 자동 암·복호화, DB 필드는 `VARCHAR(500)`에 암호문 저장

## API 문서

전체 API 참조 (통합 응답 형식, 오류 코드, 전체 엔드포인트 상세, 인증 흐름, 레이트 리밋 정책, 미들웨어 체인)는 **[docs/API.md](docs/API.ko.md)**를 참조하세요. 핵심 요점:

- **통합 응답 형식**: `{ "code": 0, "message": "success", "data": {...} }`, `code=0`은 성공을 의미
- **오류 코드**: `400` 파라미터 오류 / `401` 미로그인 / `403` 권한 없음 / `404` 존재하지 않음 / `422` 검증 실패 / `429` 레이트 리밋 / `500` 서버 오류
- **API 버전**: 요청 헤더 `API-Version: v1`로 제어 (미지정 시 기본 v1), URL에 나타나지 않음
- **인증**: `Authorization: Bearer <token>`; access_token 유효기간 2시간, refresh_token 14일
- **ID 처리**: 요청/응답의 ID는 hashids 암호화 문자열로, 실제 DB ID가 노출되지 않음

## 프론트엔드 설명

### Flutter 관리 백엔드 (PC 스타일)

- **레이아웃**: 사이드바 (64px/240px 접이식) + 상단바 + 콘텐츠 영역, 반응형 3중단 (모바일/태블릿/데스크톱)
- **페이지**: 로그인, 대시보드, 사용자 관리, 역할·권한, 시스템 설정, 작업 로그, 개인 센터
- **상태 관리**: GetX (`ApiService` 싱글턴 + `AuthService` Token 영속화)
- **대시보드**: 통계 카드, 추세 꺾은선 그래프 (fl_chart), 파이 차트, 최근 작업 로그
- **내보내기**: Excel/PDF 내보내기, PDF에는 제거 불가능한 저작권 정보 포함
- **일괄 작업**: 다중 선택 일괄 삭제, 일괄 활성화/비활성화
- **테마**: Material 3 라이트/다크 이중 테마

### HarmonyOS 모바일

- **페이지**: 로그인, 대시보드, 사용자 목록/상세, 개인 센터
- **인증**: JWT Bearer + 401 시 자동 무감지 Token 갱신, 갱신 실패 시 로그인 페이지로 자동 리다이렉트
- **저장**: Token은 AppStorage로 관리

## 개발 규칙

- 전역 함수/클래스 참조에 선행 `\`를 붙이지 않고 `use`로 통일 import
- 모든 PHP 파일 헤더에 저작권 고지를 포함해야 함
- 모든 설정 파일에 중국어 주석으로 각 설정 항목의 의미를 설명해야 함
- DB 기본 키는 반드시 애플리케이션 계층의 snowflake로 생성, 자동 증가 금지
- API 계층의 모든 파라미터와 응답의 ID는 hashids로 암·복호화해야 함
- AdminPermission 미들웨어는 Redis로 사용자 권한을 캐시 (TTL=60s)하여 N+1 쿼리 병목 제거

## 배포

### Docker Compose (권장)

프로젝트 루트에 `docker-compose.yml`이 있으며 5개 서비스를 오케스트레이션합니다:

| 서비스 | 이미지 | 포트 |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | 로컬 `Dockerfile` 빌드 | 8787 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

PHP 이미지는 `Dockerfile`로 빌드하며, 기본 이미지는 `php:8.3-cli`, OPcache가 활성화됩니다.

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

GitHub Actions 지속적 통합 파이프라인: `.github/workflows/ci.yml`

- PHP 문법 검사 (`php -l`)
- PHPUnit 단위 테스트
- Flutter 정적 분석 (`flutter analyze`)

### 데이터베이스 백업

`database/backup/` 디렉터리:

- `backup.sh` — mysqldump + gzip 백업, 30일 전 이전 백업 자동 정리
- `restore.sh` — 대화형 복구, 사용 가능한 백업을 나열해 선택

### Nginx 보안 설정

프로덕션 배포 시 `docs/nginx-security.conf`를 참조하여 리버스 프록시 보안 강화를 구성하세요.

## 오픈소스는 쉽지 않습니다. 응원 부탁드립니다

| 위챗 | 알리페이 |
|:---:|:---:|
| ![위챗](./docs/weixinpay.png "위챗") | ![알리페이](./docs/alipay.png "알리페이") |

### 해외 송금 후원 (국경 간 송금)

**수취인 정보**

- 수취인 성명: WANG KEXUN
- 수취 계좌번호: 881015918251

**수취 은행**

- ZA Bank SWIFT Code: AABLHKHHXXX
- 은행명: ZA Bank Limited
- 은행 번호: 387
- 은행 주소: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**국경 간 송금 대리 은행 (필요 시)**

> 이 정보는 국경 간 송금 대리 은행(중계 은행) 정보이며, 수취 은행 정보가 아닙니다. 송금 은행에 국경 간 송금 대리 은행 정보 제공이 필요한지 문의하시기 바랍니다.

- **홍콩 달러, 위안화, 미국 달러 송금 시** 대리 은행은 Citibank입니다:
  - 은행명: Citibank N.A. Hong Kong
  - SWIFT Code: CITIHKHXXXX
  - 은행 번호: 006
  - 지점명: Hong Kong Branch
  - 지점 번호: 391
  - 은행 주소: Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- **기타 통화 송금 시** 대리 은행은 BNY Mellon입니다:
  - 은행명: THE BANK OF NEW YORK MELLON
  - SWIFT Code: IRVTUS3NXXX
  - 은행 주소: THE BANK OF NEW YORK MELLON, 240 GREENWICH STREET, NEW YORK, United States

---

## 라이선스

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
