<?php
/**
 * ไฟล์: includes/partials/plan_eval_report_body.php
 * ตัวเลขสรุปผลการประเมินแผนฯ ของรายงาน — ใช้ทั้งหน้าดูรายงานและหน้าพิมพ์
 * ต้องมีตัวแปร $data (จาก pe_report_data)
 */
$tot = $data['totals'];
?>
<p>แผนการจัดการเรียนรู้ที่ครูส่ง <?= (int)$tot['plans'] ?> แผน จากครู <?= (int)$tot['teachers'] ?> คน
    ส่งผลการประเมินแล้ว <?= (int)$tot['evaluated'] ?> แผน (ร้อยละ <?= e((string)percent($tot['evaluated'], $tot['plans'])) ?>)
    ผ่านเกณฑ์ <?= (int)$tot['passed'] ?> แผน (ร้อยละ <?= e((string)percent($tot['passed'], $tot['evaluated'])) ?>)
    ค่าเฉลี่ยรวม <?= $tot['evaluated'] ? e(number_format((float)$tot['avg'], 2) . ' ระดับ' . plan_eval_level((float)$tot['avg'])) : '-' ?></p>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead><tr><th class="text-start">แผนกวิชา</th><th>แผนที่ส่ง</th><th>ประเมินแล้ว</th><th>ผ่านเกณฑ์</th><th>ค่าเฉลี่ย</th><th>ระดับคุณภาพ</th></tr></thead>
        <tbody>
        <?php foreach ($data['by_dept'] as $d): ?>
            <tr class="text-center">
                <td class="l text-start"><?= e((string)$d['name']) ?></td>
                <td><?= (int)$d['plans'] ?></td>
                <td><?= (int)$d['evaluated'] ?></td>
                <td><?= (int)$d['passed'] ?></td>
                <td><?= $d['evaluated'] ? e(number_format((float)$d['avg'], 2)) : '-' ?></td>
                <td><?= $d['evaluated'] ? e(plan_eval_level((float)$d['avg'])) : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($data['by_category']): ?>
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle">
            <thead><tr><th class="text-start">ด้านที่ประเมิน (ตอนที่ 2)</th><th>ค่าเฉลี่ย</th><th>ระดับคุณภาพ</th></tr></thead>
            <tbody>
            <?php foreach ($data['by_category'] as $cat => $c): ?>
                <tr class="text-center"><td class="l text-start"><?= e((string)$cat) ?></td><td><?= e(number_format((float)$c['avg'], 2)) ?></td><td><?= e(plan_eval_level((float)$c['avg'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
        <thead><tr class="text-center"><?php foreach (array_keys($data['levels']) as $lv): ?><th><?= e((string)$lv) ?></th><?php endforeach; ?></tr></thead>
        <tbody><tr class="text-center"><?php foreach ($data['levels'] as $n): ?><td><?= (int)$n ?> แผน</td><?php endforeach; ?></tr></tbody>
    </table>
</div>
