<?php
/**
 * ไฟล์: includes/plan_eval_report.php
 * รายงานสรุปผลการประเมินแผนการจัดการเรียนรู้ (เจ้าหน้าที่งานพัฒนาหลักสูตรฯ เป็นผู้จัดทำ) — ระดับวิทยาลัย รายภาคเรียน
 * ลำดับการเสนอใช้ชุดเดียวกับรายงานการนิเทศ (includes/supervision_report.php → report_approval_chain):
 *   ขั้น 1 หัวหน้างานพัฒนาหลักสูตรฯ → ขั้น 2 รองผู้อำนวยการฝ่ายวิชาการ → ขั้น 3 ผู้อำนวยการ
 *  - แต่ละขั้นทำได้เฉพาะผู้พิจารณาของขั้นปัจจุบัน จึงข้ามขั้นไม่ได้ (ตรวจฝั่งเซิร์ฟเวอร์และล็อกแถวทุกครั้ง)
 *  - ส่งกลับแก้ไข → เจ้าหน้าที่แก้แล้วเสนอใหม่ เริ่มที่ขั้น 1 อีกครั้ง
 *  - เสนอแล้วเก็บตัวเลข ณ เวลาที่เสนอ (snapshot) ผลประเมินที่แก้ภายหลังไม่กระทบรายงานที่เสนอแล้ว
 */

/** ฐานข้อมูลมีตารางรายงานสรุปแล้วหรือยัง (นำเข้า update_v1.22.sql) */
function pe_report_ready(): bool
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool)db_value("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = 'plan_eval_summary_reports' LIMIT 1");
    }
    return $ready;
}

/** เปิดหน้ารายการรายงานสรุปได้หรือไม่ */
function pe_report_can_list(array $user): bool
{
    return is_curriculum_staff($user) || admin_can($user, 'plan_eval')
        || $user['role'] === 'executive' || report_user_steps((int)$user['id']);
}

/** เปิดดูรายงานฉบับนี้ได้หรือไม่ — ร่างเห็นเฉพาะเจ้าหน้าที่และ admin ที่มีสิทธิ์ */
function pe_report_can_view(array $user, array $r): bool
{
    if (is_curriculum_staff($user) || admin_can($user, 'plan_eval')) {
        return true;
    }
    return $r['submitted_at'] !== null && ($user['role'] === 'executive' || report_user_steps((int)$user['id']));
}

/** แก้ไข/เสนอรายงานได้หรือไม่ — เจ้าหน้าที่งานพัฒนาหลักสูตรฯ เฉพาะร่างหรือฉบับที่ถูกส่งกลับ */
function pe_report_can_edit(array $user, array $r): bool
{
    return is_curriculum_staff($user) && in_array($r['status'], ['draft', 'returned'], true);
}

/** ดึงรายงาน พร้อมภาคเรียนและผู้จัดทำ */
function pe_report_fetch(int $id): ?array
{
    return db_one(
        "SELECT r.*, ay.year AS ay_year, ay.semester AS ay_semester,
                CONCAT(a.prefix, a.first_name, ' ', a.last_name) AS author_name, a.position AS author_position
         FROM plan_eval_summary_reports r
         JOIN academic_years ay ON ay.id = r.academic_year_id
         JOIN users a ON a.id = r.author_id
         WHERE r.id = ?",
        'i',
        [$id]
    );
}

/** ประวัติการเสนอ/พิจารณา */
function pe_report_actions(int $reportId): array
{
    return db_all(
        "SELECT x.*, CONCAT(u.prefix, u.first_name, ' ', u.last_name) AS user_name
         FROM plan_eval_summary_report_actions x LEFT JOIN users u ON u.id = x.user_id
         WHERE x.report_id = ? ORDER BY x.id",
        'i',
        [$reportId]
    );
}

/** ความเห็นล่าสุดของแต่ละขั้น (หลังการเสนอครั้งล่าสุด) [ขั้น => แถว] */
function pe_report_latest_opinions(int $reportId): array
{
    $out = [];
    foreach (pe_report_actions($reportId) as $a) {
        if ($a['action'] === 'submit') {
            $out = [];
        } elseif ((int)$a['step'] >= 1) {
            $out[(int)$a['step']] = $a;
        }
    }
    return $out;
}

/** ตัวเลขของรายงาน ณ ปัจจุบัน (ระดับวิทยาลัย นับเฉพาะผลที่ส่งให้ครูแล้ว) */
function pe_report_build_data(int $ayId): array
{
    $rep = plan_eval_report($ayId, 0);
    unset($rep['plans']); // รายแผนดูได้ที่ reports/plan_eval_summary.php รายงานสรุปเก็บเฉพาะตัวเลขรวม
    $rep['generated_at'] = date('Y-m-d H:i:s');
    return $rep;
}

/** ข้อมูลของรายงาน: เสนอแล้วใช้ snapshot, ร่าง/ส่งกลับใช้ข้อมูลสด */
function pe_report_data(array $r): array
{
    if ($r['snapshot'] && !in_array($r['status'], ['draft', 'returned'], true)) {
        $data = json_decode($r['snapshot'], true);
        if (is_array($data)) {
            return $data;
        }
    }
    return pe_report_build_data((int)$r['academic_year_id']);
}

