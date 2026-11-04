<?php
/**
 * ไฟล์: auth/forgot_password.php
 * ลืมรหัสผ่าน — ตรวจยืนยันตัวตนด้วย ชื่อผู้ใช้/อีเมล + เบอร์โทรศัพท์ที่ลงทะเบียนไว้
 * แล้วส่งรหัสผ่านชั่วคราวไปที่อีเมลของผู้ใช้ และแจ้งว่าส่งไปที่อีเมลใด (ปิดบังบางส่วน)
 * ถ้ายังไม่ตั้งค่าการส่งอีเมลหรือส่งไม่สำเร็จ จะส่งคำขอให้ admin รีเซ็ตที่หน้าจัดการผู้ใช้แทน
 */
require_once __DIR__ . '/../config/config.php';

redirect_if_logged_in();

$done = false;
$error = '';
$sentTo = ''; // อีเมลปลายทางแบบปิดบังบางส่วน (เมื่อส่งรหัสชั่วคราวสำเร็จ)

if (is_post()) {
    csrf_verify();

    $login = post('username');
    $phone = preg_replace('/[\s-]/', '', post('phone'));
    $note = mb_substr(post('note'), 0, 250);
    $ip = client_ip();

    // จำกัดการส่งคำขอ: ไม่เกิน 10 ครั้งต่อ IP ใน 1 ชั่วโมง นับรวมครั้งที่ข้อมูลไม่ตรง กันการสุ่มเบอร์โทร
    // (ครูในวิทยาลัยอาจใช้ IP เดียวกัน จึงจำกัดต่อบัญชีแยกอีกชั้นด้านล่าง)
    $recent = (int)db_value(
        "SELECT COUNT(*) FROM activity_logs
         WHERE action IN ('forgot_password', 'forgot_password_email', 'forgot_password_mail_failed')
           AND ip_address = ? AND created_at > (NOW() - INTERVAL 1 HOUR)",
        's',
        [$ip]
    );

    if ($login === '' || $phone === '') {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    } elseif ($recent >= 10) {
        $error = 'ส่งคำขอบ่อยเกินไป กรุณาลองใหม่ภายหลัง หรือติดต่องานพัฒนาหลักสูตรฯ โดยตรง';
    } else {
        $user = db_one(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND phone <> '' AND phone = ? LIMIT 1",
            'sss',
            [$login, mb_strtolower($login), $phone]
        );

        // ---------- ส่งรหัสผ่านชั่วคราวทางอีเมล ----------
        if ($user && $user['status'] === 'active' && mail_enabled()) {
            // ไม่เกิน 5 ครั้งต่อบัญชีใน 1 ชั่วโมง กันการส่งอีเมลรบกวนเจ้าของบัญชี
            $sentRecent = (int)db_value(
                "SELECT COUNT(*) FROM activity_logs
                 WHERE action = 'forgot_password_email' AND user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)",
                'i',
                [(int)$user['id']]
            );
            if ($sentRecent >= 5) {
                $error = 'ขอรหัสผ่านใหม่บ่อยเกินไป กรุณาตรวจสอบอีเมล ' . mask_email($user['email']) . ' หรือลองใหม่ภายหลัง';
            } else {
                $temp = random_password(10);
                $mailBody = 'เรียน ' . full_name($user) . "\n\n"
                    . 'มีการขอรหัสผ่านใหม่สำหรับบัญชี ' . $user['username'] . ' ในระบบ ' . setting('system_name', 'TeachHub') . "\n\n"
                    . 'รหัสผ่านชั่วคราวของท่านคือ: ' . $temp . "\n\n"
                    . 'เข้าสู่ระบบที่ ' . site_base_url() . "/auth/login.php\n"
                    . "ระบบจะให้ตั้งรหัสผ่านใหม่ทันทีเมื่อเข้าสู่ระบบ\n\n"
                    . 'หากท่านไม่ได้เป็นผู้ขอ กรุณาติดต่องานพัฒนาหลักสูตรฯ';
                $sent = send_mail($user['email'], 'รหัสผ่านชั่วคราว ' . setting('system_name', 'TeachHub'), $mailBody);

                if ($sent['ok']) {
                    // เปลี่ยนรหัสหลังส่งสำเร็จเท่านั้น รหัสเดิมจึงไม่หายถ้าอีเมลส่งไม่ออก
                    db_exec(
                        'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?',
                        'si',
                        [password_hash($temp, PASSWORD_DEFAULT), (int)$user['id']]
                    );
                    db_exec(
                        "UPDATE password_reset_requests SET status = 'done', handled_at = NOW() WHERE user_id = ? AND status = 'pending'",
                        'i',
                        [(int)$user['id']]
                    );
                    log_activity('forgot_password_email', 'ส่งรหัสผ่านชั่วคราวไปที่ ' . mask_email($user['email']), (int)$user['id']);
                    $sentTo = mask_email($user['email']);
                    $done = true;
                } else {
                    error_log('Password reset mail failed: ' . $sent['error']);
                    // ส่งไม่สำเร็จ → ทำงานต่อด้านล่างเพื่อส่งคำขอให้ admin แทน
                    log_activity('forgot_password_mail_failed', mb_substr($sent['error'], 0, 200), (int)$user['id']);
                }
            }
        }

        if ($error !== '' || $sentTo !== '') {
            // ส่งอีเมลแล้ว หรือถูกจำกัดจำนวนครั้ง ไม่ต้องสร้างคำขอให้ admin
        } elseif (!$user && mail_enabled()) {
            log_activity('forgot_password', 'คำขอรีเซ็ตไม่พบผู้ใช้: ' . mb_substr($login, 0, 50));
            $error = 'ชื่อผู้ใช้หรือเบอร์โทรศัพท์ไม่ตรงกับข้อมูลที่ลงทะเบียนไว้';
        } elseif ($user) {
            // ไม่สร้างคำขอซ้ำถ้ายังมีคำขอที่รอดำเนินการอยู่
            $pending = db_value(
                'SELECT id FROM password_reset_requests WHERE user_id = ? AND status = \'pending\' LIMIT 1',
                'i',
                [(int)$user['id']]
            );
            if (!$pending) {
                db_insert(
                    'INSERT INTO password_reset_requests (user_id, request_note, requested_ip) VALUES (?, ?, ?)',
                    'iss',
                    [(int)$user['id'], $note, $ip]
                );
                log_activity('forgot_password', 'ส่งคำขอรีเซ็ตรหัสผ่าน', (int)$user['id']);
            }
            $done = true;
        } else {
            log_activity('forgot_password', 'คำขอรีเซ็ตไม่พบผู้ใช้: ' . mb_substr($login, 0, 50));
            // ยังไม่เปิดส่งอีเมล: แสดงข้อความเหมือนกันทุกกรณี ไม่เปิดเผยว่ามีบัญชีหรือไม่
            $done = true;
        }
    }
}

