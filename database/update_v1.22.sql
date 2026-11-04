-- =====================================================================
-- TeachHub PT — อัปเดตฐานข้อมูลเป็นเวอร์ชัน 1.22
-- ไฟล์: update_v1.22.sql
--
-- ใช้เฉพาะเครื่องที่ติดตั้งเวอร์ชัน 1.21 ไว้แล้ว (ติดตั้งใหม่ไม่ต้องใช้)
-- สิ่งที่เปลี่ยน: สิทธิ์เจ้าหน้าที่งานพัฒนาหลักสูตรและการจัดการเรียนรู้
--   - ตารางใหม่ 2 ตาราง (รวม 56 ตาราง) เก็บรายงานสรุปผลการประเมินแผนฯ และประวัติการเสนอ/พิจารณา
--   - ผู้พิจารณาใช้ชุดเดียวกับรายงานการนิเทศ (ตั้งค่าระบบ: approver_1..3_user / approver_1..3_title)
-- นำเข้าซ้ำได้ ไม่กระทบข้อมูลเดิม
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `plan_eval_summary_reports` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` INT UNSIGNED NOT NULL,
  `author_id`        INT UNSIGNED NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `summary_overall`  TEXT NULL,
  `strengths`        TEXT NULL,
  `problems`         TEXT NULL,
  `suggestions`      TEXT NULL,
  `status`           ENUM('draft','pending','returned','approved') NOT NULL DEFAULT 'draft',
  `current_step`     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `snapshot`         LONGTEXT NULL,
  `submitted_at`     DATETIME NULL,
  `approved_at`      DATETIME NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pe_report_status` (`status`, `current_step`),
  KEY `idx_pe_report_author` (`author_id`),
  KEY `idx_pe_report_academic` (`academic_year_id`),
  CONSTRAINT `fk_pe_report_academic` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pe_report_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_eval_summary_report_actions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id`   INT UNSIGNED NOT NULL,
  `step`        TINYINT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NULL,
  `position`    VARCHAR(150) NOT NULL DEFAULT '',
  `action`      ENUM('submit','forward','approve','return') NOT NULL,
  `comment`     TEXT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pe_report_action_report` (`report_id`),
  KEY `idx_pe_report_action_user` (`user_id`),
  CONSTRAINT `fk_pe_report_action_report` FOREIGN KEY (`report_id`) REFERENCES `plan_eval_summary_reports` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_pe_report_action_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
