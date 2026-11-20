<?php
/**
 * ไฟล์: includes/follow_up.php
 * ข้อมูลเมนู "ติดตามการส่งงาน" (reports/follow_up.php และกล่องสรุปบนแดชบอร์ด)
 *   outline    : ครูที่ยังส่งโครงการสอนไม่ครบทุกรายวิชาที่สอน
 *   plan       : ครูที่ยังไม่ส่งแผนการจัดการเรียนรู้ (เกณฑ์: อย่างน้อย FOLLOW_UP_PLAN_REQUIRED วิชา ส่งเกินได้)
 *   review     : หัวหน้าแผนกวิชาที่ยังมีงานรอตรวจ และแผนที่ยังไม่ประเมิน
 *   no_subject : ครูที่ยังไม่บันทึก "รายวิชาที่สอน"
 *   research   : ครูที่ยังไม่ส่งเค้าโครงวิจัยในชั้นเรียน
 * นับเฉพาะครูผู้สอน + หัวหน้าแผนกที่ใช้งานอยู่ (ไม่นับเจ้าหน้าที่งานพัฒนาหลักสูตรฯ)
 * "ส่งแล้ว" = มีเอกสารในภาคเรียนนั้นไม่ว่าสถานะใด (รอตรวจ / ผ่าน / ให้แก้ไข)
 */

/** เกณฑ์แผนการจัดการเรียนรู้: ส่งอย่างน้อยกี่รายวิชาต่อภาคเรียน (ตรงกับ teaching_compliance) */
const FOLLOW_UP_PLAN_REQUIRED = 1;

/** ครูส่งแผนการจัดการเรียนรู้ครบตามเกณฑ์แล้วหรือไม่ */
function follow_up_plan_done(array $t): bool
{
    return $t['plans'] >= min(FOLLOW_UP_PLAN_REQUIRED, max(1, $t['subjects']));
}

/** รายการรายงานในเมนู [view => [ชื่อเมนู, ไอคอน, คำอธิบาย]] */
function follow_up_views(): array
{
    return [
        'outline'    => ['ครูที่ยังไม่ส่งโครงการสอน', 'bi-journal-x', 'ครูที่ส่งโครงการสอนยังไม่ครบทุกรายวิชาที่สอน'],
        'plan'       => ['ครูที่ยังไม่ส่งแผนการสอน', 'bi-file-earmark-x', 'เกณฑ์: ส่งแผนการจัดการเรียนรู้อย่างน้อย ' . FOLLOW_UP_PLAN_REQUIRED . ' รายวิชาต่อภาคเรียน (ส่งมากกว่านี้ได้) ครูที่ส่งแล้วถือว่าส่งครบ'],
        'review'     => ['หัวหน้าแผนกที่ยังไม่ตรวจ/ประเมินแผน', 'bi-hourglass-split', 'งานที่ยังรอหัวหน้าแผนกวิชาตรวจ และแผนที่ยังไม่ได้ประเมิน'],
        'no_subject' => ['ครูที่ยังไม่ลงรายวิชาที่สอน', 'bi-book', 'ครูที่ยังไม่บันทึกรายวิชาที่สอนในภาคเรียนนี้ จึงยังส่งโครงการสอน/แผนการสอนไม่ได้'],
        'research'   => ['ครูที่ยังไม่ส่งเค้าโครงวิจัย', 'bi-search', 'ครูที่ยังไม่ส่งเค้าโครงวิจัยในชั้นเรียนในภาคเรียนนี้'],
    ];
}

/** ผู้ใช้ดูรายงานติดตามการส่งงานได้หรือไม่ (หัวหน้าแผนกเห็นเฉพาะแผนกตนเอง) */
function can_view_follow_up(array $user): bool
{
    return in_array($user['role'], ['admin', 'executive'], true)
        || ($user['role'] === 'supervisor' && (int)$user['department_id'] > 0)
        || is_curriculum_staff($user) || is_curriculum_head($user);
}

/** แผนกที่ผู้ใช้ถูกจำกัดให้ดู (0 = ทุกแผนก) */
function follow_up_forced_dept(array $user): int
{
    return $user['role'] === 'supervisor' ? (int)$user['department_id'] : 0;
}

/** ครูที่มีหน้าที่ส่งงาน (ครู + หัวหน้าแผนก ที่ใช้งานอยู่) */
function follow_up_teachers(int $deptId): array
{
    return db_all(
        "SELECT u.id, u.prefix, u.first_name, u.last_name, u.role, u.department_id, COALESCE(d.name, '-') AS department_name
         FROM users u LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.status = 'active' AND u.role IN ('teacher','supervisor') AND (? = 0 OR u.department_id = ?)" . curriculum_staff_exclude_sql() . "
         ORDER BY d.name, u.first_name, u.last_name",
        'ii',
        [$deptId, $deptId]
    );
}

/**
 * สถานะโครงการสอน/แผนการสอนของครูทุกคนในขอบเขต
 * @return array แถวครู + subjects, outlines, plans, missing_outline[], missing_plan[], sent_plan[]
 */
function follow_up_teaching_rows(int $ayId, int $deptId): array
{
    $rows = [];
    foreach (follow_up_teachers($deptId) as $t) {
        $subjects = teaching_subjects_with_status((int)$t['id'], $ayId);
        $t['subjects'] = count($subjects);
        $t['outlines'] = $t['plans'] = 0;
        $t['missing_outline'] = $t['missing_plan'] = $t['sent_plan'] = [];
        foreach ($subjects as $s) {
            $label = $s['subject_code'] . ' ' . $s['subject_name'];
            if ($s['outline_status'] !== null) {
                $t['outlines']++;
            } else {
                $t['missing_outline'][] = $label;
            }
            if ($s['plan_status'] !== null) {
                $t['plans']++;
                $t['sent_plan'][] = $label;
            } else {
                $t['missing_plan'][] = $label;
            }
        }
        $rows[] = $t;
    }
    return $rows;
}

