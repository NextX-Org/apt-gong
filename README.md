# apt-gong — 국토교통부 실거래가 수집 시스템

국토교통부 공공데이터 API를 활용하여 전국 부동산 실거래가 데이터를 수집하고 MySQL에 저장하는 시스템

## 기술 스택

- PHP 8.3.6
- MySQL / MariaDB
- Apache
- Composer

## 디렉토리 구조

```
apt-gong/
├── .github/
│   └── workflows/
│       └── deploy.yml         # GitHub Actions 자동 배포
├── config/
│   ├── config.php             # 실제 설정
│   └── config.sample.php      # 설정 샘플
├── sql/
│   └── create_tables.sql      # DB 테이블 생성 스크립트
├── src/
│   ├── Database.php           # DB 연결 클래스 (PDO 싱글톤)
│   ├── ApiClient.php          # 공공 API 호출 및 파싱
│   ├── Collector.php          # 데이터 수집 및 저장 로직
│   └── RegionCode.php         # 전국 252개 시군구 법정동코드
├── logs/                      # 수집 로그 (gitignore)
├── check.php                  # 서버 연결 확인 페이지
├── collect.php                # CLI 수집 스크립트 (Cron용)
├── manual_collect.php         # 수동 수집 실행 페이지
└── list.php                   # 데이터 확인용 목록 페이지
```

## 수집 대상 API

| API Key | 설명 |
|---|---|
| APT_TRADE | 아파트 매매 |
| APT_TRADE_DEV | 아파트 매매 상세 |
| APT_RENT | 아파트 전월세 |
| APT_RIGHTS | 아파트 분양권전매 |
| OFFI_TRADE | 오피스텔 매매 |
| OFFI_RENT | 오피스텔 전월세 |
| RH_TRADE | 연립다세대 매매 |
| RH_RENT | 연립다세대 전월세 |
| NRG_TRADE | 상업업무용 매매 |

## 초기 세팅 (서버)

### 1. 코드 배포
```bash
cd /home/apt_gong
git clone https://github.com/NextX-Org/apt-gong.git www
cd www
```

### 2. Composer 설치 (서버에 composer 없는 경우)
```bash
cd /home/apt_gong
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
cd www
php /home/apt_gong/composer.phar install --no-dev
```

### 3. config.php 생성
```bash
cp config/config.sample.php config/config.php
nano config/config.php
```
아래 두 항목 입력:
- `DB_PASS` : 데이터베이스 비밀번호
- `API_SERVICE_KEY` : data.go.kr 발급 API 키

### 4. logs 디렉토리 생성 및 권한 설정
```bash
mkdir -p logs
chmod 777 logs
```

### 5. DB 테이블 생성 (최초 1회)
```bash
mysql -u apt_gong -p apt_gong < sql/create_tables.sql
```

### 6. 서버 연결 확인
브라우저에서 `/check.php` 접속 후 모든 항목 확인
> 확인 후 `check.php` 삭제 권장

## Crontab 등록

```bash
crontab -e
```

매일 새벽 3시 자동 수집:
```
0 3 * * * /usr/bin/php /home/apt_gong/www/collect.php >> /home/apt_gong/www/logs/cron.log 2>&1
```

## CLI 수동 실행

```bash
# 당월 전체 수집
php collect.php

# 특정 API + 지역 + 월 단건 수집
php collect.php --api=APT_TRADE --lawd_cd=11110 --deal_ymd=202501

# 기간 전체 수집 (초기 적재용)
php collect.php --from=202501 --to=202506

# 특정 API만 기간 수집
php collect.php --from=202501 --to=202506 --api=APT_TRADE
```

## GitHub Actions 자동 배포

`main` 브랜치에 push 시 서버에 자동 배포됩니다.

GitHub Repository Secrets 등록 필요:

| Secret | 설명 |
|---|---|
| `SSH_HOST` | 서버 IP |
| `SSH_USER` | SSH 계정 |
| `SSH_PASSWORD` | SSH 비밀번호 |
| `DEPLOY_PATH` | 서버 배포 경로 |

## DB 테이블 구조

| 테이블 | 설명 |
|---|---|
| `properties` | 부동산 마스터 (단지/건물 정보) |
| `transactions` | 실거래가 (거래 정보) |
| `api_collect_logs` | API 수집 로그 |

## 페이지

| URL | 설명 |
|---|---|
| `/list.php` | 수집된 데이터 확인용 목록 |
| `/manual_collect.php` | 수동 수집 실행 |