<?php

/**
 * 수집 실행 스크립트
 *
 * 사용법:
 *   # 당월 전체 수집 (Cron용)
 *   php collect.php
 *
 *   # 특정 API + 지역 + 월 수집
 *   php collect.php --api=APT_TRADE_DEV --lawd_cd=11110 --deal_ymd=202501
 *
 *   # 기간 전체 수집 (초기 적재용)
 *   php collect.php --from=202501 --to=202506
 *
 *   # 특정 API만 기간 수집
 *   php collect.php --from=202501 --to=202506 --api=APT_TRADE_DEV
 */

// CLI 전용
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('CLI 전용 스크립트입니다.');
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use NextX\AptGong\Collector;

// 옵션 파싱
$opts = getopt('', [
  'api:',       // APT_TRADE_DEV 등 특정 API
  'lawd_cd:',   // 특정 지역코드
  'deal_ymd:',  // 특정 년월
  'from:',      // 기간 시작
  'to:',        // 기간 종료
]);

$apiType  = $opts['api']      ?? null;
$lawdCd   = $opts['lawd_cd']  ?? null;
$dealYmd  = $opts['deal_ymd'] ?? null;
$from     = $opts['from']     ?? null;
$to       = $opts['to']       ?? null;


$collector = new Collector();

// 모드 1: 특정 API + 지역 + 월 단건 수집
if ($apiType && $lawdCd && $dealYmd) {
  echo "▶ 단건 수집: [{$apiType}][{$lawdCd}][{$dealYmd}]\n";
  $stats = $collector->collect($apiType, $lawdCd, $dealYmd);
  echo "완료 → 신규:{$stats['inserted']} 중복:{$stats['duplicated']} 실패:{$stats['failed']}\n";
  exit(0);
}

// 모드 2: 기간 지정 전체 수집 (초기 적재용)
if ($from && $to) {
  echo "▶ 기간 수집: {$from} ~ {$to}\n";
  $apiTypes = $apiType ? [$apiType] : [];
  $lawdCds  = $lawdCd  ? [$lawdCd]  : [];
  $stats = $collector->collectAll($from, $to, $apiTypes, $lawdCds);
  echo "완료 → 신규:{$stats['inserted']} 중복:{$stats['duplicated']} 실패:{$stats['failed']}\n";
  exit(0);
}

// 모드 3: 옵션 없음 >>> 당월 전체 수집 (Cron용)
$currentYmd = date('Ym');
echo "▶ 당월 수집: {$currentYmd}\n";
$stats = $collector->collectAll($currentYmd, $currentYmd);
echo "완료 → 신규:{$stats['inserted']} 중복:{$stats['duplicated']} 실패:{$stats['failed']}\n";
exit(0);
