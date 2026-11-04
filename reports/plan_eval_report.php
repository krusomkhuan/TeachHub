<?php
/**
 * ไฟล์: reports/plan_eval_report.php?id=xx
 * ดูรายงานสรุปผลการประเมินแผนฯ + เสนอ/พิจารณาตามลำดับ
 *   - เจ้าหน้าที่งานพัฒนาหลักสูตรฯ: แก้ไขร่าง และ "เสนอรายงาน" (ระบบบันทึกตัวเลข ณ เวลาที่เสนอ)
 *   - ผู้พิจารณาขั้นปัจจุบันเท่านั้น: "เห็นควรเสนอ" (ขั้น 1-2) / "ทราบ/อนุมัติ" (ขั้น 3) หรือ "ส่งกลับแก้ไข" พร้อมความเห็น
 */
require_once __DIR__ . '/../config/config.php';

$user = require_login();
if (!pe_report_ready()) {
    abort_page(503, 'ยังใช้งานหน้านี้ไม่ได้', 'ผู้ดูแลระบบต้องนำเข้า database/update_v1.22.sql ก่อน');
}
$rep = pe_report_fetch(get_int('id') ?: (int)post('id'));
if (!$rep || !pe_report_can_view($user, $rep)) {
    abort_page(404, 'ไม่พบรายงาน', 'รายงานนี้ไม่มีอยู่ หรือท่านไม่มีสิทธิ์เปิดดู');
}
$id = (int)$rep['id'];
$self = 'reports/plan_eval_report.php?id=' . $id;
$chain = report_approval_chain();
$errors = [];

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'submit') {
        if (!pe_report_can_edit($user, $rep)) {
            log_activity('access_denied', 'พยายามเสนอรายงานสรุปผลประเมินแผนฯ #' . $id);
            abort_page(403, 'ไม่สามารถเสนอรายงานนี้ได้');
        }
        $fresh = pe_report_build_data((int)$rep['academic_year_id']);
        $msg = pe_report_submit_error($rep, $fresh);
        if ($msg !== '') {
            $errors['submit'] = $msg;
        } else {
            if (pe_report_submit($user, $rep, $fresh)) {
                log_activity('pe_report_submit', 'เสนอรายงานสรุปผลประเมินแผนฯ #' . $id . ' ถึง' . $chain[1]['title']);
                flash('success', 'เสนอรายงานถึง ' . $chain[1]['title'] . ' แล้ว');
            } else {
                flash('warning', 'รายงานนี้ถูกเสนอไปแล้ว');
            }
            redirect($self);
        }
    }

    if ($action === 'decide') {
        if (!is_current_report_approver($user, $rep)) {
            log_activity('access_denied', 'พยายามพิจารณารายงานสรุปผลประเมินแผนฯ #' . $id . ' นอกขั้นของตนเอง');
            abort_page(403, 'ท่านไม่ใช่ผู้พิจารณาขั้นนี้', 'รายงานต้องผ่านผู้พิจารณาตามลำดับ และอาจถูกพิจารณาไปแล้ว');
        }
        $decision = post('decision');
        $comment = mb_substr(post('comment'), 0, 5000);
        $step = (int)post('step');
        if (!in_array($decision, ['forward', 'return'], true)) {
            $errors['decision'] = 'กรุณาเลือกผลการพิจารณา';
        } elseif ($decision === 'return' && $comment === '') {
            $errors['comment'] = 'กรุณาระบุสิ่งที่ต้องแก้ไข';
        }
        if (!$errors) {
            $act = $step === (int)$rep['current_step'] ? pe_report_decide($user, $id, $step, $decision, $comment) : null;
            if ($act === null) {
                flash('warning', 'รายงานนี้ถูกพิจารณาไปแล้ว');
                redirect($self);
            }
            log_activity('pe_report_' . $act, report_action_label($act, $step) . ' รายงานสรุปผลประเมินแผนฯ #' . $id . ' (' . $chain[$step]['title'] . ')');
            flash('success', ['return' => 'ส่งกลับให้เจ้าหน้าที่แก้ไขแล้ว', 'approve' => 'ลงนามทราบ/อนุมัติรายงานแล้ว',
                'forward' => 'เสนอต่อ ' . ($chain[$step + 1]['title'] ?? '') . ' แล้ว'][$act]);
            redirect($self);
        }
    }
}

$data = pe_report_data($rep);
$opinions = $rep['status'] === 'draft' ? [] : pe_report_latest_opinions($id);
$history = pe_report_actions($id);
$isApprover = is_current_report_approver($user, $rep);
$canEdit = pe_report_can_edit($user, $rep);