$pageTitle = 'ลืมรหัสผ่าน';
require APP_ROOT . '/includes/auth_header.php';
?>
<h3 class="auth-title"><i class="bi bi-key me-1"></i> ลืมรหัสผ่าน</h3>

<?php if ($sentTo !== ''): ?>
    <div class="text-center py-3">
        <i class="bi bi-envelope-check text-success" style="font-size:3.5rem"></i>
        <h5 class="mt-3">ส่งรหัสผ่านชั่วคราวแล้ว</h5>
        <p class="text-muted mb-2">กรุณาตรวจสอบรหัสผ่านที่อีเมล</p>
        <p class="fs-5 fw-bold mb-3"><?= e($sentTo) ?></p>
        <p class="small text-muted mb-4">หากไม่พบ กรุณาตรวจในโฟลเดอร์จดหมายขยะ (Spam)
            เมื่อเข้าสู่ระบบด้วยรหัสชั่วคราวแล้ว ระบบจะให้ตั้งรหัสผ่านใหม่ทันที</p>
        <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-accent px-4">ไปหน้าเข้าสู่ระบบ</a>
    </div>
<?php elseif ($done): ?>
    <div class="text-center py-3">
        <i class="bi bi-envelope-check text-success" style="font-size:3.5rem"></i>
        <h5 class="mt-3">ส่งคำขอเรียบร้อยแล้ว</h5>
        <p class="text-muted">หากข้อมูลถูกต้อง ผู้ดูแลระบบจะรีเซ็ตรหัสผ่านและแจ้งรหัสผ่านชั่วคราวให้ท่านทราบ
            เมื่อเข้าสู่ระบบด้วยรหัสชั่วคราวแล้ว ระบบจะให้ตั้งรหัสผ่านใหม่ทันที</p>
        <p class="small text-muted mb-4">ติดต่อผู้ดูแลระบบ</p>
        <a href="<?= e(url('auth/login.php')) ?>" class="btn btn-accent px-4">กลับหน้าเข้าสู่ระบบ</a>
    </div>
<?php else: ?>
    <p class="auth-subtitle"><?= mail_enabled()
        ? 'กรอกข้อมูลเพื่อรับรหัสผ่านชั่วคราวทางอีเมลที่ลงทะเบียนไว้'
        : 'กรอกข้อมูลเพื่อส่งคำขอให้ผู้ดูแลระบบรีเซ็ตรหัสผ่าน' ?></p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('auth/forgot_password.php')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="username" class="form-label">ชื่อผู้ใช้ หรือ อีเมล</label>
            <input type="text" class="form-control" id="username" name="username" value="<?= old('username') ?>" required autofocus>
        </div>
        <div class="mb-3">
            <label for="phone" class="form-label">เบอร์โทรศัพท์ที่ลงทะเบียนไว้</label>
            <input type="tel" class="form-control" id="phone" name="phone" value="<?= old('phone') ?>" maxlength="12" placeholder="08xxxxxxxx" required>
        </div>
        <?php if (!mail_enabled()): ?>
        <div class="mb-3">
            <label for="note" class="form-label">ช่องทางติดต่อกลับ / หมายเหตุ <span class="text-muted small">(ไม่บังคับ)</span></label>
            <input type="text" class="form-control" id="note" name="note" value="<?= old('note') ?>" maxlength="250"
                   placeholder="เช่น โทร 08x-xxx-xxxx หรือ LINE ID">
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-accent w-100 py-2" data-loading-text="กำลังส่ง...">
            <i class="bi bi-send me-1"></i> <?= mail_enabled() ? 'ส่งรหัสผ่านชั่วคราวทางอีเมล' : 'ส่งคำขอรีเซ็ตรหัสผ่าน' ?>
        </button>
    </form>
    <p class="text-center mt-4 mb-0"><a href="<?= e(url('auth/login.php')) ?>"><i class="bi bi-arrow-left"></i> กลับหน้าเข้าสู่ระบบ</a></p>
<?php endif; ?>

<?php require APP_ROOT . '/includes/auth_footer.php'; ?>
