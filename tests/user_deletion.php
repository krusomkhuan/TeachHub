<?php
/** Run: C:/xampp/php/php.exe tests/user_deletion.php (no database or files deleted). */
require_once __DIR__ . '/../includes/admin_perm.php';

class DeleteTestRedirect extends RuntimeException {}
function is_post(): bool { return true; }
function post($key) { return $_POST[$key] ?? ''; }
function csrf_verify(): void {
    if (!$GLOBALS['csrfValid']) { throw new DeleteTestRedirect('csrf'); }
}
function db_one($sql, $types, $params) { return $GLOBALS['fixture']; }
function db_value($sql, $types = '', $params = []) {
    return str_contains($sql, 'information_schema') ? 1 : $GLOBALS['adminCount'];
}
function db_exec($sql, $types, $params): int {
    $GLOBALS['writes'][] = [$sql, $params];
    if ($GLOBALS['dbError']) { throw new mysqli_sql_exception('Test constraint', $GLOBALS['dbError']); }
    return $GLOBALS['affected'];
}
function full_name($user): string { return $user['username']; }
function flash($type, $message): void { $GLOBALS['flashes'][] = [$type, $message]; }
function redirect($path): void { throw new DeleteTestRedirect($path); }
function delete_upload($dir, $file): void { $GLOBALS['removed'][] = [$dir, $file]; }
function log_activity($action, $detail): void { $GLOBALS['logs'][] = $action; }

// Execute the actual POST handler with isolated database and session doubles.
$source = file_get_contents(__DIR__ . '/../admin/users.php');
$start = strpos($source, 'if (is_post())');
$handler = substr($source, $start, strpos($source, '$fStatus =') - $start);
$base = ['id' => 2, 'username' => 'test-user', 'role' => 'teacher', 'status' => 'active', 'avatar' => 'test.png'];
$cases = [
    ['Active account', [], false, 1, true, 0, 1, true],
    ['Suspended account', ['status' => 'suspended'], false, 1, true, 0, 1, true],
    ['Pending account', ['status' => 'pending'], false, 1, true, 0, 1, true],
    ['Own account blocked', ['id' => 1], false, 1, true, 0, 1, false],
    ['Last full admin blocked', ['role' => 'admin'], false, 0, true, 0, 1, false],
    ['Another full admin remains', ['role' => 'admin'], false, 1, true, 0, 1, true],
    ['Limited admin cannot delete admin', ['role' => 'admin'], true, 1, true, 0, 1, false],
    ['Limited admin can delete teacher', [], true, 1, true, 0, 1, true],
    ['CSRF rejected', [], false, 1, false, 0, 1, false],
    ['Reference prevents deletion', [], false, 1, true, 1451, 1, false],
    ['Already deleted', [], false, 1, true, 0, 0, false],
];
foreach ($cases as [$label, $changes, $limitedEditor, $adminCount, $csrfValid, $dbError, $affected, $success]) {
    $fixture = array_replace($base, $changes);
    $myId = 1;
    $_POST = ['action' => 'delete', 'user_id' => $fixture['id']];
    $writes = $flashes = $removed = $logs = [];
    try { eval($handler); } catch (DeleteTestRedirect $ex) {}
    $didDelete = in_array('user_delete', $logs, true);
    if ($didDelete !== $success || (bool)$removed !== $success) {
        throw new RuntimeException('Failed: ' . $label);
    }
    $blocked = !$csrfValid || $fixture['id'] === $myId
        || ($fixture['role'] === 'admin' && ($limitedEditor || $adminCount === 0));
    if ($blocked && $writes) { throw new RuntimeException('Unauthorized write: ' . $label); }
    if ($success && ($writes !== [['DELETE FROM users WHERE id = ?', [$fixture['id']]]]
        || $flashes[0][0] !== 'success')) {
        throw new RuntimeException('Wrong deletion: ' . $label);
    }
    if (!$success && $csrfValid && ($flashes[0][0] ?? '') !== 'error') {
        throw new RuntimeException('Missing error: ' . $label);
    }
    echo "PASS: $label\n";
}
