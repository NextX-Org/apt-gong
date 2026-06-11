<?php

namespace NextX\AptGong;

class ApiClient
{
  const APIS = [
    'APT_TRADE' => [
      'service' => 'RTMSDataSvcAptTrade',
      'operation' => 'getRTMSDataSvcAptTrade',
      'label' => '아파트 매매',
      'trade_type' => '매매',
      'property_type' => 'apt',
    ],
    'APT_TRADE_DEV' => [
      'service' => 'RTMSDataSvcAptTradeDev',
      'operation' => 'getRTMSDataSvcAptTradeDev',
      'label' => '아파트 매매 상세',
      'trade_type' => '매매',
      'property_type' => 'apt',
    ],
    'APT_RENT' => [
      'service' => 'RTMSDataSvcAptRent',
      'operation' => 'getRTMSDataSvcAptRent',
      'label' => '아파트 전월세',
      'trade_type' => '전월세',
      'property_type' => 'apt',
    ],
    'APT_RIGHTS' => [
      'service' => 'RTMSDataSvcSilvTrade',
      'operation' => 'getRTMSDataSvcSilvTrade',
      'label' => '아파트 분양권전매',
      'trade_type' => '분양권전매',
      'property_type' => 'apt_rights',
    ],
    'OFFI_TRADE' => [
      'service' => 'RTMSDataSvcOffiTrade',
      'operation' => 'getRTMSDataSvcOffiTrade',
      'label' => '오피스텔 매매',
      'trade_type' => '매매',
      'property_type' => 'officetel',
    ],
    'OFFI_RENT' => [
      'service' => 'RTMSDataSvcOffiRent',
      'operation' => 'getRTMSDataSvcOffiRent',
      'label' => '오피스텔 전월세',
      'trade_type' => '전월세',
      'property_type' => 'officetel',
    ],
    'RH_TRADE' => [
      'service' => 'RTMSDataSvcRHTrade',
      'operation' => 'getRTMSDataSvcRHTrade',
      'label' => '연립다세대 매매',
      'trade_type' => '매매',
      'property_type' => 'rowhouse_multi',
    ],
    'RH_RENT' => [
      'service' => 'RTMSDataSvcRHRent',
      'operation' => 'getRTMSDataSvcRHRent',
      'label' => '연립다세대 전월세',
      'trade_type' => '전월세',
      'property_type' => 'rowhouse_multi',
    ],
    'NRG_TRADE' => [
      'service' => 'RTMSDataSvcNrgTrade',
      'operation' => 'getRTMSDataSvcNrgTrade',
      'label' => '상업업무용 매매',
      'trade_type' => '매매',
      'property_type' => 'commercial',
    ],
  ];

  private string $serviceKey;

  public function __construct(string $serviceKey)
  {
    $this->serviceKey = $serviceKey;
  }

  /**
   * API 호출 (페이지 자동 반복)
   */
  public function fetch(string $apiType, string $lawdCd, string $dealYmd): array
  {
    if (!isset(self::APIS[$apiType])) {
      throw new \InvalidArgumentException("알 수 없는 API 타입: {$apiType}");
    }

    $api        = self::APIS[$apiType];
    $url        = API_BASE_URL . "/{$api['service']}/{$api['operation']}";
    $allItems   = [];
    $pageNo     = 1;
    $numOfRows  = 1000;

    do {
      $params = http_build_query([
        'serviceKey' => $this->serviceKey,
        'LAWD_CD'    => $lawdCd,
        'DEAL_YMD'   => $dealYmd,
        'numOfRows'  => $numOfRows,
        'pageNo'     => $pageNo,
      ]);

      $raw        = $this->request("{$url}?{$params}");
      $parsed     = $this->parse($raw, $apiType, $lawdCd);
      $allItems   = array_merge($allItems, $parsed['items']);
      $totalCount = $parsed['total_count'];
      $pageNo++;

      // API 호출 간격 (과호출 방지)
      usleep(API_DELAY_MS * 1000);
    } while (count($allItems) < $totalCount);

    return [
      'items'       => $allItems,
      'total_count' => $totalCount,
      'result_code' => '000',
      'result_msg'  => 'OK',
    ];
  }

  /**
   * curl 요청
   */
  public function request(string $url): string
  {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => API_TIMEOUT,
      CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);

    if ($error) {
      throw new \RuntimeException("API 호출 실패: {$error}");
    }
    if ($httpCode !== 200) {
      throw new \RuntimeException("API 응답 오류: HTTP {$httpCode}");
    }

