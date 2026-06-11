<?php

namespace NextX\AptGong;

class Collector
{
  private \PDO $pdo;
  private ApiClient $api;

  public function __construct()
  {
    $this->pdo = Database::getInstance();
    $this->api = new ApiClient(API_SERVICE_KEY);
  }

  /**
   * 단일 수집 실행
   */
  public function collect(string $apiType, string $lawdCd, string $dealYmd): array
  {
    $logId   = $this->logStart($apiType, $lawdCd, $dealYmd);
    $stats   = ['inserted' => 0, 'duplicated' => 0, 'failed' => 0];

    try {
      $result     = $this->api->fetch($apiType, $lawdCd, $dealYmd);
      $items      = $result['items'];
      $totalCount = $result['total_count'];

      $this->logUpdate($logId, [
        'total_count' => $totalCount,
        'result_code' => $result['result_code'],
        'result_msg'  => $result['result_msg'],
      ]);

      foreach ($items as $item) {
        try {
          $saved = $this->save($item, $lawdCd);
          $saved ? $stats['inserted']++ : $stats['duplicated']++;
        } catch (\Exception $e) {
          $stats['failed']++;
          $this->log("저장 실패: " . $e->getMessage());
        }
      }
    } catch (\Exception $e) {
      $this->logError($logId, $e->getMessage());
      $this->log("수집 실패 [{$apiType}][{$lawdCd}][{$dealYmd}]: " . $e->getMessage());
    }

    $this->logEnd($logId, $stats);
    return $stats;
  }

  /**
   * 전체 수집 (전체 API × 전체 지역 × 기간)
   */
  public function collectAll(
    string $from,
    string $to,
    array  $apiTypes = [],
    array  $lawdCds  = []
  ): array {
    $apis    = $apiTypes ?: array_keys(ApiClient::APIS);
    $regions = $lawdCds  ?: RegionCode::codes();
    $months  = $this->generateMonths($from, $to);
    $total   = ['inserted' => 0, 'duplicated' => 0, 'failed' => 0];

    $this->log("=== 수집 시작 ===");
    $this->log("API: " . implode(', ', $apis));
    $this->log("지역: " . count($regions) . "개");
    $this->log("기간: {$from} ~ {$to} (" . count($months) . "개월)");
    $this->log("총 호출 예상: " . (count($apis) * count($regions) * count($months)) . "건");

    foreach ($apis as $apiType) {
      foreach ($regions as $lawdCd) {
        foreach ($months as $dealYmd) {
          $stats = $this->collect($apiType, $lawdCd, $dealYmd);
          $total['inserted']   += $stats['inserted'];
          $total['duplicated'] += $stats['duplicated'];
          $total['failed']     += $stats['failed'];

          $this->log("[{$apiType}][{$lawdCd}][{$dealYmd}] 신규:{$stats['inserted']} 중복:{$stats['duplicated']} 실패:{$stats['failed']}");
        }
      }
    }

    $this->log("=== 수집 완료 === 신규:{$total['inserted']} 중복:{$total['duplicated']} 실패:{$total['failed']}");
    return $total;
  }

  /**
   * 데이터 저장
   */
  private function save(array $item, string $lawdCd): bool
  {
    // 중복 체크
    $stmt = $this->pdo->prepare(
      'SELECT id FROM transactions WHERE source_unique_hash = ?'
    );
    $stmt->execute([$item['source_unique_hash']]);
    if ($stmt->fetch()) {
      return false; // 중복
    }

    // 지역 정보 보강
    $region = RegionCode::get($lawdCd);

    // properties upsert
    $propertyId = $this->upsertProperty($item, $region);

    // transactions insert
    $this->insertTransaction($item, $propertyId);

    return true;
  }

