<?php
/** Run: C:/xampp/php/php.exe tests/login_lockout.php (uses only a temporary table). */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// A connection-local temporary table shadows the real logs; no user data is changed.
db()->query('CREATE TEMPORARY TABLE activity_logs (
    user_id INT NULL, action VARCHAR(100), detail TEXT, ip_address VARCHAR(45), created_at DATETIME
)');

function check_lock(bool $expected, string $ip, ?int $userId, string $login, string $message): void
{
    if (login_locked($ip, $userId, $login) !== $expected) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

function add_failure(?int $userId, string $login, string $ip, int $minutesAgo = 0): void
{
    db_exec('INSERT INTO activity_logs (user_id, action, detail, ip_address, created_at)
        VALUES (?, ?, ?, ?, NOW() - INTERVAL ? MINUTE)', 'isssi',
        [$userId, 'login_failed', $userId === null ? unknown_login_detail($login) : 'ชื่อผู้ใช้: ' . $login,
            $ip, $minutesAgo]);
}

$ip = '192.0.2.1';
for ($i = 0; $i < 4; $i++) {
    add_failure(1, $i % 2 ? 'admin@example.test' : 'admin', $ip);
}
check_lock(false, $ip, 1, 'admin', 'Four failures still allow another attempt');
add_failure(1, 'admin', $ip);
check_lock(true, $ip, 1, 'admin', 'Five failures lock the account and IP');
check_lock(true, $ip, 1, 'admin@example.test', 'Email and username share the account counter');
check_lock(false, $ip, 2, 'teacher', 'Another account on the same IP remains available');
check_lock(false, '192.0.2.2', 1, 'admin', 'The same account on another IP has its own counter');

for ($i = 0; $i < 5; $i++) {
    add_failure(3, 'expired', $ip, 6);
    add_failure(null, ' MissingUser ', $ip);
    add_failure(null, str_repeat('a', 50) . 'one', $ip);
}
check_lock(false, $ip, 3, 'expired', 'Failures older than 5 minutes expire');
check_lock(true, $ip, null, 'missinguser', 'Unknown names count consistently across case and whitespace');
check_lock(false, $ip, null, 'another-missing-user', 'Unknown names have separate counters');
check_lock(false, $ip, 4, 'real-user', 'Unknown names do not block existing accounts');
check_lock(false, '192.0.2.2', null, 'missinguser', 'Unknown names are also separated by IP');
check_lock(false, $ip, null, str_repeat('a', 50) . 'two', 'Long names with the same prefix remain separate');

db()->query('DROP TEMPORARY TABLE activity_logs');
