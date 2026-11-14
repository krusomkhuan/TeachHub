-- =====================================================================
-- TeachHub PT — อัปเดตฐานข้อมูลเป็นเวอร์ชัน 1.23
-- ไฟล์: update_v1.23.sql
--
-- ใช้เฉพาะเครื่องที่ติดตั้งเวอร์ชัน 1.22 ไว้แล้ว (ติดตั้งใหม่ไม่ต้องใช้)
-- สิ่งที่เปลี่ยน: ครูกรอกข้อมูลทั่วไปของรายวิชาตอนสร้าง "รายวิชาที่สอน"
--   - teaching_subjects เพิ่มประเภทวิชา สาขาวิชา ท-ป-น จำนวนหน่วย จำนวนแผน
--   - แบบประเมินแผนฯ ใช้ค่าเหล่านี้เป็นค่าเริ่มต้นของ "ข้อมูลทั่วไป"
--   - รายวิชาที่สร้างก่อนเวอร์ชันนี้จะเป็นค่าว่าง (ตัวเลข = 0) จนกว่าครูจะกรอกเพิ่ม
-- นำเข้าซ้ำได้ ไม่กระทบข้อมูลเดิม
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE `teaching_subjects`
  ADD COLUMN IF NOT EXISTS `subject_category` VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'ประเภทวิชา' AFTER `level`,
  ADD COLUMN IF NOT EXISTS `major`            VARCHAR(150) NOT NULL DEFAULT '' COMMENT 'สาขาวิชา' AFTER `subject_category`,
  ADD COLUMN IF NOT EXISTS `credit`           VARCHAR(20)  NOT NULL DEFAULT '' COMMENT 'ท-ป-น' AFTER `major`,
  ADD COLUMN IF NOT EXISTS `unit_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนหน่วยการเรียนรู้ 0 = ยังไม่กรอก' AFTER `credit`,
  ADD COLUMN IF NOT EXISTS `plan_count`       SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนแผนการจัดการเรียนรู้ 0 = ยังไม่กรอก' AFTER `unit_count`;
