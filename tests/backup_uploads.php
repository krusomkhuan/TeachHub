<?php
/** Run: C:/xampp/php/php.exe -d extension=zip tests/backup_uploads.php (no real database or Drive). */
define('APP_VERSION', 'test');
define('UPLOAD_PATH', __DIR__ . '/backup_test_' . bin2hex(random_bytes(6)));
mkdir(UPLOAD_PATH);
require_once __DIR__ . '/../includes/backup.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/google_auth.php';

$settings = ['backup_auto' => '1', 'backup_auto_files' => '0', 'backup_auto_last' => '0'];
$remoteFiles = [
    ['subdir' => 'lesson_plans', 'stored_name' => 'remote.pdf', 'drive_id' => 'remote', 'size' => 10],
    ['subdir' => 'hr', 'stored_name' => 'local.pdf', 'drive_id' => 'duplicate', 'size' => 8],
];
function setting($key, $default = '') { global $settings; return $settings[$key] ?? $default; }
function setting_save($key, $value) { global $settings; $settings[$key] = $value; }
function settings_all($refresh = false) { return []; }
function db_value($sql) { return 1; }
function db_all($sql) {
    global $remoteFiles;
    return strpos($sql, 'information_schema') !== false ? [['t' => 'example']] : $remoteFiles;
}
function db() {
    return new class {
        public function query($sql, $mode = null) {
            return new class {
                public function fetch_row() { return ['example', 'CREATE TABLE example (id INT)']; }
                public function fetch_assoc() { return null; }
                public function free() {}
            };
        }
    };
}
function handle_upload($file, $subdir, $ext, $max) {
    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) mkdir($dir);
    $stored = 'validated.pdf';
    copy($file['tmp_name'], $dir . '/' . $stored);
    return ['ok' => true, 'subdir' => $subdir, 'stored_name' => $stored];
}
function log_activity($action, $detail) {}
function check_backup($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
function remove_test_tree($dir) {
    foreach (new FilesystemIterator($dir) as $entry) {
        if ($entry->isDir()) remove_test_tree($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($dir);
}
function backup_temp_files() {
    return array_merge(glob(sys_get_temp_dir() . '/thb*') ?: [], glob(sys_get_temp_dir() . '/thd*') ?: []);
}

try {
    $blockedDrive = false;
    try { google_auth_url('drive'); } catch (RuntimeException $ex) { $blockedDrive = true; }
    check_backup($blockedDrive, 'Legacy Drive OAuth requests are rejected before network access');
    mkdir(UPLOAD_PATH . '/hr');
    mkdir(UPLOAD_PATH . '/logs');
    file_put_contents(UPLOAD_PATH . '/hr/local.pdf', 'local-hr');
    file_put_contents(UPLOAD_PATH . '/logs/skip.log', 'log');
    file_put_contents(backup_dir() . '/skip.zip', 'old backup');
    $beforeTemps = backup_temp_files();
    check_backup(count(storage_missing_files()) === 1, 'Reports legacy files missing on the server');
    $incomplete = backup_create(true);
    check_backup(!$incomplete['ok'], 'Full backup refuses to silently omit legacy attachments');
    check_backup(storage_legacy_path(['subdir' => '../config', 'stored_name' => 'db.php']) === null, 'Rejects unsafe directories and executable files');
    $importSource = UPLOAD_PATH . '/import.pdf';
    file_put_contents($importSource, 'drive-data');
    check_backup(storage_import_file(['name' => 'wrong.pdf', 'size' => 10, 'tmp_name' => $importSource]) !== '', 'Rejects unexpected imported filenames');
    check_backup(storage_import_file(['name' => 'remote.pdf', 'size' => 9, 'tmp_name' => $importSource]) !== '', 'Rejects unexpected imported sizes');
    file_put_contents($importSource, 'bad');
    check_backup(storage_import_file(['name' => 'remote.pdf', 'size' => 10, 'tmp_name' => $importSource]) !== '' && !file_exists(UPLOAD_PATH . '/lesson_plans/remote.pdf'), 'Incomplete imports are removed');
    file_put_contents($importSource, 'drive-data');
    check_backup(storage_import_file(['name' => 'remote.pdf', 'size' => 10, 'tmp_name' => $importSource]) === '', 'Restores legacy attachment under its original stored filename');
    check_backup(storage_import_file(['name' => 'remote.pdf', 'size' => 10, 'tmp_name' => $importSource]) !== '', 'Cannot overwrite an existing imported attachment');
    unlink($importSource);
    check_backup(!storage_missing_files(), 'All legacy files are available locally after import');
    $result = backup_create(true);
    check_backup($result['ok'], 'Full backup succeeds using server files without any Drive connection');
    $path = backup_path($result['name']);
    $zip = new ZipArchive();
    $zip->open($path);
    check_backup($zip->getFromName('uploads/hr/local.pdf') === 'local-hr', 'HR attachment bytes are preserved');
    check_backup($zip->getFromName('uploads/lesson_plans/remote.pdf') === 'drive-data', 'Imported attachment bytes are in ZIP');
    check_backup($zip->locateName('uploads/logs/skip.log') === false && $zip->locateName('uploads/backups/skip.zip') === false, 'Logs and existing backups are excluded');
    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    check_backup($manifest['storage'] === 'local' && $manifest['files_bytes'] === 18, 'Manifest records local storage and complete attachment bytes');
    $zip->close();
    check_backup(!array_diff(backup_temp_files(), $beforeTemps), 'Temporary files are removed after success');

    $auto = backup_auto_maybe();
    check_backup($auto !== '' && backup_manifest(backup_path($auto))['with_files'] === true, 'Automatic backup includes files even with old disabled setting');

    unlink(UPLOAD_PATH . '/lesson_plans/remote.pdf');
    $beforeZips = glob(backup_dir() . '/*.zip');
    $failed = backup_create(true);
    check_backup(!$failed['ok'], 'Missing local attachment fails the full backup');
    check_backup(glob(backup_dir() . '/*.zip') === $beforeZips, 'Incomplete ZIP is removed');
    check_backup(!array_diff(backup_temp_files(), $beforeTemps), 'Temporary files are removed after failure');
    setting_save('backup_auto_last', '0');
    check_backup(backup_auto_maybe() === '' && setting('backup_auto_last') === '0', 'Failed automatic backup can retry');

    file_put_contents(UPLOAD_PATH . '/lesson_plans/remote.pdf', 'drive-data');
    file_put_contents(UPLOAD_PATH . '/hr/local.pdf', 'changed!');
    $restored = backup_restore($path);
    check_backup($restored['ok'] && file_get_contents(UPLOAD_PATH . '/hr/local.pdf') === 'local-hr', 'Restore recovers local attachment bytes');
    check_backup(file_get_contents(UPLOAD_PATH . '/lesson_plans/remote.pdf') === 'drive-data', 'Restore recovers imported attachment bytes on the server');
} finally {
    remove_test_tree(UPLOAD_PATH);
}
