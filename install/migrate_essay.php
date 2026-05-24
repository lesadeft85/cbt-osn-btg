<?php
// ============================================================
// install/migrate_essay.php — Migrasi: Tambah Fitur Soal Esai
// ============================================================
// Jalankan SATU KALI setelah upload file fitur esai.
// Aman dijalankan berulang kali (idempotent).
// ============================================================
require_once __DIR__ . '/../config/database.php';

$results = [];

function runMigration($conn, string $desc, string $sql): void {
    global $results;
    $ok = $conn->query($sql);
    $results[] = [
        'desc' => $desc,
        'ok'   => (bool)$ok,
        'err'  => $ok ? '' : $conn->error,
    ];
}

// 1. Tambah tipe 'essay' ke ENUM kolom tipe_soal pada tabel soal
// Cek dulu apakah 'essay' sudah ada
$colRes = $conn->query("SHOW COLUMNS FROM soal LIKE 'tipe_soal'");
$colRow = $colRes ? $colRes->fetch_assoc() : null;
$enumDef = $colRow['Type'] ?? '';
if (strpos($enumDef, 'essay') === false) {
    runMigration($conn,
        "Tambah nilai 'essay' ke ENUM tipe_soal",
        "ALTER TABLE soal MODIFY COLUMN tipe_soal ENUM('pg','bs','mcma','essay') NOT NULL DEFAULT 'pg'"
    );
} else {
    $results[] = ['desc' => "ENUM tipe_soal sudah memiliki 'essay'", 'ok' => true, 'err' => ''];
}

// 2. Tambah kolom essay_bobot (nilai maks per soal esai, default 10)
$cek = $conn->query("SHOW COLUMNS FROM soal LIKE 'essay_bobot'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom essay_bobot ke tabel soal",
        "ALTER TABLE soal ADD COLUMN essay_bobot TINYINT UNSIGNED NOT NULL DEFAULT 10 AFTER pembahasan"
    );
} else {
    $results[] = ['desc' => 'Kolom essay_bobot sudah ada', 'ok' => true, 'err' => ''];
}

// 3a. Tambah kolom peserta_id ke tabel jawaban (BUG FIX #15: dibutuhkan INSERT essay)
$cek = $conn->query("SHOW COLUMNS FROM jawaban LIKE 'peserta_id'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom peserta_id ke tabel jawaban",
        "ALTER TABLE jawaban ADD COLUMN peserta_id INT NULL DEFAULT NULL AFTER ujian_id"
    );
    // Isi peserta_id dari tabel ujian untuk data lama
    $conn->query(
        "UPDATE jawaban j JOIN ujian u ON u.id = j.ujian_id SET j.peserta_id = u.peserta_id WHERE j.peserta_id IS NULL"
    );
} else {
    $results[] = ['desc' => 'Kolom peserta_id di jawaban sudah ada', 'ok' => true, 'err' => ''];
}

// 3b. Tambah kolom teks_jawaban ke tabel jawaban (untuk isian esai peserta)
$cek = $conn->query("SHOW COLUMNS FROM jawaban LIKE 'teks_jawaban'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom teks_jawaban ke tabel jawaban",
        "ALTER TABLE jawaban ADD COLUMN teks_jawaban TEXT NULL DEFAULT NULL AFTER jawaban"
    );
} else {
    $results[] = ['desc' => 'Kolom teks_jawaban sudah ada', 'ok' => true, 'err' => ''];
}

// 4. Tambah kolom skor_essay ke tabel jawaban (nilai yang diberikan admin)
$cek = $conn->query("SHOW COLUMNS FROM jawaban LIKE 'skor_essay'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom skor_essay ke tabel jawaban",
        "ALTER TABLE jawaban ADD COLUMN skor_essay DECIMAL(5,2) NULL DEFAULT NULL AFTER teks_jawaban"
    );
} else {
    $results[] = ['desc' => 'Kolom skor_essay sudah ada', 'ok' => true, 'err' => ''];
}