$pageTitle = 'รายงานสรุปผลการประเมินแผนการจัดการเรียนรู้';
$pageIcon = 'bi-file-earmark-text';
$activeMenu = 'reports/plan_eval_reports.php';
$breadcrumbs = ['รายงานสรุปผลประเมินแผนฯ' => $activeMenu, $rep['title'] => null];
$pageActions = ($canEdit ? '<a href="' . e(url('reports/plan_eval_reports.php?edit=' . $id)) . '" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>แก้ไข</a>' : '')
    . '<a href="' . e(url('reports/plan_eval_report_print.php?id=' . $id)) . '" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>พิมพ์บันทึกข้อความ / PDF</a>';
require APP_ROOT . '/includes/header.php';
?>
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start gap-2">
            <div>
                <h5 class="fw-bold text-primary-th mb-1"><?= e($rep['title']) ?></h5>
                <div class="small text-muted">ภาคเรียน <?= e($rep['ay_semester'] . '/' . $rep['ay_year']) ?> · ผู้จัดทำ <?= e($rep['author_name']) ?></div>
            </div>
            <div class="ms-auto"><?= report_status_badge($rep) ?></div>
        </div>
        <!-- ลำดับการเสนอ -->
        <div class="row g-2 mt-3 text-center small">
            <?php
            $steps = [0 => ['title' => CURRICULUM_STAFF_POSITION, 'name' => $rep['author_name']]] + $chain;
            foreach ($steps as $i => $s):
                if ($i === 0) {
                    $state = in_array($rep['status'], ['draft', 'returned'], true) ? 'current' : 'done';
                } elseif ($rep['status'] === 'approved' || (isset($opinions[$i]) && $opinions[$i]['action'] !== 'return')) {
                    $state = 'done';
                } elseif ($rep['status'] === 'pending' && (int)$rep['current_step'] === $i) {
                    $state = 'current';
                } elseif (isset($opinions[$i])) {
                    $state = 'returned';
                } else {
                    $state = 'waiting';
                }
                $cls = ['done' => 'border-success bg-success bg-opacity-10', 'current' => 'border-warning bg-warning bg-opacity-10',
                    'returned' => 'border-danger bg-danger bg-opacity-10', 'waiting' => 'border-light-subtle'][$state];
                $icon = ['done' => 'bi-check-circle-fill text-success', 'current' => 'bi-hourglass-split text-warning',
                    'returned' => 'bi-arrow-return-left text-danger', 'waiting' => 'bi-circle text-muted'][$state];
                ?>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 p-2 h-100 <?= $cls ?>">
                        <i class="bi <?= $icon ?> fs-5"></i>
                        <div class="fw-semibold"><?= $i === 0 ? 'ผู้จัดทำ' : 'ขั้นที่ ' . $i ?></div>
                        <div><?= e($s['title']) ?></div>
                        <div class="text-muted"><?= e($s['name'] ?: '— ยังไม่กำหนด —') ?></div>
                        <?php if ($i > 0 && isset($opinions[$i])): ?><div class="text-muted"><?= e(thai_datetime($opinions[$i]['created_at'])) ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($rep['status'] === 'returned' && ($ret = end($history)) && $ret['action'] === 'return'): ?>
    <div class="alert alert-danger">
        <div class="fw-bold"><i class="bi bi-arrow-return-left me-1"></i>ส่งกลับแก้ไขโดย <?= e($ret['position']) ?> (<?= e((string)$ret['user_name']) ?>) เมื่อ <?= e(thai_datetime($ret['created_at'])) ?></div>
        <?= nl2br(e((string)$ret['comment'])) ?>
    </div>
<?php endif; ?>

