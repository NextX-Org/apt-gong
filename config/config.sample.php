<?php
// DB 설정
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'apt_gong');
define('DB_USER', 'apt_gong');
define('DB_PASS', 'apt_gong1234');

// 공공데이터 API 설정
define('API_SERVICE_KEY', '');
define('API_BASE_URL', 'http://apis.data.go.kr/1613000');
define('API_TIMEOUT', 30);
define('API_DELAY_MS', 200);

// 수집 설정
define('COLLECT_LOG_DIR', __DIR__ . '/../logs');

// 환경 설정
define('APP_TIMEZONE', 'Asia/Seoul');
date_default_timezone_set(APP_TIMEZONE);
