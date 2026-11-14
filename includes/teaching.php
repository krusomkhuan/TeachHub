<?php
/**
 * ไฟล์: includes/teaching.php
 * รายวิชาที่ครูสอน + เกณฑ์การส่งงานรายภาคเรียน
 *   เกณฑ์: 1) ส่งโครงการสอนครบทุกรายวิชาที่สอน  2) ส่งแผนการจัดการเรียนรู้อย่างน้อย 1 วิชา
 *   รายวิชาที่หัวหน้าแผนก/นักเรียนประเมิน = วิชาที่แผนการจัดการเรียนรู้ "ผ่าน" แล้วในภาคเรียนนั้น
 */

/** ชื่อประเภทเอกสารในงานแผนการสอน */
function lesson_doc_label(string $docType): string
{
    return $docType === 'outline' ? 'โครงการสอน' : 'แผนการจัดการเรียนรู้';
}

/** ประเภทเอกสารที่ครูเลือกส่งได้ (ตัวเลือก list ในฟอร์มส่ง) */
function lesson_doc_types(): array
{
    return ['outline' => 'โครงการสอน', 'plan' => 'แผนการจัดการเรียนรู้'];
}

/** ภาคเรียนของรอบ (ถ้ารอบไม่ระบุ ใช้ภาคเรียนปัจจุบัน) */
function round_academic_year_id(array $round): int
{
    return (int)($round['academic_year_id'] ?? 0) ?: (int)(current_academic_year()['id'] ?? 0);
}

/** ฐานข้อมูลมีคอลัมน์ข้อมูลทั่วไปของรายวิชาแล้วหรือยัง (นำเข้า update_v1.23.sql) */
function teaching_info_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool)db_value("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'teaching_subjects' AND COLUMN_NAME = 'plan_count' LIMIT 1");
    }
    return $ready;
}

/**
 * อ่านและตรวจข้อมูลทั่วไปของรายวิชาจากฟอร์ม (ประเภทวิชา สาขาวิชา ท-ป-น จำนวนหน่วย จำนวนแผน)
 * @return array ค่าที่ผ่านการตัดความยาวแล้ว ข้อผิดพลาดเพิ่มลงใน $errors ตามชื่อช่อง
 */
function teaching_info_from_post(array &$errors): array
{
    $info = [
        'subject_category' => mb_substr(post('subject_category'), 0, 150),
        'major'            => mb_substr(post('major'), 0, 150),
        'credit'           => mb_substr(post('credit'), 0, 20),
        'unit_count'       => (int)post('unit_count'),
        'plan_count'       => (int)post('plan_count'),
    ];
    if ($info['subject_category'] === '') {
        $errors['subject_category'] = 'กรุณากรอกประเภทวิชา';
    }
    if ($info['major'] === '') {
        $errors['major'] = 'กรุณากรอกสาขาวิชา';
    }
    if (!preg_match('/^\d+-\d+-\d+$/', $info['credit'])) {
        $errors['credit'] = 'กรุณากรอก ท-ป-น ให้ถูกรูปแบบ เช่น 1-3-2';
    }
    if ($info['unit_count'] < 1 || $info['unit_count'] > 999) {
        $errors['unit_count'] = 'ระบุจำนวนหน่วย 1–999';
    }
    if ($info['plan_count'] < 1 || $info['plan_count'] > 999) {
        $errors['plan_count'] = 'ระบุจำนวนแผน 1–999';
    }
    return $info;
}

/** กรอกข้อมูลทั่วไปของรายวิชาครบแล้วหรือยัง */
function teaching_info_complete(array $row): bool
{
    return ($row['subject_category'] ?? '') !== '' && ($row['major'] ?? '') !== '' && ($row['credit'] ?? '') !== ''
        && (int)($row['unit_count'] ?? 0) > 0 && (int)($row['plan_count'] ?? 0) > 0;
}