<?php if ($canEdit): ?>
    <div class="card mb-4 border-start border-4 border-warning">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="fw-semibold"><i class="bi bi-send me-1"></i>เสนอรายงาน</div>
                <div class="small text-muted">ตรวจสอบข้อมูลด้านล่าง แล้วกดเสนอถึง <?= e($chain[1]['title']) ?><?= $chain[1]['name'] ? ' (' . e($chain[1]['name']) . ')' : '' ?>
                    — เมื่อเสนอแล้วจะแก้ไขไม่ได้ จนกว่าจะถูกส่งกลับ</div>
                <?php if (isset($errors['submit'])): ?><div class="text-danger small mt-1"><?= e($errors['submit']) ?></div><?php endif; ?>
            </div>
            <form method="post" data-confirm="เสนอรายงานถึง <?= e($chain[1]['title']) ?>?">
                <?= csrf_field() ?><input type="hidden" name="action" value="submit"><input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-accent px-4" <?= report_chain_ready() ? '' : 'disabled' ?>><i class="bi bi-send me-1"></i>เสนอรายงาน</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($isApprover): $step = (int)$rep['current_step']; ?>
    <div class="card mb-4 border-start border-4 border-warning">
        <div class="card-header"><i class="bi bi-pen me-2"></i>พิจารณาในฐานะ <?= e($chain[$step]['title']) ?></div>
        <div class="card-body">
            <form method="post" novalidate data-confirm="ยืนยันผลการพิจารณา?">
                <?= csrf_field() ?><input type="hidden" name="action" value="decide"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="step" value="<?= $step ?>">
                <div class="btn-group mb-3" role="group" aria-label="ผลการพิจารณา">
                    <input type="radio" class="btn-check" name="decision" id="dForward" value="forward" <?= post('decision') === 'forward' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-success" for="dForward"><i class="bi bi-check2-circle me-1"></i>
                        <?= $step >= 3 ? 'ทราบ/อนุมัติ' : 'เห็นควรเสนอ ' . e($chain[$step + 1]['title']) ?></label>
                    <input type="radio" class="btn-check" name="decision" id="dReturn" value="return" <?= post('decision') === 'return' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-danger" for="dReturn"><i class="bi bi-arrow-return-left me-1"></i>ส่งกลับแก้ไข</label>
                </div>
                <?php if (isset($errors['decision'])): ?><div class="text-danger small mb-2"><?= e($errors['decision']) ?></div><?php endif; ?>
                <label class="form-label" for="comment">ความเห็น<?= $step >= 3 ? ' / ข้อสั่งการ' : '' ?></label>
                <textarea class="form-control<?= isset($errors['comment']) ? ' is-invalid' : '' ?>" name="comment" id="comment" rows="3" maxlength="5000"><?= old('comment') ?></textarea>
                <?php if (isset($errors['comment'])): ?><div class="invalid-feedback"><?= e($errors['comment']) ?></div><?php endif; ?>
                <button class="btn btn-accent mt-3 px-4"><i class="bi bi-save me-1"></i>บันทึกการพิจารณา</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($opinions): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-chat-square-text me-2"></i>ความเห็นผู้พิจารณา</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($opinions as $i => $o): ?>
                <li class="list-group-item">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="fw-semibold"><?= e($o['position']) ?></span>
                        <span class="badge <?= $o['action'] === 'return' ? 'bg-danger' : 'bg-success' ?>"><?= e(report_action_label($o['action'], $i)) ?></span>
                        <span class="small text-muted ms-auto"><?= e((string)$o['user_name']) ?> · <?= e(thai_datetime($o['created_at'])) ?></span>
                    </div>
                    <div class="mt-1"><?= $o['comment'] ? nl2br(e($o['comment'])) : '<span class="text-muted">-</span>' ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-table me-2"></i>ข้อมูลสรุปผลการประเมิน
        <span class="small text-muted fw-normal ms-2"><?= in_array($rep['status'], ['draft', 'returned'], true) ? 'ข้อมูลล่าสุด (จะบันทึกค่าเมื่อกดเสนอ)' : 'ข้อมูล ณ ' . e(thai_datetime($data['generated_at'] ?? '')) ?></span></div>
    <div class="card-body"><?php require APP_ROOT . '/includes/partials/plan_eval_report_body.php'; ?></div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-journal-text me-2"></i>บทสรุปของผู้จัดทำ</div>
    <div class="card-body">
        <?php foreach (['summary_overall' => 'สรุปผลการประเมินโดยภาพรวม', 'strengths' => 'จุดเด่น', 'problems' => 'ปัญหา/อุปสรรค', 'suggestions' => 'ข้อเสนอแนะ/แนวทางพัฒนา'] as $k => $label): ?>
            <div class="fw-semibold"><?= e($label) ?></div>
            <p><?= $rep[$k] ? nl2br(e($rep[$k])) : '<span class="text-muted">-</span>' ?></p>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($history): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-clock-history me-2"></i>ประวัติการเสนอ/พิจารณา</div>
        <ul class="list-group list-group-flush small">
            <?php foreach ($history as $h): ?>
                <li class="list-group-item d-flex flex-wrap gap-2">
                    <span class="text-muted"><?= e(thai_datetime($h['created_at'])) ?></span>
                    <span class="fw-semibold"><?= e(report_action_label($h['action'], (int)$h['step'])) ?></span>
                    <span><?= e($h['position']) ?> (<?= e((string)$h['user_name']) ?>)</span>
                    <?php if ($h['comment']): ?><span class="text-muted">— <?= e(str_limit($h['comment'], 120)) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($canEdit && $rep['submitted_at'] === null): ?>
    <form method="post" action="<?= e(url('reports/plan_eval_reports.php')) ?>" class="text-end mb-4" data-confirm="ลบร่างรายงานนี้?" data-confirm-icon="warning">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>ลบร่าง</button>
    </form>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