/**
 * ตรวจก่อนเสนอรายงาน
 * @return string ข้อความข้อผิดพลาด ('' = เสนอได้)
 */
function pe_report_submit_error(array $r, array $data): string
{
    if (!report_chain_ready()) {
        return 'ผู้ดูแลระบบยังกำหนดผู้พิจารณาไม่ครบ 3 ขั้น จึงยังเสนอไม่ได้';
    }
    if ((int)($data['totals']['evaluated'] ?? 0) < 1) {
        return 'ภาคเรียนนี้ยังไม่มีผลการประเมินแผนที่ส่งแล้ว จึงยังไม่มีข้อมูลให้สรุป';
    }
    if (trim((string)$r['summary_overall']) === '' || trim((string)$r['suggestions']) === '') {
        return 'กรุณากรอก "สรุปผลการประเมินโดยภาพรวม" และ "ข้อเสนอแนะ/แนวทางพัฒนา" ก่อนเสนอ (กดแก้ไข)';
    }
    return '';
}

/** เสนอรายงานเข้าสู่ขั้นที่ 1 — ผู้เรียกต้องตรวจ pe_report_can_edit และ pe_report_submit_error ก่อน */
function pe_report_submit(array $user, array $r, array $data): bool
{
    db()->begin_transaction();
    try {
        $now = db_one('SELECT status FROM plan_eval_summary_reports WHERE id = ? FOR UPDATE', 'i', [(int)$r['id']]);
        if (!$now || !in_array($now['status'], ['draft', 'returned'], true)) {
            db()->rollback();
            return false;
        }
        db_exec(
            "UPDATE plan_eval_summary_reports SET status = 'pending', current_step = 1, snapshot = ?, submitted_at = NOW(), approved_at = NULL WHERE id = ?",
            'si',
            [json_encode($data, JSON_UNESCAPED_UNICODE), (int)$r['id']]
        );
        db_insert(
            "INSERT INTO plan_eval_summary_report_actions (report_id, step, user_id, position, action) VALUES (?, 0, ?, ?, 'submit')",
            'iis',
            [(int)$r['id'], (int)$user['id'], CURRICULUM_STAFF_POSITION]
        );
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollback();
        throw $ex;
    }
    return true;
}

/**
 * บันทึกผลการพิจารณาของขั้นที่ระบุ — ผู้เรียกต้องตรวจ is_current_report_approver ก่อน
 * ล็อกแถวแล้วตรวจว่ายังอยู่ขั้นเดิม (กันกดซ้ำ/พิจารณาพร้อมกัน/ผู้พิจารณาคนเดียวหลายขั้นกดซ้ำแล้วข้ามขั้น)
 * @return string|null forward | approve | return หรือ null เมื่อรายงานไม่ได้อยู่ขั้นนี้แล้ว
 */
function pe_report_decide(array $user, int $reportId, int $step, string $decision, string $comment): ?string
{
    $title = report_approval_chain()[$step]['title'] ?? '';
    db()->begin_transaction();
    try {
        $now = db_one('SELECT status, current_step FROM plan_eval_summary_reports WHERE id = ? FOR UPDATE', 'i', [$reportId]);
        if (!$now || $now['status'] !== 'pending' || (int)$now['current_step'] !== $step) {
            db()->rollback();
            return null;
        }
        if ($decision === 'return') {
            db_exec("UPDATE plan_eval_summary_reports SET status = 'returned', current_step = 0 WHERE id = ?", 'i', [$reportId]);
            $act = 'return';
        } elseif ($step >= 3) {
            db_exec("UPDATE plan_eval_summary_reports SET status = 'approved', current_step = 0, approved_at = NOW() WHERE id = ?", 'i', [$reportId]);
            $act = 'approve';
        } else {
            db_exec('UPDATE plan_eval_summary_reports SET current_step = ? WHERE id = ?', 'ii', [$step + 1, $reportId]);
            $act = 'forward';
        }
        db_insert(
            'INSERT INTO plan_eval_summary_report_actions (report_id, step, user_id, position, action, comment) VALUES (?, ?, ?, ?, ?, ?)',
            'iiisss',
            [$reportId, $step, (int)$user['id'], $title, $act, $comment !== '' ? $comment : null]
        );
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollback();
        throw $ex;
    }
    return $act;
}

/** จำนวนรายงานที่รอผู้ใช้คนนี้พิจารณา */
function pe_report_pending_count(int $userId): int
{
    $steps = report_user_steps($userId);
    if (!$steps || !pe_report_ready()) {
        return 0;
    }
    $in = implode(',', array_fill(0, count($steps), '?'));
    return (int)db_value(
        "SELECT COUNT(*) FROM plan_eval_summary_reports WHERE status = 'pending' AND current_step IN ($in)",
        str_repeat('i', count($steps)),
        $steps
    );
}

/** จำนวนรายงานที่ถูกส่งกลับให้เจ้าหน้าที่แก้ไข */
function pe_report_returned_count(): int
{
    return pe_report_ready() ? (int)db_value("SELECT COUNT(*) FROM plan_eval_summary_reports WHERE status = 'returned'") : 0;
}
