<?php
/** ไฟล์บนเซิร์ฟเวอร์และนำไฟล์เดิมกลับ โดยไม่เรียก Google API */
require_once __DIR__ . '/../config/config.php';
$user = require_role('admin');
$self = 'admin/drive.php';
if (is_post()) {
    csrf_verify();
    if (post('action') === 'import') {
        $files = normalize_multi_files($_FILES['files'] ?? null);
        $done = 0;
        $errors = [];
        foreach ($files as $file) {
            $error = storage_import_file($file);
            if ($error === '') $done++;
            else $errors[] = ($file['name'] ?? '') . ': ' . $error;
        }
        if (!$files) $errors[] = 'กรุณาเลือกไฟล์เดิมที่ดาวน์โหลดมา';
        flash($errors ? 'warning' : 'success', 'นำเข้าแล้ว ' . $done . ' ไฟล์' . ($errors ? ' · ' . implode(' · ', array_slice($errors, 0, 5)) : ''));
    } else {
        flash('warning', 'ระบบเก็บไฟล์บนเซิร์ฟเวอร์เท่านั้น ไม่มีการเชื่อมต่อ Google Drive');
    }
    redirect($self);
}
$missing = storage_missing_files();
$files = backup_upload_files();
$bytes = array_sum(array_map('filesize', $files));
$pageTitle = 'ไฟล์บนเซิร์ฟเวอร์';
$pageIcon = 'bi-hdd';
$breadcrumbs = ['ไฟล์บนเซิร์ฟเวอร์' => null];
require APP_ROOT . '/includes/header.php';
?>
<div class="alert alert-success">ระบบเก็บไฟล์ที่อัปโหลดไว้บนเซิร์ฟเวอร์ใน uploads/ ไม่มีการเชื่อมต่อ Google Drive</div>
<div class="card mb-4"><div class="card-header">ไฟล์และการสำรองข้อมูล</div><div class="card-body">
    <p>ไฟล์บนเซิร์ฟเวอร์ <?= number_format(count($files)) ?> ไฟล์ · <?= e(format_bytes((int)$bytes)) ?></p>
    <p>สำรองเป็น ZIP รวมฐานข้อมูลและไฟล์ทั้งหมด แล้วกดดาวน์โหลดเพื่อเก็บไว้ในคอมพิวเตอร์พีซี</p>
    <a class="btn btn-primary" href="<?= e(url('admin/backup.php')) ?>">สำรอง / ดาวน์โหลดข้อมูล</a>
</div></div>
<?php if ($missing): ?>
<div class="alert alert-warning">ยังมีไฟล์เดิม <?= number_format(count($missing)) ?> ไฟล์ที่ไม่อยู่บนเซิร์ฟเวอร์ ต้องนำกลับมาก่อนจึงจะเปิดไฟล์และสำรองได้ครบ ระบบไม่ได้ลบต้นฉบับที่เก็บไว้ภายนอก</div>
<div class="card mb-4"><div class="card-header">นำไฟล์เดิมกลับเข้าเซิร์ฟเวอร์</div><div class="card-body">
    <p>ดาวน์โหลดไฟล์จากที่เก็บเดิมด้วยตนเอง หากได้ ZIP ให้แตกไฟล์ก่อน แล้วเลือกไฟล์โดยคงชื่อเดิมตามรายการ ระบบตรวจชื่อ ขนาด และชนิดไฟล์ก่อนนำกลับตำแหน่งเดิม นำเข้าได้ทีละชุด</p>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="import">
        <label class="form-label" for="files">เลือกไฟล์เดิมที่ดาวน์โหลดมา</label>
        <input class="form-control mb-3" type="file" name="files[]" id="files" multiple required>
        <button class="btn btn-primary">นำเข้าไฟล์เดิม</button>
    </form>
    <p class="form-text">หรือนำไฟล์กลับผ่าน File Manager/FTP ตามตำแหน่งในรายการ โดยคงชื่อไฟล์เดิม</p>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>ชื่อไฟล์เดิม</th><th>ตำแหน่งบนเซิร์ฟเวอร์</th><th>ขนาด</th></tr></thead>
        <tbody><?php foreach ($missing as $row): ?>
            <tr><td class="text-break"><?= e($row['stored_name']) ?></td><td><?= e('uploads/' . $row['subdir'] . '/') ?></td><td><?= e(format_bytes((int)$row['size'])) ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
</div></div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/footer.php'; ?>
