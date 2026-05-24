<?php
// ============================================================
// admin/cek_update.php
// Cek versi terbaru dari GitHub
// ============================================================

define('APP_VERSION', '1.0.0');
define('VERSION_URL', 'https://raw.githubusercontent.com/mrkuncen89-ui/CBT-TKA-Kecamatan/main/version.json');

function cekUpdate() {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'TKAKecamatan/1.0.0'
        ]
    ]);
    
    $json = @file_get_contents(VERSION_URL, false, $ctx);
    if (!$json) return null;
    
    $data = json_decode($json, true);
    if (!$data) return null;
    
    return $data;
}

function adaUpdate($versiTerbaru) {
    return version_compare($versiTerbaru, APP_VERSION, '>');
}

// Jika dipanggil via AJAX
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $update = cekUpdate();
    if ($update && adaUpdate($update['version'])) {
        echo json_encode([
            'ada_update' => true,
            'versi_baru' => $update['version'],
            'changelog' => $update['changelog'],
            'download_url' => $update['download_url']
        ]);
    } else {
        echo json_encode(['ada_update' => false]);
    }
    exit;
}
?>
