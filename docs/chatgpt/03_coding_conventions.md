# TeachHub PT — กติกาการเขียนโค้ดและการส่งมอบงาน

## ความปลอดภัย (บังคับ)
1. SQL ใช้ `db_all/db_one/db_value/db_exec/db_insert($sql, $types, $params)` แบบ prepared statement เท่านั้น ห้ามต่อสตริงค่าจากผู้ใช้
   - ชื่อตาราง/คอลัมน์ที่ต้องต่อสตริง ให้ผ่าน whitelist เช่น `submission_table($type)`
   - LIKE: `'%' . addcslashes($q, '%_\\') . '%'`
2. แสดงผลทุกค่าด้วย `e()` (htmlspecialchars) ทั้งใน HTML และ attribute
3. ทุกฟอร์ม POST มี `<?= csrf_field() ?>` และหน้า POST เรียก `csrf_verify()`; ปุ่มลบใช้ `<form method="post" data-confirm="...">`
4. ตรวจสิทธิ์ฝั่ง server ทุกหน้า/ทุก action (`require_role`, `can_*()`), ไม่พึ่งการซ่อนเมนู
5. อัปโหลดไฟล์ผ่าน `handle_upload($file, $subdir, $allowedExt, $maxBytes)` → เก็บชื่อสุ่มใน `uploads/<subdir>/`; ส่งไฟล์ผ่าน `send_file()` ในสคริปต์ที่ตรวจสิทธิ์; ลบด้วย `delete_upload()`
6. ข้อมูลส่วนบุคคล (PDPA): เลขบัตรประชาชน วันเกิด ที่อยู่ เห็นเฉพาะเจ้าของ/ผู้มีหน้าที่; ห้ามใส่ในบันทึก Log (`log_sanitize()` ปิดบังเลข 13 หลักอยู่แล้ว)
7. ห้าม commit/อัปโหลด: `config/db.php` ของเครื่องจริง, `config/drive_token.php`, ไฟล์ dump ฐานข้อมูลจริง, `uploads/*` (ยกเว้น index.html/.htaccess)

## รูปแบบโค้ด
- PHP 8.0: ใช้ `fn() =>`, `match`, `str_starts_with`, named args ได้; ไม่ใช้ฟีเจอร์ 8.1+ (enum, readonly, never)
- ตั้งชื่อฟังก์ชัน snake_case แบบมี prefix โมดูล (`advisor_*`, `hr_*`, `plan_eval_*`), ใส่ docblock ภาษาไทยสั้น ๆ
- ข้อความบนหน้าจอ/คอมเมนต์เป็นภาษาไทย; ป้ายสถานะใช้ฟังก์ชันกลาง (`status_badge()`, `*_badge()`)
- วันที่แสดงแบบไทย พ.ศ.: `thai_date()`, `thai_datetime()`; ภาคเรียน: `current_academic_year()`, `academic_label()`
- ฟอร์มแสดง error ใต้ช่อง: `$errors['field']` + class `is-invalid` + `<div class="invalid-feedback d-block">`
- หลัง POST สำเร็จ: `flash()` + `redirect()` (PRG); `flash('important', ...)` = popup SweetAlert (รองรับ `\n`)
- ตารางใช้ class `datatable` (DataTables ภาษาไทยใน app.js), `data-order="[]"`
- Log ทุก action ที่เปลี่ยนข้อมูล: `log_activity('module_action', 'ใครทำอะไรกับอะไร #id สถานะเดิม → ใหม่')` และเพิ่มป้ายไทยใน `log_action_label()` / หมวดใน `log_action_groups()`

## การเปลี่ยนฐานข้อมูล / เวอร์ชัน (ทุกครั้งที่แก้ schema)
1. สร้าง `database/update_vX.Y.sql` (หัวไฟล์อธิบายเป็นไทย, `SET NAMES utf8mb4;`, ใช้ `CREATE TABLE IF NOT EXISTS` / `INSERT IGNORE` ให้นำเข้าซ้ำได้เท่าที่ทำได้)
2. แก้ `database/database.sql` ให้ติดตั้งใหม่ได้ผลเท่ากัน (รวม `DROP TABLE IF EXISTS` บรรทัดบน)
3. เพิ่ม `define('APP_VERSION', 'X.Y.0')` ใน `config/config.php`
4. อัปเดต `admin/system_check.php` (จำนวนตารางที่ต้องมี / ตรวจคอลัมน์หรือ index ของเวอร์ชันนั้น)
5. อัปเดต README.md (รายการไฟล์ใน database/)
6. ฟีเจอร์ที่ยังพัฒนาไม่เสร็จ ให้มีสวิตช์ setting เปิด/ปิด (เช่น `hr_enabled`) ค่าเริ่มต้นปิด

## การทดสอบ (localhost)
- ใช้บัญชีทดสอบ (test_admin, test_supervisor) ห้ามแก้รหัสผ่านบัญชีจริง
- ระบบบังคับ PDPA: ตั้ง `users.pdpa_version` ชั่วคราวแล้วคืนเป็น NULL หลังทดสอบ
- ทดสอบทั้งกรณีปกติ กรณีผิด และสิทธิ์ (เปิด URL ตรงด้วยบัญชีที่ไม่มีสิทธิ์ต้องได้ 403/404)
- ลบข้อมูลทดสอบ ไฟล์ทดสอบ และ log ที่เกิดจากการทดสอบออกเมื่อเสร็จ
- ตรวจ syntax: `C:\xampp\php\php.exe -l ไฟล์.php`
- Git Bash: อย่าส่งภาษาไทยผ่าน argument ของ curl/mysql -e (ตัวอักษรเพี้ยน) → เขียนเป็นไฟล์ UTF-8

## การส่งมอบให้ผู้ใช้ (ทุกงาน)
สรุปเป็นภาษาไทย: ทำอะไร → วิธีใช้ → ทดสอบอะไรแล้ว/ยังไม่ได้ทดสอบ → **รายชื่อไฟล์ที่ต้องอัปโหลด** (ไฟล์ใหม่/ไฟล์ที่แก้ แยกตามโฟลเดอร์) → **SQL ที่ต้องนำเข้าตามลำดับ** → สิ่งที่ต้องทำบนเว็บจริง (เช่น `APP_DEBUG` บรรทัด 11 ของ config/config.php ต้องเป็น `false` บนเว็บจริง)
