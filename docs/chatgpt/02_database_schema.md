# TeachHub PT — โครงสร้างฐานข้อมูล (MariaDB, utf8mb4)

สร้างอัตโนมัติจากฐานข้อมูลจริงเวอร์ชัน 1.21.0 · 2026-10-25
รูปแบบ: `คอลัมน์ ชนิด [PRI|UNI|MUL] [AI] [NULL] [= ค่าเริ่มต้น] — คำอธิบาย` · FK แสดงท้ายตาราง
ไฟล์ติดตั้งใหม่: database/database.sql · ไฟล์อัปเดตรายเวอร์ชัน: database/update_vX.Y.sql

ทั้งหมด 54 ตาราง

## `academic_years`
- `id` int(10) unsigned PRI AI
- `year` smallint(5) unsigned MUL — ปีการศึกษา (พ.ศ.)
- `semester` tinyint(3) unsigned — 1, 2 หรือ 3 (ฤดูร้อน)
- `start_date` date NULL
- `end_date` date NULL
- `is_current` tinyint(1) MUL = 0
- `created_at` datetime = current_timestamp()

## `activity_logs`
- `id` bigint(20) unsigned PRI AI
- `user_id` int(10) unsigned MUL NULL
- `action` varchar(100) MUL
- `detail` varchar(1000) NULL
- `ip_address` varchar(45) = ''
- `user_agent` varchar(255) = ''
- `created_at` datetime MUL = current_timestamp()
- FK: user_id → users.id (ON DELETE SET NULL)

## `advisor_groups`
- `id` int(10) unsigned PRI AI
- `academic_year_id` int(10) unsigned MUL
- `teacher_id` int(10) unsigned MUL — ครูที่ปรึกษา
- `department_id` int(10) unsigned MUL NULL — แผนกของกลุ่มเรียน
- `group_name` varchar(100) — ชื่อกลุ่มเรียน เช่น ปวช.1/1 ช่างยนต์
- `student_count` smallint(5) unsigned = 0
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE RESTRICT); created_by → users.id (ON DELETE SET NULL); department_id → departments.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE RESTRICT)