  /**
   * properties 테이블 upsert
   */
  private function upsertProperty(array $item, ?array $region): int
  {
    // 기존 property 조회
    $stmt = $this->pdo->prepare('
            SELECT id FROM properties
            WHERE property_type = ?
              AND sgg_cd        = ?
              AND umd_nm        = ?
              AND jibun         = ?
              AND building_name = ?
              AND apt_seq       = ?
            LIMIT 1
        ');
    $stmt->execute([
      $item['property_type'],
      $item['sgg_cd'],
      $item['umd_nm'],
      $item['jibun']         ?? '',
      $item['building_name'] ?? '',
      $item['apt_seq']       ?? '',
    ]);
    $existing = $stmt->fetch();

    $lawdCd10 = ($item['sgg_cd'] && ($item['umd_cd'] ?? ''))
      ? $item['sgg_cd'] . $item['umd_cd']
      : null;

    $addressText = implode(' ', array_filter([
      $region['sido_nm']    ?? '',
      $region['sigungu_nm'] ?? $item['sigungu_nm'] ?? '',
      $item['umd_nm']       ?? '',
      $item['jibun']        ?? '',
      $item['building_name'] ?? '',
    ]));
    $geocodeAddressText = implode(' ', array_filter([
      $region['sido_nm']    ?? '',
      $region['sigungu_nm'] ?? $item['sigungu_nm'] ?? '',
      $item['umd_nm']       ?? '',
      $item['jibun']        ?? '',
    ]));

    if ($existing) {
      // 업데이트 (주소 정보 보강)
      $stmt = $this->pdo->prepare('
                UPDATE properties SET
                    sido_nm               = COALESCE(sido_nm, ?),
                    sigungu_nm            = COALESCE(sigungu_nm, ?),
                    umd_cd                = COALESCE(umd_cd, ?),
                    lawd_cd_10            = COALESCE(lawd_cd_10, ?),
                    land_cd               = COALESCE(land_cd, ?),
                    bonbun                = COALESCE(bonbun, ?),
                    bubun                 = COALESCE(bubun, ?),
                    road_nm               = COALESCE(road_nm, ?),
                    road_nm_sgg_cd        = COALESCE(road_nm_sgg_cd, ?),
                    road_nm_cd            = COALESCE(road_nm_cd, ?),
                    road_nm_seq           = COALESCE(road_nm_seq, ?),
                    road_nmb_cd           = COALESCE(road_nmb_cd, ?),
                    road_nm_bonbun        = COALESCE(road_nm_bonbun, ?),
                    road_nm_bubun         = COALESCE(road_nm_bubun, ?),
                    address_text          = ?,
                    geocode_address_text  = ?,
                    updated_at            = NOW()
                WHERE id = ?
            ');
      $stmt->execute([
        $region['sido_nm']     ?? null,
        $region['sigungu_nm']  ?? $item['sigungu_nm'] ?? null,
        $item['umd_cd']        ?? null,
        $lawdCd10,
        $item['land_cd']       ?? null,
        $item['bonbun']        ?? null,
        $item['bubun']         ?? null,
        $item['road_nm']       ?? null,
        $item['road_nm_sgg_cd'] ?? null,
        $item['road_nm_cd']    ?? null,
        $item['road_nm_seq']   ?? null,
        $item['road_nmb_cd']   ?? null,
        $item['road_nm_bonbun'] ?? null,
        $item['road_nm_bubun'] ?? null,
        $addressText,
        $geocodeAddressText,
        $existing['id'],
      ]);
      return (int)$existing['id'];
    }

    // 신규 insert
    $stmt = $this->pdo->prepare('
            INSERT INTO properties (
                property_type, sgg_cd, sido_nm, sigungu_nm,
                umd_nm, umd_cd, lawd_cd_10, land_cd,
                jibun, bonbun, bubun,
                building_name, apt_seq, house_type,
                road_nm, road_nm_sgg_cd, road_nm_cd, road_nm_seq,
                road_nmb_cd, road_nm_bonbun, road_nm_bubun,
                address_text, geocode_address_text
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?
            )
        ');
    $stmt->execute([
      $item['property_type'],
      $item['sgg_cd'],
      $region['sido_nm']     ?? null,
      $region['sigungu_nm']  ?? $item['sigungu_nm'] ?? null,
      $item['umd_nm'],
      $item['umd_cd']        ?? null,
      $lawdCd10,
      $item['land_cd']       ?? null,
      $item['jibun']         ?? null,
      $item['bonbun']        ?? null,
      $item['bubun']         ?? null,
      $item['building_name'] ?? null,
      $item['apt_seq']       ?? null,
      $item['house_type']    ?? null,
      $item['road_nm']       ?? null,
      $item['road_nm_sgg_cd'] ?? null,
      $item['road_nm_cd']    ?? null,
      $item['road_nm_seq']   ?? null,
      $item['road_nmb_cd']   ?? null,
      $item['road_nm_bonbun'] ?? null,
      $item['road_nm_bubun'] ?? null,
      $addressText,
      $geocodeAddressText,
    ]);
    return (int)$this->pdo->lastInsertId();
  }

  /**
   * transactions 테이블 insert
   */
  private function insertTransaction(array $item, int $propertyId): void
  {
    $stmt = $this->pdo->prepare('
            INSERT INTO transactions (
                property_id, api_type, property_type, trade_type,
                deal_year, deal_month, deal_day, deal_date,
                deal_amount, deposit_amount, monthly_rent,
                exclusive_area, land_area, building_area, plottage_area,
                floor, build_year, apt_dong,
                building_type, building_use, land_use,
                ownership_gbn, share_dealing_type,
                cancel_type, cancel_day,
                dealing_gbn, estate_agent_sgg_nm, registration_date,
                seller_gbn, buyer_gbn, land_leasehold_gbn,
                contract_term, contract_type, use_rr_right,
                pre_deposit, pre_monthly_rent,
                source_unique_hash, raw_item_xml, collected_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, NOW()
            )
        ');
    $stmt->execute([
      $propertyId,
      $item['api_type'],
      $item['property_type'],
      $item['trade_type'],
      $item['deal_year']     ?? null,
      $item['deal_month']    ?? null,
      $item['deal_day']      ?? null,
      $item['deal_date']     ?? null,
      $item['deal_amount']   ?? null,
      $item['deposit_amount'] ?? null,
      $item['monthly_rent']  ?? null,
      $item['exclusive_area'] ?? null,
      $item['land_area']     ?? null,
      $item['building_area'] ?? null,
      $item['plottage_area'] ?? null,
      $item['floor']         ?? null,
      $item['build_year']    ?? null,
      $item['apt_dong']      ?? null,
      $item['building_type'] ?? null,
      $item['building_use']  ?? null,
      $item['land_use']      ?? null,
      $item['ownership_gbn'] ?? null,
      $item['share_dealing_type'] ?? null,
      $item['cancel_type']   ?? null,
      $item['cancel_day']    ?? null,
      $item['dealing_gbn']   ?? null,
      $item['estate_agent_sgg_nm'] ?? null,
      $item['registration_date']   ?? null,
      $item['seller_gbn']    ?? null,
      $item['buyer_gbn']     ?? null,
      $item['land_leasehold_gbn']  ?? null,
      $item['contract_term'] ?? null,
      $item['contract_type'] ?? null,
      $item['use_rr_right']  ?? null,
      $item['pre_deposit']   ?? null,
      $item['pre_monthly_rent'] ?? null,
      $item['source_unique_hash'],
      $item['raw_item_xml'],
    ]);
  }

  // 로그헬퍼
  private function logStart(string $apiType, string $lawdCd, string $dealYmd): int
  {
    $stmt = $this->pdo->prepare('
            INSERT INTO api_collect_logs (api_type, lawd_cd, deal_ymd, started_at)
            VALUES (?, ?, ?, NOW())
        ');
    $stmt->execute([$apiType, $lawdCd, $dealYmd]);
    return (int)$this->pdo->lastInsertId();
  }

  private function logUpdate(int $logId, array $data): void
  {
    $stmt = $this->pdo->prepare('
            UPDATE api_collect_logs
            SET total_count = ?, result_code = ?, result_msg = ?
            WHERE id = ?
        ');
    $stmt->execute([
      $data['total_count'],
      $data['result_code'],
      $data['result_msg'],
      $logId,
    ]);
  }

  private function logEnd(int $logId, array $stats): void
  {
    $stmt = $this->pdo->prepare('
            UPDATE api_collect_logs
            SET inserted_count = ?, duplicated_count = ?, failed_count = ?, ended_at = NOW()
            WHERE id = ?
        ');
    $stmt->execute([
      $stats['inserted'],
      $stats['duplicated'],
      $stats['failed'],
      $logId,
    ]);
  }

  private function logError(int $logId, string $message): void
  {
    $stmt = $this->pdo->prepare('
            UPDATE api_collect_logs SET error_message = ?, ended_at = NOW() WHERE id = ?
        ');
    $stmt->execute([$message, $logId]);
  }

  private function log(string $message): void
  {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;

    $logDir = COLLECT_LOG_DIR;
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/collect_' . date('Ymd') . '.log', $line, FILE_APPEND);
  }

  /**
   * 기간 내 월 목록 생성
   * @param string $from '202501'
   * @param string $to   '202506'
   */
  private function generateMonths(string $from, string $to): array
  {
    $months  = [];
    $current = \DateTime::createFromFormat('Ym', $from);
    $end     = \DateTime::createFromFormat('Ym', $to);

    while ($current <= $end) {
      $months[] = $current->format('Ym');
      $current->modify('+1 month');
    }

    return $months;
  }
}
