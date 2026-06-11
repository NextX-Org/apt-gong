<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use NextX\AptGong\Database;

$checks = [];

// PHP 버전
$checks[] = ['label' => 'PHP 버전', 'value' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '7.4', '>=')];

// 필수 확장
foreach (['pdo', 'pdo_mysql', 'curl', 'simplexml', 'mbstring'] as $ext) {
  $checks[] = ['label' => "확장: {$ext}", 'value' => extension_loaded($ext) ? '설치됨' : '없음', 'ok' => extension_loaded($ext)];
}

// DB 연결
$db = Database::test();
$checks[] = ['label' => 'MySQL 연결', 'value' => $db['success'] ? "성공 (MySQL {$db['version']}, {$db['now']})" : "실패: {$db['error']}", 'ok' => $db['success']];

// logs 디렉토리
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
$checks[] = ['label' => 'logs 디렉토리', 'value' => is_writable($logDir) ? '쓰기 가능' : '권한 없음', 'ok' => is_writable($logDir)];

// 외부 통신
$ch = curl_init('http://apis.data.go.kr');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_NOBODY => true]);
curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$checks[] = ['label' => '공공API 서버 통신', 'value' => $code > 0 ? "응답 {$code}" : '연결 실패', 'ok' => $code > 0];

$allOk = array_reduce($checks, fn($c, $i) => $c && $i['ok'], true);
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <title>서버 연결 확인</title>
  <style>
    body {
      font-family: sans-serif;
      max-width: 650px;
      margin: 40px auto;
      padding: 0 20px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px;
      border: 1px solid #e5e7eb;
      text-align: left;
    }

    th {
      background: #f9fafb;
    }

    .ok {
      color: #059669;
      font-weight: bold;
    }

    .err {
      color: #dc2626;
      font-weight: bold;
    }

    .banner {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-weight: bold;
    }

    .banner.ok {
      background: #d1fae5;
      color: #065f46;
    }

    .banner.err {
      background: #fee2e2;
      color: #991b1b;
    }
  </style>
</head>

<body>
  <h2>서버 연결 확인</h2>
  <div class="banner <?= $allOk ? 'ok' : 'err' ?>">
    <?= $allOk ? '모든 항목 정상' : '일부 항목 확인 필요' ?>
  </div>
  <table>
    <tr>
      <th>항목</th>
      <th>결과</th>
      <th>상태</th>
    </tr>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><?= $c['label'] ?></td>
        <td><?= htmlspecialchars($c['value']) ?></td>
        <td class="<?= $c['ok'] ? 'ok' : 'err' ?>"><?= $c['ok'] ? 'OK' : 'FAIL' ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>

</html>