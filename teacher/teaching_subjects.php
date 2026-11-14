<?php
/**
 * ไฟล์: teacher/teaching_subjects.php
 * รายวิชาที่สอนในแต่ละภาคเรียน (ครู และหัวหน้าแผนกที่สอน)
 *   - ครูบันทึกรายวิชาที่สอนก่อน แล้วจึงส่งโครงการสอน/แผนการจัดการเรียนรู้ของวิชาเหล่านี้
 *   - เกณฑ์: ส่งโครงการสอนครบทุกรายวิชา + ส่งแผนการจัดการเรียนรู้อย่างน้อย 1 วิชา
 *   - ครูกรอกข้อมูลทั่วไปของรายวิชา (ประเภทวิชา สาขาวิชา ท-ป-น จำนวนหน่วย จำนวนแผน) ซึ่งเป็นค่าเริ่มต้นของแบบประเมินแผนฯ
 *   ?ay=xx เลือกภาคเรียน (ค่าเริ่มต้น = ภาคเรียนปัจจุบัน)
 *   ?edit=xx แก้ไขข้อมูลทั่วไปของรายวิชา (รหัส ชื่อ และระดับชั้นแก้ไม่ได้)
 */
require_once __DIR__ . '/../config/config.php';

$user = require_role('teacher', 'supervisor');
$uid = (int)$user['id'];
$errors = [];
$years = db_all('SELECT * FROM academic_years ORDER BY year DESC, semester DESC');
$ayIds = array_map('intval', array_column($years, 'id'));
$ay = (int)(post('academic_year_id') ?: get_int('ay', (int)(current_academic_year()['id'] ?? 0)));
if (!in_array($ay, $ayIds, true)) {
    $ay = $ayIds[0] ?? 0;
}
$self = 'teacher/teaching_subjects.php';
$infoReady = teaching_info_ready();

// รายวิชาที่กำลังแก้ไขข้อมูลทั่วไป (เฉพาะของครูคนนี้)
$editRow = null;
$editId = get_int('edit') ?: (post('action') === 'update_info' ? (int)post('id') : 0);
if ($editId > 0 && $infoReady) {
    $editRow = db_one('SELECT * FROM teaching_subjects WHERE id = ? AND teacher_id = ?', 'ii', [$editId, $uid]);
    if (!$editRow) {
        abort_page(404, 'ไม่พบรายวิชา');
    }
    $ay = (int)$editRow['academic_year_id'];
}

