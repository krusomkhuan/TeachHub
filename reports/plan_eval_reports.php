<?php
/**
 * ไฟล์: reports/plan_eval_reports.php
 * รายงานสรุปผลการประเมินแผนการจัดการเรียนรู้ (ระดับวิทยาลัย รายภาคเรียน)
 *   ?tab=mine  รายงานที่รอท่านพิจารณา (ผู้พิจารณา)
 *   ?tab=all   รายงานทั้งหมด (เจ้าหน้าที่/admin เห็นร่างด้วย, ผู้บริหารเห็นเฉพาะที่เสนอแล้ว)
 *   ?new=1     จัดทำรายงานใหม่ (เจ้าหน้าที่งานพัฒนาหลักสูตรฯ)
 *   ?edit=id   แก้ไขร่าง/รายงานที่ถูกส่งกลับ
 * การดู เสนอ และพิจารณา อยู่ที่ reports/plan_eval_report.php?id=
 */
require_once __DIR__ . '/../config/config.php';

$user = require_login();
if (!pe_report_can_list($user)) {
    log_activity('access_denied', 'พยายามเข้าหน้า ' . ($_SERVER['REQUEST_URI'] ?? ''));
    abort_page(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้', 'หน้านี้สำหรับ ' . CURRICULUM_STAFF_POSITION . ' ผู้พิจารณา และผู้บริหาร');
}
if (!pe_report_ready()) {
    abort_page(503, 'ยังใช้งานหน้านี้ไม่ได้', 'ผู้ดูแลระบบต้องนำเข้า database/update_v1.22.sql ก่อน');
}
$uid = (int)$user['id'];
$self = 'reports/plan_eval_reports.php';
$isStaff = is_curriculum_staff($user);
$mySteps = report_user_steps($uid);
$chain = report_approval_chain();
$errors = [];

// =====================================================================
// POST: บันทึก / ลบร่าง (เฉพาะเจ้าหน้าที่ ตรวจฝั่งเซิร์ฟเวอร์ทุกครั้ง)
// =====================================================================
if (is_post()) {
    csrf_verify();
    if (!$isStaff) {
        abort_page(403, 'ไม่มีสิทธิ์จัดทำรายงาน', 'จัดทำได้เฉพาะ ' . CURRICULUM_STAFF_POSITION);
    }
    $id = (int)post('id');
    $old = $id ? pe_report_fetch($id) : null;
    if ($id && (!$old || !pe_report_can_edit($user, $old))) {
        abort_page(403, 'ไม่สามารถแก้ไขรายงานนี้ได้', 'แก้ไขได้เฉพาะร่างหรือรายงานที่ถูกส่งกลับแก้ไข');
    }
    if (post('action') === 'delete' && $old) {
        if ($old['submitted_at'] !== null) {
            flash('error', 'รายงานที่เคยเสนอแล้วลบไม่ได้ (มีประวัติการพิจารณา)');
        } else {
            db_exec('DELETE FROM plan_eval_summary_reports WHERE id = ?', 'i', [$id]);
            log_activity('pe_report_delete', 'ลบร่างรายงานสรุปผลประเมินแผนฯ #' . $id);
            flash('success', 'ลบร่างรายงานแล้ว');
        }
        redirect($self);
    }
    if (post('action') === 'save') {
        $d = [
            'academic_year_id' => (int)post('academic_year_id'),
            'title'            => mb_substr(post('title'), 0, 255),
            'summary_overall'  => mb_substr(post('summary_overall'), 0, 10000),
            'strengths'        => mb_substr(post('strengths'), 0, 10000),
            'problems'         => mb_substr(post('problems'), 0, 10000),
            'suggestions'      => mb_substr(post('suggestions'), 0, 10000),
        ];
        if (!db_value('SELECT id FROM academic_years WHERE id = ?', 'i', [$d['academic_year_id']])) {
            $errors['academic_year_id'] = 'กรุณาเลือกภาคเรียน';
        }
        if ($d['title'] === '') {
            $errors['title'] = 'กรุณาระบุเรื่อง';
        }
        if (!$errors) {
            $params = [$d['academic_year_id'], $d['title'], $d['summary_overall'], $d['strengths'], $d['problems'], $d['suggestions']];
            if ($old) {
                db_exec(
                    'UPDATE plan_eval_summary_reports SET academic_year_id = ?, title = ?, summary_overall = ?, strengths = ?, problems = ?, suggestions = ? WHERE id = ?',
                    'isssssi',
                    array_merge($params, [$id])
                );
            } else {
                $id = db_insert(
                    'INSERT INTO plan_eval_summary_reports (academic_year_id, title, summary_overall, strengths, problems, suggestions, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'isssssi',
                    array_merge($params, [$uid])
                );
            }
            log_activity('pe_report_save', 'บันทึกรายงานสรุปผลประเมินแผนฯ #' . $id);
            flash('success', 'บันทึกรายงานแล้ว ตรวจสอบความถูกต้องแล้วกด "เสนอรายงาน"');
            redirect('reports/plan_eval_report.php?id=' . $id);
        }
    }
}

// =====================================================================
// ฟอร์มจัดทำ/แก้ไข
// =====================================================================
$editId = get_int('edit') ?: (int)post('id');
$editing = $editId ? pe_report_fetch($editId) : null;
if ($isStaff && (get('new') === '1' || $editing || $errors)) {
    if ($editId && (!$editing || !pe_report_can_edit($user, $editing))) {
        abort_page(403, 'ไม่สามารถแก้ไขรายงานนี้ได้', 'แก้ไขได้เฉพาะร่างหรือรายงานที่ถูกส่งกลับแก้ไข');
    }
    $years = db_all('SELECT * FROM academic_years ORDER BY year DESC, semester DESC');
    $curAy = (int)(current_academic_year()['id'] ?? 0);
    $fv = fn(string $k, string $default = '') => is_post() ? post($k) : (string)($editing[$k] ?? $default);
    $pageTitle = $editing ? 'แก้ไขรายงานสรุปผลการประเมินแผนฯ' : 'จัดทำรายงานสรุปผลการประเมินแผนฯ';
    $pageIcon = 'bi-file-earmark-text';
    $activeMenu = $self;
    $breadcrumbs = ['รายงานสรุปผลประเมินแผนฯ' => $self, $pageTitle => null];
    require APP_ROOT . '/includes/header.php';
    ?>
    <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$editId ?>">
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="academic_year_id">ภาคเรียน *</label>
                        <select class="form-select<?= isset($errors['academic_year_id']) ? ' is-invalid' : '' ?>" name="academic_year_id" id="academic_year_id">
                            <?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= (int)$fv('academic_year_id', (string)$curAy) === (int)$y['id'] ? 'selected' : '' ?>><?= e(academic_label($y)) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label" for="title">เรื่อง *</label>
                        <input class="form-control<?= isset($errors['title']) ? ' is-invalid' : '' ?>" name="title" id="title" maxlength="255" value="<?= e($fv('title', 'รายงานสรุปผลการประเมินแผนการจัดการเรียนรู้')) ?>">
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>
                    <?php foreach (['summary_overall' => 'สรุปผลการประเมินโดยภาพรวม *', 'strengths' => 'จุดเด่น', 'problems' => 'ปัญหา/อุปสรรค', 'suggestions' => 'ข้อเสนอแนะ/แนวทางพัฒนา *'] as $k => $label): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="<?= e($k) ?>"><?= e($label) ?></label>
                            <textarea class="form-control" name="<?= e($k) ?>" id="<?= e($k) ?>" rows="4" maxlength="10000"><?= e($fv($k)) ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-text mt-2">ตัวเลขสรุปรายแผนก รายด้าน และระดับคุณภาพ ระบบดึงจากผลการประเมินที่ส่งแล้วของภาคเรียนที่เลือก และบันทึกค่า ณ เวลาที่กดเสนอ</div>
            </div>
        </div>
        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= e(url($editing ? 'reports/plan_eval_report.php?id=' . (int)$editing['id'] : $self)) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
            <button class="btn btn-accent px-4"><i class="bi bi-save me-1"></i>บันทึก</button>
        </div>
    </form>
    <?php
    require APP_ROOT . '/includes/footer.php';
    exit;
}

// =====================================================================
// รายการ
// =====================================================================
$tab = get('tab') === 'mine' || (get('tab') !== 'all' && $mySteps) ? 'mine' : 'all';
if ($tab === 'mine' && !$mySteps) {
    $tab = 'all';
}
$base = "SELECT r.*, ay.year, ay.semester, CONCAT(a.prefix, a.first_name, ' ', a.last_name) AS author_name
         FROM plan_eval_summary_reports r JOIN academic_years ay ON ay.id = r.academic_year_id JOIN users a ON a.id = r.author_id ";
if ($tab === 'mine') {
    $rows = db_all(
        $base . "WHERE r.status = 'pending' AND r.current_step IN (" . implode(',', array_fill(0, count($mySteps), '?')) . ') ORDER BY r.submitted_at',
        str_repeat('i', count($mySteps)),
        $mySteps
    );
} else {
    // ร่างที่ยังไม่เคยเสนอ เห็นเฉพาะเจ้าหน้าที่และ admin ที่มีสิทธิ์
    $seeDrafts = $isStaff || admin_can($user, 'plan_eval');
    $rows = db_all($base . ($seeDrafts ? '' : 'WHERE r.submitted_at IS NOT NULL ') . 'ORDER BY r.id DESC');
}
$pendingMine = pe_report_pending_count($uid);

$pageTitle = 'รายงานสรุปผลการประเมินแผนการจัดการเรียนรู้';
$pageIcon = 'bi-file-earmark-check';
$activeMenu = $self;
$breadcrumbs = ['รายงานสรุปผลประเมินแผนฯ' => null];
$pageActions = $isStaff ? '<a href="' . e(url($self . '?new=1')) . '" class="btn btn-accent"><i class="bi bi-plus-lg me-1"></i>จัดทำรายงาน</a>' : '';
require APP_ROOT . '/includes/header.php';
?>
<div class="alert alert-light border small"><i class="bi bi-diagram-2 me-1 text-accent"></i>ลำดับการเสนอ:
    <?= e(implode(' → ', array_map(fn($s) => $s['title'] . ($s['name'] ? ' (' . $s['name'] . ')' : ' (ยังไม่กำหนด)'), $chain))) ?></div>
<?php if ($mySteps): ?>
    <ul class="nav nav-pills mb-3 gap-1">
        <li class="nav-item"><a class="nav-link <?= $tab === 'mine' ? 'active bg-primary-th' : 'bg-white border' ?>" href="?tab=mine">
            <i class="bi bi-inbox me-1"></i>รอท่านพิจารณา <?php if ($pendingMine): ?><span class="badge bg-danger ms-1"><?= $pendingMine ?></span><?php endif; ?></a></li>
        <li class="nav-item"><a class="nav-link <?= $tab === 'all' ? 'active bg-primary-th' : 'bg-white border' ?>" href="?tab=all">
            <i class="bi bi-collection me-1"></i>รายงานทั้งหมด</a></li>
    </ul>
<?php endif; ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable" data-order="[]">
                <thead><tr><th>เรื่อง</th><th>ภาคเรียน</th><th>ผู้จัดทำ</th><th>สถานะ</th><th>เสนอเมื่อ</th><th class="no-sort"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): $mine = is_current_report_approver($user, $r); ?>
                    <tr class="<?= $mine ? 'table-warning' : '' ?>">
                        <td><?= e($r['title']) ?></td>
                        <td><?= e($r['semester'] . '/' . $r['year']) ?></td>
                        <td class="small"><?= e($r['author_name']) ?></td>
                        <td><?= report_status_badge($r) ?></td>
                        <td class="small" data-order="<?= e((string)$r['submitted_at']) ?>"><?= $r['submitted_at'] ? e(thai_datetime($r['submitted_at'])) : '-' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('reports/plan_eval_report.php?id=' . (int)$r['id'])) ?>" class="btn btn-sm <?= $mine ? 'btn-accent' : 'btn-outline-primary' ?>">
                                <i class="bi <?= $mine ? 'bi-pen' : 'bi-eye' ?>"></i> <?= $mine ? 'พิจารณา' : 'ดู' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
