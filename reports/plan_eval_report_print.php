<?php
/**
 * ไฟล์: reports/plan_eval_report_print.php?id=xx
 * พิมพ์ "บันทึกข้อความ" รายงานสรุปผลการประเมินแผนฯ (A4) พร้อมช่องความเห็น/ลงนาม 3 ขั้น
 * ใช้ "พิมพ์ → บันทึกเป็น PDF" จากเบราว์เซอร์
 */
require_once __DIR__ . '/../config/config.php';

$user = require_login();
if (!pe_report_ready()) {
    abort_page(503, 'ยังใช้งานหน้านี้ไม่ได้', 'ผู้ดูแลระบบต้องนำเข้า database/update_v1.22.sql ก่อน');
}
$rep = pe_report_fetch(get_int('id'));
if (!$rep || !pe_report_can_view($user, $rep)) {
    abort_page(404, 'ไม่พบรายงาน');
}
$data = pe_report_data($rep);
$chain = report_approval_chain();
$opinions = $rep['status'] === 'draft' ? [] : pe_report_latest_opinions((int)$rep['id']);
$memoDate = $rep['submitted_at'] ?: date('Y-m-d');
$watermark = ['draft' => 'ฉบับร่าง', 'returned' => 'ส่งกลับแก้ไข', 'pending' => 'อยู่ระหว่างพิจารณา', 'approved' => ''][$rep['status']];
log_activity('pe_report_print', 'พิมพ์รายงานสรุปผลประเมินแผนฯ #' . (int)$rep['id']);
$dots = fn(int $n = 50) => str_repeat('.', $n);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($rep['title']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 20mm 20mm 20mm 30mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; font-size: 16px; line-height: 1.55; color: #000; background: #e9ecef; margin: 0; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; padding: 20mm 20mm 20mm 30mm; position: relative; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
        .memo-title { font-size: 29px; font-weight: 700; text-align: center; margin: 0 0 10px; letter-spacing: 1px; }
        .row { display: flex; gap: 8px; }
        .lbl { font-weight: 700; white-space: nowrap; }
        .line { border-bottom: 1px dotted #000; flex: 1; padding: 0 4px; }
        .indent { text-indent: 2.5cm; text-align: justify; }
        h4 { font-size: 16px; margin: 12px 0 2px; }
        .box { white-space: pre-wrap; margin-left: 1cm; text-align: justify; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin: 4px 0 6px; }
        th, td { border: 1px solid #000; padding: 2px 5px; text-align: center; }
        th { background: #f0f0f0; }
        td.l, th.text-start { text-align: left; }
        .sign { width: 60%; margin-left: 40%; text-align: center; margin-top: 18px; page-break-inside: avoid; }
        .sign .s { margin-top: 26px; }
        .approve { border: 1px solid #000; padding: 8px 12px; margin-top: 10px; page-break-inside: avoid; }
        .approve .head { font-weight: 700; }
        .approve .comment { min-height: 40px; white-space: pre-wrap; border-bottom: 1px dotted #999; margin: 4px 0; }
        .esign { font-size: 12px; color: #444; }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 80px; font-weight: 700; color: rgba(220, 53, 69, .12); transform: rotate(-25deg); pointer-events: none; }
        .toolbar { text-align: center; margin: 16px; }
        .toolbar button { font-family: inherit; font-size: 15px; padding: 8px 18px; border-radius: 8px; border: 0; background: #f47b20; color: #fff; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
            .toolbar { display: none; }
        }
        @media screen and (max-width: 820px) { .sheet { width: 100%; padding: 16px; min-height: auto; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">🖨 พิมพ์ / บันทึกเป็น PDF</button></div>
<div class="sheet">
    <?php if ($watermark): ?><div class="watermark"><?= e($watermark) ?></div><?php endif; ?>
    <div class="memo-title">บันทึกข้อความ</div>
    <div class="row"><span class="lbl">ส่วนราชการ</span><span class="line">งานพัฒนาหลักสูตรและการจัดการเรียนรู้ <?= e(college_name()) ?></span></div>
    <div class="row">
        <span class="lbl">ที่</span><span class="line" style="flex:.8"></span>
        <span class="lbl">วันที่</span><span class="line"><?= e(thai_date($memoDate, false, true)) ?></span>
    </div>
    <div class="row"><span class="lbl">เรื่อง</span><span class="line"><?= e($rep['title']) ?></span></div>
    <hr style="border:0;border-top:1px solid #000;margin:8px 0">
    <div><span class="lbl">เรียน</span> ผู้อำนวยการ<?= e(college_name()) ?></div>

    <p class="indent" style="margin-top:8px">
        ตามที่หัวหน้าแผนกวิชาได้ดำเนินการประเมินแผนการจัดการเรียนรู้ของครูผู้สอน ประจำภาคเรียนที่ <?= e($rep['ay_semester'] . '/' . $rep['ay_year']) ?>
        บัดนี้งานพัฒนาหลักสูตรและการจัดการเรียนรู้ได้รวบรวมข้อมูลเรียบร้อยแล้ว จึงขอรายงานสรุปผล ดังนี้
    </p>

    <h4>1. ข้อมูลการประเมิน</h4>
    <?php require APP_ROOT . '/includes/partials/plan_eval_report_body.php'; ?>
    <h4>2. สรุปผลการประเมินโดยภาพรวม</h4>
    <div class="box"><?= e($rep['summary_overall'] ?: '-') ?></div>
    <h4>3. จุดเด่น</h4>
    <div class="box"><?= e($rep['strengths'] ?: '-') ?></div>
    <h4>4. ปัญหา/อุปสรรค</h4>
    <div class="box"><?= e($rep['problems'] ?: '-') ?></div>
    <h4>5. ข้อเสนอแนะ/แนวทางพัฒนา</h4>
    <div class="box"><?= e($rep['suggestions'] ?: '-') ?></div>

    <p class="indent" style="margin-top:12px">จึงเรียนมาเพื่อโปรดทราบและพิจารณา</p>

    <div class="sign">
        <div class="s">(ลงชื่อ) <?= $dots(45) ?></div>
        <div>( <?= e($rep['author_name']) ?> )</div>
        <div><?= e(CURRICULUM_STAFF_POSITION) ?></div>
        <?php if ($rep['submitted_at']): ?><div class="esign">เสนอผ่านระบบ <?= e(system_name()) ?> เมื่อ <?= e(thai_datetime($rep['submitted_at'])) ?></div><?php endif; ?>
    </div>

    <?php foreach ($chain as $i => $s): $o = $opinions[$i] ?? null; ?>
        <div class="approve">
            <div class="head"><?= $i === 3 ? 'ความเห็น/ข้อสั่งการ' : 'ความเห็น' ?><?= e($s['title']) ?></div>
            <?php if ($o): ?>
                <div>☑ <?= e(report_action_label($o['action'], $i)) ?></div>
                <div class="comment"><?= e($o['comment'] ?: ' ') ?></div>
            <?php else: ?>
                <div><?= $i === 3 ? '☐ ทราบ/อนุมัติ &nbsp;&nbsp; ☐ อื่น ๆ' : '☐ เห็นควรเสนอ &nbsp;&nbsp; ☐ อื่น ๆ' ?></div>
                <div class="comment"> </div>
            <?php endif; ?>
            <div class="sign" style="margin-top:6px">
                <div class="s">(ลงชื่อ) <?= $dots(45) ?></div>
                <div>( <?= e($o ? (string)$o['user_name'] : ($s['name'] ?: $dots(40))) ?> )</div>
                <div><?= e($s['title']) ?></div>
                <div><?= $o ? 'วันที่ ' . e(thai_date($o['created_at'], false, true)) : 'วันที่ ' . $dots(30) ?></div>
                <?php if ($o): ?><div class="esign">พิจารณาผ่านระบบ <?= e(system_name()) ?> เมื่อ <?= e(thai_datetime($o['created_at'])) ?></div><?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
