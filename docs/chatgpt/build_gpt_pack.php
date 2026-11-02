<?php
/**
 * ไฟล์: docs/chatgpt/build_gpt_pack.php
 * สร้างไฟล์ความรู้ของ Custom GPT "ผู้ช่วยพัฒนา TeachHub PT" ใหม่จากระบบจริง (รันจาก CLI เท่านั้น):
 *   /c/xampp/php/php.exe docs/chatgpt/build_gpt_pack.php
 * - 02_database_schema.md      จากฐานข้อมูลตาม config/db.php (โครงสร้างเท่านั้น ไม่มีข้อมูลในตาราง)
 * - 04_function_index.md       จาก includes/*.php
 * - teachhub_chatgpt_pack.zip  รวมไฟล์ทั้งหมดสำหรับอัปโหลดขึ้น ChatGPT
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
chdir($root);
define('APP_ROOT', $root);
require $root . '/config/db.php';
$ver = preg_match("/define\('APP_VERSION', '([^']+)'\)/", (string)file_get_contents($root . '/config/config.php'), $vm) ? $vm[1] : '?';
$dir = __DIR__;

// ---------- 02_database_schema.md ----------
$db = db();
$schema = DB_NAME;
$q = fn(string $sql) => $db->query($sql)->fetch_all(MYSQLI_ASSOC);
$tables = $q("SELECT TABLE_NAME, TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . $db->real_escape_string($schema) . "' ORDER BY TABLE_NAME");
$md = "# TeachHub PT — โครงสร้างฐานข้อมูล (MariaDB, utf8mb4)\n\n"
    . "สร้างอัตโนมัติจากฐานข้อมูลจริงเวอร์ชัน $ver · " . date('Y-m-d') . "\n"
    . "รูปแบบ: `คอลัมน์ ชนิด [PRI|UNI|MUL] [AI] [NULL] [= ค่าเริ่มต้น] — คำอธิบาย` · FK แสดงท้ายตาราง\n"
    . "ไฟล์ติดตั้งใหม่: database/database.sql · ไฟล์อัปเดตรายเวอร์ชัน: database/update_vX.Y.sql\n\n"
    . "ทั้งหมด " . count($tables) . " ตาราง\n\n";
foreach ($tables as $t) {
    $n = $db->real_escape_string($t['TABLE_NAME']);
    $md .= "## `{$t['TABLE_NAME']}`" . ($t['TABLE_COMMENT'] ? " — {$t['TABLE_COMMENT']}" : '') . "\n";
    foreach ($q("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = '$schema' AND TABLE_NAME = '$n' ORDER BY ORDINAL_POSITION") as $c) {
        $line = "- `{$c['COLUMN_NAME']}` {$c['COLUMN_TYPE']}";
        $line .= $c['COLUMN_KEY'] ? " {$c['COLUMN_KEY']}" : '';
        $line .= $c['EXTRA'] === 'auto_increment' ? ' AI' : '';
        $line .= $c['IS_NULLABLE'] === 'YES' ? ' NULL' : '';
        $line .= ($c['COLUMN_DEFAULT'] !== null && $c['COLUMN_DEFAULT'] !== 'NULL') ? " = {$c['COLUMN_DEFAULT']}" : '';
        $line .= $c['COLUMN_COMMENT'] ? " — {$c['COLUMN_COMMENT']}" : '';
        $md .= $line . "\n";
    }
    $fks = $q("SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
               FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
               WHERE k.TABLE_SCHEMA = '$schema' AND k.TABLE_NAME = '$n' AND k.REFERENCED_TABLE_NAME IS NOT NULL");
    if ($fks) {
        $md .= '- FK: ' . implode('; ', array_map(fn($f) => "{$f['COLUMN_NAME']} → {$f['REFERENCED_TABLE_NAME']}.{$f['REFERENCED_COLUMN_NAME']} (ON DELETE {$f['DELETE_RULE']})", $fks)) . "\n";
    }
    $md .= "\n";
}
file_put_contents("$dir/02_database_schema.md", $md);
echo '02_database_schema.md: ' . count($tables) . " tables\n";

// ---------- 04_function_index.md ----------
$md = "# TeachHub PT — ดัชนีฟังก์ชันกลาง (includes/*.php)\n\nสร้างอัตโนมัติ เวอร์ชัน $ver · " . date('Y-m-d')
    . " · ใช้ฟังก์ชันเหล่านี้ก่อนเขียนใหม่ · คำอธิบายมาจาก docblock บรรทัดแรก\n";
$files = glob('includes/*.php');
sort($files);
$total = 0;
foreach ($files as $f) {
    $src = "\n" . file_get_contents($f);
    if (!preg_match_all('/(?:\/\*\*((?:(?!\*\/).)*?)\*\/\s*)?\nfunction\s+([a-z_0-9]+)\s*\(([^)]*)\)\s*(?::\s*([?\w|]+))?/s', $src, $m, PREG_SET_ORDER)) {
        continue;
    }
    $md .= "\n## $f\n";
    foreach ($m as $x) {
        $doc = '';
        if ($x[1] !== '') {
            $lines = array_filter(array_map(fn($l) => trim(ltrim(trim($l), '*')), explode("\n", $x[1])), fn($l) => $l !== '' && $l[0] !== '@');
            $doc = (string)reset($lines);
        }
        $sig = $x[2] . '(' . preg_replace('/\s+/', ' ', trim($x[3])) . ')' . (!empty($x[4]) ? ': ' . $x[4] : '');
        $md .= "- `$sig`" . ($doc !== '' ? ' — ' . mb_substr($doc, 0, 140) : '') . "\n";
        $total++;
    }
}
file_put_contents("$dir/04_function_index.md", $md);
echo "04_function_index.md: $total functions\n";

// ---------- zip ----------
$zipPath = "$dir/teachhub_chatgpt_pack.zip";
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
foreach (['00_README_วิธีตั้งค่า.md', 'gpt_instructions.txt', '01_architecture.md', '02_database_schema.md', '03_coding_conventions.md', '04_function_index.md'] as $f) {
    $zip->addFile("$dir/$f", $f);
}
$zip->close();
echo "teachhub_chatgpt_pack.zip: OK\n";
