<?php
/**
 * ไฟล์: reports/supervision_summary.php
 * รายงานสรุปการนิเทศการสอนระดับวิทยาลัย รวม 2 รูปแบบในหน้าเดียว: โดยหัวหน้าแผนก และโดยนักเรียน นักศึกษา
 *   ?ay= ภาคเรียน, ?dept= แผนก, ?export=csv ส่งออก Excel — พิมพ์/บันทึก PDF จากปุ่มในหน้า
 * สิทธิ์: เจ้าหน้าที่งานพัฒนาหลักสูตรฯ, admin (สิทธิ์รายงานผล), ผู้บริหาร
 */
require_once __DIR__ . '/../config/config.php';

$user = require_role_or_curriculum_staff('admin', 'executive');
$years = db_all('SELECT * FROM academic_years ORDER BY year DESC, semester DESC');
$departments = db_all('SELECT id, name FROM departments ORDER BY name');
$f = ['ay' => get_int('ay', (int)(current_academic_year()['id'] ?? 0)), 'round_id' => 0, 'dept' => get_int('dept'), 'level' => ''];

$sup = report_supervision_rows($f);
$stu = report_student_eval_rows($f);
$supDept = report_supervision_departments($sup);
$stuDept = report_supervision_departments($stu);
$supCats = report_supervision_categories($f);
$stuCats = report_student_eval_categories($f);
$stuByLevel = report_student_eval_by_level($f);
$stuById = array_column($stu, null, 'id');

$summarize = function (array $rows): array {
    $done = array_filter($rows, fn($r) => (int)$r['done'] > 0);
    return ['total' => count($rows), 'done' => count($done), 'pct' => percent(count($done), count($rows)),
        'avg' => $done ? round(array_sum(array_column($done, 'avg_score')) / count($done), 2) : null];
};
$supSum = $summarize($sup);
$stuSum = $summarize($stu);
$responses = array_sum(array_column($stu, 'responses'));
$score = fn($v) => $v !== null ? number_format((float)$v, 2) : '-';
$level = fn($v) => $v !== null ? quality_level((float)$v) : '-';

$filterText = [];
foreach ($years as $y) {
    if ((int)$y['id'] === $f['ay']) {
        $filterText[] = 'ภาคเรียน ' . academic_label($y);
    }
}
$deptLabel = 'ทุกแผนก';
foreach ($departments as $d) {
    if ((int)$d['id'] === $f['dept']) {
        $deptLabel = $d['name'];
    }
}
$filterText[] = $deptLabel;

if (get('export') === 'csv') {
    log_activity('report_export', 'ส่งออกรายงานสรุปการนิเทศการสอน (' . implode(', ', $filterText) . ')');
    $data = [];
    $i = 0;
    foreach ($sup as $r) {
        $s = $stuById[$r['id']] ?? ['responses' => 0, 'avg_score' => null];
        $data[] = [++$i, $r['teacher_name'], $r['department_name'], $r['done'], $r['avg_score'] ?? '', $r['avg_score'] !== null ? quality_level((float)$r['avg_score']) : '',
            $s['responses'], $s['avg_score'] ?? '', $s['avg_score'] !== null ? quality_level((float)$s['avg_score']) : ''];
    }
    $data[] = [];
    $data[] = ['ตัวกรอง', implode(' / ', $filterText)];
    $data[] = ['ออกรายงานเมื่อ', thai_datetime(date('Y-m-d H:i:s')), 'โดย', full_name($user)];
    csv_download('รายงานสรุปการนิเทศการสอน_' . date('Ymd_His') . '.csv',
        ['ลำดับ', 'ชื่อ-สกุล', 'แผนกวิชา', 'หัวหน้าแผนกนิเทศ (ครั้ง)', 'คะแนนเฉลี่ย (หัวหน้าแผนก)', 'ระดับคุณภาพ (หัวหน้าแผนก)',
            'นักเรียนที่ตอบ (คน)', 'คะแนนเฉลี่ย (นักเรียน)', 'ระดับคุณภาพ (นักเรียน)'],
        $data);
}

$pageTitle = 'รายงานสรุปการนิเทศการสอน';
$pageIcon = 'bi-clipboard2-data';
$breadcrumbs = ['รายงานสรุปการนิเทศการสอน' => null];
$activeMenu = 'reports/supervision_summary.php';
$pageActions = '<a href="?' . e(http_build_query(['ay' => $f['ay'], 'dept' => $f['dept'], 'export' => 'csv'])) . '" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>ส่งออก Excel</a>'
    . '<button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>พิมพ์ / PDF</button>';
require APP_ROOT . '/includes/header.php';
?>
<div class="d-none d-print-block text-center mb-3">
    <h4 class="mb-0"><?= e(college_name()) ?></h4>
    <h5>รายงานสรุปการนิเทศการสอนโดยหัวหน้าแผนกและโดยนักเรียน นักศึกษา</h5>
    <div><?= e(implode(' · ', $filterText)) ?></div>
    <div class="small">ออกรายงานเมื่อ <?= e(thai_datetime(date('Y-m-d H:i:s'))) ?> โดย <?= e(full_name($user)) ?></div>
</div>