if (is_post()) {
    csrf_verify();
    $action = post('action');

    if ($action === 'update_info' && $editRow) {
        $info = teaching_info_from_post($errors);
        if (!$errors) {
            db_exec(
                'UPDATE teaching_subjects SET subject_category = ?, major = ?, credit = ?, unit_count = ?, plan_count = ? WHERE id = ?',
                'sssiii',
                [$info['subject_category'], $info['major'], $info['credit'], $info['unit_count'], $info['plan_count'], (int)$editRow['id']]
            );
            log_activity('teaching_subject_update', 'แก้ไขข้อมูลรายวิชาที่สอน ' . $editRow['subject_code']);
            flash('success', 'บันทึกข้อมูลรายวิชา ' . $editRow['subject_code'] . ' เรียบร้อยแล้ว');
            redirect($self . '?ay=' . $ay);
        }
    }

    if ($action === 'add') {
        $code = strtoupper(post('subject_code'));
        $name = mb_substr(post('subject_name'), 0, 200);
        $level = post('level');
        if (!preg_match('/^[A-Z0-9-]{2,20}$/', $code)) {
            $errors['subject_code'] = 'รหัสวิชาไม่ถูกต้อง (ตัวเลข/ตัวอักษรอังกฤษ/ขีด 2-20 ตัว)';
        }
        if ($name === '') {
            $errors['subject_name'] = 'กรุณากรอกชื่อวิชา';
        }
        if (!in_array($level, ['ปวช.', 'ปวส.'], true)) {
            $errors['level'] = 'กรุณาเลือกระดับชั้น';
        }
        $info = $infoReady ? teaching_info_from_post($errors) : null;
        if (!$errors && db_value(
            'SELECT id FROM teaching_subjects WHERE teacher_id = ? AND academic_year_id = ? AND subject_code = ?',
            'iis',
            [$uid, $ay, $code]
        )) {
            $errors['subject_code'] = 'มีรายวิชานี้ในภาคเรียนนี้แล้ว';
        }
        if (!$errors) {
            if ($info !== null) {
                db_insert(
                    'INSERT INTO teaching_subjects (teacher_id, academic_year_id, subject_code, subject_name, level, subject_category, major, credit, unit_count, plan_count)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    'iissssssii',
                    [$uid, $ay, $code, $name, $level, $info['subject_category'], $info['major'], $info['credit'], $info['unit_count'], $info['plan_count']]
                );
            } else {
                db_insert(
                    'INSERT INTO teaching_subjects (teacher_id, academic_year_id, subject_code, subject_name, level) VALUES (?, ?, ?, ?, ?)',
                    'iisss',
                    [$uid, $ay, $code, $name, $level]
                );
            }
            log_activity('teaching_subject_add', 'เพิ่มรายวิชาที่สอน ' . $code . ' ' . $name);
            flash('success', 'เพิ่มรายวิชา ' . $code . ' เรียบร้อยแล้ว');
            redirect($self . '?ay=' . $ay);
        }
    }

    if ($action === 'delete') {
        $row = db_one('SELECT * FROM teaching_subjects WHERE id = ? AND teacher_id = ?', 'ii', [(int)post('id'), $uid]);
        if (!$row) {
            abort_page(404, 'ไม่พบรายวิชา');
        }
        $used = db_value(
            'SELECT id FROM lesson_plans WHERE teacher_id = ? AND academic_year_id = ? AND subject_code = ? LIMIT 1',
            'iis',
            [$uid, (int)$row['academic_year_id'], $row['subject_code']]
        );
        if ($used) {
            flash('error', 'ลบไม่ได้ เพราะส่งโครงการสอน/แผนการจัดการเรียนรู้ของวิชานี้แล้ว');
        } else {
            db_exec('DELETE FROM teaching_subjects WHERE id = ?', 'i', [(int)$row['id']]);
            log_activity('teaching_subject_delete', 'ลบรายวิชาที่สอน ' . $row['subject_code']);
            flash('success', 'ลบรายวิชา ' . $row['subject_code'] . ' แล้ว');
        }
        redirect($self . '?ay=' . (int)$row['academic_year_id']);
    }
}

