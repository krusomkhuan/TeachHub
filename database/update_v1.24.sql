-- =====================================================================
-- TeachHub PT — อัปเดตฐานข้อมูลเป็นเวอร์ชัน 1.24
-- ไฟล์: update_v1.24.sql
--
-- ใช้เฉพาะเครื่องที่ติดตั้งเวอร์ชัน 1.23 ไว้แล้ว (ติดตั้งใหม่ไม่ต้องใช้)
-- สิ่งที่เปลี่ยน: เอกสารดาวน์โหลด 1 รายการแนบได้ 2 ไฟล์ (เช่น Word และ PDF)
--   - downloads เพิ่มช่องของไฟล์ที่ 2 (ชื่อเดิม ชื่อที่เก็บ ชนิด ขนาด)
--   - หน้าดาวน์โหลดแสดงปุ่มแยกตามชนิดไฟล์ เช่น "DOCX" และ "PDF"
-- นำเข้าซ้ำได้ ไม่กระทบข้อมูลเดิม
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `downloads`
  ADD COLUMN IF NOT EXISTS `alt_original_name` VARCHAR(255) NULL COMMENT 'ไฟล์ที่ 2: ชื่อไฟล์ต้นฉบับ' AFTER `file_size`,
  ADD COLUMN IF NOT EXISTS `alt_stored_name`   VARCHAR(255) NULL COMMENT 'ไฟล์ที่ 2: ชื่อที่เก็บใน uploads/downloads' AFTER `alt_original_name`,
  ADD COLUMN IF NOT EXISTS `alt_mime_type`     VARCHAR(150) NULL AFTER `alt_stored_name`,
  ADD COLUMN IF NOT EXISTS `alt_file_size`     INT UNSIGNED NOT NULL DEFAULT 0 AFTER `alt_mime_type`;
