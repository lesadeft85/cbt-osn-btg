<?php
// AJAX endpoint: ujian/ajax_get_peserta.php
// Returns JSON { success: bool, data: { nama, kode_sekolah, nama_sekolah }, message }
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/database.php';

$kode = strtoupper(trim($_GET['kode'] ?? ''));
if ($kode === '') {
    echo json_encode(['success' => false, 'message' => 'Kode peserta kosong']);
    exit;
}

// Prepared statement to avoid injection
if ($stmt = $conn->prepare(
    "SELECT p.nama, p.kode_sekolah, s.nama_sekolah
     FROM peserta p
     LEFT JOIN sekolah s ON s.id = p.sekolah_id
     WHERE p.kode_peserta = ? LIMIT 1"
)) {
    $stmt->bind_param('s', $kode);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        echo json_encode(['success' => true, 'data' => [
            'nama' => $row['nama'] ?? '',
            'kode_sekolah' => $row['kode_sekolah'] ?? '',
            'nama_sekolah' => $row['nama_sekolah'] ?? ''
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Peserta tidak ditemukan']);
    }
    $stmt->close();
} else {
    // Provide a helpful error message for debugging in development environments.
    $err = isset($conn) && method_exists($conn, 'error') ? $conn->error : 'unknown error';

    // Fallback: try a simple escaped query if prepare() is not available for some reason
    $kodeEsc = isset($conn) ? $conn->real_escape_string($kode) : addslashes($kode);
        $q = "SELECT p.nama, p.kode_sekolah, s.nama_sekolah
            FROM peserta p
            LEFT JOIN sekolah s ON s.id = p.sekolah_id
            WHERE p.kode_peserta = '$kodeEsc' LIMIT 1";
    $qr = (isset($conn) ? $conn->query($q) : false);
    if ($qr && $qr->num_rows > 0) {
        $row = $qr->fetch_assoc();
        echo json_encode(['success' => true, 'data' => [
            'nama' => $row['nama'] ?? '',
            'kode_sekolah' => $row['kode_sekolah'] ?? '',
            'nama_sekolah' => $row['nama_sekolah'] ?? ''
        ]]);
    } else {
        $dbErr = '';
        $dbErrNo = 0;
        if (isset($conn)) {
            $dbErr = isset($conn->error) ? $conn->error : '';
            $dbErrNo = isset($conn->errno) ? (int)$conn->errno : 0;
        }
        $msg = 'Query gagal' . ($dbErr ? ': ' . $dbErr : '');
        $resp = ['success' => false, 'message' => $msg];
        // Only include lightweight debug info to help local troubleshooting
        $resp['debug'] = ['db_errno' => $dbErrNo, 'db_error' => $dbErr ? $dbErr : 'none', 'conn_present' => isset($conn)];
        echo json_encode($resp);
    }
}
