-- 5S SMART MySQL backup
-- created_at: 2026-11-14T12:48:34+07:00
-- schema: mysql-1
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET time_zone='+00:00';
SET sql_mode='NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;
INSERT INTO `app_settings` (`key`,`value`) VALUES
('mysql_schema_version','mysql-1'),
('office_target_pct','87'),
('production_target_pct','85'),
('target_pct','85');

DROP TABLE IF EXISTS `areas`;
CREATE TABLE `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_code` varchar(191) NOT NULL,
  `area_name` varchar(255) NOT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  `department` varchar(100) NOT NULL DEFAULT '',
  `group_name` varchar(191) NOT NULL DEFAULT '',
  `unit_no` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_areas_code_name` (`area_code`,`area_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `auditor_12month_migrated`;
CREATE TABLE `auditor_12month_migrated` (
  `flag` int(11) NOT NULL,
  PRIMARY KEY (`flag`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`flag` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `auditor_12month_rounds`;
CREATE TABLE `auditor_12month_rounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `history_year` int(11) NOT NULL,
  `history_month` int(11) NOT NULL,
  `team` varchar(50) NOT NULL,
  `auditor_name` varchar(191) NOT NULL,
  `round_no` int(11) NOT NULL,
  `checked` int(11) NOT NULL DEFAULT 0,
  `inspection_date` varchar(20) NOT NULL DEFAULT '',
  `department` varchar(191) NOT NULL DEFAULT '',
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_a12_rounds` (`history_year`,`history_month`,`team`,`auditor_name`,`round_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `auditor_monthly_history`;
CREATE TABLE `auditor_monthly_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `history_year` int(11) NOT NULL,
  `history_month` int(11) NOT NULL,
  `team` varchar(50) NOT NULL DEFAULT '',
  `auditor_name` varchar(191) NOT NULL DEFAULT '',
  `inspection_count` int(11) NOT NULL DEFAULT 0,
  `inspection_dates` varchar(2000) NOT NULL DEFAULT '',
  `departments` varchar(2000) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amh` (`history_year`,`history_month`,`team`,`auditor_name`),
  KEY `idx_auditor_monthly_history_date` (`history_year`,`history_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `auditor_monthly_history_unified`;
CREATE TABLE `auditor_monthly_history_unified` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `history_year` int(11) NOT NULL,
  `history_month` int(11) NOT NULL,
  `auditor_name` varchar(191) NOT NULL DEFAULT '',
  `inspection_count` int(11) NOT NULL DEFAULT 0,
  `inspection_dates` varchar(2000) NOT NULL DEFAULT '',
  `departments` varchar(2000) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amhu` (`history_year`,`history_month`,`auditor_name`),
  KEY `idx_auditor_monthly_history_unified_date` (`history_year`,`history_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `audits`;
CREATE TABLE `audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `audit_date` varchar(20) NOT NULL,
  `auditor` varchar(191) NOT NULL DEFAULT '',
  `period` varchar(100) NOT NULL DEFAULT '',
  `overall_note` varchar(2000) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `department` varchar(100) NOT NULL DEFAULT '',
  `group_name` varchar(191) NOT NULL DEFAULT '',
  `auditor2` varchar(191) NOT NULL DEFAULT '',
  `auditor3` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audits_area_date_auditor` (`area_id`,`audit_date`,`auditor`),
  KEY `idx_audits_date` (`audit_date`),
  CONSTRAINT `fk_audits_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `audit_plans`;
CREATE TABLE `audit_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `month` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `team` varchar(50) NOT NULL,
  `auditor_name` varchar(191) NOT NULL DEFAULT '',
  `department` varchar(191) NOT NULL DEFAULT '',
  `inspection_date` varchar(20) NOT NULL DEFAULT '',
  `inspection_time` varchar(50) NOT NULL DEFAULT '',
  `area_5s` varchar(500) NOT NULL DEFAULT '',
  `unit_count` int(11) DEFAULT NULL,
  `sort_no` int(11) NOT NULL DEFAULT 0,
  `active` int(11) NOT NULL DEFAULT 1,
  `note` varchar(1000) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'รอตรวจ',
  `date_color` varchar(20) NOT NULL DEFAULT '#ffffff',
  `department_color` varchar(20) NOT NULL DEFAULT '#ffffff',
  PRIMARY KEY (`id`),
  KEY `idx_audit_plans_period` (`year`,`month`,`team`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `criteria`;
CREATE TABLE `criteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_no` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `max_score` double NOT NULL,
  `description` varchar(1000) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_criteria_item` (`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `excel_monthly`;
CREATE TABLE `excel_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_month` varchar(32) NOT NULL,
  `source_year` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `group_name` varchar(191) NOT NULL,
  `unit_no` varchar(50) NOT NULL,
  `score` double NOT NULL,
  `target` double NOT NULL,
  `actual` double NOT NULL,
  `section` varchar(191) NOT NULL DEFAULT '',
  `auditor` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_excel_monthly` (`source_month`,`source_year`,`department`,`group_name`,`unit_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `legacy_scores`;
CREATE TABLE `legacy_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `score1` double DEFAULT NULL,
  `score2` double DEFAULT NULL,
  `score3` double DEFAULT NULL,
  `source_note` varchar(500) NOT NULL DEFAULT 'นำเข้าจาก Excel ต้นฉบับ ไม่มีวันที่ประเมิน',
  PRIMARY KEY (`id`),
  KEY `fk_legacy_area` (`area_id`),
  KEY `fk_legacy_criterion` (`criterion_id`),
  CONSTRAINT `fk_legacy_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_legacy_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `monthly_scan_documents`;
CREATE TABLE `monthly_scan_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department` varchar(100) NOT NULL DEFAULT 'Production',
  `scan_year` int(11) NOT NULL,
  `scan_month` int(11) NOT NULL,
  `title` varchar(500) NOT NULL DEFAULT '',
  `filename` varchar(500) NOT NULL DEFAULT '',
  `stored_path` varchar(1000) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `office_criteria`;
CREATE TABLE `office_criteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_no` int(11) NOT NULL,
  `max_score` double NOT NULL,
  `description` varchar(1000) NOT NULL,
  `active` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_office_criteria_item` (`item_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `office_scores`;
CREATE TABLE `office_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `score1` double DEFAULT NULL,
  `score2` double DEFAULT NULL,
  `score3` double DEFAULT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_office_scores` (`audit_id`,`criterion_id`),
  KEY `fk_office_scores_criterion` (`criterion_id`),
  CONSTRAINT `fk_office_scores_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_office_scores_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `office_criteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `scores`;
CREATE TABLE `scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `score1` double DEFAULT NULL,
  `score2` double DEFAULT NULL,
  `score3` double DEFAULT NULL,
  `note` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scores` (`audit_id`,`criterion_id`),
  KEY `fk_scores_criterion` (`criterion_id`),
  CONSTRAINT `fk_scores_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scores_criterion` FOREIGN KEY (`criterion_id`) REFERENCES `criteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_no` varchar(100) NOT NULL,
  `department` varchar(191) NOT NULL DEFAULT '',
  `unit_no` varchar(50) NOT NULL DEFAULT '',
  `tag_date` varchar(20) NOT NULL DEFAULT '',
  `due_date` varchar(20) NOT NULL DEFAULT '',
  `closed_date` varchar(20) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'OPEN',
  `auditor` varchar(191) NOT NULL DEFAULT '',
  `note` varchar(1000) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `before_photo` varchar(500) NOT NULL DEFAULT '',
  `after_photo` varchar(500) NOT NULL DEFAULT '',
  `corrective_action` varchar(1000) NOT NULL DEFAULT '',
  `corrective_by` varchar(191) NOT NULL DEFAULT '',
  `corrective_date` varchar(20) NOT NULL DEFAULT '',
  `verification_note` varchar(1000) NOT NULL DEFAULT '',
  `tag_type` varchar(100) NOT NULL DEFAULT '',
  `defect_types` varchar(255) NOT NULL DEFAULT '',
  `suggestion` varchar(1000) NOT NULL DEFAULT '',
  `issued_by` varchar(191) NOT NULL DEFAULT '',
  `receiver` varchar(191) NOT NULL DEFAULT '',
  `source_photo` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_tags_dept` (`department`),
  KEY `idx_tags_month` (`tag_date`),
  KEY `idx_tags_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `tag_monthly_summary`;
CREATE TABLE `tag_monthly_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_sheet` varchar(191) NOT NULL,
  `source_title` varchar(500) NOT NULL DEFAULT '',
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `department` varchar(191) NOT NULL,
  `total` int(11) NOT NULL DEFAULT 0,
  `closed` int(11) NOT NULL DEFAULT 0,
  `open_count` int(11) NOT NULL DEFAULT 0,
  `percent_closed` double DEFAULT NULL,
  `source_file` varchar(500) NOT NULL DEFAULT 'สรุปการปิด Tag (Recovered).xlsx',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tag_monthly_summary` (`source_sheet`,`department`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `unit_import_audits`;
CREATE TABLE `unit_import_audits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kind` varchar(50) NOT NULL DEFAULT 'production',
  `audit_date` varchar(20) NOT NULL,
  `department` varchar(191) NOT NULL DEFAULT '',
  `auditor` varchar(191) NOT NULL DEFAULT '',
  `period` varchar(100) NOT NULL DEFAULT '',
  `filename` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `unit_import_scores`;
CREATE TABLE `unit_import_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `criterion_id` int(11) NOT NULL,
  `unit_slot` int(11) NOT NULL,
  `score` double DEFAULT NULL,
  `na` int(11) NOT NULL DEFAULT 0,
  `note` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_import_scores` (`audit_id`,`criterion_id`,`unit_slot`),
  CONSTRAINT `fk_uis_audit` FOREIGN KEY (`audit_id`) REFERENCES `unit_import_audits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `unit_import_units`;
CREATE TABLE `unit_import_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `audit_id` int(11) NOT NULL,
  `slot` int(11) NOT NULL,
  `unit_no` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_import_units` (`audit_id`,`slot`),
  CONSTRAINT `fk_uiu_audit` FOREIGN KEY (`audit_id`) REFERENCES `unit_import_audits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `display_name` varchar(191) NOT NULL DEFAULT '',
  `role` varchar(20) NOT NULL DEFAULT 'auditor',
  `active` int(11) NOT NULL DEFAULT 1,
  `must_change` int(11) NOT NULL DEFAULT 0,
  `last_login` varchar(30) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `department` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

SET FOREIGN_KEY_CHECKS=1;