## `advisor_reports`
- `id` int(10) unsigned PRI AI
- `group_id` int(10) unsigned MUL
- `teacher_id` int(10) unsigned MUL
- `meet_date` date MUL — วันพุธที่พบนักเรียน
- `total_students` smallint(5) unsigned = 0
- `present_count` smallint(5) unsigned = 0 — มา
- `late_count` smallint(5) unsigned = 0 — มาสาย
- `leave_count` smallint(5) unsigned = 0 — ลา
- `absent_count` smallint(5) unsigned = 0 — ขาด
- `topics` text — เรื่องที่พูดคุย/แจ้งนักเรียน
- `problems` text NULL — นักเรียนที่มีปัญหาและการช่วยเหลือ
- `status` enum('pending','approved','revision') MUL = 'pending'
- `is_late` tinyint(1) = 0
- `review_comment` text NULL
- `reviewed_by` int(10) unsigned MUL NULL
- `reviewed_at` datetime NULL
- `submitted_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: group_id → advisor_groups.id (ON DELETE RESTRICT); reviewed_by → users.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE RESTRICT)

## `advisor_report_images`
- `id` int(10) unsigned PRI AI
- `report_id` int(10) unsigned MUL
- `original_name` varchar(255)
- `stored_name` varchar(255) UNI
- `mime_type` varchar(100)
- `file_size` int(10) unsigned = 0
- `uploaded_at` datetime = current_timestamp()
- FK: report_id → advisor_reports.id (ON DELETE CASCADE)

## `advisor_skip_dates`
- `id` int(10) unsigned PRI AI
- `skip_date` date UNI
- `reason` varchar(200) = ''
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- FK: created_by → users.id (ON DELETE SET NULL)

## `announcements`
- `id` int(10) unsigned PRI AI
- `title` varchar(255)
- `content` text
- `is_pinned` tinyint(1) = 0
- `is_published` tinyint(1) MUL = 1
- `views` int(10) unsigned = 0
- `created_by` int(10) unsigned MUL NULL
- `published_at` datetime = current_timestamp()
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: created_by → users.id (ON DELETE SET NULL)

## `board_replies`
- `id` int(10) unsigned PRI AI
- `topic_id` int(10) unsigned MUL
- `user_id` int(10) unsigned MUL NULL
- `body` text
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: topic_id → board_topics.id (ON DELETE CASCADE); user_id → users.id (ON DELETE SET NULL)

## `board_topics`
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL NULL
- `category` enum('general','question','feature','bug') MUL = 'general'
- `title` varchar(200)
- `body` text
- `status` enum('open','answered','planned','done','closed') = 'open'
- `is_pinned` tinyint(1) MUL = 0
- `views` int(10) unsigned = 0
- `reply_count` int(10) unsigned = 0
- `last_reply_at` datetime NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: user_id → users.id (ON DELETE SET NULL)

## `departments`
- `id` int(10) unsigned PRI AI
- `code` varchar(20) UNI
- `name` varchar(150)
- `is_active` tinyint(1) = 1
- `created_at` datetime = current_timestamp()

## `downloads`
- `id` int(10) unsigned PRI AI
- `title` varchar(255)
- `description` varchar(500) NULL
- `category` varchar(30) MUL = 'other'
- `original_name` varchar(255) NULL
- `stored_name` varchar(255) NULL
- `mime_type` varchar(150) NULL
- `file_size` int(10) unsigned = 0
- `download_count` int(10) unsigned = 0
- `is_active` tinyint(1) = 1
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: created_by → users.id (ON DELETE SET NULL)

## `download_categories`
- `id` int(10) unsigned PRI AI
- `code` varchar(30) UNI — รหัสหมวด (อ้างอิงจาก downloads.category)
- `name` varchar(100)
- `sort_order` smallint(6) = 0
- `is_active` tinyint(1) MUL = 1
- `created_at` datetime = current_timestamp()

## `drive_files`
- `id` int(10) unsigned PRI AI
- `subdir` varchar(30) MUL — โฟลเดอร์ใน uploads/ เช่น lesson_plans, supervision
- `stored_name` varchar(100) — ชื่อไฟล์ที่ระบบตั้ง (ตรงกับคอลัมน์ stored_name ของตารางเจ้าของไฟล์)
- `drive_id` varchar(128) — File ID ใน Google Drive
- `size` bigint(20) unsigned = 0
- `created_at` datetime = current_timestamp()

## `home_visits`
- `id` int(10) unsigned PRI AI
- `school_year` smallint(5) unsigned MUL — ปีการศึกษา (พ.ศ.)
- `group_id` int(10) unsigned MUL — กลุ่มที่ปรึกษา
- `teacher_id` int(10) unsigned MUL
- `student_code` varchar(20)
- `student_name` varchar(150)
- `visit_date` date
- `informant_name` varchar(150) = '' — ผู้ให้ข้อมูล
- `informant_relation` varchar(50) = '' — ความเกี่ยวข้องกับนักเรียน
- `address` varchar(500) = ''
- `living_with` varchar(50) = '' — นักเรียนอาศัยอยู่กับ
- `economic_status` enum('sufficient','insufficient','poor') = 'sufficient' — พอเพียง/ไม่พอเพียง/ขัดสน
- `family_info` text NULL — สภาพครอบครัวและที่อยู่อาศัย
- `learning_behavior` text NULL — ด้านการเรียนและพฤติกรรม
- `risk_level` enum('normal','risk','problem') = 'normal' — ปกติ/เสี่ยง/มีปัญหา
- `assistance` text NULL — การช่วยเหลือ/ข้อเสนอแนะ
- `latitude` decimal(10,7) NULL
- `longitude` decimal(10,7) NULL
- `status` enum('pending','approved','revision') MUL = 'pending'
- `review_comment` text NULL
- `reviewed_by` int(10) unsigned MUL NULL
- `reviewed_at` datetime NULL
- `submitted_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: group_id → advisor_groups.id (ON DELETE RESTRICT); reviewed_by → users.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE RESTRICT)

## `home_visit_images`
- `id` int(10) unsigned PRI AI
- `visit_id` int(10) unsigned MUL
- `original_name` varchar(255)
- `stored_name` varchar(255) UNI
- `mime_type` varchar(100)
- `file_size` int(10) unsigned = 0
- `uploaded_at` datetime = current_timestamp()
- FK: visit_id → home_visits.id (ON DELETE CASCADE)

## `hr_educations` — วุฒิการศึกษา
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL
- `level` varchar(50) — ระดับการศึกษา
- `degree` varchar(150) = '' — ชื่อปริญญา/วุฒิ เช่น ค.อ.บ.
- `major` varchar(150) = '' — สาขาวิชา
- `institution` varchar(200) = ''
- `grad_year` smallint(5) unsigned NULL — ปีที่สำเร็จ (พ.ศ.)
- `created_at` datetime = current_timestamp()
- FK: user_id → users.id (ON DELETE CASCADE)

