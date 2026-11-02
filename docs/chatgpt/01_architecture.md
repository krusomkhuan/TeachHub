# TeachHub PT — สถาปัตยกรรมระบบ (สำหรับผู้ช่วยพัฒนา)

ระบบงานพัฒนาหลักสูตรและการจัดการเรียนรู้ วิทยาลัยการอาชีพโพนทอง · เวอร์ชัน 1.21.0
- PHP 8.0 (ไม่มี framework, ไม่มี Composer) + MariaDB/MySQL (mysqli, utf8mb4_unicode_ci)
- หน้าเว็บ: Bootstrap 5.3 + Bootstrap Icons + DataTables + SweetAlert2 + Chart.js (โหลดจาก CDN)
- เครื่องพัฒนา: XAMPP บน Windows `C:\xampp\htdocs\teachhub_pt` → `http://localhost/teachhub_pt`
- เว็บจริง: `https://krusomkhuan.com/teachhub` (โฮสติ้ง Plesk) — อัปโหลดไฟล์และนำเข้า SQL เองผ่าน phpMyAdmin
- Git: `github.com/krusomkhuan/TeachHub-PT` branch `main`

## โครงสร้างโฟลเดอร์

| โฟลเดอร์ | หน้าที่ |
|---|---|
| `config/` | `config.php` (bootstrap: ค่าคงที่, session, require ทุก include), `db.php` (ค่าเชื่อมต่อ — ห้ามแก้/อัปโหลดทับ) |
| `includes/` | ฟังก์ชันกลางแยกตามโมดูล + `header.php`/`footer.php`/`sidebar.php` (หลังบ้าน), `public_header.php`/`public_footer.php` (หน้าบ้าน), `auth_header.php`/`auth_footer.php` (หน้าเข้าสู่ระบบ) |
| `includes/partials/` | ชิ้นส่วนหน้าที่ใช้ซ้ำ เช่น `review_page.php` (หน้าตรวจงานกลาง), `submission_detail.php` |
| `admin/` | หน้าผู้ดูแลระบบ (role admin) — ถูกจำกัดรายเมนูด้วย `admin_perm` ถ้าเป็นเจ้าหน้าที่ |
| `teacher/` `supervisor/` `executive/` | หน้าตาม role (ครู / หัวหน้าแผนก / ผู้บริหาร) |
| `review/` | หน้าตรวจงานตามตำแหน่ง (ผู้ตรวจ, ประเมินแผน, งานครูที่ปรึกษาและแนะแนว) |
| `reports/` | รายงานสรุป/พิมพ์ |
| `hr/` | ระบบงานบุคลากร (v1.19) — เปิด/ปิดด้วย setting `hr_enabled` |
| `public/` | หน้าบ้านไม่ต้องล็อกอิน (หน้าแรก ประกาศ ปฏิทิน ดาวน์โหลด PDPA, `evaluate.php` นักเรียนประเมินผ่าน QR) |
| `auth/` | login/logout/register/forgot/change_password/profile/PDPA consent/Google OAuth |
| `board/` | กระดานข่าว |
| `database/` | `database.sql` (ติดตั้งใหม่), `update_vX.Y.sql` (อัปเดตทีละเวอร์ชัน), `sample_data.sql` — มี `.htaccess` บล็อกเว็บ |
| `uploads/<subdir>/` | ไฟล์ที่อัปโหลด (บล็อกการเปิดตรง) — เปิดผ่านสคริปต์ที่ตรวจสิทธิ์ เช่น `file.php`, `hr/file.php`, `advisor_image.php` |
| `uploads/logs/` | ไฟล์ Log รายเดือน `activity-YYYY-MM.log` (บล็อกเว็บ) |
| `assets/` | `css/style.css`, `js/app.js` (DataTables, toast/flash, data-confirm, ย่อรูปก่อนอัปโหลด) |

## วงจรการทำงานของ 1 หน้า (หลังบ้าน)

```php
<?php
require_once __DIR__ . '/../config/config.php';   // โหลดทุกอย่าง
$user = require_role('admin');                      // หรือ require_login() / require_role('teacher','supervisor')
// require_login() ตรวจ: ล็อกอิน → หมดเวลา session → บังคับเปลี่ยนรหัส → PDPA → admin_perm_enforce() → hr_enforce_enabled()
if (is_post()) {
    csrf_verify();                                  // ทุก POST ต้องมี csrf_field() ในฟอร์ม
    $action = post('action');
    // ... ตรวจข้อมูล → $errors['field'] = 'ข้อความไทย'
    // ... db_exec()/db_insert() แบบ prepared statement เท่านั้น
    log_activity('xxx_save', 'ข้อความ: ใครทำอะไรกับอะไร #id');
    flash('success', 'บันทึกแล้ว');
    redirect('admin/xxx.php');                      // PRG pattern
}
$pageTitle = 'ชื่อหน้า'; $pageIcon = 'bi-xxx';
$breadcrumbs = ['หน้าแม่' => 'admin/parent.php', 'หน้านี้' => null];
$pageActions = '<a class="btn btn-accent" ...>';    // ปุ่มขวาบน (HTML)
$activeMenu = 'admin/parent.php';                   // (ถ้าต้องไฮไลต์เมนูแม่)
require APP_ROOT . '/includes/header.php';
?> ...HTML (Bootstrap)... <?php
$pageScripts = '(function(){ ... })();';            // JS ท้ายหน้า (optional)
require APP_ROOT . '/includes/footer.php';
```
ตัวอย่างหน้า CRUD ที่ใช้อ้างอิงได้ดี: `admin/subjects.php`, `admin/download_categories.php`

## role และสิทธิ์

