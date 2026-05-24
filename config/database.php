<?php

// ── PHP 7.4 Polyfills (fungsi-fungsi PHP 8.0+ yang belum ada) ──
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

// ============================================================
// config/database.php
// Konfigurasi koneksi database MySQL — TKA Kecamatan
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// FIX: path log dinamis — berjalan di Windows maupun Linux
// File disimpan di: www/../logs/php_error.log (di luar folder www/)
$_logDir  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$_logFile = $_logDir . DIRECTORY_SEPARATOR . 'php_error.log';
if (!is_dir($_logDir)) {
    @mkdir($_logDir, 0755, true); // buat folder logs jika belum ada
}
ini_set('error_log', $_logFile);
unset($_logDir, $_logFile);

// Cloudflare HTTPS detection
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
if (isset($_SERVER['HTTP_CF_VISITOR'])) {
    $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
    if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') {
        $_SERVER['HTTPS'] = 'on';
    }
}

// Ambil host tanpa port (hindari double port)
$_rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_host    = strtok($_rawHost, ':');
$_port    = $_SERVER['SERVER_PORT'] ?? 80;

// BUG FIX: str_starts_with/str_ends_with butuh PHP 8.0+, ganti ke strpos/substr
$_isLocal = in_array($_host, ['localhost', '127.0.0.1'])
         || strpos($_host, '192.168.') === 0
         || strpos($_host, '10.')      === 0
         || strpos($_host, '172.')     === 0
         || substr($_host, -6)         === '.local';

// ============================================================
// FIX: DETEKSI SUB-FOLDER DI URL SECARA DINAMIS
// ============================================================
// DETEKSI SUB-FOLDER: cari path project relatif terhadap DOCUMENT_ROOT
// Gunakan path filesystem untuk menentukan URL subfolder dengan lebih andal
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
$projDir = realpath(dirname(__DIR__)) ?: '';
$_projectSubFolder = '';
if ($docRoot !== '' && $projDir !== '' && strpos($projDir, $docRoot) === 0) {
    $sub = substr($projDir, strlen($docRoot));
    $sub = str_replace('\\', '/', $sub);
    // pastikan diawali slash dan tidak berakhir dengan slash
    $sub = '/' . trim($sub, '/');
    $_projectSubFolder = $sub === '/' ? '' : $sub;
}

// Set Konfigurasi Berdasarkan Environment
if ($_isLocal) {
    define('DB_HOST', 'cp.bilhill.net');
    define('DB_PORT', 3306);
    define('DB_USER', 'root');
    define('DB_PASS', 'admin8511');
    define('DB_NAME', 'osn_btg');

    $_portStr = ($_port != 80 && $_port != 443) ? ':' . $_port : '';
    // Menambahkan $_projectSubFolder secara dinamis di lokal
    define('BASE_URL', 'http://' . $_host . $_portStr . $_projectSubFolder);
    unset($_portStr);

} else {
    define('DB_HOST', 'cp.bilhill.net');
    define('DB_PORT', 3306);
    define('DB_USER', 'root');
    define('DB_PASS', 'admin8511');
    define('DB_NAME', 'osn_btg');


    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    // Menambahkan $_projectSubFolder secara dinamis di hosting/online
    define('BASE_URL', $protocol . '://' . $_rawHost . $_projectSubFolder);
}

// Bersihkan variabel temporary agar tidak mengotori global scope
unset($_rawHost, $_host, $_port, $_isLocal, $_scriptName, $_currentDir, $_projectSubFolder);

// BUG FIX: PHP 8.1+ mengubah default mysqli_report menjadi MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT
mysqli_report(MYSQLI_REPORT_OFF);

function getConnection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($conn->connect_error) {
        http_response_code(503);
        die('<div style="font-family:sans-serif;padding:40px;color:#c00">'
          . '<h2>Koneksi database gagal</h2>'
          . '<p>Pastikan MySQL berjalan dan database <strong>'
          . DB_NAME . '</strong> sudah dibuat.</p>'
          . '<p><a href="' . BASE_URL . '/install/install.php">Jalankan Installer</a></p>'
          . '</div>');
    }

    $conn->set_charset('utf8mb4');
    date_default_timezone_set('Asia/Jakarta');
    $conn->query("SET time_zone = '+07:00'");

    return $conn;
}

$conn = getConnection();