## `hr_events` — กิจกรรมวันสำคัญ
- `id` int(10) unsigned PRI AI
- `fiscal_year` smallint(5) unsigned MUL
- `title` varchar(300)
- `event_date` date
- `start_time` time NULL
- `location` varchar(200) = ''
- `dress_code` varchar(200) = '' — การแต่งกาย
- `detail` varchar(2000) = ''
- `is_required` tinyint(1) = 1 — 1 = บุคลากรทุกคนต้องเข้าร่วม (นับในสรุป)
- `self_report` tinyint(1) = 1 — 1 = บุคลากรรายงานการเข้าร่วมเองได้
- `participant_mode` varchar(10) = 'all' — all = บุคลากรทุกคน / selected = เฉพาะผู้ที่กำหนด
- `order_id` int(10) unsigned NULL — คำสั่งที่อ้างอิง (hr_orders)
- `created_by` int(10) unsigned NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL

## `hr_event_attendances` — การเข้าร่วมกิจกรรมวันสำคัญ
- `event_id` int(10) unsigned PRI
- `user_id` int(10) unsigned PRI
- `status` enum('attended','leave','absent') = 'attended' — เข้าร่วม / ลา-ไปราชการ / ไม่เข้าร่วม
- `note` varchar(300) = ''
- `photo_stored` varchar(100) NULL — ภาพหลักฐาน (uploads/hr)
- `photo_original` varchar(200) NULL
- `verified` tinyint(1) = 0 — 1 = งานบุคลากรยืนยันแล้ว
- `recorded_by` int(10) unsigned NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: event_id → hr_events.id (ON DELETE CASCADE); user_id → users.id (ON DELETE CASCADE)

## `hr_event_participants` — ผู้ที่ต้องเข้าร่วมกิจกรรม (เมื่อ participant_mode = selected)
- `event_id` int(10) unsigned PRI
- `user_id` int(10) unsigned PRI
- FK: event_id → hr_events.id (ON DELETE CASCADE); user_id → users.id (ON DELETE CASCADE)

## `hr_leaves` — ใบลา
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL
- `fiscal_year` smallint(5) unsigned — ปีงบประมาณ (พ.ศ.) ของวันเริ่มลา
- `leave_type` varchar(20) — sick personal vacation maternity paternity ordination other
- `start_date` date
- `end_date` date
- `half_day` varchar(10) = '' — ลาครึ่งวัน (ลา 1 วัน): morning / afternoon
- `days` decimal(5,1) — จำนวนวันลา (งานบุคลากรปรับได้ตอนตรวจสอบ)
- `reason` varchar(1000) = ''
- `contact` varchar(300) = '' — ระหว่างลาติดต่อได้ที่
- `substitute` varchar(150) = '' — ผู้ปฏิบัติงาน/สอนแทน
- `attach_stored` varchar(100) NULL — ไฟล์แนบ เช่น ใบรับรองแพทย์ (uploads/hr)
- `attach_original` varchar(200) NULL
- `status` varchar(20) MUL = 'pending_head' — pending_head pending_hr pending_deputy pending_director approved rejected cancelled
- `recorded_by` int(10) unsigned NULL — งานบุคลากรบันทึกแทน (ใบลากระดาษ/ย้อนหลัง)
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: user_id → users.id (ON DELETE CASCADE)

## `hr_leave_actions` — ลำดับการพิจารณาใบลา
- `id` int(10) unsigned PRI AI
- `leave_id` int(10) unsigned MUL
- `user_id` int(10) unsigned MUL NULL
- `step` varchar(20) — submit head hr deputy director cancel record
- `decision` varchar(20) = '' — approve reject (ความเห็น/ผลพิจารณา)
- `comment` varchar(1000) = ''
- `created_at` datetime = current_timestamp()
- FK: leave_id → hr_leaves.id (ON DELETE CASCADE); user_id → users.id (ON DELETE SET NULL)

## `hr_leave_quotas` — สิทธิ์วันลาพักผ่อนรายปีงบประมาณ
- `user_id` int(10) unsigned PRI
- `fiscal_year` smallint(5) unsigned PRI
- `vacation_days` decimal(4,1) — สิทธิ์ลาพักผ่อนปีนี้ รวมวันสะสม
- FK: user_id → users.id (ON DELETE CASCADE)

## `hr_orders` — คำสั่ง/หนังสือราชการ
- `id` int(10) unsigned PRI AI
- `order_no` varchar(50) — เลขที่คำสั่ง เช่น 123/2569
- `order_date` date MUL
- `category` varchar(50)
- `title` varchar(300)
- `detail` varchar(2000) = ''
- `file_stored` varchar(100) NULL — ไฟล์คำสั่ง (uploads/hr)
- `file_original` varchar(200) NULL
- `is_public` tinyint(1) = 0 — 1 = บุคลากรทุกคนเห็น / 0 = เฉพาะผู้มีชื่อในคำสั่ง
- `created_by` int(10) unsigned NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL

