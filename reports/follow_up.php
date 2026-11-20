<?php
/**
 * ไฟล์: reports/follow_up.php
 * เมนู "ติดตามการส่งงาน" — รายชื่อครู/หัวหน้าแผนกที่ยังค้างงานในภาคเรียน
 *   ?view= outline | plan | review | no_subject | research
 *   ?ay= ภาคเรียน, ?dept= แผนก (ผู้บริหาร/admin/งานพัฒนาหลักสูตรฯ), ?all=1 แสดงรายการที่ครบแล้วด้วย
 * สิทธิ์: admin, ผู้บริหาร, งานพัฒนาหลักสูตรฯ (ทุกแผนก) · หัวหน้าแผนก (เฉพาะแผนกตนเอง)
 */
require_once __DIR__ . '/../config/config.php';

$user = require_login();
if (!can_view_follow_up($user)) {
    abort_page(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้', 'หน้านี้สำหรับผู้บริหาร หัวหน้าแผนกวิชา งานพัฒนาหลักสูตรฯ และผู้ดูแลระบบ');
}

$views = follow_up_views();
$view = isset($views[get('view')]) ? get('view') : 'outline';
[$viewTitle, $viewIcon, $viewHint] = $views[$view];

$years = db_all('SELECT * FROM academic_years ORDER BY year DESC, semester DESC');
$ay = get_int('ay', (int)(current_academic_year()['id'] ?? 0));
$ayRow = null;
foreach ($years as $y) {
    if ((int)$y['id'] === $ay) {
        $ayRow = $y;
    }
}
$forced = follow_up_forced_dept($user);
$dept = $forced ?: get_int('dept');
$departments = $forced ? [] : db_all('SELECT id, name FROM departments ORDER BY name');
$showAll = get('all') === '1';
$shortDept = fn(string $name) => preg_replace('/^แผนกวิชา/u', '', $name);

// ---------- ข้อมูลตามรายงาน ----------
$rows = [];
$stat = [];
if ($view === 'outline' || $view === 'plan' || $view === 'no_subject') {
    $all = follow_up_teaching_rows($ay, $dept);
    $withSubjects = array_values(array_filter($all, fn($t) => $t['subjects'] > 0));
    $noSubject = array_values(array_filter($all, fn($t) => $t['subjects'] === 0));
    if ($view === 'no_subject') {
        $rows = $noSubject;
        $stat = ['ครูทั้งหมด' => count($all), 'ลงรายวิชาแล้ว' => count($withSubjects), 'ยังไม่ลงรายวิชา' => count($noSubject)];
    } else {
        $key = $view === 'outline' ? 'outlines' : 'plans';
        $done = array_values(array_filter($withSubjects, fn($t) => $t[$key] === $t['subjects']));
        $none = array_values(array_filter($withSubjects, fn($t) => $t[$key] === 0));
        $rows = $showAll ? $withSubjects : array_values(array_filter($withSubjects, fn($t) => $t[$key] < $t['subjects']));
        // ยังไม่ส่งเลยขึ้นก่อน แล้วเรียงตามแผนก/ชื่อ
        usort($rows, fn($a, $b) => [$a[$key] > 0, $a['department_name'], $a['first_name']] <=> [$b[$key] > 0, $b['department_name'], $b['first_name']]);
        $stat = ['ครูที่ลงรายวิชาแล้ว' => count($withSubjects), 'ส่งครบทุกวิชา' => count($done),
            'ส่งบางวิชา' => count($withSubjects) - count($done) - count($none), 'ยังไม่ส่งเลย' => count($none)];
    }
} elseif ($view === 'research') {
    $all = follow_up_research_rows($ay, $dept);
    $notSent = array_values(array_filter($all, fn($t) => !$t['sent']));
    $rows = $showAll ? $all : $notSent;
    usort($rows, fn($a, $b) => [$a['sent'], $a['department_name'], $a['first_name']] <=> [$b['sent'], $b['department_name'], $b['first_name']]);
    $stat = ['ครูทั้งหมด' => count($all), 'ส่งเค้าโครงแล้ว' => count($all) - count($notSent), 'ยังไม่ส่ง' => count($notSent)];
} else {
    $all = follow_up_review_rows($ay, $dept);
    $rows = $showAll ? $all : array_values(array_filter($all, fn($d) => $d['pending'] > 0 || $d['eval_left'] > 0));
    usort($rows, fn($a, $b) => [-$a['pending'] - $a['eval_left'], $a['department_name']] <=> [-$b['pending'] - $b['eval_left'], $b['department_name']]);
    $stat = ['แผนกที่ยังมีงานค้าง' => count(array_filter($all, fn($d) => $d['pending'] > 0 || $d['eval_left'] > 0)),
        'งานรอตรวจ' => array_sum(array_column($all, 'pending')),
        'แผนยังไม่ประเมิน' => array_sum(array_column($all, 'eval_none')),
        'ประเมินค้างเป็นร่าง' => array_sum(array_column($all, 'eval_draft'))];
}

$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(
    ['view' => $view, 'ay' => $ay, 'dept' => $forced ? null : ($dept ?: null), 'all' => $showAll ? '1' : null], $over
), fn($v) => $v !== null && $v !== ''));
$statColors = ['text-primary-th', 'text-success', 'text-warning', 'text-danger'];