/** ครูที่ยังไม่ส่งเค้าโครงวิจัยในภาคเรียน (นับทุกสถานะ) */
function follow_up_research_rows(int $ayId, int $deptId): array
{
    $sent = [];
    foreach (db_all(
        "SELECT DISTINCT rs.teacher_id FROM research_submissions rs LEFT JOIN submission_rounds r ON r.id = rs.round_id
         WHERE rs.stage = 'proposal' AND COALESCE(rs.academic_year_id, r.academic_year_id) = ?",
        'i',
        [$ayId]
    ) as $r) {
        $sent[(int)$r['teacher_id']] = true;
    }
    $rows = [];
    foreach (follow_up_teachers($deptId) as $t) {
        $t['sent'] = isset($sent[(int)$t['id']]);
        $rows[] = $t;
    }
    return $rows;
}

/**
 * งานค้างของหัวหน้าแผนกแต่ละแผนก
 * pending_outline / pending_plan = รอตรวจ, oldest = วันที่ส่งของงานที่รอนานที่สุด,
 * plans_total = แผนทั้งหมดในภาคเรียน, eval_none = ยังไม่ประเมิน, eval_draft = บันทึกร่างไว้
 */
function follow_up_review_rows(int $ayId, int $deptId): array
{
    $depts = db_all(
        'SELECT d.id, d.name FROM departments d
         WHERE (? = 0 OR d.id = ?) AND EXISTS (SELECT 1 FROM users u WHERE u.department_id = d.id AND u.status = \'active\' AND u.role IN (\'teacher\',\'supervisor\'))
         ORDER BY d.name',
        'ii',
        [$deptId, $deptId]
    );
    $heads = [];
    foreach (db_all("SELECT id, prefix, first_name, last_name, department_id FROM users
                     WHERE role = 'supervisor' AND status = 'active' AND department_id IS NOT NULL ORDER BY first_name") as $h) {
        $heads[(int)$h['department_id']][] = full_name($h);
    }
    $ay = plan_eval_ay_sql('lp', 'r');
    $stats = [];
    foreach (db_all(
        "SELECT u.department_id AS dept,
                SUM(lp.status = 'pending' AND lp.doc_type = 'outline') AS pending_outline,
                SUM(lp.status = 'pending' AND lp.doc_type = 'plan') AS pending_plan,
                MIN(CASE WHEN lp.status = 'pending' THEN lp.submitted_at END) AS oldest,
                SUM(lp.doc_type = 'plan') AS plans_total,
                SUM(lp.doc_type = 'plan' AND pe.id IS NULL) AS eval_none,
                SUM(lp.doc_type = 'plan' AND pe.status = 'draft') AS eval_draft
         FROM lesson_plans lp JOIN users u ON u.id = lp.teacher_id
         LEFT JOIN submission_rounds r ON r.id = lp.round_id
         LEFT JOIN plan_evaluations pe ON pe.lesson_plan_id = lp.id
         WHERE $ay = ? AND u.department_id IS NOT NULL
         GROUP BY u.department_id",
        'i',
        [$ayId]
    ) as $s) {
        $stats[(int)$s['dept']] = $s;
    }
    $rows = [];
    foreach ($depts as $d) {
        $s = $stats[(int)$d['id']] ?? [];
        $row = [
            'id' => (int)$d['id'],
            'department_name' => $d['name'],
            'heads' => $heads[(int)$d['id']] ?? [],
            'pending_outline' => (int)($s['pending_outline'] ?? 0),
            'pending_plan' => (int)($s['pending_plan'] ?? 0),
            'oldest' => $s['oldest'] ?? null,
            'plans_total' => (int)($s['plans_total'] ?? 0),
            'eval_none' => (int)($s['eval_none'] ?? 0),
            'eval_draft' => (int)($s['eval_draft'] ?? 0),
        ];
        $row['pending'] = $row['pending_outline'] + $row['pending_plan'];
        $row['eval_left'] = $row['eval_none'] + $row['eval_draft'];
        $rows[] = $row;
    }
    return $rows;
}

/** จำนวนวันที่รอ นับจากวันที่ส่ง */
function follow_up_days_waiting(?string $since): int
{
    return $since ? max(0, (int)floor((time() - strtotime($since)) / 86400)) : 0;
}

/**
 * ตัวเลขสรุปสำหรับแดชบอร์ด (จำนวนคน/รายการที่ยังค้าง)
 * @return array outline, plan_none, no_subject, research, pending_review, eval_left
 */
function follow_up_summary(int $ayId, int $deptId): array
{
    $sum = ['outline' => 0, 'plan_none' => 0, 'no_subject' => 0, 'research' => 0, 'pending_review' => 0, 'eval_left' => 0];
    if ($ayId <= 0) {
        return $sum;
    }
    foreach (follow_up_teaching_rows($ayId, $deptId) as $t) {
        if ($t['subjects'] === 0) {
            $sum['no_subject']++;
            continue;
        }
        $sum['outline'] += $t['outlines'] < $t['subjects'] ? 1 : 0;
        $sum['plan_none'] += follow_up_plan_done($t) ? 0 : 1;
    }
    foreach (follow_up_research_rows($ayId, $deptId) as $t) {
        $sum['research'] += $t['sent'] ? 0 : 1;
    }
    foreach (follow_up_review_rows($ayId, $deptId) as $d) {
        $sum['pending_review'] += $d['pending'];
        $sum['eval_left'] += $d['eval_left'];
    }
    return $sum;
}
