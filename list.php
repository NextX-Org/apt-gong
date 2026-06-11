<?php

/**
 * 데이터 확인용 목록 페이지
 */
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';

use NextX\AptGong\Database;
use NextX\AptGong\ApiClient;
use NextX\AptGong\RegionCode;

$pdo = Database::getInstance();

// 필터 파라미터
$apiType     = $_GET['api_type']     ?? '';
$propertyType = $_GET['property_type'] ?? '';
$sggCd       = $_GET['sgg_cd']       ?? '';
$tradeType   = $_GET['trade_type']   ?? '';
$dealYm      = $_GET['deal_ym']      ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;
$offset      = ($page - 1) * $perPage;

// 쿼리 조건 빌드
$where  = ['1=1'];
$params = [];

if ($apiType) {
  $where[]  = 't.api_type = ?';
  $params[] = $apiType;
}
if ($propertyType) {
  $where[]  = 't.property_type = ?';
  $params[] = $propertyType;
}
if ($sggCd) {
  $where[]  = 'p.sgg_cd = ?';
  $params[] = $sggCd;
}
if ($tradeType) {
  $where[]  = 't.trade_type = ?';
  $params[] = $tradeType;
}
if ($dealYm) {
  $where[]  = "DATE_FORMAT(t.deal_date, '%Y%m') = ?";
  $params[] = $dealYm;
}

$whereStr = implode(' AND ', $where);

// 전체 건수
$countSql = "
    SELECT COUNT(*) FROM transactions t
    JOIN properties p ON t.property_id = p.id
    WHERE {$whereStr}
";
$totalCount = (int)$pdo->prepare($countSql)->execute($params) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = (int)$stmt->fetchColumn();
$totalPages = max(1, ceil($totalCount / $perPage));

// 목록 조회
$listSql = "
    SELECT
        t.id,
        t.api_type,
        t.property_type,
        t.trade_type,
        t.deal_date,
        t.deal_amount,
        t.deposit_amount,
        t.monthly_rent,
        t.exclusive_area,
        t.floor,
        t.build_year,
        t.dealing_gbn,
        p.sido_nm,
        p.sigungu_nm,
        p.umd_nm,
        p.jibun,
        p.building_name,
        p.sgg_cd,
        t.collected_at
    FROM transactions t
    JOIN properties p ON t.property_id = p.id
    WHERE {$whereStr}
    ORDER BY t.id DESC
    LIMIT {$perPage} OFFSET {$offset}
";
$stmt = $pdo->prepare($listSql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// 통계 요약
$summarySql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN t.trade_type = '매매' THEN 1 ELSE 0 END) AS trade_cnt,
        SUM(CASE WHEN t.trade_type = '전월세' THEN 1 ELSE 0 END) AS rent_cnt,
        MAX(t.collected_at) AS last_collected
    FROM transactions t
    JOIN properties p ON t.property_id = p.id
";
$summary = $pdo->query($summarySql)->fetch();

$apis    = ApiClient::APIS;
$regions = RegionCode::all();

