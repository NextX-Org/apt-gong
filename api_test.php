<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use NextX\AptGong\ApiClient;

// API 키 있으면 실제 호출, 없으면 구조만 확인
$serviceKey = "7dedf271f3fc43347ac261f78618c38bec9d703d672bdc749b44d62e58ac998e";

echo "<pre>";

if (empty($serviceKey)) {
  echo "ApiClient 클래스 로드 성공\n";
  echo "API 엔드포인트 목록:\n\n";

  foreach (ApiClient::APIS as $type => $info) {
    echo "  [{$type}] {$info['label']}\n";
    echo "   └ {$info['url']}\n";
  }

  echo "\n API 키 없음";
} else {
  try {
    $client = new ApiClient($serviceKey);
    $result = $client->fetch('APT_TRADE_DEV', '11110', '202501');
    echo "수신 건수: " . count($result) . "건\n\n";
    print_r($result[0] ?? '데이터 없음');
  } catch (Exception $e) {
    echo "오류: " . $e->getMessage() . "\n";
  }
}

echo "</pre>";