/** ข้อมูลทั่วไปที่ครูกรอกไว้ในรายวิชาที่สอน (null = ไม่พบรายวิชา หรือยังไม่ได้นำเข้า update_v1.23.sql) */
function teaching_subject_info(int $teacherId, int $ayId, string $subjectCode): ?array
{
    if (!teaching_info_ready()) {
        return null;
    }
    return db_one(
        'SELECT id, subject_category, major, credit, unit_count, plan_count FROM teaching_subjects
         WHERE teacher_id = ? AND academic_year_id = ? AND subject_code = ?',
        'iis',
        [$teacherId, $ayId, $subjectCode]
    );
}

/**
 * รหัสแถวรายวิชาที่สอนที่ยังกรอกข้อมูลทั่วไปไม่ครบ ใช้ตรวจก่อนส่งแผนการจัดการเรียนรู้
 * @return int|null null = ส่งแผนได้ (กรอกครบ / แผนรุ่นเก่าไม่มีแถวรายวิชา / ยังไม่ได้นำเข้า update_v1.23.sql)
 */
function teaching_info_missing_id(int $teacherId, int $ayId, string $subjectCode): ?int
{
    $info = teaching_subject_info($teacherId, $ayId, $subjectCode);
    return $info && !teaching_info_complete($info) ? (int)$info['id'] : null;
}

/** รายวิชาที่ครูสอนในภาคเรียน */
function teaching_subjects(int $teacherId, int $ayId): array
{
    return db_all(
        'SELECT * FROM teaching_subjects WHERE teacher_id = ? AND academic_year_id = ? ORDER BY subject_code',
        'ii',
        [$teacherId, $ayId]
    );
}

/**
 * รายวิชาที่สอนพร้อมสถานะโครงการสอน/แผนการสอนล่าสุดของแต่ละวิชา
 * @return array แถว: teaching_subjects.* + outline_status, outline_id, plan_status, plan_id
 */
function teaching_subjects_with_status(int $teacherId, int $ayId): array
{
    $rows = teaching_subjects($teacherId, $ayId);
    $docs = db_all(
        'SELECT id, doc_type, subject_code, status FROM lesson_plans
         WHERE teacher_id = ? AND academic_year_id = ? ORDER BY id',
        'ii',
        [$teacherId, $ayId]
    );
    $map = [];
    foreach ($docs as $d) {
        $k = $d['doc_type'] . '|' . $d['subject_code'];
        // ถ้ามีหลายรอบ ให้ใช้ฉบับที่ดีที่สุด (ผ่าน > รอตรวจ > ให้แก้ไข)
        $rank = ['approved' => 3, 'pending' => 2, 'revision' => 1][$d['status']] ?? 0;
        if (!isset($map[$k]) || $rank > $map[$k]['rank']) {
            $map[$k] = ['rank' => $rank, 'status' => $d['status'], 'id' => (int)$d['id']];
        }
    }
    foreach ($rows as &$r) {
        $o = $map['outline|' . $r['subject_code']] ?? null;
        $p = $map['plan|' . $r['subject_code']] ?? null;
        $r['outline_status'] = $o['status'] ?? null;
        $r['outline_id'] = $o['id'] ?? 0;
        $r['plan_status'] = $p['status'] ?? null;
        $r['plan_id'] = $p['id'] ?? 0;
    }
    unset($r);
    return $rows;
}

/**
 * สรุปเกณฑ์การส่งของครู 1 คนใน 1 ภาคเรียน
 * @return array subjects, outlines (ส่งแล้วกี่วิชา), plans (ส่งแล้วกี่วิชา), plans_approved, outline_ok, plan_ok, ok
 */
