<?php
/**
 * ไฟล์: includes/mailer.php
 * ส่งอีเมลผ่าน SMTP (ไม่ต้องติดตั้งไลบรารีเพิ่ม) — ตั้งค่าที่ ตั้งค่าระบบ → การส่งอีเมล
 * ค่าที่ใช้: mail_enabled, smtp_host, smtp_port, smtp_secure, smtp_user, smtp_pass, mail_from, mail_from_name
 */

/** เปิดใช้การส่งอีเมลและตั้งค่าครบหรือไม่ */
function mail_enabled(): bool
{
    return setting('mail_enabled', '0') === '1'
        && trim((string)setting('smtp_host')) !== ''
        && filter_var((string)setting('mail_from'), FILTER_VALIDATE_EMAIL) !== false;
}

/** ส่งอีเมลข้อความล้วนด้วยค่าที่ตั้งไว้ในระบบ คืน ['ok' => bool, 'error' => string] */
function send_mail(string $to, string $subject, string $body): array
{
    if (!mail_enabled()) {
        return ['ok' => false, 'error' => 'ยังไม่ได้ตั้งค่าการส่งอีเมล'];
    }
    return smtp_send([
        'host'      => trim((string)setting('smtp_host')),
        'port'      => (int)setting('smtp_port', '587'),
        'secure'    => (string)setting('smtp_secure', 'tls'),
        'user'      => (string)setting('smtp_user'),
        'pass'      => (string)setting('smtp_pass'),
        'from'      => (string)setting('mail_from'),
        'from_name' => (string)setting('mail_from_name'),
    ], $to, $subject, $body);
}

/** สร้างข้อความอีเมล (หัวข้อและเนื้อหาเข้ารหัส UTF-8 + ตัดอักขระขึ้นบรรทัดใหม่ กันการแทรก header) */
function mail_build_message(array $cfg, string $to, string $subject, string $body): string
{
    $clean = fn(string $s) => trim(str_replace(["\r", "\n"], ' ', $s));
    $encode = fn(string $s) => '=?UTF-8?B?' . base64_encode($clean($s)) . '?=';
    $from = $clean($cfg['from']);
    $fromName = $clean((string)($cfg['from_name'] ?? ''));
    $domain = substr((string)strrchr($from, '@'), 1) ?: 'localhost';

    $headers = [
        'Date: ' . date('r'),
        'From: ' . ($fromName !== '' ? $encode($fromName) . ' <' . $from . '>' : $from),
        'To: ' . $clean($to),
        'Subject: ' . $encode($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    return implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
}

/** อ่านคำตอบจากเซิร์ฟเวอร์ SMTP (รองรับหลายบรรทัด) คืน [รหัส, ข้อความ] */
function smtp_read($fp): array
{
    $text = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $text .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    return [(int)substr($text, 0, 3), trim($text)];
}

/** ส่งอีเมลผ่าน SMTP คืน ['ok' => bool, 'error' => string] — ข้อความ error ไม่มีรหัสผ่าน */
function smtp_send(array $cfg, string $to, string $subject, string $body): array
{
    $fail = fn(string $msg) => ['ok' => false, 'error' => $msg];

    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($cfg['from'], FILTER_VALIDATE_EMAIL) === false) {
        return $fail('รูปแบบอีเมลผู้รับหรือผู้ส่งไม่ถูกต้อง');
    }
    $secure = $cfg['secure'] ?? 'tls';
    if ($secure === 'none' && ($cfg['user'] ?? '') !== '') {
        return $fail('ไม่ส่งรหัสผ่าน SMTP ผ่านการเชื่อมต่อที่ไม่เข้ารหัส กรุณาเลือก TLS หรือ SSL');
    }

    $context = stream_context_create(['ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
        'peer_name'        => $cfg['host'],
    ]]);
    $target = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $cfg['host'] . ':' . (int)$cfg['port'];
    $fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        return $fail('เชื่อมต่อเซิร์ฟเวอร์อีเมลไม่ได้: ' . $errstr);
    }
    stream_set_timeout($fp, 15);

    // ส่งคำสั่งแล้วตรวจรหัสตอบกลับ ($label ใช้แสดงแทนคำสั่งใน error)
    $step = function (?string $command, array $expect, string $label) use ($fp): ?string {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        [$code, $text] = smtp_read($fp);
        return in_array($code, $expect, true) ? null : $label . ' ไม่สำเร็จ: ' . ($text !== '' ? $text : 'ไม่มีการตอบกลับ');
    };
    $hostname = 'localhost';

    $error = $step(null, [220], 'เชื่อมต่อ') ?? $step('EHLO ' . $hostname, [250], 'EHLO');
    if ($error === null && $secure === 'tls') {
        $error = $step('STARTTLS', [220], 'STARTTLS');
        if ($error === null) {
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $error = 'เปิดการเข้ารหัส TLS ไม่สำเร็จ';
            } else {
                $error = $step('EHLO ' . $hostname, [250], 'EHLO');
            }
        }
    }
    if ($error === null && ($cfg['user'] ?? '') !== '') {
        $error = $step('AUTH LOGIN', [334], 'เข้าสู่ระบบ SMTP')
            ?? $step(base64_encode($cfg['user']), [334], 'ชื่อผู้ใช้ SMTP')
            ?? $step(base64_encode((string)($cfg['pass'] ?? '')), [235], 'รหัสผ่าน SMTP');
    }
    if ($error === null) {
        $error = $step('MAIL FROM:<' . $cfg['from'] . '>', [250], 'ผู้ส่ง')
            ?? $step('RCPT TO:<' . $to . '>', [250, 251], 'ผู้รับ')
            ?? $step('DATA', [354], 'DATA')
            ?? $step(mail_build_message($cfg, $to, $subject, $body) . "\r\n.", [250], 'ส่งข้อความ');
    }
    fwrite($fp, "QUIT\r\n");
    fclose($fp);

    return $error === null ? ['ok' => true, 'error' => ''] : $fail($error);
}

/** ปิดบังอีเมลบางส่วนสำหรับแสดงบนหน้าเว็บ เช่น somchai@gmail.com → so*****@gmail.com */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false) {
        return '***';
    }
    $local = substr($email, 0, $at);
    $length = mb_strlen($local);
    $keep = $length > 3 ? 2 : 1;
    return mb_substr($local, 0, $keep) . str_repeat('*', max(3, $length - $keep)) . substr($email, $at);
}
