<?php
/** Run: C:/xampp/php/php.exe tests/registration_email_domains.php (no database). */
define('APP_DEBUG', false);
require_once __DIR__ . '/../includes/google_auth.php';

$testSettings = ['google_allowed_domain' => 'pticec.ac.th', 'google_client_id' => 'test-client'];
function setting(string $key, $default = '') { global $testSettings; return $testSettings[$key] ?? $default; }
function site_base_url(): string { return 'https://example.test/teachhub'; }

function check_registration(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}

check_registration(google_allowed_domain() === '', 'Ignore legacy organization-only setting');
foreach (['login', 'link'] as $mode) {
    parse_str(parse_url(google_auth_url($mode), PHP_URL_QUERY), $params);
    check_registration(!isset($params['hd']), 'Allow all Google account domains for ' . $mode);
    check_registration($params['code_challenge_method'] === 'S256' && !empty($params['state']), 'Keep PKCE and state for ' . $mode);
}
$result = google_handle_callback(['state' => 'invalid', 'code' => 'invalid']);
check_registration(!$result['ok'], 'Reject invalid OAuth state');
