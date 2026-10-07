<?php
// ---- Database (Auto-detect Local XAMPP vs InfinityFree Hosting) ----
$__isLocalEnv = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) || php_sapi_name() === 'cli';
if ($__isLocalEnv) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'if0_42277792_suryamithra');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'sql202.infinityfree.com');
    define('DB_NAME', 'if0_42277792_suryamithra');
    define('DB_USER', 'if0_42277792');
    define('DB_PASS', 'pxBLuNBapV5zih9');
}

// ---- App ----
define('APP_NAME', 'SURYAMITHRA');
define('APP_TAGLINE', 'Student Success & Placement Decision Intelligence Platform');

// Optional: OAuth credentials for Google & Microsoft Single Sign-On (SSO)
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');
define('MICROSOFT_CLIENT_ID', '');
define('MICROSOFT_CLIENT_SECRET', '');

// Firebase Web SDK Authentication Configuration
define('FIREBASE_API_KEY', 'AIzaSyAQbR9Htl_DEWM2_YWTWN9ku5k0TL5cGco');
define('FIREBASE_AUTH_DOMAIN', 'suryamithra.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'suryamithra');
define('FIREBASE_STORAGE_BUCKET', 'suryamithra.firebasestorage.app');
define('FIREBASE_MESSAGING_SENDER_ID', '562733026375');
define('FIREBASE_APP_ID', '1:562733026375:web:bbec64d6c9d6701eb87562');

// Optional: paste an Anthropic API key to let the AI Copilot use an LLM.
// Leave empty to use the built-in analytics engine (works offline).
define('LLM_API_KEY', '');
define('LLM_MODEL', 'claude-sonnet-4-6');

// Auto-detect the URL base (works for http://localhost/campusiq or any folder name)
$__root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$__doc  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$__base = ($__doc && strpos($__root, $__doc) === 0) ? substr($__root, strlen($__doc)) : '';
define('BASE_URL', rtrim($__base, '/'));
define('ROOT_PATH', $__root);
