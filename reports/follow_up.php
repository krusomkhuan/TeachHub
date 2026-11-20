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
$shortDept = fn(string $name) => preg_replace('/^แผนกวิชา/u', '', $name);

// ---------- ข้อมูลตามรายงาน ----------
// $groups: การ์ดสรุปที่กดเพื่อกรองรายการได้ [filter => [ชื่อ, ฟังก์ชันคัดแถว, สีตัวเลข (null = ไม่แสดงเป็นการ์ด)]]
// ค่าเริ่มต้น = 'todo' (รายการที่ยังค้าง)
$defaultFilter = 'todo';
$sortKey = null;
if ($view === 'outline' || $view === 'plan' || $view === 'no_subject') {
    $listRows = follow_up_teaching_rows($ay, $dept);
    $hasSubj = fn($t) => $t['subjects'] > 0;
    if ($view === 'no_subject') {
        $groups = [
            'everyone' => ['ครูทั้งหมด', fn($t) => true, 'text-primary-th'],
            'has'      => ['ลงรายวิชาแล้ว', $hasSubj, 'text-success'],
            'todo'     => ['ยังไม่ลงรายวิชา', fn($t) => $t['subjects'] === 0, 'text-danger'],
        ];
        $sortKey = fn($t) => [$t['subjects'] > 0, $t['department_name'], $t['first_name']];
    } else {
        // เกณฑ์: โครงการสอนต้องครบทุกวิชา · แผนการจัดการเรียนรู้อย่างน้อย FOLLOW_UP_PLAN_REQUIRED วิชา (ส่งเกินได้)
        $key = $view === 'outline' ? 'outlines' : 'plans';
        $isDone = $view === 'outline' ? fn($t) => $t['outlines'] === $t['subjects'] : 'follow_up_plan_done';
        $groups = $view === 'outline'
            ? [
                'all'     => ['ครูที่ลงรายวิชาแล้ว', $hasSubj, 'text-primary-th'],
                'done'    => ['ส่งครบทุกวิชา', fn($t) => $hasSubj($t) && $isDone($t), 'text-success'],
                'partial' => ['ส่งบางวิชา', fn($t) => $hasSubj($t) && !$isDone($t) && $t['outlines'] > 0, 'text-warning'],
                'none'    => ['ยังไม่ส่งเลย', fn($t) => $hasSubj($t) && $t['outlines'] === 0, 'text-danger'],
                'todo'    => ['ยังส่งไม่ครบ', fn($t) => $hasSubj($t) && !$isDone($t), null],
            ]
            : [
                'all'  => ['ครูที่ลงรายวิชาแล้ว', $hasSubj, 'text-primary-th'],
                'done' => ['ส่งครบแล้ว', fn($t) => $hasSubj($t) && $isDone($t), 'text-success'],
                'todo' => ['ยังไม่ส่ง', fn($t) => $hasSubj($t) && !$isDone($t), 'text-danger'],
            ];
        // ยังไม่ส่งเลยขึ้นก่อน แล้วเรียงตามแผนก/ชื่อ
        $sortKey = fn($t) => [$t[$key] > 0, $t['department_name'], $t['first_name']];
    }
} elseif ($view === 'research') {
    $listRows = follow_up_research_rows($ay, $dept);
    $groups = [
        'all'  => ['ครูทั้งหมด', fn($t) => true, 'text-primary-th'],
        'done' => ['ส่งเค้าโครงแล้ว', fn($t) => $t['sent'], 'text-success'],
        'todo' => ['ยังไม่ส่ง', fn($t) => !$t['sent'], 'text-danger'],
    ];
    $sortKey = fn($t) => [$t['sent'], $t['department_name'], $t['first_name']];
} else {
    $listRows = follow_up_review_rows($ay, $dept);
    $groups = [
        'all'     => ['แผนกทั้งหมด', fn($d) => true, 'text-primary-th'],
        'todo'    => ['แผนกที่ยังมีงานค้าง', fn($d) => $d['pending'] > 0 || $d['eval_left'] > 0, 'text-danger'],
        'pending' => ['มีงานรอตรวจ', fn($d) => $d['pending'] > 0, 'text-warning'],
        'eval'    => ['มีแผนยังไม่ประเมิน', fn($d) => $d['eval_left'] > 0, 'text-warning'],
        'clear'   => ['ตรวจ/ประเมินครบแล้ว', fn($d) => $d['pending'] === 0 && $d['eval_left'] === 0, 'text-success'],
    ];
    $sortKey = fn($d) => [-$d['pending'] - $d['eval_left'], $d['department_name']];
}
// ?filter= มาจากการกดการ์ด (รองรับลิงก์เดิม ?all=1 = แสดงทั้งหมด)
$filter = get('filter') !== '' ? get('filter') : (get('all') === '1' ? (string)array_key_first($groups) : $defaultFilter);
if (!isset($groups[$filter])) {
    $filter = $defaultFilter;
}
$counts = array_map(fn($g) => count(array_filter($listRows, $g[1])), $groups);
$rows = array_values(array_filter($listRows, $groups[$filter][1]));
usort($rows, fn($a, $b) => $sortKey($a) <=> $sortKey($b));
$filterLabel = $groups[$filter][0];

$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(
    ['view' => $view, 'ay' => $ay, 'dept' => $forced ? null : ($dept ?: null), 'filter' => $filter === $defaultFilter ? null : $filter], $over
), fn($v) => $v !== null && $v !== ''));