## `hr_order_users` — บุคลากรที่มีชื่อในคำสั่ง
- `order_id` int(10) unsigned PRI
- `user_id` int(10) unsigned PRI
- FK: order_id → hr_orders.id (ON DELETE CASCADE); user_id → users.id (ON DELETE CASCADE)

## `hr_profiles` — ทะเบียนประวัติบุคลากร
- `user_id` int(10) unsigned PRI
- `staff_type` varchar(50) MUL = '' — ประเภทบุคลากร เช่น ข้าราชการครู พนักงานราชการ
- `citizen_id` char(13) NULL — เลขประจำตัวประชาชน (เห็นเฉพาะเจ้าของและงานบุคลากร)
- `birth_date` date NULL
- `gender` varchar(10) = ''
- `blood_type` varchar(5) = ''
- `religion` varchar(50) = ''
- `nationality` varchar(50) = ''
- `address` varchar(500) = ''
- `emergency_name` varchar(150) = ''
- `emergency_relation` varchar(50) = ''
- `emergency_phone` varchar(20) = ''
- `position_no` varchar(30) = '' — เลขที่ตำแหน่ง
- `service_start_date` date NULL — วันที่บรรจุ/เริ่มรับราชการ
- `college_start_date` date NULL — วันที่เริ่มปฏิบัติงานที่วิทยาลัย
- `standing_date` date NULL — วันที่ได้รับวิทยฐานะปัจจุบัน
- `duties` text NULL — งาน/หน้าที่ที่ได้รับมอบหมาย
- `verified_by` int(10) unsigned NULL
- `verified_at` datetime NULL — งานบุคลากรตรวจสอบแล้ว (เจ้าของแก้ไขข้อมูล = ล้างค่า)
- `updated_by` int(10) unsigned NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: user_id → users.id (ON DELETE CASCADE)

## `hr_service_records` — ประวัติการรับราชการ/การดำรงตำแหน่ง
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL
- `record_date` date — วันที่มีผล
- `position` varchar(150) — ตำแหน่ง
- `standing` varchar(100) = '' — วิทยฐานะ/ระดับ
- `unit` varchar(200) = '' — สังกัด/หน่วยงาน
- `order_ref` varchar(150) = '' — เอกสารอ้างอิง/คำสั่ง
- `note` varchar(300) = ''
- `created_at` datetime = current_timestamp()
- FK: user_id → users.id (ON DELETE CASCADE)

## `hr_trainings` — การพัฒนาตนเอง/อบรม
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL
- `fiscal_year` smallint(5) unsigned
- `category` varchar(50) — อบรม สัมมนา ประชุมวิชาการ ศึกษาดูงาน อบรมออนไลน์ อื่น ๆ
- `title` varchar(300)
- `organizer` varchar(200) = ''
- `location` varchar(200) = ''
- `start_date` date
- `end_date` date
- `hours` decimal(6,1) = 0.0
- `detail` varchar(2000) = '' — สิ่งที่ได้รับ/การนำไปใช้
- `cert_stored` varchar(100) NULL — เกียรติบัตร (uploads/hr)
- `cert_original` varchar(200) NULL
- `status` enum('pending','approved','revision') MUL = 'pending'
- `review_comment` varchar(1000) = ''
- `reviewed_by` int(10) unsigned NULL
- `reviewed_at` datetime NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: user_id → users.id (ON DELETE CASCADE)

## `lesson_plans`
- `id` int(10) unsigned PRI AI
- `round_id` int(10) unsigned MUL
- `doc_type` enum('plan','outline') = 'plan'
- `teacher_id` int(10) unsigned MUL
- `academic_year_id` int(10) unsigned MUL NULL
- `subject_id` int(10) unsigned MUL NULL
- `subject_code` varchar(20)
- `subject_name` varchar(200)
- `level` enum('ปวช.','ปวส.') = 'ปวช.'
- `units` smallint(5) unsigned = 1 — จำนวนหน่วยการเรียนรู้
- `status` enum('pending','approved','revision') MUL = 'pending'
- `is_late` tinyint(1) = 0
- `current_version` smallint(5) unsigned = 1
- `submitted_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); round_id → submission_rounds.id (ON DELETE RESTRICT); subject_id → subjects.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE CASCADE)

## `password_reset_requests`
- `id` int(10) unsigned PRI AI
- `user_id` int(10) unsigned MUL
- `status` enum('pending','done','rejected') MUL = 'pending'
- `request_note` varchar(255) NULL
- `requested_ip` varchar(45) = ''
- `handled_by` int(10) unsigned MUL NULL
- `handled_at` datetime NULL
- `created_at` datetime = current_timestamp()
- FK: handled_by → users.id (ON DELETE SET NULL); user_id → users.id (ON DELETE CASCADE)

## `pa_submissions`
- `id` int(10) unsigned PRI AI
- `round_id` int(10) unsigned MUL
- `teacher_id` int(10) unsigned MUL
- `phase` enum('agreement','report') = 'agreement'
- `fiscal_year` smallint(5) unsigned MUL — ปีงบประมาณ (พ.ศ.)
- `position` varchar(100) = ''
- `academic_standing` varchar(100) = ''
- `part1_workload` text NULL — ส่วนที่ 1 ข้อตกลงภาระงาน
- `part2_challenge` text NULL — ส่วนที่ 2 ประเด็นท้าทาย
- `status` enum('pending','approved','revision') MUL = 'pending'
- `is_late` tinyint(1) = 0
- `current_version` smallint(5) unsigned = 1
- `submitted_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: round_id → submission_rounds.id (ON DELETE RESTRICT); teacher_id → users.id (ON DELETE CASCADE)

