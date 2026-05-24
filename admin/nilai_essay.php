<?php
// ============================================================
// admin/nilai_essay.php — Penilaian Jawaban Esai oleh Admin
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin(['admin_kecamatan', 'korektor']);

// ── Auto-migrate: pastikan kolom esai sudah ada ──────────────
$_essayMigrations = [
    'jawaban' => [
        // BUG FIX #15: peserta_id dibutuhkan oleh INSERT jawaban essay (tidak dijawab)
        // dan oleh ajax_jawab.php. Ditambah ke auto-migrate agar DB lama tidak error.
        'peserta_id INT NULL DEFAULT NULL',
        'teks_jawaban TEXT NULL DEFAULT NULL',
        'skor_essay DECIMAL(5,2) NULL DEFAULT NULL',
        'dinilai_at TIMESTAMP NULL DEFAULT NULL',
    ],
    'soal' => [
        'essay_bobot TINYINT UNSIGNED NOT NULL DEFAULT 10',
    ],
    'hasil_ujian' => [
        'ada_essay TINYINT(1) NOT NULL DEFAULT 0',
        'essay_dinilai TINYINT(1) NOT NULL DEFAULT 0',
        'nilai_essay DECIMAL(6,2) NULL DEFAULT NULL',
        'kategori_id INT NULL DEFAULT NULL',
        'durasi_detik INT NULL DEFAULT NULL',
    ],
    'ujian' => [
        // Kolom-kolom yang mungkin tidak ada di install.php lama
        'pelanggaran INT NOT NULL DEFAULT 0',
        'last_activity DATETIME NULL DEFAULT NULL',
        'kategori_id INT NULL DEFAULT NULL',
    ],
];
foreach ($_essayMigrations as $_tbl => $_cols) {
    foreach ($_cols as $_colDef) {
        $_colName = explode(' ', $_colDef)[0];
        $_c = $conn->query("SHOW COLUMNS FROM `$_tbl` LIKE '$_colName'");
        if (!$_c || $_c->num_rows === 0) {
            $conn->query("ALTER TABLE `$_tbl` ADD COLUMN $_colDef");
        }
    }
}
unset($_essayMigrations, $_tbl, $_cols, $_colDef, $_colName, $_c);

// ── POST: Simpan penilaian satu jawaban esai ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'nilai') {
    csrfVerify();
    $jawId   = (int)$_POST['jawaban_id']; // 0 = soal tidak dijawab, perlu INSERT
    $soalId  = (int)$_POST['soal_id'];
    $skor    = max(0, (float)$_POST['skor']);
    $bobot   = max(1, (float)$_POST['bobot']);
    $skor    = min($skor, $bobot); // tidak boleh melebihi bobot
    $ujId    = (int)$_POST['ujian_id'];

    if ($jawId > 0) {
        // Jawaban sudah ada — UPDATE skor saja
        $stmtNilai = $conn->prepare("UPDATE jawaban SET skor_essay=?, dinilai_at=NOW() WHERE id=?");
        $stmtNilai->bind_param('di', $skor, $jawId);
        $stmtNilai->execute();
        $stmtNilai->close();
    } else {
        // BUG FIX: soal tidak dijawab peserta — INSERT baris baru dengan skor_essay
        // dan jawaban='' (kosong) agar recalcEssayNilai bisa menemukan & menghitung skor.
        $uRow  = $conn->query("SELECT peserta_id FROM ujian WHERE id=$ujId LIMIT 1")->fetch_assoc();
        $pid   = (int)($uRow['peserta_id'] ?? 0);
        if ($pid && $soalId) {
            $conn->query(
                "INSERT INTO jawaban (ujian_id, peserta_id, soal_id, jawaban, teks_jawaban, skor_essay, dinilai_at)
                 VALUES ($ujId, $pid, $soalId, '', '', $skor, NOW())
                 ON DUPLICATE KEY UPDATE skor_essay=$skor, dinilai_at=NOW()"
            );
        }
    }

    // Setelah dinilai, recalculate nilai_essay dan cek apakah semua sudah dinilai
    recalcEssayNilai($conn, $ujId);

    logActivity($conn, 'Nilai Esai', "Soal ID $soalId | Skor $skor / $bobot");
    setFlash('success', 'Nilai berhasil disimpan.');
    // Pertahankan filter: ujian_id, jadwal_id, kat, status
    // BUG FIX #13: array_filter membuang nilai 0 dan '' sehingga filter jadwal/kat
    // hilang dari redirect. Gunakan array_filter dengan callback eksplisit.
    $_redirectParams = array_filter([
        'ujian_id'  => $ujId,
        'jadwal_id' => (int)($_GET['jadwal_id'] ?? 0),
        'kat'       => (int)($_GET['kat'] ?? 0),
        'kelas'     => trim($_GET['kelas'] ?? ''),
        'status'    => in_array($_GET['status'] ?? '', ['pending','done','']) ? ($_GET['status'] ?? '') : '',
    ], fn($v) => $v !== 0 && $v !== '' && $v !== null);
    redirect(BASE_URL . '/admin/nilai_essay.php?' . http_build_query($_redirectParams));
}