$pageTitle = $viewTitle;
$pageIcon = $viewIcon;
$breadcrumbs = ['ติดตามการส่งงาน' => null, $viewTitle => null];
$activeMenu = 'reports/follow_up.php?view=' . $view;
require APP_ROOT . '/includes/header.php';
?>
<ul class="nav nav-pills flex-wrap gap-1 mb-3">
    <?php foreach ($views as $k => [$label, $icon]): ?>
        <li class="nav-item"><a class="nav-link py-1 px-3 small<?= $k === $view ? ' active' : '' ?>" href="<?= e($qs(['view' => $k, 'filter' => null])) ?>">
            <i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-accent"></i><?= e($viewHint) ?>
    <?php if ($view === 'outline' || $view === 'plan'): ?>
        · นับว่า “ส่งแล้ว” เมื่อมีเอกสารของวิชานั้นในภาคเรียนนี้ ไม่ว่าจะรอตรวจ ผ่าน หรือให้แก้ไข
        · ครูที่ยังไม่ลงรายวิชาอยู่ในรายงาน <a href="<?= e($qs(['view' => 'no_subject', 'filter' => null])) ?>">ครูที่ยังไม่ลงรายวิชาที่สอน</a>
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
            <?php if ($filter !== $defaultFilter): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> แสดง</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($groups as $g => [$label, , $color]): if ($color === null) { continue; } ?>
        <div class="col-6 col-md">
            <a href="<?= e($qs(['filter' => $g === $defaultFilter ? null : $g])) ?>"
               class="card text-center h-100 text-decoration-none fu-filter<?= $g === $filter ? ' active' : '' ?>"
               <?= $g === $filter ? 'aria-current="true"' : '' ?> title="กดเพื่อแสดงรายการ: <?= e($label) ?>">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold <?= e($color) ?>"><?= number_format($counts[$g]) ?></div>
                    <div class="text-muted small"><?= e($label) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header"><i class="bi <?= e($viewIcon) ?> me-2"></i><?= e($viewTitle) ?> · ภาคเรียน <?= e(academic_label($ayRow)) ?>
        <span class="badge bg-secondary ms-1"><?= e($filterLabel) ?> · <?= count($rows) ?> รายการ</span>
        <?php if ($filter !== $defaultFilter): ?><a href="<?= e($qs(['filter' => null])) ?>" class="small ms-2">กลับไปดูรายการที่ยังค้าง</a><?php endif; ?></div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="empty-state"><i class="bi bi-check2-circle text-success"></i><?= $filter === $defaultFilter ? 'ไม่มีรายการค้างในภาคเรียนนี้' : 'ไม่มีรายการ “' . e($filterLabel) . '”' ?></div>
        <?php else: ?>
        <div class="table-responsive">
            <?php if ($view === 'outline' || $view === 'plan'):
                $key = $view === 'outline' ? 'outlines' : 'plans';
                $other = $view === 'outline' ? 'plans' : 'outlines';
                $listKey = $view === 'outline' ? 'missing_outline' : 'sent_plan'; ?>
                <table class="table table-hover align-middle datatable" data-order="[]">
                    <thead><tr><th>#</th><th>ครู</th><th>แผนก</th><th class="text-center">วิชาที่สอน</th>
                        <th class="text-center"><?= $view === 'outline' ? 'ส่งโครงการสอน' : 'ส่งแผนการสอน' ?></th>
                        <th class="text-center"><?= $view === 'outline' ? 'ส่งแผนการสอน' : 'ส่งโครงการสอน' ?></th>
                        <th>สถานะ</th><th><?= $view === 'outline' ? 'วิชาที่ยังไม่ส่ง' : 'วิชาที่ส่งแผนแล้ว' ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $n => $r): ?>
                        <tr>
                            <td class="text-muted"><?= $n + 1 ?></td>
                            <td style="min-width: 150px"><?= e(full_name($r)) ?><?= $r['role'] === 'supervisor' ? ' <span class="badge bg-light text-dark border">หัวหน้าแผนก</span>' : '' ?></td>
                            <td class="small"><?= e($shortDept($r['department_name'])) ?></td>
                            <td class="text-center"><?= $r['subjects'] ?></td>
                            <?php $ok = $isDone($r); ?>
                            <td class="text-center fw-semibold <?= $ok ? 'text-success' : ($r[$key] === 0 ? 'text-danger' : 'text-warning') ?>"
                                data-order="<?= $r[$key] ?>"><?= $view === 'outline' ? $r[$key] . ' / ' . $r['subjects'] : $r[$key] . ' วิชา' ?></td>
                            <td class="text-center text-muted"><?= $r[$other] ?> / <?= $r['subjects'] ?></td>
                            <td data-order="<?= $ok ? 2 : ($r[$key] === 0 ? 0 : 1) ?>">
                                <?php if ($ok): ?><span class="badge bg-success">ส่งครบแล้ว</span>
                                <?php elseif ($r[$key] === 0): ?><span class="badge bg-danger"><?= $view === 'outline' ? 'ยังไม่ส่งเลย' : 'ยังไม่ส่ง' ?></span>
                                <?php else: ?><span class="badge bg-warning text-dark">ส่งแล้ว <?= $r[$key] ?> วิชา</span><?php endif; ?>
                            </td>
                            <td class="small" style="min-width: 260px">
                                <?php foreach ($r[$listKey] ?: ['-'] as $m): ?><div><?= e($m) ?></div><?php endforeach; ?></td>
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
                            <td><?php if ($view === 'no_subject' && $r['subjects'] > 0): ?><span class="badge bg-success">ลงรายวิชาแล้ว <?= $r['subjects'] ?> วิชา</span>
                                <?php elseif ($view === 'no_subject'): ?><span class="badge bg-secondary">ยังไม่ลงรายวิชาที่สอน</span>
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