    return $response;
  }

  /**
   * XML 파싱
   */
  public function parse(string $xml, string $apiType, string $lawdCd = ''): array
  {
    $data = simplexml_load_string($xml);
    if ($data === false) {
      throw new \RuntimeException("XML 파싱 실패");
    }

    $resultCode = trim((string)($data->header->resultCode ?? ''));
    $resultMsg  = trim((string)($data->header->resultMsg ?? ''));

    if (!in_array($resultCode, ['00', '0', '000', '0000'])) {
      throw new \RuntimeException("API 에러 [{$resultCode}]: {$resultMsg}");
    }

    $totalCount = (int)($data->body->totalCount ?? 0);
    $items      = $data->body->items->item ?? [];
    $result     = [];

    foreach ($items as $item) {
      $result[] = $this->normalize($item, $apiType, $lawdCd, $xml);
    }

    return [
      'result_code' => $resultCode,
      'result_msg'  => $resultMsg,
      'total_count' => $totalCount,
      'items'       => $result,
    ];
  }

  /**
   * API 응답 필드 → 공통 구조 정규화
   */
  private function normalize(\SimpleXMLElement $item, string $apiType, string $lawdCd, string $rawXml): array
  {
    $api = self::APIS[$apiType];

    // properties 관련 필드
    $sggCd        = trim((string)($item->sggCd       ?? $item->sggcd       ?? $lawdCd));
    $umdNm        = trim((string)($item->umdNm       ?? $item->umdnm       ?? ''));
    $jibun        = trim((string)($item->jibun       ?? ''));
    $buildingName = trim((string)($item->aptNm       ?? $item->offiNm      ?? $item->mhouseNm ?? ''));
    $aptSeq       = trim((string)($item->aptSeq      ?? $item->aptseq      ?? ''));
    $bonbun       = trim((string)($item->bonbun      ?? ''));
    $bubun        = trim((string)($item->bubun       ?? ''));
    $umdCd        = trim((string)($item->umdCd       ?? $item->umdcd       ?? ''));
    $landCd       = trim((string)($item->landCd      ?? $item->landcd      ?? ''));
    $houseType    = trim((string)($item->houseType   ?? $item->housetype   ?? ''));
    $sggNm        = trim((string)($item->sggNm       ?? $item->sggnm       ?? ''));

    // 도로명 관련
    $roadNm       = trim((string)($item->roadNm      ?? $item->roadnm      ?? ''));
    $roadNmSggCd  = trim((string)($item->roadNmSggCd ?? $item->roadnmsggcd ?? ''));
    $roadNmCd     = trim((string)($item->roadNmCd    ?? $item->roadnmcd    ?? ''));
    $roadNmSeq    = trim((string)($item->roadNmSeq   ?? $item->roadnmseq   ?? ''));
    $roadNmbCd    = trim((string)($item->roadNmbCd   ?? $item->roadnmbcd   ?? ''));
    $roadNmBonbun = trim((string)($item->roadNmBonbun ?? $item->roadnmbonbun ?? ''));
    $roadNmBubun  = trim((string)($item->roadNmBubun  ?? $item->roadnmbubun  ?? ''));

    // transactions 관련 필드
    $dealYear    = trim((string)($item->dealYear    ?? ''));
    $dealMonth   = trim((string)($item->dealMonth   ?? ''));
    $dealDay     = trim((string)($item->dealDay     ?? ''));
    $dealAmount  = $this->parseAmount((string)($item->dealAmount  ?? ''));
    $deposit     = $this->parseAmount((string)($item->deposit     ?? ''));
    $monthlyRent = $this->parseAmount((string)($item->monthlyRent ?? ''));
    $preDeposit     = $this->parseAmount((string)($item->preDeposit     ?? ''));
    $preMonthlyRent = $this->parseAmount((string)($item->preMonthlyRent ?? ''));

    $exclusiveArea  = $this->parseDecimal((string)($item->excluUseAr  ?? ''));
    $landArea       = $this->parseDecimal((string)($item->landAr      ?? ''));
    $buildingArea   = $this->parseDecimal((string)($item->buildingAr  ?? ''));
    $plottageArea   = $this->parseDecimal((string)($item->plottageAr  ?? ''));

    $floor          = trim((string)($item->floor        ?? ''));
    $buildYear      = trim((string)($item->buildYear    ?? '')) ?: null;
    $aptDong        = trim((string)($item->aptDong      ?? ''));
    $cancelType     = trim((string)($item->cdealType    ?? ''));
    $cancelDay      = trim((string)($item->cdealDay     ?? ''));
    $dealingGbn     = trim((string)($item->dealingGbn   ?? ''));
    $estateAgentSggNm = trim((string)($item->estateAgentSggNm ?? ''));
    $rgstDate       = trim((string)($item->rgstDate     ?? ''));
    $sellerGbn      = trim((string)($item->slerGbn      ?? ''));
    $buyerGbn       = trim((string)($item->buyerGbn     ?? ''));
    $landLeaseholdGbn = trim((string)($item->landLeaseholdGbn ?? ''));
    $ownershipGbn   = trim((string)($item->ownershipGbn ?? ''));
    $buildingType   = trim((string)($item->buildingType ?? ''));
    $buildingUse    = trim((string)($item->buildingUse  ?? ''));
    $landUse        = trim((string)($item->landUse      ?? ''));
    $shareDealingType = trim((string)($item->shareDealingType ?? ''));
    $contractTerm   = trim((string)($item->contractTerm ?? ''));
    $contractType   = trim((string)($item->contractType ?? ''));
    $useRRRight     = trim((string)($item->useRRRight   ?? ''));

    // 날짜 조합
    $dealDate = null;
    if ($dealYear && $dealMonth && $dealDay) {
      $dealDate = sprintf('%04d-%02d-%02d', $dealYear, $dealMonth, $dealDay);
    }

    // 주소 텍스트 생성
    $addressText = implode(' ', array_filter([
      $sggNm,
      $umdNm,
      $jibun,
      $buildingName
    ]));
    $geocodeAddressText = implode(' ', array_filter([
      $sggNm,
      $umdNm,
      $jibun
    ]));

    // 중복방지 해시 
    $hashSource = implode('|', [
      $apiType,
      $sggCd,
      $umdNm,
      $jibun,
      $buildingName,
      $dealDate ?? '',
      $exclusiveArea ?? '',
      $floor,
      $dealAmount ?? '',
      $deposit ?? '',
      $monthlyRent ?? '',
      $cancelType,
      $cancelDay,
    ]);
    $uniqueHash = hash('sha256', $hashSource);

    // raw XML (item 태그만 추출)
    $rawItemXml = $item->asXML();

    return [
      // properties
      'property_type'        => $api['property_type'],
      'trade_type'           => $api['trade_type'],
      'sgg_cd'               => $sggCd,
      'sigungu_nm'           => $sggNm,
      'umd_nm'               => $umdNm,
      'umd_cd'               => $umdCd,
      'land_cd'              => $landCd,
      'jibun'                => $jibun,
      'bonbun'               => $bonbun,
      'bubun'                => $bubun,
      'building_name'        => $buildingName,
      'apt_seq'              => $aptSeq,
      'house_type'           => $houseType,
      'road_nm'              => $roadNm,
      'road_nm_sgg_cd'       => $roadNmSggCd,
      'road_nm_cd'           => $roadNmCd,
      'road_nm_seq'          => $roadNmSeq,
      'road_nmb_cd'          => $roadNmbCd,
      'road_nm_bonbun'       => $roadNmBonbun,
      'road_nm_bubun'        => $roadNmBubun,
      'address_text'         => $addressText,
      'geocode_address_text' => $geocodeAddressText,

      // transactions
      'api_type'             => $apiType,
      'deal_year'            => $dealYear ?: null,
      'deal_month'           => $dealMonth ?: null,
      'deal_day'             => $dealDay ?: null,
      'deal_date'            => $dealDate,
      'deal_amount'          => $dealAmount,
      'deposit_amount'       => $deposit,
      'monthly_rent'         => $monthlyRent,
      'exclusive_area'       => $exclusiveArea,
      'land_area'            => $landArea,
      'building_area'        => $buildingArea,
      'plottage_area'        => $plottageArea,
      'floor'                => $floor,
      'build_year'           => $buildYear ? (int)$buildYear : null,
      'apt_dong'             => $aptDong,
      'cancel_type'          => $cancelType,
      'cancel_day'           => $cancelDay,
      'dealing_gbn'          => $dealingGbn,
      'estate_agent_sgg_nm'  => $estateAgentSggNm,
      'registration_date'    => $rgstDate,
      'seller_gbn'           => $sellerGbn,
      'buyer_gbn'            => $buyerGbn,
      'land_leasehold_gbn'   => $landLeaseholdGbn,
      'ownership_gbn'        => $ownershipGbn,
      'building_type'        => $buildingType,
      'building_use'         => $buildingUse,
      'land_use'             => $landUse,
      'share_dealing_type'   => $shareDealingType,
      'contract_term'        => $contractTerm,
      'contract_type'        => $contractType,
      'use_rr_right'         => $useRRRight,
      'pre_deposit'          => $preDeposit,
      'pre_monthly_rent'     => $preMonthlyRent,

      // 공통
      'source_unique_hash'   => $uniqueHash,
      'raw_item_xml'         => $rawItemXml,
    ];
  }

  /**
   * 금액 문자열 → INT (콤마 제거, 빈값 null)
   */
  private function parseAmount(string $val): ?int
  {
    $val = trim(str_replace(',', '', $val));
    return $val !== '' ? (int)$val : null;
  }

  /**
   * 면적 문자열 → FLOAT (빈값 null)
   */
  private function parseDecimal(string $val): ?float
  {
    $val = trim($val);
    return $val !== '' ? (float)$val : null;
  }
}