$pageTitle = $viewTitle;
$pageIcon = $viewIcon;
$breadcrumbs = ['ติดตามการส่งงาน' => null, $viewTitle => null];
$activeMenu = 'reports/follow_up.php?view=' . $view;
require APP_ROOT . '/includes/header.php';
?>
<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php foreach ($views as $k => [$label, $icon]): ?>
        <li class="nav-item"><a class="nav-link py-1 px-3 small<?= $k === $view ? ' active' : '' ?>" href="<?= e($qs(['view' => $k, 'all' => null])) ?>">
            <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-accent"></i><?= e($viewHint) ?>
    <?php if ($view === 'outline' || $view === 'plan'): ?>
        · นับว่า “ส่งแล้ว” เมื่อมีเอกสารของวิชานั้นในภาคเรียนนี้ ไม่ว่าจะรอตรวจ ผ่าน หรือให้แก้ไข
        · ครูที่ยังไม่ลงรายวิชาอยู่ในรายงาน <a href="<?= e($qs(['view' => 'no_subject', 'all' => null])) ?>">ครูที่ยังไม่ลงรายวิชาที่สอน</a>
    <?php elseif ($view === 'review'): ?>
        · “รอตรวจ” = โครงการสอน/แผนที่ยังไม่ได้บันทึกผลการตรวจ · “ยังไม่ประเมิน” = แผนการจัดการเรียนรู้ที่ยังไม่มีผลประเมิน (รวมที่บันทึกร่างไว้)
    <?php endif; ?>
    <?php if ($forced): ?> · แสดงเฉพาะ<?= e($user['department_name'] ?? 'แผนกของท่าน') ?><?php endif; ?></div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <div class="col-md-3">
                <label class="form-label small mb-1" for="ay">ภาคเรียน</label>
                <select name="ay" id="ay" class="form-select">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int)$y['id'] ?>" <?= $ay === (int)$y['id'] ? 'selected' : '' ?>><?= e(academic_label($y)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!$forced): ?>
                <div class="col-md-4">
                    <label class="form-label small mb-1" for="dept">แผนกวิชา</label>
                    <select name="dept" id="dept" class="form-select">
                        <option value="0">ทุกแผนก</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int)$d['id'] ?>" <?= $dept === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($view !== 'no_subject'): ?>
                <div class="col-md-3">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="all" id="all" value="1" <?= $showAll ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="all"><?= $view === 'review' ? 'แสดงแผนกที่ไม่มีงานค้างด้วย' : 'แสดงครูที่ส่งครบแล้วด้วย' ?></label>
                    </div>
                </div>
            <?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> แสดง</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php $i = 0; foreach ($stat as $label => $n): ?>
        <div class="col-6 col-md"><div class="card text-center h-100"><div class="card-body py-3">
            <div class="fs-2 fw-bold <?= $statColors[min($i++, 3)] ?>"><?= number_format($n) ?></div>
            <div class="text-muted small"><?= e($label) ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><i class="bi <?= e($viewIcon) ?> me-2"></i><?= e($viewTitle) ?> · ภาคเรียน <?= e(academic_label($ayRow)) ?>
        <span class="badge bg-secondary ms-1"><?= count($rows) ?> รายการ</span></div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="empty-state"><i class="bi bi-check2-circle text-success"></i>ไม่มีรายการค้างในภาคเรียนนี้</div>
        <?php else: ?>
        <div class="table-responsive">
            <?php if ($view === 'outline' || $view === 'plan'):
                $key = $view === 'outline' ? 'outlines' : 'plans';
                $other = $view === 'outline' ? 'plans' : 'outlines';
                $missingKey = $view === 'outline' ? 'missing_outline' : 'missing_plan'; ?>
                <table class="table table-hover align-middle datatable" data-order="[]">
                    <thead><tr><th>#</th><th>ครู</th><th>แผนก</th><th class="text-center">วิชาที่สอน</th>
                        <th class="text-center"><?= $view === 'outline' ? 'ส่งโครงการสอน' : 'ส่งแผนการสอน' ?></th>
                        <th class="text-center"><?= $view === 'outline' ? 'ส่งแผนการสอน' : 'ส่งโครงการสอน' ?></th>
                        <th>สถานะ</th><th>วิชาที่ยังไม่ส่ง</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $n => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $n + 1 ?></td>
                            <td style="min-width: 150px"><?= e(full_name($r)) ?><?= $r['role'] === 'supervisor' ? ' <span class="badge bg-light text-dark border">หัวหน้าแผนก</span>' : '' ?></td>
                            <td class="small"><?= e($shortDept($r['department_name'])) ?></td>
                            <td class="text-center"><?= $r['subjects'] ?></td>
                            <td class="text-center fw-semibold <?= $r[$key] === $r['subjects'] ? 'text-success' : ($r[$key] === 0 ? 'text-danger' : 'text-warning') ?>"
                                data-order="<?= $r[$key] ?>"><?= $r[$key] ?> / <?= $r['subjects'] ?></td>
                            <td class="text-center text-muted"><?= $r[$other] ?> / <?= $r['subjects'] ?></td>
                            <td data-order="<?= $r[$key] === 0 ? 0 : ($r[$key] < $r['subjects'] ? 1 : 2) ?>">
                                <?php if ($r[$key] === 0): ?><span class="badge bg-danger">ยังไม่ส่งเลย</span>
                                <?php elseif ($r[$key] < $r['subjects']): ?><span class="badge bg-warning text-dark">ส่งแล้ว <?= $r[$key] ?> วิชา</span>
                                <?php else: ?><span class="badge bg-success">ส่งครบ</span><?php endif; ?>
                            </td>
                            <td class="small" style="min-width: 260px">
                                <?php foreach ($r[$missingKey] ?: ['-'] as $m): ?><div><?= e($m) ?></div><?php endforeach; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($view === 'no_subject' || $view === 'research'): ?>
                <table class="table table-hover align-middle datatable" data-order="[]">
                    <thead><tr><th>#</th><th>ครู</th><th>แผนก</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $n => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $n + 1 ?></td>
                            <td><?= e(full_name($r)) ?><?= $r['role'] === 'supervisor' ? ' <span class="badge bg-light text-dark border">หัวหน้าแผนก</span>' : '' ?></td>
                            <td class="small"><?= e($shortDept($r['department_name'])) ?></td>
                            <td><?php if ($view === 'no_subject'): ?><span class="badge bg-secondary">ยังไม่ลงรายวิชาที่สอน</span>
                                <?php elseif ($r['sent']): ?><span class="badge bg-success">ส่งเค้าโครงแล้ว</span>
                                <?php else: ?><span class="badge bg-danger">ยังไม่ส่งเค้าโครงวิจัย</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table table-hover align-middle datatable" data-order="[]">
                    <thead><tr><th>แผนก</th><th>หัวหน้าแผนก</th><th class="text-center">โครงการสอนรอตรวจ</th><th class="text-center">แผนรอตรวจ</th>
                        <th class="text-center">รอนานสุด</th><th class="text-center">แผนยังไม่ประเมิน</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $days = follow_up_days_waiting($r['oldest']); ?>
                        <tr>
                            <td><?= e($shortDept($r['department_name'])) ?></td>
                            <td class="small"><?= $r['heads'] ? e(implode(', ', $r['heads'])) : '<span class="text-danger">ยังไม่มีหัวหน้าแผนกในระบบ</span>' ?></td>
                            <td class="text-center <?= $r['pending_outline'] ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $r['pending_outline'] ?></td>
                            <td class="text-center <?= $r['pending_plan'] ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $r['pending_plan'] ?></td>
                            <td class="text-center small" data-order="<?= $days ?>"><?= $r['oldest'] ? $days . ' วัน<div class="text-muted">ส่ง ' . e(thai_date($r['oldest'])) . '</div>' : '-' ?></td>
                            <td class="text-center" data-order="<?= $r['eval_left'] ?>">
                                <span class="<?= $r['eval_left'] ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $r['eval_left'] ?></span> / <?= $r['plans_total'] ?>
                                <?php if ($r['eval_draft']): ?><div class="small text-muted">ร่าง <?= $r['eval_draft'] ?></div><?php endif; ?></td>
                            <td><?= ($r['pending'] || $r['eval_left'])
                                    ? '<span class="badge bg-danger">ยังมีงานค้าง</span>'
                                    : '<span class="badge bg-success">ตรวจ/ประเมินครบ</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