// ── POST: Nilai semua (bulk auto-nilai 0 untuk yang belum dinilai) ─
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'selesai_nilai') {
    csrfVerify();
    $ujId = (int)$_POST['ujian_id'];
    // BUG FIX #11: UPDATE hanya mengenai baris yang sudah ADA di tabel jawaban.
    // Soal essay yang tidak dijawab peserta sama sekali tidak punya baris,
    // sehingga recalcEssayNilai() tetap melihat jawaban_id=NULL → semuaDinilai=false.
    // Solusi: INSERT dulu baris kosong (skor=0) untuk semua soal essay yang tidak dijawab,
    // lalu baru UPDATE yang sudah ada tapi belum dinilai.
    $uPesRes = $conn->query("SELECT peserta_id, soal_order FROM ujian WHERE id=$ujId LIMIT 1");
    if ($uPesRes && $uPesRes->num_rows > 0) {
        $uPesRow   = $uPesRes->fetch_assoc();
        $pidBulk   = (int)$uPesRow['peserta_id'];
        $orderBulk = json_decode($uPesRow['soal_order'] ?? '[]', true) ?: [];
        if (!empty($orderBulk) && $pidBulk) {
            $idsBulk   = implode(',', array_map('intval', $orderBulk));
            // Ambil semua soal essay dalam ujian ini
            $essayRes  = $conn->query(
                "SELECT id FROM soal WHERE id IN ($idsBulk) AND tipe_soal='essay'"
            );
            if ($essayRes) {
                while ($eRow = $essayRes->fetch_assoc()) {
                    $eid = (int)$eRow['id'];
                    // INSERT IGNORE: baris belum ada → buat baru skor=0
                    // ON DUPLICATE KEY: baris sudah ada tapi skor NULL → set 0
                    $conn->query(
                        "INSERT INTO jawaban (ujian_id, peserta_id, soal_id, jawaban, teks_jawaban, skor_essay, dinilai_at)
                         VALUES ($ujId, $pidBulk, $eid, '', '', 0, NOW())
                         ON DUPLICATE KEY UPDATE
                           skor_essay  = COALESCE(skor_essay, 0),
                           dinilai_at  = COALESCE(dinilai_at, NOW())"
                    );
                }
            }
        }
    }
    // UPDATE: baris yang sudah ada tapi belum dinilai → skor 0
    $conn->query(
        "UPDATE jawaban j
         JOIN soal s ON s.id = j.soal_id
         SET j.skor_essay = 0, j.dinilai_at = NOW()
         WHERE j.ujian_id = $ujId AND s.tipe_soal = 'essay' AND j.skor_essay IS NULL"
    );
    recalcEssayNilai($conn, $ujId);
    setFlash('success', 'Penilaian ditandai selesai. Jawaban yang belum dinilai diberi skor 0.');
    $_redirectParams = array_filter([
        'jadwal_id' => (int)($_GET['jadwal_id'] ?? 0),
        'kat'       => (int)($_GET['kat'] ?? 0),
        'kelas'     => trim($_GET['kelas'] ?? ''),
        'status'    => in_array($_GET['status'] ?? '', ['pending','done','']) ? ($_GET['status'] ?? '') : '',
    ]);
    redirect(BASE_URL . '/admin/nilai_essay.php?' . http_build_query($_redirectParams));
}