// 5. Tambah kolom dinilai_at ke tabel jawaban
$cek = $conn->query("SHOW COLUMNS FROM jawaban LIKE 'dinilai_at'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom dinilai_at ke tabel jawaban",
        "ALTER TABLE jawaban ADD COLUMN dinilai_at TIMESTAMP NULL DEFAULT NULL AFTER skor_essay"
    );
} else {
    $results[] = ['desc' => 'Kolom dinilai_at sudah ada', 'ok' => true, 'err' => ''];
}

// 6. Tambah kolom ada_essay dan essay_dinilai ke tabel hasil_ujian
$cek = $conn->query("SHOW COLUMNS FROM hasil_ujian LIKE 'ada_essay'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom ada_essay ke tabel hasil_ujian",
        "ALTER TABLE hasil_ujian ADD COLUMN ada_essay TINYINT(1) NOT NULL DEFAULT 0 AFTER nilai"
    );
} else {
    $results[] = ['desc' => 'Kolom ada_essay sudah ada', 'ok' => true, 'err' => ''];
}

$cek = $conn->query("SHOW COLUMNS FROM hasil_ujian LIKE 'essay_dinilai'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom essay_dinilai ke tabel hasil_ujian",
        "ALTER TABLE hasil_ujian ADD COLUMN essay_dinilai TINYINT(1) NOT NULL DEFAULT 0 AFTER ada_essay"
    );
} else {
    $results[] = ['desc' => 'Kolom essay_dinilai sudah ada', 'ok' => true, 'err' => ''];
}

// 7. Tambah kolom nilai_essay (skor esai terpisah) ke hasil_ujian
$cek = $conn->query("SHOW COLUMNS FROM hasil_ujian LIKE 'nilai_essay'");
if (!$cek || $cek->num_rows === 0) {
    runMigration($conn,
        "Tambah kolom nilai_essay ke tabel hasil_ujian",
        "ALTER TABLE hasil_ujian ADD COLUMN nilai_essay DECIMAL(6,2) NULL DEFAULT NULL AFTER essay_dinilai"
    );
} else {
    $results[] = ['desc' => 'Kolom nilai_essay sudah ada', 'ok' => true, 'err' => ''];
}

$allOk = !in_array(false, array_column($results, 'ok'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Migrasi Soal Esai</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:640px">
  <div class="card shadow-sm border-0 rounded-4 p-4">
    <h4 class="fw-bold mb-3">
      <i class="bi bi-database-add me-2 text-primary"></i>Migrasi: Fitur Soal Esai
    </h4>
    <div class="alert alert-<?= $allOk ? 'success' : 'danger' ?> fw-semibold">
      <?= $allOk ? '✅ Semua migrasi berhasil!' : '❌ Ada migrasi yang gagal.' ?>
    </div>
    <ul class="list-group list-group-flush">
      <?php foreach ($results as $r): ?>
      <li class="list-group-item d-flex align-items-start gap-2 px-0">
        <span style="font-size:18px"><?= $r['ok'] ? '✅' : '❌' ?></span>
        <div>
          <div class="fw-semibold small"><?= htmlspecialchars($r['desc']) ?></div>
          <?php if ($r['err']): ?>
          <div class="text-danger small"><?= htmlspecialchars($r['err']) ?></div>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($allOk): ?>
    <div class="alert alert-info mt-3 mb-0 small">
      <strong>Langkah selanjutnya:</strong> Hapus atau batasi akses ke file ini setelah migrasi berhasil.
      Kemudian tambah soal dengan tipe <strong>Essay</strong> di halaman Bank Soal.
    </div>
    <?php endif; ?>
    <a href="../admin/soal.php" class="btn btn-primary mt-3">
      <i class="bi bi-arrow-left me-1"></i>Kembali ke Bank Soal
    </a>
  </div>
</div>
</body>
</html>
