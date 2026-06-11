<?php

/**
 * 수동 수집 실행 페이지
 * 브라우저에서 수집을 직접 실행할 수 있는 페이지
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use NextX\AptGong\ApiClient;
use NextX\AptGong\RegionCode;
use NextX\AptGong\Collector;

$result  = null;
$running = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $mode    = $_POST['mode']     ?? 'single';
  $apiType = $_POST['api_type'] ?? '';
  $lawdCd  = $_POST['lawd_cd']  ?? '';
  $dealYmd = $_POST['deal_ymd'] ?? '';
  $from    = $_POST['from']     ?? '';
  $to      = $_POST['to']       ?? '';

  $collector = new Collector();
  $running   = true;

  ob_start();

  if ($mode === 'single' && $apiType && $lawdCd && $dealYmd) {
    $stats = $collector->collect($apiType, $lawdCd, $dealYmd);
  } elseif ($mode === 'range' && $from && $to) {
    $apiTypes = $apiType ? [$apiType] : [];
    $lawdCds  = $lawdCd  ? [$lawdCd]  : [];
    $stats    = $collector->collectAll($from, $to, $apiTypes, $lawdCds);
  } elseif ($mode === 'current') {
    $currentYmd = date('Ym');
    $stats      = $collector->collectAll($currentYmd, $currentYmd);
  }

  $log    = ob_get_clean();
  $result = ['stats' => $stats ?? null, 'log' => $log];
}

$apis    = ApiClient::APIS;
$regions = RegionCode::all();
$thisYm  = date('Ym');
$prevYm  = date('Ym', strtotime('-1 month'));
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>수동 수집 실행 - apt_gong</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, sans-serif;
      max-width: 860px;
      margin: 40px auto;
      padding: 0 20px;
      color: #333;
      background: #f9fafb;
    }

    h1 {
      font-size: 1.3rem;
      margin-bottom: 4px;
    }

    p.desc {
      font-size: 0.85rem;
      color: #6b7280;
      margin-bottom: 24px;
    }

    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 24px;
      margin-bottom: 20px;
    }

    .card h2 {
      font-size: 1rem;
      margin: 0 0 16px;
    }

    .tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
    }

    .tab {
      padding: 8px 18px;
      border-radius: 6px;
      border: 1px solid #d1d5db;
      background: #fff;
      cursor: pointer;
      font-size: 0.9rem;
    }

    .tab.active {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
    }

    .form-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 4px;
      flex: 1;
      min-width: 180px;
    }

    label {
      font-size: 0.8rem;
      color: #6b7280;
      font-weight: 500;
    }

    select,
    input {
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 0.9rem;
      background: #fff;
    }

    .btn {
      padding: 10px 24px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 0.95rem;
      cursor: pointer;
      font-weight: 600;
    }

    .btn:hover {
      background: #1d4ed8;
    }

    .btn-current {
      background: #059669;
    }

    .btn-current:hover {
      background: #047857;
    }

    .panel {
      display: none;
    }

    .panel.active {
      display: block;
    }

    .result {
      margin-top: 20px;
    }

    .stats {
      display: flex;
      gap: 12px;
      margin-bottom: 16px;
    }

    .stat {
      flex: 1;
      padding: 14px;
      border-radius: 8px;
      text-align: center;
    }

    .stat.inserted {
      background: #d1fae5;
      color: #065f46;
    }

    .stat.duplicated {
      background: #fef3c7;
      color: #92400e;
    }

    .stat.failed {
      background: #fee2e2;
      color: #991b1b;
    }

    .stat .num {
      font-size: 1.6rem;
      font-weight: bold;
    }

    .stat .lbl {
      font-size: 0.8rem;
      margin-top: 2px;
    }

    .log-box {
      background: #1e1e1e;
      color: #d4d4d4;
      padding: 16px;
      border-radius: 8px;
      font-size: 0.8rem;
      font-family: monospace;
      white-space: pre-wrap;
      max-height: 400px;
      overflow-y: auto;
    }

    .hidden {
      display: none;
    }
  </style>
</head>

<body>

  <h1>수동 수집 실행</h1>
  <p class="desc">수집 실행 후 결과를 확인할 수 있습니다. 대량 수집 시 시간이 걸릴 수 있습니다.</p>

  <div class="card">
    <div class="tabs">
      <button class="tab active" onclick="switchTab('single', this)">단건 수집</button>
      <button class="tab" onclick="switchTab('range', this)">기간 수집</button>
      <button class="tab" onclick="switchTab('current', this)">당월 전체 수집</button>
    </div>

    <!-- 단건 수집 -->
    <div id="panel-single" class="panel active">
      <form method="POST">
        <input type="hidden" name="mode" value="single">
        <div class="form-row">
          <div class="form-group">
            <label>API 유형</label>
            <select name="api_type" required>
              <option value="">선택</option>
              <?php foreach ($apis as $key => $info): ?>
                <option value="<?= $key ?>"><?= $key ?> — <?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>지역코드</label>
            <select name="lawd_cd" required>
              <option value="">선택</option>
              <?php foreach ($regions as $code => $info): ?>
                <option value="<?= $code ?>"><?= $code ?> <?= $info['sigungu_nm'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>계약년월 (YYYYMM)</label>
            <input type="text" name="deal_ymd" value="<?= $prevYm ?>" maxlength="6" required>
          </div>
        </div>
        <button type="submit" class="btn">▶ 수집 실행</button>
      </form>
    </div>

    <!-- 기간 수집 -->
    <div id="panel-range" class="panel">
      <form method="POST">
        <input type="hidden" name="mode" value="range">
        <div class="form-row">
          <div class="form-group">
            <label>API 유형 (비우면 전체)</label>
            <select name="api_type">
              <option value="">전체</option>
              <?php foreach ($apis as $key => $info): ?>
                <option value="<?= $key ?>"><?= $key ?> — <?= $info['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>지역코드 (비우면 전체)</label>
            <select name="lawd_cd">
              <option value="">전체 (252개)</option>
              <?php foreach ($regions as $code => $info): ?>
                <option value="<?= $code ?>"><?= $code ?> <?= $info['sigungu_nm'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>시작 월 (YYYYMM)</label>
            <input type="text" name="from" value="<?= $prevYm ?>" maxlength="6" required>
          </div>
          <div class="form-group">
            <label>종료 월 (YYYYMM)</label>
            <input type="text" name="to" value="<?= $thisYm ?>" maxlength="6" required>
          </div>
        </div>
        <button type="submit" class="btn">▶ 수집 실행</button>
      </form>
    </div>

    <!-- 당월 전체 수집 -->
    <div id="panel-current" class="panel">
      <form method="POST">
        <input type="hidden" name="mode" value="current">
        <p style="color:#6b7280; font-size:0.9rem; margin-bottom:16px">
          당월 (<strong><?= $thisYm ?></strong>) 전체 API × 252개 지역 데이터를 수집합니다.<br>
          약 2,500건 이상의 API 호출이 발생합니다.
        </p>
        <button type="submit" class="btn btn-current">▶ 당월 전체 수집 실행</button>
      </form>
    </div>
  </div>

  <?php if ($result): ?>
    <div class="card result">
      <h2>📊 수집 결과</h2>
      <?php if ($result['stats']): ?>
        <div class="stats">
          <div class="stat inserted">
            <div class="num"><?= number_format($result['stats']['inserted']) ?></div>
            <div class="lbl">신규 저장</div>
          </div>
          <div class="stat duplicated">
            <div class="num"><?= number_format($result['stats']['duplicated']) ?></div>
            <div class="lbl">중복 제외</div>
          </div>
          <div class="stat failed">
            <div class="num"><?= number_format($result['stats']['failed']) ?></div>
            <div class="lbl">실패</div>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($result['log']): ?>
        <div class="log-box"><?= htmlspecialchars($result['log']) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <script>
    function switchTab(name, el) {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
      el.classList.add('active');
      document.getElementById('panel-' + name).classList.add('active');
    }
  </script>

</body>

</html>