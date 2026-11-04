<?php
/**
 * ไฟล์: auth/google_callback.php
 * Google ส่งผู้ใช้กลับมาที่นี่หลังเลือกบัญชี (Redirect URI ที่ต้องลงทะเบียนใน Google Cloud Console)
 */
require_once __DIR__ . '/../config/config.php';

// ไม่รับ callback สำหรับที่เก็บไฟล์ Google Drive เดิม
if (($_SESSION['google_oauth']['mode'] ?? '') === 'drive') {
    unset($_SESSION['google_oauth']);
    flash('warning', 'ระบบเก็บไฟล์บนเซิร์ฟเวอร์แล้ว ไม่มีการเชื่อมต่อ Google Drive');
    redirect('admin/drive.php');
}
if (!google_enabled()) {
    redirect('auth/login.php');
}
check_session_timeout();
$res = google_handle_callback($_GET);
$mode = $res['mode'];

// ---------- เชื่อมบัญชี Google จากหน้าโปรไฟล์ ----------
if ($mode === 'link') {
    $user = current_user();
    if (!$user) {
        redirect('auth/login.php');
    }
    if (!$res['ok']) {
        flash('error', $res['error']);
        redirect('auth/profile.php');
    }
    $p = $res['profile'];
    $owner = db_value('SELECT id FROM users WHERE google_sub = ?', 's', [$p['sub']]);
    if ($owner && (int)$owner !== (int)$user['id']) {
        flash('error', 'บัญชี Google นี้เชื่อมกับผู้ใช้อื่นแล้ว');
    } else {
        db_exec('UPDATE users SET google_sub = ? WHERE id = ?', 'si', [$p['sub'], (int)$user['id']]);
        log_activity('google_link', 'เชื่อมบัญชี Google ' . $p['email']);
        flash('success', 'เชื่อมบัญชี Google (' . $p['email'] . ') เรียบร้อยแล้ว ครั้งต่อไปกด “เข้าสู่ระบบด้วย Google” ได้ทันที');
    }
    redirect('auth/profile.php');
}

// ---------- เข้าสู่ระบบ / สมัครสมาชิก ----------
if (!$res['ok']) {
    flash('error', $res['error']);
    redirect('auth/login.php');
}
$p = $res['profile'];

$user = db_one('SELECT * FROM users WHERE google_sub = ? LIMIT 1', 's', [$p['sub']]);
if (!$user) {
    // ไม่เชื่อมอัตโนมัติจากอีเมลที่ตรงกัน: อีเมลตอนสมัครไม่ได้ผ่านการยืนยัน ผู้อื่นอาจสมัครด้วยอีเมลนี้และถือรหัสผ่านอยู่
    // ถ้าเชื่อมให้ เจ้าของอีเมลตัวจริงจะเข้าบัญชีเดียวกับผู้ที่รู้รหัสนั้น จึงต้องเข้าด้วยรหัสผ่านแล้วเชื่อมที่หน้าโปรไฟล์
    $emailOwner = db_one('SELECT id, google_sub FROM users WHERE email = ? LIMIT 1', 's', [$p['email']]);
    if ($emailOwner) {
        log_activity('google_link_blocked', 'ไม่เชื่อม Google อัตโนมัติจากอีเมล ' . $p['email'], (int)$emailOwner['id']);
        flash('error', empty($emailOwner['google_sub'])
            ? 'อีเมล ' . $p['email'] . ' มีบัญชีอยู่แล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่าน แล้วเชื่อมบัญชี Google ที่หน้าโปรไฟล์ หากท่านไม่ได้สมัครบัญชีนี้ กรุณาติดต่องานพัฒนาหลักสูตรฯ'
            : 'อีเมลนี้เชื่อมกับบัญชี Google อื่นแล้ว กรุณาติดต่องานพัฒนาหลักสูตรฯ');
        redirect('auth/login.php');
    }
}

if ($user) {
    if ($user['status'] === 'pending') {
        flash('warning', 'บัญชีของท่านอยู่ระหว่างรออนุมัติจากผู้ดูแลระบบ');
        redirect('auth/login.php');
    }
    if ($user['status'] === 'suspended') {
        flash('error', 'บัญชีของท่านถูกระงับการใช้งาน กรุณาติดต่องานพัฒนาหลักสูตรฯ');
        redirect('auth/login.php');
    }
    login_user($user);
    log_activity('login_google', 'เข้าสู่ระบบด้วย Google ' . $p['email'], (int)$user['id']);
    if ((int)$user['must_change_password'] === 1) {
        redirect('auth/change_password.php');
    }
    flash('success', 'ยินดีต้อนรับ ' . full_name($user));
    header('Location: ' . safe_next($res['next'], dashboard_url($user['role'])));
    exit;
}

// ยังไม่มีบัญชี → ไปกรอกข้อมูลสมัครสมาชิก
if (setting('allow_register', '1') !== '1') {
    flash('warning', 'ไม่พบบัญชีที่ใช้อีเมล ' . $p['email'] . ' และขณะนี้ระบบปิดรับสมัครสมาชิก กรุณาติดต่องานพัฒนาหลักสูตรฯ');
    redirect('auth/login.php');
}
$_SESSION['google_signup'] = $p + ['at' => time()];
flash('info', 'ยืนยันบัญชี Google แล้ว กรุณากรอกข้อมูลเพิ่มเติมเพื่อสมัครสมาชิก');
redirect('auth/register.php');