// 페이지네이션 URL 헬퍼
function pageUrl(int $p): string
{
  $params = $_GET;
  $params['page'] = $p;
  return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ko">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>실거래가 데이터 목록 - apt_gong</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, sans-serif;
      max-width: 1200px;
      margin: 30px auto;
      padding: 0 20px;
      color: #333;
      background: #f9fafb;
    }

    h1 {
      font-size: 1.2rem;
      margin-bottom: 4px;
    }

    p.desc {
      font-size: 0.82rem;
      color: #6b7280;
      margin-bottom: 20px;
    }

    /* 요약 통계 */
    .summary {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .summary-item {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 14px 20px;
      flex: 1;
      min-width: 150px;
    }

    .summary-item .num {
      font-size: 1.4rem;
      font-weight: bold;
      color: #2563eb;
    }

    .summary-item .lbl {
      font-size: 0.78rem;
      color: #6b7280;
      margin-top: 2px;
    }

    /* 필터 */
    .filter-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 16px;
    }

    .filter-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: flex-end;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .filter-group label {
      font-size: 0.75rem;
      color: #6b7280;
      font-weight: 500;
    }

    select,
    input[type=text] {
      padding: 7px 10px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 0.85rem;
      background: #fff;
    }

    .btn-filter {
      padding: 7px 18px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
    }

    .btn-reset {
      padding: 7px 14px;
      background: #fff;
      color: #6b7280;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
    }

    /* 테이블 */
    .table-wrap {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 16px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.82rem;
    }

    th {
      background: #f3f4f6;
      padding: 10px 12px;
      text-align: left;
      font-weight: 600;
      color: #374151;
      border-bottom: 1px solid #e5e7eb;
      white-space: nowrap;
    }

    td {
      padding: 9px 12px;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: #f9fafb;
    }

    /* 뱃지 */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 0.72rem;
      font-weight: 600;
    }

    .badge-trade {
      background: #dbeafe;
      color: #1e40af;
    }

    .badge-rent {
      background: #d1fae5;
      color: #065f46;
    }

    .badge-rights {
      background: #ede9fe;
      color: #5b21b6;
    }

    .badge-apt {
      background: #fef3c7;
      color: #92400e;
    }

    .badge-offi {
      background: #e0e7ff;
      color: #3730a3;
    }

    .badge-rh {
      background: #fce7f3;
      color: #9d174d;
    }

    .badge-comm {
      background: #f3f4f6;
      color: #374151;
    }

    /* 금액 */
    .amount {
      font-weight: 600;
      color: #111;
    }

    /* 페이지네이션 */
    .pagination {
      display: flex;
      gap: 4px;
      justify-content: center;
      flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
      padding: 6px 12px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 0.82rem;
      text-decoration: none;
      color: #374151;
      background: #fff;
    }

    .pagination .active {
      background: #2563eb;
      color: #fff;
      border-color: #2563eb;
    }

    .pagination a:hover {
      background: #f3f4f6;
    }

    .no-data {
      text-align: center;
      padding: 40px;
      color: #9ca3af;
    }

    .total-info {
      font-size: 0.82rem;
      color: #6b7280;
      margin-bottom: 8px;
    }

    .link-manual {
      font-size: 0.82rem;
      color: #2563eb;
      text-decoration: none;
    }

    .link-manual:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>

  <h1>📋 실거래가 데이터 목록</h1>
  <p class="desc">
    수집된 실거래가 데이터를 확인합니다.
    <a href="manual_collect.php" class="link-manual">→ 수동 수집 실행</a>
  </p>

  <!-- 요약 통계 -->
  <div class="summary">
    <div class="summary-item">
      <div class="num"><?= number_format($summary['total'] ?? 0) ?></div>
      <div class="lbl">전체 거래 건수</div>
    </div>
    <div class="summary-item">
      <div class="num"><?= number_format($summary['trade_cnt'] ?? 0) ?></div>
      <div class="lbl">매매</div>
    </div>
    <div class="summary-item">
      <div class="num"><?= number_format($summary['rent_cnt'] ?? 0) ?></div>
      <div class="lbl">전월세</div>
    </div>
    <div class="summary-item">
      <div class="num" style="font-size:1rem"><?= $summary['last_collected'] ? date('m/d H:i', strtotime($summary['last_collected'])) : '-' ?></div>
      <div class="lbl">마지막 수집</div>
    </div>
  </div>

  <!-- 필터 -->
  <div class="filter-card">
    <form method="GET">
      <div class="filter-row">
        <div class="filter-group">
          <label>API 유형</label>
          <select name="api_type">
            <option value="">전체</option>
            <?php foreach ($apis as $key => $info): ?>
              <option value="<?= $key ?>" <?= $apiType === $key ? 'selected' : '' ?>><?= $key ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>거래유형</label>
          <select name="trade_type">
            <option value="">전체</option>
            <option value="매매" <?= $tradeType === '매매'     ? 'selected' : '' ?>>매매</option>
            <option value="전월세" <?= $tradeType === '전월세'   ? 'selected' : '' ?>>전월세</option>
            <option value="분양권전매" <?= $tradeType === '분양권전매' ? 'selected' : '' ?>>분양권전매</option>
          </select>
        </div>
        <div class="filter-group">
          <label>지역</label>
          <select name="sgg_cd">
            <option value="">전체</option>
            <?php foreach ($regions as $code => $info): ?>
              <option value="<?= $code ?>" <?= $sggCd === $code ? 'selected' : '' ?>>
                <?= $info['sigungu_nm'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label>계약년월 (YYYYMM)</label>
          <input type="text" name="deal_ym" value="<?= htmlspecialchars($dealYm) ?>" maxlength="6" placeholder="202501">
        </div>
        <button type="submit" class="btn-filter">검색</button>
        <a href="list.php" class="btn-reset">초기화</a>
      </div>
    </form>
  </div>

  <!-- 목록 -->
  <div class="total-info">총 <?= number_format($totalCount) ?>건 | <?= $page ?> / <?= $totalPages ?> 페이지</div>

  <div class="table-wrap">
    <?php if (empty($rows)): ?>
      <div class="no-data">데이터가 없습니다.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>유형</th>
            <th>거래</th>
            <th>지역</th>
            <th>건물명</th>
            <th>지번</th>
            <th>계약일</th>
            <th>금액(만원)</th>
            <th>면적(㎡)</th>
            <th>층</th>
            <th>건축년도</th>
            <th>거래유형</th>
            <th>수집일시</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php
            $badgeClass = match ($row['property_type']) {
              'apt'            => 'badge-apt',
              'officetel'      => 'badge-offi',
              'rowhouse_multi' => 'badge-rh',
              'commercial'     => 'badge-comm',
              default          => 'badge-comm',
            };
            $tradeClass = match ($row['trade_type']) {
              '매매'       => 'badge-trade',
              '전월세'     => 'badge-rent',
              '분양권전매' => 'badge-rights',
              default      => '',
            };
            $amount = $row['deal_amount']
              ? number_format($row['deal_amount']) . '만'
              : ($row['deposit_amount'] ? '보증 ' . number_format($row['deposit_amount']) . '만' . ($row['monthly_rent'] ? ' / 월 ' . number_format($row['monthly_rent']) . '만' : '') : '-');
            ?>
            <tr>
              <td style="color:#9ca3af"><?= $row['id'] ?></td>
              <td><span class="badge <?= $badgeClass ?>"><?= $row['property_type'] ?></span></td>
              <td><span class="badge <?= $tradeClass ?>"><?= $row['trade_type'] ?></span></td>
              <td><?= htmlspecialchars(($row['sigungu_nm'] ?? '') . ' ' . ($row['umd_nm'] ?? '')) ?></td>
              <td><?= htmlspecialchars($row['building_name'] ?? '-') ?></td>
              <td style="color:#6b7280"><?= htmlspecialchars($row['jibun'] ?? '-') ?></td>
              <td><?= $row['deal_date'] ?? '-' ?></td>
              <td class="amount"><?= $amount ?></td>
              <td><?= $row['exclusive_area'] ? number_format($row['exclusive_area'], 2) : '-' ?></td>
              <td><?= $row['floor'] ?: '-' ?></td>
              <td><?= $row['build_year'] ?: '-' ?></td>
              <td style="color:#6b7280; font-size:0.78rem"><?= $row['dealing_gbn'] ?: '-' ?></td>
              <td style="color:#9ca3af; font-size:0.78rem"><?= date('m/d H:i', strtotime($row['collected_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- 페이지네이션 -->
  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="<?= pageUrl(1) ?>">«</a>
        <a href="<?= pageUrl($page - 1) ?>">‹</a>
      <?php endif; ?>

      <?php
      $start = max(1, $page - 2);
      $end   = min($totalPages, $page + 2);
      for ($i = $start; $i <= $end; $i++):
      ?>
        <?php if ($i === $page): ?>
          <span class="active"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= pageUrl($i) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="<?= pageUrl($page + 1) ?>">›</a>
        <a href="<?= pageUrl($totalPages) ?>">»</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</body>

</html>