$rows = teaching_subjects_with_status($uid, $ay);
$comp = teaching_compliance($uid, $ay);
$ayRow = null;
foreach ($years as $y) {
    if ((int)$y['id'] === $ay) {
        $ayRow = $y;
    }
}
$invalid = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';
$err = fn(string $k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
// ค่าในช่องข้อมูลทั่วไป: ค่าที่เพิ่งกรอก > ค่าเดิมของรายวิชาที่แก้ไข (ตัวเลข 0 = ยังไม่กรอก แสดงเป็นช่องว่าง)
$infoVal = function (string $k) use ($editRow): string {
    $v = is_post() ? post($k) : (string)($editRow[$k] ?? '');
    return $v === '0' ? '' : $v;
};
$subjectCats = $majors = [];
if ($infoReady) {
    $subjectCats = array_column(db_all("SELECT DISTINCT subject_category FROM teaching_subjects WHERE subject_category <> '' ORDER BY subject_category"), 'subject_category');
    $majors = array_column(db_all("SELECT DISTINCT major FROM teaching_subjects WHERE major <> '' ORDER BY major"), 'major');
}
$docBadge = function (?string $status, string $doc = ''): string {
    if ($status !== null) {
        return status_badge($status, false);
    }
    $badge = '<span class="badge bg-light text-muted border">ยังไม่ส่ง</span>';
    return $doc === '' ? $badge : $badge . ' <a href="' . e(url('teacher/lesson_plans.php?doc=' . $doc . '#submitForm')) . '" class="small">ส่ง</a>';
};

$pageTitle = 'รายวิชาที่สอน';
$pageIcon = 'bi-book';
$breadcrumbs = ['รายวิชาที่สอน' => null];
$activeMenu = $self;
require APP_ROOT . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-sm-8">
                        <label class="form-label small mb-1" for="ay">ภาคเรียน</label>
                        <select name="ay" id="ay" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= (int)$y['id'] ?>" <?= $ay === (int)$y['id'] ? 'selected' : '' ?>><?= e(academic_label($y)) ?><?= (int)$y['is_current'] === 1 ? ' (ปัจจุบัน)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> แสดง</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul me-2"></i>รายวิชาที่สอน ภาคเรียน <?= e(academic_label($ayRow)) ?> (<?= count($rows) ?> วิชา)</div>
            <div class="card-body">
                <?php if (!$rows): ?>
                    <div class="empty-state"><i class="bi bi-book"></i>ยังไม่ได้บันทึกรายวิชาที่สอนในภาคเรียนนี้ — เพิ่มรายวิชาที่ฟอร์มด้านขวา</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>รหัส/ชื่อวิชา</th><th>ระดับ</th><th>โครงการสอน</th><th>แผนการจัดการเรียนรู้</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><div class="fw-semibold"><?= e($r['subject_code']) ?></div><small><?= e($r['subject_name']) ?></small>
                                        <?php if ($infoReady): ?>
                                            <?php if (teaching_info_complete($r)): ?>
                                                <div class="small text-muted"><?= e($r['subject_category'] . ' · ' . $r['major'] . ' · ท-ป-น ' . $r['credit']) ?> · <?= (int)$r['unit_count'] ?> หน่วย · <?= (int)$r['plan_count'] ?> แผน</div>
                                            <?php else: ?>
                                                <div class="small"><a href="<?= e(url($self . '?edit=' . (int)$r['id'])) ?>" class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>ยังกรอกข้อมูลรายวิชาไม่ครบ</a></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($r['level']) ?></td>
                                    <td><?= $r['outline_id'] ? '<a href="' . e(url('teacher/lesson_plans.php?id=' . $r['outline_id'])) . '">' . $docBadge($r['outline_status']) . '</a>' : $docBadge(null, 'outline') ?></td>
                                    <td><?= $r['plan_id'] ? '<a href="' . e(url('teacher/lesson_plans.php?id=' . $r['plan_id'])) . '">' . $docBadge($r['plan_status']) . '</a>' : $docBadge(null, 'plan') ?></td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($infoReady): ?>
                                            <a href="<?= e(url($self . '?edit=' . (int)$r['id'])) ?>" class="btn btn-sm btn-outline-primary" title="แก้ไขข้อมูลรายวิชา"><i class="bi bi-pencil"></i></a>
                                        <?php endif; ?>
                                        <?php if (!$r['outline_id'] && !$r['plan_id']): ?>
                                            <form method="post" class="d-inline" data-confirm="ลบรายวิชา <?= e($r['subject_code']) ?> ออกจากรายวิชาที่สอน?" data-confirm-icon="warning">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" title="ลบ"><i class="bi bi-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="<?= e(url('teacher/lesson_plans.php')) ?>" class="btn btn-accent"><i class="bi bi-upload me-1"></i> ไปส่งโครงการสอน / แผนการจัดการเรียนรู้</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4 border-top border-4 <?= $comp['ok'] ? 'border-success' : 'border-warning' ?>">
            <div class="card-header"><i class="bi bi-clipboard-check me-2"></i>เกณฑ์การส่งภาคเรียนนี้</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex align-items-center">
                    <span>โครงการสอนครบทุกรายวิชา<br><span class="text-muted">ส่งแล้ว <?= $comp['outlines'] ?>/<?= $comp['subjects'] ?> วิชา</span></span>
                    <span class="ms-auto"><?= compliance_badge($comp['outline_ok'], 'ครบ', 'ยังไม่ครบ') ?></span>
                </li>
                <li class="list-group-item d-flex align-items-center">
                    <span>แผนการจัดการเรียนรู้อย่างน้อย 1 วิชา<br><span class="text-muted">ส่งแล้ว <?= $comp['plans'] ?> วิชา (ผ่าน <?= $comp['plans_approved'] ?>)</span></span>
                    <span class="ms-auto"><?= compliance_badge($comp['plan_ok'], 'ครบ', 'ยังไม่ส่ง') ?></span>
                </li>
                <li class="list-group-item d-flex align-items-center fw-semibold">
                    ผลรวม <span class="ms-auto"><?= compliance_badge($comp['ok']) ?></span>
                </li>
            </ul>
            <div class="card-body small text-muted">
                รายวิชาที่หัวหน้าแผนกวิชาและนักเรียน นักศึกษาประเมิน คือรายวิชาที่แผนการจัดการเรียนรู้ “ผ่าน” แล้ว
            </div>
        </div>

        <div class="card" id="subjectForm">
            <div class="card-header"><i class="bi <?= $editRow ? 'bi-pencil' : 'bi-plus-circle' ?> me-2"></i><?= $editRow ? 'แก้ไขข้อมูลรายวิชา' : 'เพิ่มรายวิชาที่สอน' ?></div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editRow ? 'update_info' : 'add' ?>">
                    <input type="hidden" name="academic_year_id" value="<?= $ay ?>">
                    <?php if ($editRow): ?>
                        <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
                        <div class="alert alert-light border small">
                            <div class="fw-semibold"><?= e($editRow['subject_code']) ?> (<?= e($editRow['level']) ?>)</div><?= e($editRow['subject_name']) ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label" for="subject_code">รหัสวิชา *</label>
                            <input class="form-control<?= $invalid('subject_code') ?>" name="subject_code" id="subject_code" maxlength="20" value="<?= old('subject_code') ?>" placeholder="เช่น 20104-2101"><?= $err('subject_code') ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="subject_name">ชื่อวิชา *</label>
                            <input class="form-control<?= $invalid('subject_name') ?>" name="subject_name" id="subject_name" maxlength="200" value="<?= old('subject_name') ?>"><?= $err('subject_name') ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="level">ระดับชั้น *</label>
                            <select class="form-select<?= $invalid('level') ?>" name="level" id="level">
                                <option value="">-- เลือก --</option>
                                <?php foreach (['ปวช.', 'ปวส.'] as $lv): ?>
                                    <option value="<?= e($lv) ?>" <?= post('level') === $lv ? 'selected' : '' ?>><?= e($lv) ?></option>
                                <?php endforeach; ?>
                            </select><?= $err('level') ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($infoReady): ?>
                        <div class="mb-3">
                            <label class="form-label" for="subject_category">ประเภทวิชา *</label>
                            <input class="form-control<?= $invalid('subject_category') ?>" name="subject_category" id="subject_category" maxlength="150" list="catList" value="<?= e($infoVal('subject_category')) ?>" placeholder="เช่น อุตสาหกรรม"><?= $err('subject_category') ?>
                            <datalist id="catList"><?php foreach ($subjectCats as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="major">สาขาวิชา *</label>
                            <input class="form-control<?= $invalid('major') ?>" name="major" id="major" maxlength="150" list="majorList" value="<?= e($infoVal('major')) ?>" placeholder="เช่น ช่างยนต์"><?= $err('major') ?>
                            <datalist id="majorList"><?php foreach ($majors as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="credit">ท-ป-น *</label>
                            <input class="form-control<?= $invalid('credit') ?>" name="credit" id="credit" maxlength="20" value="<?= e($infoVal('credit')) ?>" placeholder="เช่น 1-3-2"><?= $err('credit') ?>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="unit_count">จำนวนหน่วยการเรียนรู้ *</label>
                                <input type="number" class="form-control<?= $invalid('unit_count') ?>" name="unit_count" id="unit_count" min="1" max="999" value="<?= e($infoVal('unit_count')) ?>"><?= $err('unit_count') ?>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="plan_count">จำนวนแผนการจัดการเรียนรู้ *</label>
                                <input type="number" class="form-control<?= $invalid('plan_count') ?>" name="plan_count" id="plan_count" min="1" max="999" value="<?= e($infoVal('plan_count')) ?>"><?= $err('plan_count') ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($editRow): ?>
                        <div class="d-flex gap-2">
                            <a href="<?= e(url($self . '?ay=' . $ay)) ?>" class="btn btn-outline-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-accent flex-fill"><i class="bi bi-check-lg me-1"></i> บันทึกข้อมูลรายวิชา</button>
                        </div>
                    <?php else: ?>
                        <button type="submit" class="btn btn-accent w-100"><i class="bi bi-plus-lg me-1"></i> เพิ่มรายวิชา (ภาคเรียน <?= e(academic_label($ayRow)) ?>)</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
