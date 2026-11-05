<?php
/** Run: C:/xampp/php/php.exe tests/db_reconnect.php (reads only; drops its own connection via wait_timeout). */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function check(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: $message\n";
}

/** Make the server close this connection, the same failure as "MySQL server has gone away". */
function drop_connection(): void
{
    db()->query('SET SESSION wait_timeout = 1');
    sleep(3);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$before = (int)db_value('SELECT CONNECTION_ID()');
drop_connection();
$after = (int)db_value('SELECT CONNECTION_ID()');
check($after > 0 && $after !== $before, 'GET reconnects and the query succeeds');
check(db_value('SELECT @@session.time_zone') === '+07:00', 'reconnect restores the time zone');
check(db_value('SELECT @@character_set_client') === 'utf8mb4', 'reconnect restores the charset');

drop_connection();
$_SERVER['REQUEST_METHOD'] = 'POST';
$thrown = null;
try {
    db_value('SELECT 1');
} catch (mysqli_sql_exception $ex) {
    $thrown = $ex;
}
check($thrown !== null && db_connection_lost($thrown), 'POST does not reconnect, so a transaction cannot be half saved');

check(!db_connection_lost(new mysqli_sql_exception('Syntax error', 1064)), 'other SQL errors are not treated as a lost connection');