<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1" for="ay">ปีการศึกษา/ภาคเรียน</label>
                <select name="ay" id="ay" class="form-select">
                    <option value="0">ทั้งหมด</option>
                    <?php foreach ($years as $y): ?><option value="<?= (int)$y['id'] ?>" <?= $f['ay'] === (int)$y['id'] ? 'selected' : '' ?>><?= e(academic_label($y)) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small mb-1" for="dept">แผนกวิชา</label>
                <select name="dept" id="dept" class="form-select">
                    <option value="0">ทุกแผนก</option>
                    <?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $f['dept'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> แสดง</button></div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['1. โดยหัวหน้าแผนก', 'bi-person-video3', 'ครูที่ได้รับการนิเทศ', $supSum],
        ['2. โดยนักเรียน นักศึกษา', 'bi-people', 'ครูที่มีผลประเมินจากนักเรียน', $stuSum],
    ] as $cardNo => [$title, $icon, $label, $s]): ?>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi <?= $icon ?> me-2"></i><?= e($title) ?></div>
                <div class="card-body">
                    <div><?= e($label) ?> <strong><?= $s['done'] ?></strong> จาก <?= $s['total'] ?> คน (ร้อยละ <?= e((string)$s['pct']) ?>)</div>
                    <div>คะแนนเฉลี่ย <strong><?= e($score($s['avg'])) ?></strong> ระดับ <?= e($level($s['avg'])) ?></div>
                    <?php if ($cardNo === 1): ?><div class="small text-muted">จำนวนนักเรียนที่ตอบรวม <?= (int)$responses ?> ครั้ง</div><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-diagram-3 me-2"></i>สรุปรายแผนกวิชา</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 align-middle text-center">
            <thead><tr><th class="text-start" rowspan="2">แผนกวิชา</th><th rowspan="2">ครู (คน)</th><th colspan="3">โดยหัวหน้าแผนก</th><th colspan="3">โดยนักเรียน นักศึกษา</th></tr>
                <tr><th>นิเทศแล้ว (คน)</th><th>คะแนนเฉลี่ย</th><th>ระดับ</th><th>มีผลประเมิน (คน)</th><th>คะแนนเฉลี่ย</th><th>ระดับ</th></tr></thead>
            <tbody>
            <?php foreach ($supDept as $name => $d): $sd = $stuDept[$name] ?? ['done' => 0, 'avg' => null]; ?>
                <tr>
                    <td class="text-start"><?= e((string)$name) ?></td><td><?= (int)$d['teachers'] ?></td>
                    <td><?= (int)$d['done'] ?></td><td><?= e($score($d['avg'])) ?></td><td><?= e($level($d['avg'])) ?></td>
                    <td><?= (int)$sd['done'] ?></td><td><?= e($score($sd['avg'])) ?></td><td><?= e($level($sd['avg'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php foreach ([['คะแนนเฉลี่ยรายด้าน: โดยหัวหน้าแผนก', $supCats], ['คะแนนเฉลี่ยรายด้าน: โดยนักเรียน นักศึกษา', $stuCats]] as [$title, $cats]): ?>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-bullseye me-2"></i><?= e($title) ?></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>ด้าน</th><th class="text-center">คะแนนเฉลี่ย</th><th class="text-center">ระดับ</th></tr></thead>
                        <tbody>
                        <?php foreach ($cats as $c): ?>
                            <tr><td class="small"><?= e($c['category']) ?></td><td class="text-center"><?= e($score($c['avg_score'])) ?></td><td class="text-center"><?= e($level($c['avg_score'])) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$cats): ?><tr><td colspan="3" class="text-center text-muted">ยังไม่มีข้อมูล</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($stuByLevel): ?>
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-mortarboard me-2"></i>ผลประเมินโดยนักเรียน นักศึกษา แยกระดับชั้น</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 text-center">
                <thead><tr><th class="text-start">ระดับ</th><th>ชั้นปี</th><th>แบบประเมิน</th><th>ผู้ตอบ</th><th>คะแนนเฉลี่ย</th><th>ระดับคุณภาพ</th></tr></thead>
                <tbody>
                <?php foreach ($stuByLevel as $l): ?>
                    <tr><td class="text-start"><?= e($l['level']) ?></td><td><?= e((string)($l['grade_year'] ?: '-')) ?></td><td><?= (int)$l['sessions'] ?></td><td><?= (int)$l['responses'] ?></td><td><?= e($score($l['avg_score'])) ?></td><td><?= e($level($l['avg_score'])) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-2"></i>รายบุคคล</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover datatable align-middle" data-order="[]">
                <thead><tr><th>ชื่อ-สกุล</th><th>แผนกวิชา</th><th class="text-center">หัวหน้าแผนกนิเทศ (ครั้ง)</th><th class="text-center">คะแนน (หัวหน้าแผนก)</th><th class="text-center">นักเรียนที่ตอบ</th><th class="text-center">คะแนน (นักเรียน)</th></tr></thead>
                <tbody>
                <?php foreach ($sup as $r): $s = $stuById[$r['id']] ?? ['responses' => 0, 'avg_score' => null]; ?>
                    <tr>
                        <td><?= e($r['teacher_name']) ?></td>
                        <td class="small"><?= e(preg_replace('/^แผนกวิชา/u', '', $r['department_name'])) ?></td>
                        <td class="text-center"><?= (int)$r['done'] ?></td>
                        <td class="text-center"><?= e($score($r['avg_score'])) ?></td>
                        <td class="text-center"><?= (int)$s['responses'] ?></td>
                        <td class="text-center"><?= e($score($s['avg_score'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