## `plan_evaluations`
- `id` int(10) unsigned PRI AI
- `lesson_plan_id` int(10) unsigned UNI — แผนการจัดการเรียนรู้ที่ครูส่ง (lesson_plans.doc_type = plan)
- `teacher_id` int(10) unsigned MUL
- `department_id` int(10) unsigned MUL NULL — แผนกของครู ณ วันที่ประเมิน (ใช้ทำรายงานรายแผนก)
- `academic_year_id` int(10) unsigned MUL NULL
- `evaluator_id` int(10) unsigned MUL NULL
- `evaluator_position` varchar(150) = '' — ตำแหน่งผู้ประเมิน ณ วันที่ประเมิน
- `plan_version` smallint(5) unsigned = 1 — ฉบับของแผนที่ประเมิน
- `program` enum('ปวช.','ปวส.') = 'ปวช.' — หลักสูตร
- `subject_category` varchar(150) = '' — ประเภทวิชา
- `major` varchar(150) = '' — สาขาวิชา
- `credit` varchar(20) = '' — ท-ป-น
- `unit_count` smallint(5) unsigned = 0 — จำนวนหน่วยการเรียนรู้
- `plan_count` smallint(5) unsigned = 0 — จำนวนแผนการจัดการเรียนรู้
- `has_standard` tinyint(1) = 0 — อ้างอิงมาตรฐานอาชีพ/หลักสูตรการฝึกยกระดับฝีมือ
- `total_score` smallint(5) unsigned = 0 — คะแนนรวมตอนที่ 2
- `max_score` smallint(5) unsigned = 0
- `item_count` smallint(5) unsigned = 0 — จำนวนข้อตอนที่ 2
- `avg_score` decimal(4,2) = 0.00
- `quality_level` varchar(50) = ''
- `check_passed` tinyint(1) = 0 — ตอนที่ 1 รายการที่ต้องมี "มี" ครบ
- `is_passed` tinyint(1) = 0 — ผ่านเกณฑ์: ค่าเฉลี่ย >= 3.51 และตอนที่ 1 ครบ
- `strengths` text NULL — จุดเด่นของแผนการจัดการเรียนรู้
- `improvements` text NULL — สิ่งที่ควรปรับปรุง/พัฒนา
- `decision` enum('approve','approve_revise','resubmit') NULL — ผลการพิจารณา (ร่างยังไม่เลือกได้)
- `resubmit_by` date NULL — กำหนดนำเสนอใหม่ภายในวันที่
- `status` enum('draft','final') MUL = 'draft' — draft = ร่าง (ครูยังไม่เห็น), final = ประเมินเสร็จ ส่งผลให้ครูแล้ว
- `finalized_at` datetime NULL — วันเวลาที่ส่งผลให้ครู
- `acknowledged_at` datetime NULL — วันเวลาที่ครูกดรับทราบ
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); department_id → departments.id (ON DELETE SET NULL); evaluator_id → users.id (ON DELETE SET NULL); lesson_plan_id → lesson_plans.id (ON DELETE CASCADE); teacher_id → users.id (ON DELETE CASCADE)

## `plan_eval_criteria`
- `id` int(10) unsigned PRI AI
- `part` enum('check','quality') MUL — check = ตอนที่ 1 ตรวจองค์ประกอบ, quality = ตอนที่ 2 ประเมินคุณภาพ
- `category` varchar(150) — ส่วน (ตอนที่ 1) / ด้าน (ตอนที่ 2)
- `title` varchar(500) — รายการ
- `is_required` tinyint(1) = 0 — ตอนที่ 1: รายการที่ต้องมีทุกรายวิชา (เลือก "ไม่เกี่ยวข้อง" ไม่ได้)
- `sort_order` smallint(5) unsigned = 0
- `is_active` tinyint(1) = 1
- `created_at` datetime = current_timestamp()

