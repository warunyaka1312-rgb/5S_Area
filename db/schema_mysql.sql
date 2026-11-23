-- =====================================================================
-- 5S SMART | ALUMET — MySQL / MariaDB schema (converted from SQLite)
-- ---------------------------------------------------------------------
-- * Safe to run many times: every statement is CREATE TABLE IF NOT EXISTS.
-- * includes/config.php runs this file automatically when the schema
--   version stored in app_settings changes (FIVE_S_SCHEMA_VERSION).
-- * Charset utf8mb4 + utf8mb4_bin = same text compare / sort order as SQLite
--   (BINARY). Columns that were "COLLATE NOCASE" in SQLite use utf8mb4_general_ci.
-- * Date columns that may be '' (empty) are kept as VARCHAR, exactly like
--   the SQLite version, so existing PHP logic keeps working unchanged.
-- * Indexes live inside CREATE TABLE so this also works on MySQL 5.7/8.
-- =====================================================================

CREATE TABLE IF NOT EXISTS app_settings (
  `key`   VARCHAR(191) NOT NULL,
  `value` MEDIUMTEXT   NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS areas (
  id         INT NOT NULL AUTO_INCREMENT,
  area_code  VARCHAR(191) NOT NULL,
  area_name  VARCHAR(255) NOT NULL,
  active     INT NOT NULL DEFAULT 1,
  department VARCHAR(100) NOT NULL DEFAULT '',
  group_name VARCHAR(191) NOT NULL DEFAULT '',
  unit_no    VARCHAR(50)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_areas_code_name (area_code, area_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS audit_plans (
  id               INT NOT NULL AUTO_INCREMENT,
  month            INT NOT NULL,
  year             INT NOT NULL,
  team             VARCHAR(50)  NOT NULL,
  auditor_name     VARCHAR(191) NOT NULL DEFAULT '',
  department       VARCHAR(191) NOT NULL DEFAULT '',
  inspection_date  VARCHAR(20)  NOT NULL DEFAULT '',
  inspection_time  VARCHAR(50)  NOT NULL DEFAULT '',
  area_5s          VARCHAR(500) NOT NULL DEFAULT '',
  unit_count       INT NULL,
  sort_no          INT NOT NULL DEFAULT 0,
  active           INT NOT NULL DEFAULT 1,
  note             VARCHAR(1000) NOT NULL DEFAULT '',
  created_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  status           VARCHAR(50)  NOT NULL DEFAULT 'รอตรวจ',
  date_color       VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
  department_color VARCHAR(20)  NOT NULL DEFAULT '#ffffff',
  PRIMARY KEY (id),
  KEY idx_audit_plans_period (year, month, team)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS auditor_12month_migrated (
  flag INT NOT NULL,
  PRIMARY KEY (flag),
  CHECK (flag = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS auditor_12month_rounds (
  id              INT NOT NULL AUTO_INCREMENT,
  history_year    INT NOT NULL,
  history_month   INT NOT NULL,
  team            VARCHAR(50)  NOT NULL,
  auditor_name    VARCHAR(191) NOT NULL,
  round_no        INT NOT NULL,
  checked         INT NOT NULL DEFAULT 0,
  inspection_date VARCHAR(20)  NOT NULL DEFAULT '',
  department      VARCHAR(191) NOT NULL DEFAULT '',
  updated_at      DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_a12_rounds (history_year, history_month, team, auditor_name, round_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS auditor_monthly_history (
  id               INT NOT NULL AUTO_INCREMENT,
  history_year     INT NOT NULL,
  history_month    INT NOT NULL,
  team             VARCHAR(50)  NOT NULL DEFAULT '',
  auditor_name     VARCHAR(191) NOT NULL DEFAULT '',
  inspection_count INT NOT NULL DEFAULT 0,
  inspection_dates VARCHAR(2000) NOT NULL DEFAULT '',
  departments      VARCHAR(2000) NOT NULL DEFAULT '',
  created_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_amh (history_year, history_month, team, auditor_name),
  KEY idx_auditor_monthly_history_date (history_year, history_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS auditor_monthly_history_unified (
  id               INT NOT NULL AUTO_INCREMENT,
  history_year     INT NOT NULL,
  history_month    INT NOT NULL,
  auditor_name     VARCHAR(191) NOT NULL DEFAULT '',
  inspection_count INT NOT NULL DEFAULT 0,
  inspection_dates VARCHAR(2000) NOT NULL DEFAULT '',
  departments      VARCHAR(2000) NOT NULL DEFAULT '',
  created_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_amhu (history_year, history_month, auditor_name),
  KEY idx_auditor_monthly_history_unified_date (history_year, history_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS audits (
  id           INT NOT NULL AUTO_INCREMENT,
  area_id      INT NOT NULL,
  audit_date   VARCHAR(20)  NOT NULL,
  auditor      VARCHAR(191) NOT NULL DEFAULT '',
  period       VARCHAR(100) NOT NULL DEFAULT '',
  overall_note VARCHAR(2000) NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  department   VARCHAR(100) NOT NULL DEFAULT '',
  group_name   VARCHAR(191) NOT NULL DEFAULT '',
  auditor2     VARCHAR(191) NOT NULL DEFAULT '',
  auditor3     VARCHAR(191) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_audits_area_date_auditor (area_id, audit_date, auditor),
  KEY idx_audits_date (audit_date),
  CONSTRAINT fk_audits_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS criteria (
  id          INT NOT NULL AUTO_INCREMENT,
  item_no     INT NOT NULL,
  category    INT NOT NULL,
  max_score   DOUBLE NOT NULL,
  description VARCHAR(1000) NOT NULL DEFAULT '',
  active      INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_criteria_item (item_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS departments (
  id         INT NOT NULL AUTO_INCREMENT,
  name       VARCHAR(191) COLLATE utf8mb4_general_ci NOT NULL,
  active     INT NOT NULL DEFAULT 1,
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS excel_monthly (
  id           INT NOT NULL AUTO_INCREMENT,
  source_month VARCHAR(32)  NOT NULL,
  source_year  INT NOT NULL,
  department   VARCHAR(100) NOT NULL,
  group_name   VARCHAR(191) NOT NULL,
  unit_no      VARCHAR(50)  NOT NULL,
  score        DOUBLE NOT NULL,
  target       DOUBLE NOT NULL,
  actual       DOUBLE NOT NULL,
  section      VARCHAR(191) NOT NULL DEFAULT '',
  auditor      VARCHAR(191) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_excel_monthly (source_month, source_year, department, group_name, unit_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS legacy_scores (
  id           INT NOT NULL AUTO_INCREMENT,
  area_id      INT NOT NULL,
  criterion_id INT NOT NULL,
  score1       DOUBLE NULL,
  score2       DOUBLE NULL,
  score3       DOUBLE NULL,
  source_note  VARCHAR(500) NOT NULL DEFAULT 'นำเข้าจาก Excel ต้นฉบับ ไม่มีวันที่ประเมิน',
  PRIMARY KEY (id),
  CONSTRAINT fk_legacy_area FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE CASCADE,
  CONSTRAINT fk_legacy_criterion FOREIGN KEY (criterion_id) REFERENCES criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS monthly_scan_documents (
  id          INT NOT NULL AUTO_INCREMENT,
  department  VARCHAR(100) NOT NULL DEFAULT 'Production',
  scan_year   INT NOT NULL,
  scan_month  INT NOT NULL,
  title       VARCHAR(500) NOT NULL DEFAULT '',
  filename    VARCHAR(500) NOT NULL DEFAULT '',
  stored_path VARCHAR(1000) NOT NULL DEFAULT '',
  created_at  DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS office_criteria (
  id          INT NOT NULL AUTO_INCREMENT,
  item_no     INT NOT NULL,
  max_score   DOUBLE NOT NULL,
  description VARCHAR(1000) NOT NULL,
  active      INT NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_criteria_item (item_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS office_scores (
  id           INT NOT NULL AUTO_INCREMENT,
  audit_id     INT NOT NULL,
  criterion_id INT NOT NULL,
  score1       DOUBLE NULL,
  score2       DOUBLE NULL,
  score3       DOUBLE NULL,
  note         VARCHAR(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_scores (audit_id, criterion_id),
  CONSTRAINT fk_office_scores_audit FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE,
  CONSTRAINT fk_office_scores_criterion FOREIGN KEY (criterion_id) REFERENCES office_criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS scores (
  id           INT NOT NULL AUTO_INCREMENT,
  audit_id     INT NOT NULL,
  criterion_id INT NOT NULL,
  score1       DOUBLE NULL,
  score2       DOUBLE NULL,
  score3       DOUBLE NULL,
  note         VARCHAR(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_scores (audit_id, criterion_id),
  CONSTRAINT fk_scores_audit FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE,
  CONSTRAINT fk_scores_criterion FOREIGN KEY (criterion_id) REFERENCES criteria(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS tag_monthly_summary (
  id             INT NOT NULL AUTO_INCREMENT,
  source_sheet   VARCHAR(191) NOT NULL,
  source_title   VARCHAR(500) NOT NULL DEFAULT '',
  year           INT NOT NULL,
  month          INT NOT NULL,
  department     VARCHAR(191) NOT NULL,
  total          INT NOT NULL DEFAULT 0,
  closed         INT NOT NULL DEFAULT 0,
  open_count     INT NOT NULL DEFAULT 0,
  percent_closed DOUBLE NULL,
  source_file    VARCHAR(500) NOT NULL DEFAULT 'สรุปการปิด Tag (Recovered).xlsx',
  PRIMARY KEY (id),
  UNIQUE KEY uq_tag_monthly_summary (source_sheet, department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS tags (
  id                INT NOT NULL AUTO_INCREMENT,
  tag_no            VARCHAR(100) NOT NULL,
  department        VARCHAR(191) NOT NULL DEFAULT '',
  unit_no           VARCHAR(50)  NOT NULL DEFAULT '',
  tag_date          VARCHAR(20)  NOT NULL DEFAULT '',
  due_date          VARCHAR(20)  NOT NULL DEFAULT '',
  closed_date       VARCHAR(20)  NOT NULL DEFAULT '',
  status            VARCHAR(20)  NOT NULL DEFAULT 'OPEN',
  auditor           VARCHAR(191) NOT NULL DEFAULT '',
  note              VARCHAR(1000) NOT NULL DEFAULT '',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  before_photo      VARCHAR(500) NOT NULL DEFAULT '',
  after_photo       VARCHAR(500) NOT NULL DEFAULT '',
  corrective_action VARCHAR(1000) NOT NULL DEFAULT '',
  corrective_by     VARCHAR(191) NOT NULL DEFAULT '',
  corrective_date   VARCHAR(20)  NOT NULL DEFAULT '',
  verification_note VARCHAR(1000) NOT NULL DEFAULT '',
  tag_type          VARCHAR(100) NOT NULL DEFAULT '',
  defect_types      VARCHAR(255) NOT NULL DEFAULT '',
  suggestion        VARCHAR(1000) NOT NULL DEFAULT '',
  issued_by         VARCHAR(191) NOT NULL DEFAULT '',
  receiver          VARCHAR(191) NOT NULL DEFAULT '',
  source_photo      VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_tags_dept (department),
  KEY idx_tags_month (tag_date),
  KEY idx_tags_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS unit_import_audits (
  id         INT NOT NULL AUTO_INCREMENT,
  kind       VARCHAR(50)  NOT NULL DEFAULT 'production',
  audit_date VARCHAR(20)  NOT NULL,
  department VARCHAR(191) NOT NULL DEFAULT '',
  auditor    VARCHAR(191) NOT NULL DEFAULT '',
  period     VARCHAR(100) NOT NULL DEFAULT '',
  filename   VARCHAR(500) NOT NULL DEFAULT '',
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS unit_import_scores (
  id           INT NOT NULL AUTO_INCREMENT,
  audit_id     INT NOT NULL,
  criterion_id INT NOT NULL,
  unit_slot    INT NOT NULL,
  score        DOUBLE NULL,
  na           INT NOT NULL DEFAULT 0,
  note         VARCHAR(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit_import_scores (audit_id, criterion_id, unit_slot),
  CONSTRAINT fk_uis_audit FOREIGN KEY (audit_id) REFERENCES unit_import_audits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS unit_import_units (
  id       INT NOT NULL AUTO_INCREMENT,
  audit_id INT NOT NULL,
  slot     INT NOT NULL,
  unit_no  VARCHAR(50) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit_import_units (audit_id, slot),
  CONSTRAINT fk_uiu_audit FOREIGN KEY (audit_id) REFERENCES unit_import_audits(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS users (
  id            INT NOT NULL AUTO_INCREMENT,
  username      VARCHAR(191) COLLATE utf8mb4_general_ci NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(191) NOT NULL DEFAULT '',
  role          VARCHAR(20)  NOT NULL DEFAULT 'auditor',
  active        INT NOT NULL DEFAULT 1,
  must_change   INT NOT NULL DEFAULT 0,
  last_login    VARCHAR(30)  NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  department    VARCHAR(191) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
