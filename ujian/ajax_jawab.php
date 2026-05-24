<?php
// ============================================================
// ujian/ajax_jawab.php — Simpan jawaban via AJAX
// FIX: Semua query menggunakan prepared statements (mysqli)
//      untuk mencegah SQL Injection
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';

header('Content-Type: application/json');

if (empty($_SESSION['peserta_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Session habis']);
    exit;
}

$ujianId   = (int)$_SESSION['ujian_id'];
$pesertaId = (int)$_SESSION['peserta_id'];

// Ping (keep-alive)
if (isset($_POST['ping'])) {
    updateUjianActivity($conn, $ujianId);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Proctoring ────────────────────────────────────────────────
if (isset($_POST['violation'])) {
    // FIX #1: prepared statement, bukan string interpolasi
    $stmt = $conn->prepare("UPDATE ujian SET pelanggaran = pelanggaran + 1 WHERE id = ?");
    $stmt->bind_param('i', $ujianId);
    $stmt->execute();
    $stmt->close();
    updateUjianActivity($conn, $ujianId);
    echo json_encode(['ok' => true]);
    exit;
}

// ── Validasi waktu ────────────────────────────────────────────
$jamSelesai = $_SESSION['jam_selesai'] ?? null;
if ($jamSelesai) {
    $tanggalUjian = $_SESSION['tanggal_ujian'] ?? date('Y-m-d');
    $batasWaktu   = strtotime("$tanggalUjian $jamSelesai");
    if (time() > $batasWaktu) {
        echo json_encode(['ok' => false, 'msg' => 'Waktu ujian sudah berakhir', 'expired' => true]);
        exit;
    }
}

$soalId  = (int)($_POST['soal_id'] ?? 0);
$jawaban = trim($_POST['jawaban'] ?? '');

if (!$soalId || $jawaban === '') {
    echo json_encode(['ok' => false, 'msg' => 'Data tidak lengkap']);
    exit;
}

// ── Validasi soal & tipe ──────────────────────────────────────
// FIX #1: prepared statement
$stmt = $conn->prepare("SELECT id, tipe_soal FROM soal WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $soalId);
$stmt->execute();
$cekSoal = $stmt->get_result();
if (!$cekSoal || $cekSoal->num_rows === 0) {
    $stmt->close();
    echo json_encode(['ok' => false, 'msg' => 'Soal tidak valid']);
    exit;
}
$soalRow = $cekSoal->fetch_assoc();
$stmt->close();
$tipe = $soalRow['tipe_soal'];

// Validasi nilai jawaban per tipe soal
if ($tipe === 'bs') {
    if (!in_array($jawaban, ['benar', 'salah'])) {
        echo json_encode(['ok' => false, 'msg' => 'Jawaban tidak valid']);
        exit;
    }
} elseif ($tipe === 'mcma') {
    $arr = explode(',', $jawaban);
    foreach ($arr as $h) {
        if (!in_array(trim($h), ['a', 'b', 'c', 'd'])) {
            echo json_encode(['ok' => false, 'msg' => 'Jawaban MCMA tidak valid']);
            exit;
        }
    }
    sort($arr);
    $jawaban = implode(',', array_unique($arr));
} elseif ($tipe === 'essay') {
    $teksJawaban = trim($_POST['teks_jawaban'] ?? '');
    if (mb_strlen($teksJawaban) > 3000) {
        $teksJawaban = mb_substr($teksJawaban, 0, 3000);
    }
    $jawaban = 'essay';
} else {
    if (!in_array($jawaban, ['a', 'b', 'c', 'd'])) {
        echo json_encode(['ok' => false, 'msg' => 'Jawaban tidak valid']);
        exit;
    }
}

// ── Validasi ujian aktif ──────────────────────────────────────
// FIX #1: prepared statement
$stmt = $conn->prepare(
    "SELECT id FROM ujian WHERE id = ? AND peserta_id = ? AND waktu_selesai IS NULL LIMIT 1"
);
$stmt->bind_param('ii', $ujianId, $pesertaId);
$stmt->execute();
$cekUjian = $stmt->get_result();
if (!$cekUjian || $cekUjian->num_rows === 0) {
    $stmt->close();
    echo json_encode(['ok' => false, 'msg' => 'Ujian tidak aktif']);
    exit;
}
$stmt->close();

// Ambil soal_order — session first, fallback ke DB
$soalOrder = $_SESSION['soal_order'] ?? [];
if (empty($soalOrder)) {
    $stmt = $conn->prepare(
        "SELECT soal_order FROM ujian WHERE id = ? AND peserta_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $ujianId, $pesertaId);
    $stmt->execute();
    $ujianRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!empty($ujianRow['soal_order'])) {
        $soalOrder = json_decode($ujianRow['soal_order'], true) ?: [];
        $_SESSION['soal_order'] = $soalOrder;
    }
}

if (!empty($soalOrder) && !in_array((int)$soalId, array_map('intval', $soalOrder))) {
    echo json_encode(['ok' => false, 'msg' => 'Soal tidak dalam sesi ujian']);
    exit;
}

// Auto-migrate kolom teks_jawaban
$_cEssay = $conn->query("SHOW COLUMNS FROM jawaban LIKE 'teks_jawaban'");
if (!$_cEssay || $_cEssay->num_rows === 0) {
    $conn->query("ALTER TABLE jawaban ADD COLUMN teks_jawaban TEXT NULL");
}

// ── Upsert jawaban — FIX #1: prepared statements ─────────────
if ($tipe === 'essay') {
    $stmt = $conn->prepare(
        "INSERT INTO jawaban (ujian_id, peserta_id, soal_id, jawaban, teks_jawaban)
         VALUES (?, ?, ?, 'essay', ?)
         ON DUPLICATE KEY UPDATE jawaban = 'essay', teks_jawaban = ?, peserta_id = ?"
    );
    $stmt->bind_param('iiissi', $ujianId, $pesertaId, $soalId, $teksJawaban, $teksJawaban, $pesertaId);
} else {
    $stmt = $conn->prepare(
        "INSERT INTO jawaban (ujian_id, peserta_id, soal_id, jawaban)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE jawaban = ?, peserta_id = ?"
    );
    $stmt->bind_param('iiissi', $ujianId, $pesertaId, $soalId, $jawaban, $jawaban, $pesertaId);
}

$result = $stmt->execute();
if (!$result) {
    $errMsg = $stmt->error;
    $stmt->close();
    echo json_encode(['ok' => false, 'msg' => 'DB error: ' . $errMsg]);
    exit;
}
$stmt->close();

updateUjianActivity($conn, $ujianId);

// Update session cache
if (!isset($_SESSION['_jawaban_cache']) || !is_array($_SESSION['_jawaban_cache'])) {
    $_SESSION['_jawaban_cache'] = [];
}
if (!isset($_SESSION['_teks_jawaban_cache']) || !is_array($_SESSION['_teks_jawaban_cache'])) {
    $_SESSION['_teks_jawaban_cache'] = [];
}
$_SESSION['_jawaban_cache'][$soalId] = $jawaban;
if ($tipe === 'essay') {
    $_SESSION['_teks_jawaban_cache'][$soalId] = $teksJawaban ?? '';
}

echo json_encode(['ok' => true]);