## `plan_eval_items`
- `id` int(10) unsigned PRI AI
- `evaluation_id` int(10) unsigned MUL
- `criteria_id` int(10) unsigned MUL NULL
- `part` enum('check','quality')
- `category` varchar(150) = ''
- `title` varchar(500) = ''
- `is_required` tinyint(1) = 0
- `check_result` enum('yes','no','na') NULL — ตอนที่ 1: มี / ไม่มี / ไม่เกี่ยวข้อง
- `score` tinyint(3) unsigned NULL — ตอนที่ 2: 1-5
- FK: criteria_id → plan_eval_criteria.id (ON DELETE SET NULL); evaluation_id → plan_evaluations.id (ON DELETE CASCADE)

## `research_submissions`
- `id` int(10) unsigned PRI AI
- `round_id` int(10) unsigned MUL
- `teacher_id` int(10) unsigned MUL
- `academic_year_id` int(10) unsigned MUL NULL
- `parent_id` int(10) unsigned MUL NULL
- `stage` enum('proposal','final') = 'proposal'
- `title` varchar(255)
- `problem` text NULL — ปัญหาการวิจัย
- `target_group` varchar(255) = '' — กลุ่มเป้าหมาย
- `subject_id` int(10) unsigned MUL NULL
- `subject_text` varchar(255) = '' — วิชาที่เกี่ยวข้อง
- `status` enum('pending','approved','revision') MUL = 'pending'
- `is_late` tinyint(1) = 0
- `current_version` smallint(5) unsigned = 1
- `submitted_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); parent_id → research_submissions.id (ON DELETE SET NULL); round_id → submission_rounds.id (ON DELETE RESTRICT); subject_id → subjects.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE CASCADE)

## `reviews`
- `id` int(10) unsigned PRI AI
- `submission_type` enum('lesson_plan','pa','research') MUL
- `submission_id` int(10) unsigned
- `version` smallint(5) unsigned = 1
- `reviewer_id` int(10) unsigned MUL NULL
- `result` enum('approved','revision')
- `comment` text NULL
- `created_at` datetime = current_timestamp()
- FK: reviewer_id → users.id (ON DELETE SET NULL)

## `settings`
- `setting_key` varchar(100) PRI
- `setting_value` text NULL
- `description` varchar(255) NULL
- `updated_at` datetime NULL

## `site_visitors` — กันนับผู้เข้าชมซ้ำภายในวัน
- `visit_date` date PRI
- `visitor_hash` char(64) PRI — SHA-256 ของ IP+เบราว์เซอร์+วันที่ (ย้อนกลับเป็น IP ไม่ได้)

## `site_visit_daily` — จำนวนผู้เข้าชมรายวัน
- `visit_date` date PRI
- `visits` int(10) unsigned = 0

## `student_eval_responses`
- `id` int(10) unsigned PRI AI
- `session_id` int(10) unsigned MUL
- `avg_score` decimal(4,2) = 0.00
- `comment` text NULL — ข้อเสนอแนะถึงครู
- `created_at` datetime = current_timestamp()
- FK: session_id → student_eval_sessions.id (ON DELETE CASCADE)

## `student_eval_scores`
- `id` int(10) unsigned PRI AI
- `response_id` int(10) unsigned MUL
- `criteria_id` int(10) unsigned MUL NULL
- `category` varchar(150) = ''
- `criteria_title` varchar(255) = ''
- `score` tinyint(3) unsigned — 1-5
- FK: criteria_id → supervision_criteria.id (ON DELETE SET NULL); response_id → student_eval_responses.id (ON DELETE CASCADE)

## `student_eval_sessions`
- `id` int(10) unsigned PRI AI
- `token` char(32) UNI — รหัสสุ่มในลิงก์/QR Code
- `academic_year_id` int(10) unsigned MUL NULL
- `teacher_id` int(10) unsigned MUL
- `subject_id` int(10) unsigned MUL NULL
- `subject_name` varchar(200) = ''
- `level` enum('ปวช.','ปวส.') MUL NULL — ระดับของกลุ่มนักเรียน
- `grade_year` tinyint(3) unsigned NULL — ชั้นปี (ปวช. 1-3, ปวส. 1-2)
- `group_no` varchar(20) = '' — กลุ่ม/ห้อง เช่น 1, 2, ชฟ.1
- `classroom` varchar(100) = ''
- `open_at` datetime
- `close_at` datetime
- `max_responses` smallint(5) unsigned = 0 — 0 = ไม่จำกัด
- `is_active` tinyint(1) = 1
- `note` varchar(500) NULL
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); created_by → users.id (ON DELETE SET NULL); subject_id → subjects.id (ON DELETE SET NULL); teacher_id → users.id (ON DELETE CASCADE)

## `subjects`
- `id` int(10) unsigned PRI AI
- `code` varchar(20) UNI
- `name` varchar(200)
- `credit` varchar(20) = '' — ท-ป-น เช่น 1-3-2
- `level` enum('ปวช.','ปวส.') = 'ปวช.'
- `department_id` int(10) unsigned MUL NULL
- `is_active` tinyint(1) = 1
- `created_at` datetime = current_timestamp()
- FK: department_id → departments.id (ON DELETE SET NULL)

## `submission_files`
- `id` int(10) unsigned PRI AI
- `submission_type` enum('lesson_plan','pa','research') MUL
- `submission_id` int(10) unsigned
- `version` smallint(5) unsigned = 1
- `original_name` varchar(255)
- `stored_name` varchar(255) UNI — ชื่อไฟล์สุ่มบนเซิร์ฟเวอร์
- `file_dir` varchar(50) — โฟลเดอร์ย่อยใน uploads/
- `mime_type` varchar(150)
- `file_size` int(10) unsigned = 0
- `is_late` tinyint(1) = 0
- `note` varchar(500) NULL — หมายเหตุการส่ง/แก้ไข
- `uploaded_by` int(10) unsigned MUL NULL
- `uploaded_at` datetime = current_timestamp()
- FK: uploaded_by → users.id (ON DELETE SET NULL)

## `submission_rounds`
- `id` int(10) unsigned PRI AI
- `type` enum('lesson_plan','pa','research') MUL
- `phase` enum('main','outline','agreement','report','proposal','final') = 'main'
- `name` varchar(200)
- `academic_year_id` int(10) unsigned MUL NULL
- `fiscal_year` smallint(5) unsigned NULL — ปีงบประมาณ (ใช้กับ PA)
- `description` text NULL
- `open_at` datetime MUL
- `close_at` datetime
- `allow_late` tinyint(1) = 0
- `is_active` tinyint(1) = 1
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); created_by → users.id (ON DELETE SET NULL)

## `supervision_criteria`
- `id` int(10) unsigned PRI AI
- `audience` enum('supervisor','student') MUL = 'supervisor' — ชุดหัวข้อ: หัวหน้าแผนกนิเทศ / นักเรียนประเมิน
- `category` varchar(150) — ด้านการประเมิน
- `title` varchar(255) — รายการประเมิน
- `sort_order` smallint(5) unsigned = 0
- `is_active` tinyint(1) = 1
- `created_at` datetime = current_timestamp()

## `supervision_images`
- `id` int(10) unsigned PRI AI
- `result_id` int(10) unsigned MUL
- `original_name` varchar(255)
- `stored_name` varchar(255) UNI
- `mime_type` varchar(100)
- `file_size` int(10) unsigned = 0
- `uploaded_at` datetime = current_timestamp()
- FK: result_id → supervision_results.id (ON DELETE CASCADE)

## `supervision_reports`
- `id` int(10) unsigned PRI AI
- `department_id` int(10) unsigned MUL
- `academic_year_id` int(10) unsigned MUL
- `author_id` int(10) unsigned MUL — หัวหน้าแผนกผู้จัดทำ
- `title` varchar(255)
- `period_start` date NULL — ช่วงวันที่นิเทศที่นำมารายงาน (ว่าง = ทั้งภาคเรียน)
- `period_end` date NULL
- `summary_overall` text NULL — สรุปผลการนิเทศโดยภาพรวม
- `strengths` text NULL — จุดเด่น
- `problems` text NULL — ปัญหา/อุปสรรค
- `suggestions` text NULL — ข้อเสนอแนะ/แนวทางพัฒนา
- `status` enum('draft','pending','returned','approved') MUL = 'draft'
- `current_step` tinyint(3) unsigned = 0 — 1-3 = ขั้นที่รอพิจารณา
- `snapshot` longtext NULL
- `submitted_at` datetime NULL
- `approved_at` datetime NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE RESTRICT); author_id → users.id (ON DELETE RESTRICT); department_id → departments.id (ON DELETE RESTRICT)

## `supervision_report_actions`
- `id` int(10) unsigned PRI AI
- `report_id` int(10) unsigned MUL
- `step` tinyint(3) unsigned
- `user_id` int(10) unsigned MUL NULL
- `position` varchar(150) = '' — ตำแหน่งของผู้พิจารณา ณ เวลานั้น
- `action` enum('submit','forward','approve','return')
- `comment` text NULL
- `created_at` datetime = current_timestamp()
- FK: report_id → supervision_reports.id (ON DELETE CASCADE); user_id → users.id (ON DELETE SET NULL)

## `supervision_results`
- `id` int(10) unsigned PRI AI
- `schedule_id` int(10) unsigned UNI
- `supervisor_id` int(10) unsigned MUL NULL
- `avg_score` decimal(4,2) = 0.00
- `quality_level` varchar(50) = '' — ดีมาก/ดี/ปานกลาง/พอใช้/ปรับปรุง
- `strengths` text NULL — จุดเด่น
- `suggestions` text NULL — ข้อเสนอแนะ
- `acknowledged_at` datetime NULL — วันเวลาที่ครูกดรับทราบ
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: schedule_id → supervision_schedules.id (ON DELETE CASCADE); supervisor_id → users.id (ON DELETE SET NULL)

## `supervision_schedules`
- `id` int(10) unsigned PRI AI
- `academic_year_id` int(10) unsigned MUL NULL
- `teacher_id` int(10) unsigned MUL
- `supervisor_id` int(10) unsigned MUL
- `subject_id` int(10) unsigned MUL NULL
- `subject_name` varchar(200) = ''
- `classroom` varchar(100) = ''
- `supervise_date` date MUL
- `start_time` time
- `end_time` time
- `status` enum('scheduled','completed','cancelled') = 'scheduled'
- `note` varchar(500) NULL
- `created_by` int(10) unsigned MUL NULL
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: academic_year_id → academic_years.id (ON DELETE SET NULL); created_by → users.id (ON DELETE SET NULL); subject_id → subjects.id (ON DELETE SET NULL); supervisor_id → users.id (ON DELETE RESTRICT); teacher_id → users.id (ON DELETE CASCADE)

## `supervision_scores`
- `id` int(10) unsigned PRI AI
- `result_id` int(10) unsigned MUL
- `criteria_id` int(10) unsigned MUL NULL
- `category` varchar(150) = ''
- `criteria_title` varchar(255) = ''
- `score` tinyint(3) unsigned — 1-5
- FK: criteria_id → supervision_criteria.id (ON DELETE SET NULL); result_id → supervision_results.id (ON DELETE CASCADE)

## `teaching_subjects`
- `id` int(10) unsigned PRI AI
- `teacher_id` int(10) unsigned MUL
- `academic_year_id` int(10) unsigned MUL
- `subject_code` varchar(20)
- `subject_name` varchar(200)
- `level` enum('ปวช.','ปวส.') = 'ปวช.'
- `created_at` datetime = current_timestamp()
- FK: academic_year_id → academic_years.id (ON DELETE CASCADE); teacher_id → users.id (ON DELETE CASCADE)

## `users`
- `id` int(10) unsigned PRI AI
- `username` varchar(50) UNI
- `password_hash` varchar(255)
- `prefix` varchar(30) = ''
- `first_name` varchar(100)
- `last_name` varchar(100)
- `department_id` int(10) unsigned MUL NULL
- `position` varchar(100) = '' — ตำแหน่ง เช่น ครู, ครูผู้ช่วย, พนักงานราชการ
- `academic_standing` varchar(100) = '' — วิทยฐานะ
- `email` varchar(150) UNI
- `phone` varchar(20) = ''
- `avatar` varchar(255) NULL
- `role` enum('admin','supervisor','teacher','executive') MUL = 'teacher'
- `status` enum('pending','active','suspended') = 'pending'
- `admin_permissions` text NULL — สิทธิ์เจ้าหน้าที่ดูแลระบบ (JSON) — NULL = ผู้ดูแลระบบเต็มสิทธิ์ ใช้เฉพาะ role admin
- `must_change_password` tinyint(1) = 0
- `google_sub` varchar(64) UNI NULL — รหัสบัญชี Google ที่เชื่อมไว้ (เข้าสู่ระบบด้วย Google)
- `password_login` tinyint(1) = 1 — 0 = สมัครด้วย Google ยังไม่เคยตั้งรหัสผ่าน
- `pdpa_version` varchar(20) NULL — ฉบับของข้อตกลง PDPA ที่ยอมรับล่าสุด
- `pdpa_accepted_at` datetime NULL — วันเวลาที่ยอมรับข้อตกลง PDPA
- `last_login_at` datetime NULL
- `last_seen_at` datetime MUL NULL — ใช้งานล่าสุด (อัปเดตไม่เกิน 1 ครั้งต่อนาที)
- `created_at` datetime = current_timestamp()
- `updated_at` datetime NULL
- FK: department_id → departments.id (ON DELETE SET NULL)

## `user_active_days` — วันที่ผู้ใช้เข้าใช้งาน
- `user_id` int(10) unsigned PRI
- `active_date` date PRI
- `cheered` tinyint(1) = 0 — 1 = แจ้งข้อความชื่นชมของวันนี้แล้ว
- FK: user_id → users.id (ON DELETE CASCADE)
