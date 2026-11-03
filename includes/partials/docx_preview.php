<?php
/** Included by file.php only after authenticating and authorizing the file. */
if (!isset($file, $user) || !defined('UPLOAD_PATH')) {
    http_response_code(404);
    exit;
}
$subdir = preg_replace('/[^a-z0-9_]/', '', strtolower($file['file_dir']));
$path = UPLOAD_PATH . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . basename($file['stored_name']);
if (!is_file($path)) {
    abort_page(404, 'ไม่พบไฟล์บนเครื่องที่ติดตั้งระบบ');
}
if (filesize($path) > 40 * 1024 * 1024) {
    abort_page(413, 'ไฟล์ใหญ่เกินกว่าจะแสดงตัวอย่างได้', 'กรุณาดาวน์โหลดแล้วเปิดด้วยโปรแกรมในเครื่อง');
}
$nonce = base64_encode(random_bytes(18));
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
// Isolate document markup, block external resources and disable embedded HTML parts.
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-$nonce'; style-src 'unsafe-inline'; img-src data: blob:; font-src data: blob:; connect-src 'none'; frame-src 'none'; base-uri 'none'; form-action 'none'; sandbox allow-scripts");
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($file['original_name']) ?></title>
    <style>
        body { margin: 0; background: #e9edf3; font-family: Tahoma, sans-serif; }
        #status { padding: 16px; text-align: center; color: #495566; }
        #document { overflow: auto; }
        .docx-wrapper { padding: 12px !important; }
        .docx-wrapper > section.docx { margin-bottom: 12px; }
        .notice { padding: 8px 12px; font-size: 12px; background: #fff; color: #596273; }
    </style>
</head>
<body>
    <div class="notice">ตัวอย่าง DOCX · การจัดหน้าอาจต่างจาก Word หากต้องการต้นฉบับให้กดดาวน์โหลด</div>
    <div id="status" role="status">กำลังเปิดไฟล์ DOCX…</div>
    <div id="document"></div>
    <script nonce="<?= e($nonce) ?>" src="<?= e(asset('vendor/docx-preview/jszip.min.js')) ?>"></script>
    <script nonce="<?= e($nonce) ?>" src="<?= e(asset('vendor/docx-preview/docx-preview.min.js')) ?>"></script>
    <script nonce="<?= e($nonce) ?>">
    (async function () {
        var status = document.getElementById('status');
        try {
            var raw = atob(<?= json_encode(base64_encode(file_get_contents($path))) ?>);
            var bytes = Uint8Array.from(raw, function (c) { return c.charCodeAt(0); });
            await docx.renderAsync(bytes, document.getElementById('document'), null, {
                renderAltChunks: false,
                ignoreLastRenderedPageBreak: false,
                useBase64URL: true
            });
            var fitPages = function () {
                document.querySelectorAll('section.docx').forEach(function (page) {
                    page.style.zoom = Math.min(1, (window.innerWidth - 24) / page.offsetWidth);
                });
            };
            fitPages();
            window.addEventListener('resize', fitPages);
            status.hidden = true;
        } catch (error) {
            status.textContent = 'เปิดตัวอย่าง DOCX ไม่สำเร็จ กรุณากดดาวน์โหลดเพื่อเปิดด้วย Word หรือ LibreOffice';
        }
    })();
    </script>
</body>
</html>