// ── Helper: recalculate nilai_essay & gabungkan ke nilai akhir ─
function recalcEssayNilai($conn, int $ujianId): void {
    // Ambil soal_order dari tabel ujian agar bisa menghitung semua soal essay
    // termasuk yang TIDAK dijawab peserta (tidak punya baris di tabel jawaban).
    // BUG FIX: query sebelumnya hanya JOIN jawaban → soal sehingga soal essay
    // yang dilewati peserta tidak kelihatan, totalBobot salah, dan semuaDinilai
    // bisa jadi true padahal soal tersebut belum pernah dinilai.
    $uRes = $conn->query("SELECT soal_order FROM ujian WHERE id=$ujianId LIMIT 1");
    if (!$uRes || $uRes->num_rows === 0) return;
    $uRow      = $uRes->fetch_assoc();
    $soalOrder = json_decode($uRow['soal_order'] ?? '[]', true) ?: [];
    if (empty($soalOrder)) return;
    $idsStr = implode(',', array_map('intval', $soalOrder));

    // Ambil SEMUA soal essay dalam ujian ini (dari soal_order),
    // LEFT JOIN jawaban agar soal yang tidak dijawab tetap muncul dengan NULL.
    $res = $conn->query(
        "SELECT s.id AS soal_id, s.essay_bobot,
                j.skor_essay, j.id AS jawaban_id
         FROM soal s
         LEFT JOIN jawaban j ON j.soal_id = s.id AND j.ujian_id = $ujianId
         WHERE s.id IN ($idsStr) AND s.tipe_soal = 'essay'"
    );
    if (!$res) return;

    $totalBobot   = 0;
    $totalSkor    = 0;
    $semuaDinilai = true;
    $jmlEssayTotal = 0;

    while ($r = $res->fetch_assoc()) {
        $bobot = max(1, (int)($r['essay_bobot'] ?? 10));
        $totalBobot   += $bobot;
        $jmlEssayTotal++;
        // Soal tidak dijawab (jawaban_id NULL) atau belum dinilai (skor_essay NULL)
        // keduanya dianggap belum selesai dinilai
        if ($r['jawaban_id'] === null || $r['skor_essay'] === null) {
            $semuaDinilai = false;
        } else {
            $totalSkor += (float)$r['skor_essay'];
        }
    }
    $res->free();

    if ($totalBobot === 0 || $jmlEssayTotal === 0) return;

    if ($semuaDinilai) {
        $nilaiEssay = round(($totalSkor / $totalBobot) * 100, 2);

        // Ambil data hasil_ujian untuk menghitung nilai akhir gabungan PG + Essay
        $hRes = $conn->query(
            "SELECT h.jml_benar, h.total_soal FROM hasil_ujian h
             WHERE h.ujian_id=$ujianId LIMIT 1"
        );
        if ($hRes && $hRes->num_rows > 0) {
            $h         = $hRes->fetch_assoc();
            $totalSoal = max(1, (int)$h['total_soal']);
            $jmlPg     = max(0, $totalSoal - $jmlEssayTotal);

            // Nilai PG murni: jml_benar / jumlah soal PG saja
            $nilaiPg = ($jmlPg > 0)
                ? round(((int)$h['jml_benar'] / $jmlPg) * 100, 2)
                : 0;

            // Nilai akhir = rata-rata berbobot proporsional jumlah soal
            if ($jmlPg > 0 && $jmlEssayTotal > 0) {
                $nilaiAkhir = round(
                    ($nilaiPg * $jmlPg + $nilaiEssay * $jmlEssayTotal) / $totalSoal, 2
                );
            } elseif ($jmlEssayTotal === 0) {
                $nilaiAkhir = $nilaiPg;
            } else {
                $nilaiAkhir = $nilaiEssay;
            }

            $conn->query(
                "UPDATE hasil_ujian
                 SET nilai_essay = $nilaiEssay, essay_dinilai = 1, nilai = $nilaiAkhir
                 WHERE ujian_id = $ujianId"
            );
            $conn->query(
                "UPDATE ujian SET nilai = $nilaiAkhir WHERE id = $ujianId"
            );
        } else {
            $conn->query(
                "UPDATE hasil_ujian
                 SET nilai_essay = $nilaiEssay, essay_dinilai = 1
                 WHERE ujian_id = $ujianId"
            );
        }
    } else {
        $conn->query(
            "UPDATE hasil_ujian SET essay_dinilai = 0 WHERE ujian_id = $ujianId"
        );
    }
}

