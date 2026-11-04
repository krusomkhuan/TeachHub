<?php
/** เก็บไฟล์บนเครื่องที่ติดตั้งระบบเท่านั้น ไม่มีการเชื่อมต่อ Google Drive */
function storage_legacy_files(): array
{
    static $ready = null;
    if ($ready === null) {
        $ready = (bool)db_value("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drive_files'");
    }
    return $ready ? db_all("SELECT subdir, stored_name, size FROM drive_files WHERE subdir NOT IN ('backups', 'logs') ORDER BY subdir, stored_name") : [];
}

/** ยอมรับเฉพาะชื่อไฟล์เดิมที่นำเข้ามาได้อย่างปลอดภัย */
function storage_legacy_path(array $row): ?string
{
    if (!in_array($row['subdir'], ['lesson_plans', 'pa', 'research', 'supervision', 'advisor', 'home_visit', 'downloads', 'hr', 'avatars', 'system'], true)
        || !preg_match('/^[A-Za-z0-9_-]+\.[a-z0-9]{1,5}$/D', $row['stored_name'])
        || !in_array(strtolower(pathinfo($row['stored_name'], PATHINFO_EXTENSION)), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip'], true)) {
        return null;
    }
    return UPLOAD_PATH . DIRECTORY_SEPARATOR . $row['subdir'] . DIRECTORY_SEPARATOR . $row['stored_name'];
}

/** ไฟล์ที่เคยย้ายออกจากเครื่องและยังไม่ได้รับกลับมาครบ */
function storage_missing_files(): array
{
    $missing = [];
    foreach (storage_legacy_files() as $row) {
        $path = storage_legacy_path($row);
        if ($path !== null) clearstatcache(true, $path);
        if ($path === null || !is_file($path) || !is_readable($path) || (int)filesize($path) !== (int)$row['size']) {
            $missing[] = $row;
        }
    }
    return $missing;
}

/** นำเข้าไฟล์เดิมตามทะเบียน โดยใช้ตัวตรวจไฟล์อัปโหลดของระบบ */
function storage_import_file(array $file): string
{
    $matches = array_values(array_filter(storage_missing_files(), static function ($row) use ($file) {
        return $row['stored_name'] === ($file['name'] ?? '') && (int)$row['size'] === (int)($file['size'] ?? -1);
    }));
    if (count($matches) !== 1 || storage_legacy_path($matches[0]) === null) {
        return 'ชื่อหรือขนาดไฟล์ไม่ตรงกับรายการไฟล์เดิมที่รอนำเข้า';
    }
    $row = $matches[0];
    $dest = storage_legacy_path($row);
    if (file_exists($dest)) return 'มีไฟล์ชื่อนี้บนเครื่องแล้ว กรุณาตรวจไฟล์เดิมก่อนนำเข้า';
    $ext = strtolower(pathinfo($row['stored_name'], PATHINFO_EXTENSION));
    $upload = handle_upload($file, $row['subdir'], [$ext], max(1, (int)$row['size']));
    if (!$upload['ok']) return $upload['error'];
    $source = UPLOAD_PATH . DIRECTORY_SEPARATOR . $upload['subdir'] . DIRECTORY_SEPARATOR . $upload['stored_name'];
    $in = @fopen($source, 'rb');
    $out = $in ? @fopen($dest, 'xb') : false;
    if (!$out) {
        if ($in) fclose($in);
        @unlink($source);
        return 'บันทึกไฟล์เดิมไม่ได้ กรุณาตรวจสิทธิ์โฟลเดอร์ uploads/' . $row['subdir'];
    }
    $copied = stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    @unlink($source);
    if ($copied !== (int)$row['size']) {
        @unlink($dest);
        return 'นำเข้าไฟล์ไม่ครบ กรุณาตรวจพื้นที่ว่างแล้วลองใหม่';
    }
    @chmod($dest, 0644);
    log_activity('storage_import', 'นำเข้าไฟล์เดิม ' . $row['subdir'] . '/' . $row['stored_name']);
    return '';
}
