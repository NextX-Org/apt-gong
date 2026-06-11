SET NAMES utf8mb4;
SET time_zone = '+09:00';

-- properties
CREATE TABLE IF NOT EXISTS `properties` (
  `id`                    BIGINT        NOT NULL AUTO_INCREMENT,
  `property_type`         VARCHAR(30)   NOT NULL COMMENT 'apt/officetel/rowhouse_multi/commercial/apt_rights',
  `sgg_cd`                CHAR(5)       NOT NULL COMMENT '법정동 시군구코드 (LAWD_CD)',
  `sido_nm`               VARCHAR(30)   NULL     COMMENT '시도명',
  `sigungu_nm`            VARCHAR(30)   NULL     COMMENT '시군구명',
  `umd_nm`                VARCHAR(60)   NOT NULL COMMENT '법정동명',
  `umd_cd`                CHAR(5)       NULL     COMMENT '법정동읍면동코드',
  `lawd_cd_10`            CHAR(10)      NULL     COMMENT '법정동코드 10자리 (sggCd+umdCd)',
  `land_cd`               CHAR(1)       NULL     COMMENT '법정동지번코드',
  `jibun`                 VARCHAR(20)   NULL     COMMENT '지번',
  `bonbun`                CHAR(4)       NULL     COMMENT '본번',
  `bubun`                 CHAR(4)       NULL     COMMENT '부번',
  `building_name`         VARCHAR(150)  NULL     COMMENT '단지명/건물명',
  `apt_seq`               VARCHAR(20)   NULL     COMMENT '단지 일련번호',
  `house_type`            VARCHAR(20)   NULL     COMMENT '연립/다세대 구분',
  `road_nm`               VARCHAR(100)  NULL     COMMENT '도로명',
  `road_nm_full`          VARCHAR(255)  NULL     COMMENT '도로명 주소 원문',
  `road_nm_sgg_cd`        CHAR(5)       NULL     COMMENT '도로명시군구코드',
  `road_nm_cd`            CHAR(7)       NULL     COMMENT '도로명코드',
  `road_nm_seq`           CHAR(2)       NULL     COMMENT '도로명일련번호코드',
  `road_nmb_cd`           CHAR(1)       NULL     COMMENT '도로명지상지하코드',
  `road_nm_bonbun`        CHAR(5)       NULL     COMMENT '도로명건물본번호코드',
  `road_nm_bubun`         CHAR(5)       NULL     COMMENT '도로명건물부번호코드',
  `address_text`          VARCHAR(255)  NOT NULL COMMENT '표시용 주소 문자열',
  `geocode_address_text`  VARCHAR(255)  NOT NULL COMMENT '좌표 변환용 주소 (건물명 제외)',
  `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_property` (`property_type`, `sgg_cd`, `umd_nm`, `jibun`, `building_name`, `apt_seq`),
  INDEX `idx_sgg_cd`       (`sgg_cd`),
  INDEX `idx_property_type` (`property_type`),
  INDEX `idx_building_name` (`building_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='부동산 마스터 테이블';


-- transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id`                    BIGINT          NOT NULL AUTO_INCREMENT,
  `property_id`           BIGINT          NOT NULL COMMENT 'properties.id FK',
  `api_type`              VARCHAR(30)     NOT NULL COMMENT 'APT_TRADE / APT_RENT 등',
  `property_type`         VARCHAR(30)     NOT NULL COMMENT '부동산 유형 (조회 성능용 중복 저장)',
  `trade_type`            VARCHAR(30)     NOT NULL COMMENT '매매/전월세/분양권전매',
  `deal_year`             SMALLINT        NULL,
  `deal_month`            TINYINT         NULL,
  `deal_day`              TINYINT         NULL,
  `deal_date`             DATE            NULL     COMMENT '계약일자 (deal_year/month/day 조합)',
  `deal_amount`           INT             NULL     COMMENT '거래금액 (만원)',
  `deposit_amount`        INT             NULL     COMMENT '보증금액 (만원)',
  `monthly_rent`          INT             NULL     COMMENT '월세금액 (만원)',
  `exclusive_area`        DECIMAL(12,4)   NULL     COMMENT '전용면적',
  `land_area`             DECIMAL(12,4)   NULL     COMMENT '대지권면적',
  `building_area`         DECIMAL(12,4)   NULL     COMMENT '건물면적',
  `plottage_area`         DECIMAL(12,4)   NULL     COMMENT '대지면적',
  `floor`                 VARCHAR(10)     NULL     COMMENT '층 (지하/음수 가능)',
  `build_year`            SMALLINT        NULL     COMMENT '건축년도',
  `apt_dong`              VARCHAR(100)    NULL     COMMENT '아파트 동명',
  `building_type`         VARCHAR(20)     NULL     COMMENT '건물유형 (상업업무용)',
  `building_use`          VARCHAR(100)    NULL     COMMENT '건물주용도 (상업업무용)',
  `land_use`              VARCHAR(100)    NULL     COMMENT '용도지역 (상업업무용)',
  `ownership_gbn`         VARCHAR(10)     NULL     COMMENT '분양권/입주권 구분',
  `share_dealing_type`    VARCHAR(20)     NULL     COMMENT '지분거래구분',
  `cancel_type`           VARCHAR(10)     NULL     COMMENT '해제여부',
  `cancel_day`            VARCHAR(8)      NULL     COMMENT '해제사유발생일',
  `dealing_gbn`           VARCHAR(20)     NULL     COMMENT '거래유형 (중개/직거래)',
  `estate_agent_sgg_nm`   VARCHAR(255)    NULL     COMMENT '중개사 소재지',
  `registration_date`     VARCHAR(8)      NULL     COMMENT '등기일자',
  `seller_gbn`            VARCHAR(100)    NULL     COMMENT '매도자 구분',
  `buyer_gbn`             VARCHAR(100)    NULL     COMMENT '매수자 구분',
  `land_leasehold_gbn`    CHAR(1)         NULL     COMMENT '토지임대부 여부 Y/N',
  `contract_term`         VARCHAR(20)     NULL     COMMENT '계약기간',
  `contract_type`         VARCHAR(20)     NULL     COMMENT '계약구분 (신규/갱신)',
  `use_rr_right`          VARCHAR(20)     NULL     COMMENT '갱신요구권 사용',
  `pre_deposit`           INT             NULL     COMMENT '종전계약 보증금 (만원)',
  `pre_monthly_rent`      INT             NULL     COMMENT '종전계약 월세 (만원)',
  `source_unique_hash`    CHAR(64)        NOT NULL COMMENT '중복방지 SHA256 해시',
  `raw_item_xml`          MEDIUMTEXT      NOT NULL COMMENT 'API 원본 XML',
  `collected_at`          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '수집일시',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hash`         (`source_unique_hash`),
  INDEX `idx_property_id`      (`property_id`),
  INDEX `idx_api_type`         (`api_type`),
  INDEX `idx_deal_date`        (`deal_date`),
  INDEX `idx_trade_type`       (`trade_type`),
  INDEX `idx_property_type`    (`property_type`),
  INDEX `idx_sgg_cd_deal_date` (`property_id`, `deal_date`),
  CONSTRAINT `fk_transactions_property`
    FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='실거래가 테이블';


-- api_collect_logs
CREATE TABLE IF NOT EXISTS `api_collect_logs` (
  `id`                BIGINT        NOT NULL AUTO_INCREMENT,
  `api_type`          VARCHAR(30)   NOT NULL COMMENT 'APT_TRADE 등',
  `lawd_cd`           CHAR(5)       NOT NULL COMMENT '호출 지역코드',
  `deal_ymd`          CHAR(6)       NOT NULL COMMENT '호출 계약년월',
  `page_no`           INT           NOT NULL DEFAULT 1,
  `num_of_rows`       INT           NOT NULL DEFAULT 1000,
  `total_count`       INT           NULL,
  `result_code`       VARCHAR(10)   NULL,
  `result_msg`        VARCHAR(255)  NULL,
  `request_url`       TEXT          NULL     COMMENT 'serviceKey 마스킹된 URL',
  `inserted_count`    INT           NULL     DEFAULT 0 COMMENT '신규 저장 건수',
  `duplicated_count`  INT           NULL     DEFAULT 0 COMMENT '중복 제외 건수',
  `failed_count`      INT           NULL     DEFAULT 0 COMMENT '실패 건수',
  `error_message`     TEXT          NULL,
  `started_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at`          DATETIME      NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_api_type`  (`api_type`),
  INDEX `idx_lawd_cd`   (`lawd_cd`),
  INDEX `idx_deal_ymd`  (`deal_ymd`),
  INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='API 수집 로그 테이블';