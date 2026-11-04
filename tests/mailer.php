<?php
/** Run: C:/xampp/php/php.exe tests/mailer.php (no database, uses a local fake SMTP server). */
define('APP_DEBUG', false);

// Child process: minimal SMTP server that records what it receives, then exits after QUIT.
if (($argv[1] ?? '') === 'server') {
    $server = stream_socket_server('tcp://127.0.0.1:' . (int)$argv[2]);
    echo "ready\n";
    $conn = stream_socket_accept($server, 15);
    $log = '';
    $inData = false;
    fwrite($conn, "220 test\r\n");
    while (($line = fgets($conn)) !== false) {
        $log .= $line;
        if ($inData) {
            if (rtrim($line, "\r\n") === '.') {
                $inData = false;
                fwrite($conn, "250 queued\r\n");
            }
            continue;
        }
        if (stripos($line, 'EHLO') === 0) {
            fwrite($conn, "250-test\r\n250 AUTH LOGIN\r\n");
        } elseif (stripos($line, 'DATA') === 0) {
            $inData = true;
            fwrite($conn, "354 go\r\n");
        } elseif (stripos($line, 'RCPT') === 0 && strpos($line, 'reject') !== false) {
            fwrite($conn, "550 no such user\r\n");
        } elseif (stripos($line, 'QUIT') === 0) {
            fwrite($conn, "221 bye\r\n");
            break;
        } else {
            fwrite($conn, "250 ok\r\n");
        }
    }
    file_put_contents($argv[3], $log);
    exit(0);
}

require_once __DIR__ . '/../includes/mailer.php';

function check_mail(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}

/** Run one send against a fresh fake server and return [result, what the server received]. */
function send_via_fake_server(string $to, string $subject, string $body): array
{
    $port = random_int(20000, 40000);
    $logFile = tempnam(sys_get_temp_dir(), 'smtp');
    $proc = proc_open([PHP_BINARY, __FILE__, 'server', (string)$port, $logFile], [1 => ['pipe', 'w']], $pipes);
    fgets($pipes[1]); // wait for "ready"
    $result = smtp_send([
        'host' => '127.0.0.1', 'port' => $port, 'secure' => 'none', 'user' => '', 'pass' => '',
        'from' => 'noreply@example.test', 'from_name' => 'ระบบทดสอบ',
    ], $to, $subject, $body);
    fclose($pipes[1]);
    proc_close($proc);
    $log = (string)file_get_contents($logFile);
    unlink($logFile);
    return [$result, $log];
}

// Delivery and Thai encoding
[$result, $log] = send_via_fake_server('teacher@example.test', 'รหัสผ่านชั่วคราว', "รหัสผ่านชั่วคราว: Ab12cd34\n");
check_mail($result['ok'], 'Send through SMTP');
check_mail(strpos($log, 'RCPT TO:<teacher@example.test>') !== false, 'Send to the registered address');
check_mail(strpos($log, 'Subject: =?UTF-8?B?' . base64_encode('รหัสผ่านชั่วคราว') . '?=') !== false, 'Encode Thai subject');
check_mail(strpos($log, base64_encode("รหัสผ่านชั่วคราว: Ab12cd34\n")) !== false || strpos(str_replace("\r\n", '', $log), base64_encode("รหัสผ่านชั่วคราว: Ab12cd34\n")) !== false, 'Encode Thai body');

// Server rejection is reported instead of counted as sent
[$result] = send_via_fake_server('reject@example.test', 'x', 'x');
check_mail(!$result['ok'] && strpos($result['error'], '550') !== false, 'Report a rejected recipient');

// Header injection cannot add headers
$message = mail_build_message(['from' => 'a@example.test', 'from_name' => "x\r\nBcc: evil@example.test"], 'b@example.test', "y\r\nBcc: evil@example.test", 'z');
check_mail(stripos($message, "\r\nBcc:") === false, 'Block header injection');

// Credentials are never sent over an unencrypted connection, invalid addresses are refused
$plain = smtp_send(['host' => '127.0.0.1', 'port' => 1, 'secure' => 'none', 'user' => 'u', 'pass' => 'p', 'from' => 'a@example.test'], 'b@example.test', 'x', 'x');
check_mail(!$plain['ok'] && strpos($plain['error'], 'TLS') !== false, 'Refuse credentials without encryption');
$bad = smtp_send(['host' => '127.0.0.1', 'port' => 1, 'secure' => 'none', 'user' => '', 'from' => 'a@example.test'], "b@example.test\r\nRCPT TO:<c@example.test>", 'x', 'x');
check_mail(!$bad['ok'], 'Refuse an invalid recipient');

// Masked address still tells the user which mailbox to check
check_mail(mask_email('somchai@gmail.com') === 'so*****@gmail.com', 'Mask a long address');
check_mail(mask_email('ab@pticec.ac.th') === 'a***@pticec.ac.th', 'Mask a short address');