- `users.role` ∈ `admin` (ผู้ดูแลระบบ) · `supervisor` (หัวหน้าแผนก) · `teacher` (ครู) · `executive` (ผู้บริหาร)
- `teacher_roles()` = ['teacher','supervisor'] (คนที่ส่งงานได้)
- **สิทธิ์ตามตำแหน่ง** (ช่อง `users.position` — แก้ได้เฉพาะ admin, ครูเปลี่ยนเองไม่ได้: `is_privileged_position()`):
  - ผู้ตรวจงานตามตำแหน่ง: `reviewer_positions()` / `is_reviewer_position()` (includes/submissions.php)
  - หัวหน้างานพัฒนาหลักสูตรฯ: `is_curriculum_head()` — ประเมินแผนทุกแผนก (includes/plan_eval.php)
  - งานครูที่ปรึกษาและแนะแนว: `advisor_staff_positions()`, `is_advisor_staff()`, `can_manage_advisor()`, `is_advisor_staff_only()` (เจ้าหน้าที่ → เห็นเฉพาะเมนูงานครูที่ปรึกษา)
  - งานบุคลากร: `hr_staff_positions()`, `can_manage_hr()` (includes/hr.php)
- **เจ้าหน้าที่ดูแลระบบ** (v1.17): role admin + `users.admin_permissions` (JSON) — NULL = เต็มสิทธิ์
  - `is_full_admin()`, `admin_can($user,'perm')`, `admin_can_path()`; รายการสิทธิ์/หน้าอยู่ใน `admin_permission_groups()` (includes/admin_perm.php)
  - **เพิ่มหน้าใหม่ใน admin/ ต้องลงทะเบียนใน `admin_permission_groups()`** ไม่อย่างนั้นเจ้าหน้าที่ที่จำกัดสิทธิ์จะเปิดไม่ได้ (ปฏิเสธไว้ก่อน)
- `executive` ห้ามได้สิทธิ์ตามตำแหน่ง; สิทธิ์ทั้งหมดต้องตรวจฝั่ง server ไม่ใช่แค่ซ่อนเมนู

## เมนู (includes/sidebar.php)

`$menus[role] = ['หัวข้อกลุ่ม' => [[ชื่อ, path, ไอคอน bi-*], ...]]` แล้วต่อเติมตามตำแหน่ง/สิทธิ์ (`$roleMenu`), ตัวเลขแจ้งเตือน `$badgeCounts[path] = n`
ไฟล์ที่ยังไม่มีจะแสดงป้าย "เร็ว ๆ นี้" อัตโนมัติ · เมนูหน้าบ้านอยู่ใน `$navItems` ของ `includes/public_header.php` (ระวังความกว้าง: ที่ 1200px ต้องอยู่แถวเดียว)

## โมดูลหลักและไฟล์

| โมดูล | ไฟล์ฟังก์ชัน | หน้า |
|---|---|---|
| ส่งงาน (แผนการสอน, PA, วิจัย) + รอบส่งงาน | `submissions.php` | `teacher/*.php`, `review/*.php`, `admin/{lesson_plans,pa,research,rounds}.php`, `partials/review_page.php` |
| นิเทศโดยหัวหน้าแผนก / รายงานการนิเทศ 3 ขั้น | `supervision.php`, `supervision_report.php` | `supervisor/supervision*.php`, `admin/supervision*.php`, `reports/supervision_*` |
| นิเทศโดยนักเรียน (QR) | `student_eval.php` | `public/evaluate.php`, `admin/student_eval.php` |
| ประเมินแผนการจัดการเรียนรู้ (v1.15) | `plan_eval.php` | `review/plan_evaluations.php`, `teacher/plan_evaluations.php`, `reports/plan_eval_*` |
| งานครูที่ปรึกษาและแนะแนว: รายงานกิจกรรมโฮมรูม (สัปดาห์ละ 1 ครั้ง/กลุ่ม เลือกวันได้), เยี่ยมบ้าน | `advisor.php`, `home_visit.php` | `teacher/{advisor_reports,home_visits}.php`, `review/advisor_*`, `review/home_visits.php`, `reports/advisor_*`, `reports/home_visit_*` |
| งานบุคลากร (v1.19) | `hr.php` | `hr/*.php` |
| เอกสารดาวน์โหลด + หมวดหมู่ | `functions.php` (`download_categories()`) | `admin/downloads.php`, `admin/download_categories.php`, `public/downloads.php` |
| PDPA | `pdpa.php` | `auth/pdpa_consent.php`, `admin/pdpa.php` |
| Google login / Google Drive | `google_auth.php`, `drive.php` | `auth/google_*`, `admin/drive.php` |
| สำรอง/กู้คืน | `backup.php` | `admin/backup.php` |
| กระดานข่าว | `board.php` | `board/*.php` |
| ผู้ใช้ออนไลน์/ตัวนับ/ชื่นชมผู้ใช้งานบ่อย (v1.20) | `presence.php` | `partials/user_cheer.php`, หน้าแรก |
| บันทึก Log (v1.21) | `functions.php` (`log_*`) | `admin/logs.php` |
| ตรวจสอบระบบ | — | `admin/system_check.php` (นับจำนวนตาราง/ตรวจโครงสร้างแต่ละเวอร์ชัน) |

## ค่าตั้งค่า (ตาราง settings — `setting()` / `setting_save()`)
ตัวอย่างคีย์: `college_name`, `system_name`, `allow_register`, `google_enabled`, `pdpa_version`, `pdpa_required`, `advisor_late_days`, `hr_enabled` (ค่าเริ่มต้น 0 = ปิด), `log_retention_days` (365), `max_upload_mb`, `session_timeout_min`