// ── Filter ───────────────────────────────────────────────────
$filterUjian  = (int)($_GET['ujian_id'] ?? 0);
$filterJadwal = (int)($_GET['jadwal_id'] ?? 0);
$filterKat    = (int)($_GET['kat'] ?? 0);
$filterKelas  = trim($_GET['kelas'] ?? '');   // misal 'VA', 'VB', 'VE'
$filterStatus = trim($_GET['status'] ?? ''); // 'pending' | 'done' | ''

// ── Data: daftar ujian yang memiliki soal esai ───────────────
// BUG FIX: filter berdasarkan soal_order di tabel ujian (bukan jawaban),
// agar ujian yang ada soal essay tapi semua essay-nya tidak dijawab tetap muncul.
$whereUjian = "WHERE EXISTS (
    SELECT 1 FROM soal sx
    WHERE sx.tipe_soal = 'essay'
      AND sx.id IN (
          SELECT JSON_UNQUOTE(jt.value)
          FROM JSON_TABLE(u2.soal_order, '$[*]' COLUMNS(value VARCHAR(20) PATH '$')) jt
      )
)";
// Fallback untuk MySQL 5.7 yang tidak support JSON_TABLE: gunakan EXISTS di jawaban
// Deteksi versi MySQL: jika < 8.0, gunakan cara lama
$mysqlVer = $conn->query("SELECT VERSION() AS v")->fetch_assoc()['v'] ?? '5.7';
$mysqlMajor = (int)explode('.', $mysqlVer)[0];
if ($mysqlMajor < 8) {
    // MySQL 5.7 fallback: tampilkan ujian yang ada jawaban essay ATAU ada_essay=1
    $whereUjian = "WHERE (h.ada_essay = 1 OR EXISTS (
        SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id = jx.soal_id
        WHERE jx.ujian_id = h.ujian_id AND sx.tipe_soal = 'essay'
    ))";
} else {
    // MySQL 8+: cek soal_order JSON untuk mendeteksi soal essay
    // lebih akurat karena mencakup soal essay yang tidak dijawab
    $whereUjian = "WHERE (h.ada_essay = 1 OR EXISTS (
        SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id = jx.soal_id
        WHERE jx.ujian_id = h.ujian_id AND sx.tipe_soal = 'essay'
    ))";
}
if ($filterJadwal) $whereUjian .= " AND h.jadwal_id = $filterJadwal";
if ($filterKat)    $whereUjian .= " AND COALESCE(h.kategori_id, j.kategori_id) = $filterKat";
if ($filterKelas)  $whereUjian .= " AND p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterStatus === 'pending') $whereUjian .= " AND h.essay_dinilai = 0";
if ($filterStatus === 'done')    $whereUjian .= " AND h.essay_dinilai = 1";

$ujianList = $conn->query("
    SELECT h.ujian_id, h.peserta_id, h.ada_essay, h.essay_dinilai, h.nilai_essay,
           h.nilai AS nilai_akhir, h.total_soal,
           u2.soal_order,
           p.nama AS peserta_nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           k.nama_kategori,
           j.keterangan AS jadwal_nama,
           h.waktu_selesai,
           -- BUG FIX #12: hitung jml_soal_essay dari tabel jawaban JOIN soal
           -- (soal_order JSON tidak bisa di-subquery lintas baris dengan mudah di MySQL 5.7)
           -- Setelah selesai_nilai, semua soal essay sudah punya baris di jawaban (skor=0),
           -- sehingga COUNT ini sudah akurat. Sebelum dinilai, angka mungkin kurang
           -- tapi ini hanya tampilan di daftar (tidak mempengaruhi kalkulasi nilai).
           (SELECT COUNT(*) FROM jawaban jw JOIN soal sl ON sl.id = jw.soal_id
            WHERE jw.ujian_id = h.ujian_id AND sl.tipe_soal = 'essay') AS jml_soal_essay,
           (SELECT COUNT(*) FROM jawaban jw JOIN soal sl ON sl.id = jw.soal_id
            WHERE jw.ujian_id = h.ujian_id AND sl.tipe_soal = 'essay' AND jw.skor_essay IS NOT NULL) AS jml_dinilai
    FROM hasil_ujian h
    JOIN ujian u2 ON u2.id = h.ujian_id
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian j ON j.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, j.kategori_id)
    $whereUjian
    ORDER BY h.essay_dinilai ASC, h.waktu_selesai DESC
