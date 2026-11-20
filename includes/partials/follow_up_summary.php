<?php
/**
 * ไฟล์: includes/partials/follow_up_summary.php
 * กล่องสรุป "ติดตามการส่งงาน" บนแดชบอร์ด (ผู้บริหาร / หัวหน้าแผนก / admin) — กดแต่ละช่องไปที่รายงาน
 * ต้องมีตัวแปร $user · ใช้ภาคเรียนที่หน้าแดชบอร์ดเลือกไว้ ($ay แบบ array) ไม่มี = ภาคเรียนปัจจุบัน · หัวหน้าแผนกเห็นเฉพาะแผนกตนเอง
 */
if (!isset($user) || !can_view_follow_up($user) || (is_limited_admin($user) && !admin_can_path($user, 'reports/follow_up.php'))) {
    return;
}
$fuAy = (isset($ay) && is_array($ay) && !empty($ay['id'])) ? $ay : current_academic_year();
if (!$fuAy) {
    return;
}
$fuSum = follow_up_summary((int)$fuAy['id'], follow_up_forced_dept($user));
$fuItems = [
    ['outline', $fuSum['outline'], 'คน', 'ส่งโครงการสอนยังไม่ครบ', 'ic-red'],
    ['plan', $fuSum['plan_none'], 'คน', 'ยังไม่ส่งแผนการสอนเลย', 'ic-orange'],
    ['review', $fuSum['pending_review'], 'รายการ', 'งานรอหัวหน้าแผนกตรวจ', 'ic-yellow'],
    ['review', $fuSum['eval_left'], 'แผน', 'แผนที่ยังไม่ประเมิน', 'ic-purple'],
    ['no_subject', $fuSum['no_subject'], 'คน', 'ยังไม่ลงรายวิชาที่สอน', 'ic-blue'],
    ['research', $fuSum['research'], 'คน', 'ยังไม่ส่งเค้าโครงวิจัย', 'ic-green'],
];
$fuIcons = array_map(fn($v) => $v[1], follow_up_views());
?>
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-list-check me-2"></i>ติดตามการส่งงาน · ภาคเรียน <?= e(academic_label($fuAy)) ?>
            <?= follow_up_forced_dept($user) ? '<span class="text-muted small">(แผนกของท่าน)</span>' : '' ?></span>
        <a href="<?= e(url('reports/follow_up.php?ay=' . (int)$fuAy['id'])) ?>" class="small">ดูรายงานทั้งหมด <i class="bi bi-chevron-right"></i></a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($fuItems as [$view, $n, $unit, $label, $ic]): ?>
                <div class="col-6 col-md-4 col-xl-2">
                    <a href="<?= e(url('reports/follow_up.php?view=' . $view . '&ay=' . (int)$fuAy['id'])) ?>" class="stat-card h-100">
                        <div class="stat-icon <?= e($ic) ?>"><i class="bi <?= e($fuIcons[$view]) ?>"></i></div>
                        <div><div class="stat-value <?= $n > 0 ? '' : 'text-success' ?>"><?= number_format($n) ?> <small class="fs-6 fw-normal"><?= e($unit) ?></small></div>
                            <div class="stat-label"><?= e($label) ?></div></div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