function teaching_compliance(int $teacherId, int $ayId): array
{
    $rows = teaching_subjects_with_status($teacherId, $ayId);
    $c = ['subjects' => count($rows), 'outlines' => 0, 'plans' => 0, 'plans_approved' => 0, 'missing' => []];
    foreach ($rows as $r) {
        if ($r['outline_status'] !== null) {
            $c['outlines']++;
        } else {
            $c['missing'][] = $r['subject_code'] . ' ' . $r['subject_name'];
        }
        if ($r['plan_status'] !== null) {
            $c['plans']++;
        }
        if ($r['plan_status'] === 'approved') {
            $c['plans_approved']++;
        }
    }
    $c['outline_ok'] = $c['subjects'] > 0 && $c['outlines'] === $c['subjects'];
    $c['plan_ok'] = $c['plans'] >= 1;
    $c['ok'] = $c['outline_ok'] && $c['plan_ok'];
    return $c;
}

/** ป้ายผลตามเกณฑ์ */
function compliance_badge(bool $ok, string $okText = 'ครบตามเกณฑ์', string $noText = 'ยังไม่ครบ'): string
{
    return $ok
        ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>' . e($okText) . '</span>'
        : '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>' . e($noText) . '</span>';
}

/**
 * รายวิชาที่แผนการจัดการเรียนรู้ผ่านแล้ว (ใช้เลือกวิชาในการนิเทศ/การประเมินโดยนักเรียน)
 * @return array [teacher_id][academic_year_id] => ["รหัส ชื่อวิชา", ...]
 */
function approved_plan_subject_map(): array
{
    $rows = db_all(
        "SELECT DISTINCT teacher_id, academic_year_id, CONCAT(subject_code, ' ', subject_name) AS label
         FROM lesson_plans
         WHERE doc_type = 'plan' AND status = 'approved' AND academic_year_id IS NOT NULL
         ORDER BY label"
    );
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['teacher_id']][(int)$r['academic_year_id']][] = $r['label'];
    }
    return $map;
}

/** วิชานี้ (รูปแบบ "รหัส ชื่อวิชา") แผนการจัดการเรียนรู้ผ่านแล้วหรือไม่ */
function approved_plan_subject_ok(int $teacherId, int $ayId, string $label): bool
{
    return (bool)db_value(
        "SELECT id FROM lesson_plans
         WHERE teacher_id = ? AND academic_year_id = ? AND doc_type = 'plan' AND status = 'approved'
           AND CONCAT(subject_code, ' ', subject_name) = ? LIMIT 1",
        'iis',
        [$teacherId, $ayId, $label]
    );
}

/**
 * JavaScript: เติมตัวเลือก "รายวิชา" ตามครูและภาคเรียนที่เลือก
 * ใช้กับ <select id="subject_name" data-value="ค่าเดิม"> + #teacher_id + #academic_year_id
 */
function approved_plan_subject_js(array $map): string
{
    return '(function () {
    var map = ' . json_encode($map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_FORCE_OBJECT) . ';
    var t = document.getElementById("teacher_id"), ay = document.getElementById("academic_year_id"),
        s = document.getElementById("subject_name"), hint = document.getElementById("subjectHint");
    if (!t || !ay || !s) return;
    function fill() {
        var cur = s.value || s.dataset.value || "", list = ((map[t.value] || {})[ay.value]) || [];
        list = Object.keys(list).map(function (k) { return list[k]; });
        s.innerHTML = "";
        var first = document.createElement("option");
        first.value = "";
        first.textContent = list.length ? "-- เลือกรายวิชา --" : "-- ไม่มีแผนการจัดการเรียนรู้ที่ผ่านแล้ว --";
        s.add(first);
        if (cur && list.indexOf(cur) < 0) list = [cur].concat(list);
        list.forEach(function (v) { var o = document.createElement("option"); o.value = v; o.textContent = v; if (v === cur) o.selected = true; s.add(o); });
        if (hint) hint.classList.toggle("text-danger", list.length === 0);
    }
    t.addEventListener("change", function () { s.dataset.value = ""; fill(); });
    ay.addEventListener("change", function () { s.dataset.value = ""; fill(); });
    fill();
})();';
}