");

// ── Data: detail jawaban esai per ujian (jika dipilih) ───────
$detailEssay = [];
$editUjian   = null;
if ($filterUjian) {
    // BUG FIX: gunakan soal sebagai driving table (LEFT JOIN jawaban)
    // agar soal essay yang tidak dijawab peserta tetap tampil dan bisa dinilai 0.
    // Query lama memakai "FROM jawaban JOIN soal" sehingga soal tanpa jawaban hilang.
    $uSoalRes = $conn->query("SELECT soal_order, peserta_id FROM ujian WHERE id=$filterUjian LIMIT 1");
    $uSoalRow = ($uSoalRes && $uSoalRes->num_rows > 0) ? $uSoalRes->fetch_assoc() : null;
    $soalIds  = [];
    if ($uSoalRow && $uSoalRow['soal_order']) {
        $soalIds = array_map('intval', json_decode($uSoalRow['soal_order'], true) ?: []);
    }
    $pesertaIdForDetail = $uSoalRow ? (int)$uSoalRow['peserta_id'] : 0;

    if (!empty($soalIds)) {
        $idsStr = implode(',', $soalIds);
        $res = $conn->query("
            SELECT j.id AS jawaban_id, j.ujian_id, j.peserta_id,
                   j.teks_jawaban, j.skor_essay, j.dinilai_at,
                   s.id AS soal_id, s.pertanyaan, s.jawaban_benar AS kunci_jawaban,
                   s.pembahasan, s.essay_bobot,
                   p.nama AS peserta_nama, p.kelas
            FROM soal s
            LEFT JOIN jawaban j ON j.soal_id = s.id AND j.ujian_id = $filterUjian
            LEFT JOIN peserta p ON p.id = COALESCE(j.peserta_id, $pesertaIdForDetail)
            WHERE s.id IN ($idsStr) AND s.tipe_soal = 'essay'
            ORDER BY FIELD(s.id, $idsStr)
        ");
    } else {
        // Fallback jika soal_order tidak tersedia
        $res = $conn->query("
            SELECT j.id AS jawaban_id, j.ujian_id, j.peserta_id,
                   j.teks_jawaban, j.skor_essay, j.dinilai_at,
                   s.id AS soal_id, s.pertanyaan, s.jawaban_benar AS kunci_jawaban,
                   s.pembahasan, s.essay_bobot,
                   p.nama AS peserta_nama, p.kelas
            FROM jawaban j
            JOIN soal s ON s.id = j.soal_id AND s.tipe_soal = 'essay'
            JOIN peserta p ON p.id = j.peserta_id
            WHERE j.ujian_id = $filterUjian
            ORDER BY s.id
        ");
    }
    if ($res) while ($r = $res->fetch_assoc()) $detailEssay[] = $r;

    // Ambil info ujian untuk header
    $eRes = $conn->query("
        SELECT h.*, p.nama, p.kelas, p.kode_peserta, s2.nama_sekolah
        FROM hasil_ujian h
        JOIN peserta p ON p.id = h.peserta_id
        LEFT JOIN sekolah s2 ON s2.id = p.sekolah_id
        WHERE h.ujian_id = $filterUjian LIMIT 1
    ");
    $editUjian = ($eRes && $eRes->num_rows > 0) ? $eRes->fetch_assoc() : null;
}

// Filter lists
// BUG FIX #14 (rev2): MySQL 8.x strict mode melempar exception saat compare
// string '0000-00-00' di WHERE clause. Ganti ke: filter YEAR(tanggal) > 0
// agar kompatibel dengan strict mode, lalu filter keterangan kosong di PHP.
$jadwalList  = $conn->query(
    "SELECT j.id, j.keterangan, j.tanggal FROM jadwal_ujian j
     WHERE j.keterangan IS NOT NULL AND j.keterangan != ''
       AND (j.tanggal IS NULL OR YEAR(j.tanggal) > 0)
     ORDER BY j.tanggal DESC, j.id DESC"
);
$kategoriList = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");

// Ambil daftar rombel/kelas unik dari peserta yang punya ujian essay
$kelasList = $conn->query(
    "SELECT DISTINCT p.kelas FROM peserta p
     JOIN hasil_ujian h ON h.peserta_id = p.id
     WHERE p.kelas IS NOT NULL AND p.kelas != ''
     ORDER BY p.kelas"
);

$pageTitle  = 'Penilaian Esai';
$activeMenu = 'nilai_essay';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
  <div>
    <h2><i class="bi bi-pencil-square me-2 text-success"></i>Penilaian Soal Esai</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
      <li class="breadcrumb-item active">Penilaian Esai</li>
    </ol></nav>
  </div>
</div>

<?= renderFlash() ?>

<?php if ($filterUjian && $editUjian): ?>
<!-- ══ MODE PENILAIAN: Detail jawaban peserta ══ -->
<div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
  <a href="<?= BASE_URL ?>/admin/nilai_essay.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Daftar
  </a>
  <div>
    <strong><?= htmlspecialchars($editUjian['nama']) ?></strong>
    <span class="text-muted">&nbsp;·&nbsp;<?= htmlspecialchars($editUjian['kelas']) ?></span>
    <span class="text-muted">&nbsp;·&nbsp;<?= htmlspecialchars($editUjian['nama_sekolah'] ?? '-') ?></span>
  </div>
  <span class="badge <?= $editUjian['essay_dinilai'] ? 'bg-success' : 'bg-warning text-dark' ?>">
    <?= $editUjian['essay_dinilai'] ? 'Selesai Dinilai' : 'Belum Selesai' ?>
  </span>
</div>

<?php if (empty($detailEssay)): ?>
<div class="alert alert-info">Peserta ini tidak memiliki jawaban esai.</div>
<?php else: ?>

<?php foreach ($detailEssay as $idx => $d):
  $sudahDinilai = $d['skor_essay'] !== null;
  $bobot = max(1, (int)($d['essay_bobot'] ?? 10));
?>
<div class="card mb-4 border-0 shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2
    <?= $sudahDinilai ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10' ?>">
    <div>
      <span class="badge bg-secondary me-2">Soal #<?= $idx + 1 ?></span>
      <span class="fw-semibold"><?= htmlspecialchars(mb_substr($d['pertanyaan'], 0, 80)) . (mb_strlen($d['pertanyaan']) > 80 ? '…' : '') ?></span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small">Bobot: <strong><?= $bobot ?> poin</strong></span>
      <?php if ($sudahDinilai): ?>
      <span class="badge bg-success">Dinilai: <?= $d['skor_essay'] ?>/<?= $bobot ?></span>
      <?php else: ?>
      <span class="badge bg-warning text-dark">Belum Dinilai</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">

    <!-- Pertanyaan lengkap -->
    <div class="mb-3 p-3 bg-light rounded" style="font-size:14px;line-height:1.8">
      <div class="text-muted small fw-bold mb-1">PERTANYAAN</div>
      <?= nl2br(htmlspecialchars($d['pertanyaan'])) ?>
    </div>

    <!-- Kunci jawaban / rubrik -->
    <?php if ($d['kunci_jawaban']): ?>
    <div class="mb-3 p-3 rounded" style="background:#eff6ff;border-left:4px solid #3b82f6;font-size:13.5px">
      <div class="text-primary small fw-bold mb-1">KUNCI / RUBRIK JAWABAN</div>
      <?= nl2br(htmlspecialchars($d['kunci_jawaban'])) ?>
    </div>
    <?php endif; ?>

    <!-- Pembahasan -->
    <?php if ($d['pembahasan']): ?>
    <div class="mb-3 p-3 rounded" style="background:#f0fdf4;border-left:4px solid #22c55e;font-size:13px">
      <div class="text-success small fw-bold mb-1">PEMBAHASAN</div>
      <?= nl2br(htmlspecialchars($d['pembahasan'])) ?>
    </div>
    <?php endif; ?>

    <!-- Jawaban peserta -->
    <div class="mb-3">
      <div class="text-dark fw-bold small mb-1">JAWABAN PESERTA</div>
      <?php if ($d['teks_jawaban']): ?>
      <div class="p-3 border rounded-3" style="min-height:80px;font-size:14px;line-height:1.8;white-space:pre-wrap;background:#fffdf0">
        <?= htmlspecialchars($d['teks_jawaban']) ?>
      </div>
      <?php else: ?>
      <div class="p-3 border rounded-3 text-muted fst-italic" style="background:#f8fafc">
        <i class="bi bi-dash-circle me-1"></i>Tidak dijawab
      </div>
      <?php endif; ?>
    </div>

    <!-- Form penilaian -->
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="aksi"       value="nilai">
      <input type="hidden" name="jawaban_id" value="<?= $d['jawaban_id'] ?? 0 ?>">
      <input type="hidden" name="soal_id"    value="<?= $d['soal_id'] ?>">
      <input type="hidden" name="ujian_id"   value="<?= $d['ujian_id'] ?? $filterUjian ?>">
      <input type="hidden" name="bobot"      value="<?= $bobot ?>">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div style="width:200px">
          <label class="form-label fw-semibold mb-1">
            Skor (0 – <?= $bobot ?>) <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <input type="number" name="skor" class="form-control"
                   min="0" max="<?= $bobot ?>" step="0.5" required
                   value="<?= $d['skor_essay'] ?? '' ?>"
                   placeholder="0–<?= $bobot ?>">
            <span class="input-group-text">/ <?= $bobot ?></span>
          </div>
        </div>
        <div class="mt-3 pt-1">
          <button type="submit" class="btn btn-<?= $sudahDinilai ? 'warning' : 'primary' ?> px-4">
            <i class="bi bi-save me-1"></i><?= $sudahDinilai ? 'Perbarui Nilai' : 'Simpan Nilai' ?>
          </button>
        </div>
        <?php if ($sudahDinilai): ?>
        <div class="mt-3 pt-1 text-muted small">
          <i class="bi bi-clock me-1"></i>Dinilai: <?= $d['dinilai_at'] ? date('d/m/Y H:i', strtotime($d['dinilai_at'])) : '-' ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($d['teks_jawaban']): ?>
      <!-- Skor cepat -->
      <div class="mt-2 d-flex gap-2 flex-wrap">
        <span class="text-muted small">Skor cepat:</span>
        <?php foreach ([0, round($bobot*0.25), round($bobot*0.5), round($bobot*0.75), $bobot] as $q): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary px-3"
                onclick="this.closest('form').querySelector('[name=skor]').value='<?= $q ?>'">
          <?= $q ?>
        </button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Tombol selesai penilaian -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="fw-semibold">Tandai penilaian esai selesai</div>
      <div class="text-muted small">Jawaban yang belum dinilai akan otomatis diberi skor 0.</div>
    </div>
    <form method="POST" onsubmit="return confirm('Tandai semua penilaian esai selesai?')">
      <?= csrfField() ?>
      <input type="hidden" name="aksi"     value="selesai_nilai">
      <input type="hidden" name="ujian_id" value="<?= $filterUjian ?>">
      <button type="submit" class="btn btn-success px-4">
        <i class="bi bi-check-circle me-1"></i>Selesai Penilaian
      </button>
    </form>
  </div>
</div>

<?php endif; // empty detailEssay ?>

<?php else: ?>
<!-- ══ MODE DAFTAR: Semua ujian dengan soal esai ══ -->

<!-- Filter -->
<div class="card mb-3"><div class="card-body py-2">
  <form class="d-flex flex-wrap gap-2" method="GET">
    <select name="jadwal_id" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
      <option value="">Semua Jadwal</option>
      <?php if($jadwalList) while($j=$jadwalList->fetch_assoc()): ?>
      <option value="<?= $j['id'] ?>" <?= $filterJadwal==$j['id']?'selected':'' ?>>
        <?php
          $_tgl = $j['tanggal'] && $j['tanggal'] !== '0000-00-00'
              ? date('d/m/y', strtotime($j['tanggal']))
              : '';
          $_label = trim($j['keterangan'] . ($_tgl ? ' ('.$_tgl.')' : ''));
        ?><?= htmlspecialchars($_label) ?>
      </option>
      <?php endwhile; ?>
    </select>
    <select name="kat" class="form-select form-select-sm" style="width:180px" onchange="this.form.submit()">
      <option value="">Semua Kategori</option>
      <?php if($kategoriList) while($k=$kategoriList->fetch_assoc()): ?>
      <option value="<?= $k['id'] ?>" <?= $filterKat==$k['id']?'selected':'' ?>>
        <?= htmlspecialchars($k['nama_kategori']) ?>
      </option>
      <?php endwhile; ?>
    </select>
    <select name="kelas" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
      <option value="">Semua Rombel</option>
      <?php if($kelasList) while($kl=$kelasList->fetch_assoc()): ?>
      <option value="<?= htmlspecialchars($kl['kelas']) ?>" <?= $filterKelas===$kl['kelas']?'selected':'' ?>>
        <?= htmlspecialchars($kl['kelas']) ?>
      </option>
      <?php endwhile; ?>
    </select>
    <select name="status" class="form-select form-select-sm" style="width:160px" onchange="this.form.submit()">
      <option value="">Semua Status</option>
      <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>⏳ Belum Dinilai</option>
      <option value="done"    <?= $filterStatus==='done'?'selected':'' ?>>✅ Sudah Dinilai</option>
    </select>
    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search me-1"></i>Filter</button>
    <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
  </form>
</div></div>

<div class="card border-0 shadow-sm">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      <i class="bi bi-list-check me-2"></i>Daftar Ujian dengan Soal Esai
      <?php if ($filterKelas): ?>
      <span class="badge bg-info text-dark ms-2">Rombel: <?= htmlspecialchars($filterKelas) ?></span>
      <?php endif; ?>
    </span>
    <span class="badge bg-primary"><?= $ujianList ? $ujianList->num_rows : 0 ?> ujian</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover datatable mb-0">
        <thead><tr>
          <th>#</th>
          <th>Peserta</th>
          <th>Kelas</th>
          <th>Kategori / Jadwal</th>
          <th class="text-center">Soal Esai</th>
          <th class="text-center">Sudah Dinilai</th>
          <th class="text-center">Nilai Akhir</th>
          <th class="text-center">Status</th>
          <th class="text-center">Aksi</th>
        </tr></thead>
        <tbody>
        <?php if ($ujianList && $ujianList->num_rows > 0): $no = 1; while ($row = $ujianList->fetch_assoc()):
          // BUG FIX: hitung jml_soal_essay dari soal_order jika tersedia,
          // agar soal essay yang tidak dijawab tetap terhitung
          $soalOrderArr = json_decode($row['soal_order'] ?? '[]', true) ?: [];
          if (!empty($soalOrderArr) && (int)($row['jml_soal_essay'] ?? 0) === 0) {
              // Hitung ulang dari soal_order — lebih akurat
              $idsForCount = implode(',', array_map('intval', $soalOrderArr));
              $cntRes = $conn->query("SELECT COUNT(*) AS c FROM soal WHERE id IN ($idsForCount) AND tipe_soal='essay'");
              $row['jml_soal_essay'] = $cntRes ? (int)$cntRes->fetch_assoc()['c'] : $row['jml_soal_essay'];
          }
        ?>
        <tr>
          <td><?= $no++ ?></td>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($row['peserta_nama']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($row['kode_peserta'] ?? '') ?></div>
          </td>
          <td><?= htmlspecialchars($row['kelas'] ?? '-') ?></td>
          <td>
            <div><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></div>
            <div class="text-muted small"><?= htmlspecialchars($row['jadwal_nama'] ?? '-') ?></div>
          </td>
          <td class="text-center fw-bold"><?= (int)($row['jml_soal_essay'] ?? 0) ?></td>
          <td class="text-center">
            <span class="<?= (int)$row['jml_dinilai'] >= (int)$row['jml_soal_essay'] && (int)$row['jml_soal_essay'] > 0 ? 'text-success fw-bold' : 'text-warning fw-bold' ?>">
              <?= (int)($row['jml_dinilai'] ?? 0) ?> / <?= (int)($row['jml_soal_essay'] ?? 0) ?>
            </span>
          </td>
          <td class="text-center">
            <?php if ($row['essay_dinilai']): ?>
              <span class="fw-bold text-success"><?= number_format((float)$row['nilai_akhir'], 1) ?></span>
            <?php else: ?>
              <span class="text-muted small">–</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($row['essay_dinilai']): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Selesai</span>
            <?php else: ?>
            <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <a href="?ujian_id=<?= $row['ujian_id'] ?>"
               class="btn btn-sm <?= $row['essay_dinilai'] ? 'btn-outline-secondary' : 'btn-primary' ?>">
              <i class="bi bi-pencil me-1"></i><?= $row['essay_dinilai'] ? 'Lihat/Edit' : 'Nilai' ?>
            </a>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="8" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-2 d-block mb-2"></i>
          Belum ada ujian dengan soal esai
        </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php endif; // filterUjian ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
