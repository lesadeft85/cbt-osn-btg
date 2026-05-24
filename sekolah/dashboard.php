<?php
// ============================================================
// sekolah/dashboard.php  — Dashboard Operator Sekolah
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

$_qSek = $conn->query("SELECT id, nama_sekolah, npsn, jenjang, alamat, telepon FROM sekolah WHERE id=$sekolahId LIMIT 1");
$sekolah = ($_qSek && $_qSek->num_rows > 0) ? $_qSek->fetch_assoc() : null;

// ── Statistik utama ───────────────────────────────────────────
$jmlPeserta = (int)$conn->query("SELECT COUNT(*) AS c FROM peserta WHERE sekolah_id=$sekolahId")->fetch_assoc()['c'];

$jmlSudah = (int)$conn->query("
    SELECT COUNT(DISTINCT p.id) AS c FROM peserta p
    JOIN hasil_ujian h ON h.peserta_id = p.id
    WHERE p.sekolah_id = $sekolahId AND h.nilai IS NOT NULL
")->fetch_assoc()['c'];

$sedang = (int)$conn->query("
    SELECT COUNT(*) AS c FROM ujian u JOIN peserta p ON p.id = u.peserta_id
    WHERE p.sekolah_id = $sekolahId AND u.waktu_selesai IS NULL AND u.waktu_mulai IS NOT NULL
")->fetch_assoc()['c'];

$_rRata = $conn->query("
    SELECT ROUND(AVG(h.nilai), 1) AS r FROM hasil_ujian h JOIN peserta p ON p.id = h.peserta_id
    WHERE p.sekolah_id = $sekolahId AND h.nilai IS NOT NULL
");
$rata = ($_rRata && $_rRata->num_rows > 0) ? ($_rRata->fetch_assoc()['r'] ?? 0) : 0;
if ($_rRata) $_rRata->free();

// ── Token & jadwal hari ini ────────────────────────────────────
$today   = date('Y-m-d');
$nowTime = date('H:i:s');

$_qTok = $conn->query(
    "SELECT token FROM token_ujian WHERE tanggal='$today' AND status='aktif' ORDER BY id DESC LIMIT 1"
);
$tokenHariIni = null;
if ($_qTok && $_qTok->num_rows > 0) $tokenHariIni = $_qTok->fetch_assoc();
if ($_qTok) $_qTok->free();

$_qJA = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, status, kategori_id,
            IFNULL(keterangan,'') AS keterangan
     FROM jadwal_ujian
     WHERE tanggal='$today' AND jam_mulai<='$nowTime' AND jam_selesai>='$nowTime'
       AND status='aktif' LIMIT 1"
);
$jadwalAktif = null;
if ($_qJA && $_qJA->num_rows > 0) $jadwalAktif = $_qJA->fetch_assoc();
if ($_qJA) $_qJA->free();

// ── Trend 7 hari ──────────────────────────────────────────────
$trendRes = $conn->query("
    SELECT DATE(h.waktu_selesai) AS tgl, COUNT(*) AS jml, ROUND(AVG(h.nilai), 1) AS rata
    FROM hasil_ujian h JOIN peserta p ON p.id = h.peserta_id
    WHERE p.sekolah_id = $sekolahId AND h.nilai IS NOT NULL
      AND h.waktu_selesai >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY tgl ORDER BY tgl
");
$tLabels = []; $tRata = [];
if ($trendRes) while ($t = $trendRes->fetch_assoc()) {
    $tLabels[] = date('d/m', strtotime($t['tgl']));
    $tRata[]   = $t['rata'];
}

// ── 5 nilai terbaru peserta sekolah ini ───────────────────────
$nilaiTerbaru = $conn->query("
    SELECT h.nilai, h.waktu_selesai, p.nama, p.kelas, p.kode_sekolah,
           COALESCE(k.nama_kategori, '') AS nama_kategori
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    WHERE p.sekolah_id = $sekolahId AND h.nilai IS NOT NULL
    ORDER BY h.waktu_selesai DESC LIMIT 5
");

// ── Rekap per kelas ───────────────────────────────────────────
$kelasStatRes = $conn->query("
    SELECT p.kelas,
           COUNT(DISTINCT p.id) AS total,
           COUNT(DISTINCT CASE WHEN h.nilai IS NOT NULL THEN p.id END) AS sudah,
           ROUND(AVG(CASE WHEN h.nilai IS NOT NULL THEN h.nilai END), 1) AS rata
    FROM peserta p
    LEFT JOIN hasil_ujian h ON h.peserta_id = p.id
    WHERE p.sekolah_id = $sekolahId AND p.kelas IS NOT NULL AND p.kelas != ''
    GROUP BY p.kelas ORDER BY p.kelas
");
$kelasStat = [];
if ($kelasStatRes) while ($r = $kelasStatRes->fetch_assoc()) $kelasStat[] = $r;

// ── Peserta belum ujian ───────────────────────────────────────
$belumUjianRes = $conn->query("\
    SELECT p.nama, p.kelas, p.kode_peserta, p.kode_sekolah\
    FROM peserta p
    LEFT JOIN hasil_ujian h ON h.peserta_id = p.id AND h.nilai IS NOT NULL
    WHERE p.sekolah_id = $sekolahId AND h.id IS NULL
    ORDER BY p.kelas, p.nama LIMIT 10
");
$belumUjianList = [];
if ($belumUjianRes) while ($r = $belumUjianRes->fetch_assoc()) $belumUjianList[] = $r;

$pageTitle  = 'Dashboard Sekolah';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card mb-4 border-0"
     style="background:linear-gradient(135deg,#0e9f6e 0%,#0ea5e9 100%);color:#fff;overflow:hidden;position:relative">
    <div class="card-body py-4 px-4">
        <div class="d-flex align-items-center gap-3">
            <div style="font-size:44px;line-height:1">🏫</div>
            <div>
                <h4 class="fw-800 mb-1" style="color:#fff">
                    <?= e($sekolah['nama_sekolah'] ?? 'Sekolah Anda') ?>
                </h4>
                <p class="mb-0 small" style="color:rgba(255,255,255,.85)">
                    <?= date('l, d F Y') ?> &nbsp;·&nbsp; Selamat datang, <?= e($user['nama']) ?>!
                </p>
            </div>
            <a href="<?= BASE_URL ?>/sekolah/profil.php"
               class="btn btn-light btn-sm ms-auto text-success fw-700 no-print">
                <i class="bi bi-building me-1"></i>Lihat Profil
            </a>
        </div>
    </div>
    <div style="position:absolute;top:-20px;right:-20px;width:100px;height:100px;
                border-radius:50%;background:rgba(255,255,255,.06)"></div>
</div>

<?= renderFlash() ?>

<!-- Status CBT -->
<?php if (is_array($jadwalAktif) || is_array($tokenHariIni)): ?>
<div class="row g-3 mb-4">
    <?php if (is_array($jadwalAktif)): ?>
    <div class="col-md-6">
        <div class="alert alert-success d-flex align-items-center gap-3 mb-0">
            <span class="live-badge">● LIVE</span>
            <div>
                <strong>Ujian sedang berlangsung!</strong>
                <div class="text-sm">
                    <?= substr($jadwalAktif['jam_mulai'], 0, 5) ?>–<?= substr($jadwalAktif['jam_selesai'], 0, 5) ?>
                    (<?= $jadwalAktif['durasi_menit'] ?> menit)
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if (is_array($tokenHariIni)): ?>
    <div class="col-md-6">
        <div class="alert alert-warning d-flex align-items-center gap-3 mb-0">
            <i class="bi bi-key-fill fs-4"></i>
            <div>
                <strong>Token Ujian Hari Ini</strong>
                <div><code class="fw-800" style="font-size:16px;letter-spacing:3px">
                    <?= e($tokenHariIni['token']) ?>
                </code></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($sedang > 0): ?>
<div class="alert alert-info mb-4">
    <i class="bi bi-hourglass-split me-2"></i>
    <strong><?= $sedang ?> peserta</strong> dari sekolah Anda sedang mengerjakan ujian saat ini.
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/sekolah/peserta.php" class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="stat-label">Total Peserta</div>
                <div class="stat-value"><?= $jmlPeserta ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Sudah Ujian</div>
                <div class="stat-value"><?= $jmlSudah ?></div>
                <div class="stat-sub"><?= $jmlPeserta > 0 ? round($jmlSudah / $jmlPeserta * 100) : 0 ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-clock-fill"></i></div>
            <div>
                <div class="stat-label">Belum Ujian</div>
                <div class="stat-value"><?= $jmlPeserta - $jmlSudah ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-bar-chart-fill"></i></div>
            <div>
                <div class="stat-label">Rata-rata Nilai</div>
                <div class="stat-value"><?= $rata ?: '-' ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Bar -->
<?php if ($jmlPeserta > 0):
      $pct = round($jmlSudah / $jmlPeserta * 100); ?>
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between mb-2">
            <span class="fw-700">Progress Keikutsertaan Ujian</span>
            <span class="fw-700 text-primary"><?= $pct ?>%</span>
        </div>
        <div class="progress" style="height:12px">
            <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="d-flex justify-content-between mt-2 text-muted text-xs">
            <span><?= $jmlSudah ?> selesai</span>
            <?php if ($sedang): ?>
            <span class="text-danger">● <?= $sedang ?> sedang ujian</span>
            <?php endif; ?>
            <span><?= $jmlPeserta - $jmlSudah ?> belum</span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Rekap per Kelas -->
    <?php if ($kelasStat): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people-fill me-2 text-success"></i>Rekap Per Ruang</span>
                <a href="<?=BASE_URL?>/sekolah/rekap_nilai.php" class="btn btn-sm btn-outline-success">Detail</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 table-sm">
                    <thead><tr>
                        <th>Ruang</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Sudah</th>
                        <th class="text-center">Rata-rata</th>
                        <th>Progress</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($kelasStat as $ks):
                        $pct = $ks['total'] > 0 ? round($ks['sudah'] / $ks['total'] * 100) : 0;
                    ?>
                    <tr>
                        <td><strong>Ruang <?= e($ks['kelas']) ?></strong></td>
                        <td class="text-center"><?= $ks['total'] ?></td>
                        <td class="text-center text-success fw-bold"><?= $ks['sudah'] ?></td>
                        <td class="text-center"><?= $ks['rata'] ?? '-' ?></td>
                        <td style="min-width:80px">
                            <div class="d-flex align-items-center gap-1">
                                <div class="progress flex-grow-1" style="height:10px">
                                    <div class="progress-bar <?=$pct>=60?'bg-success':'bg-warning'?>" style="width:<?=$pct?>%"></div>
                                </div>
                                <span style="font-size:10px;min-width:28px"><?=$pct?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Belum Ujian -->
    <div class="col-lg-<?= $kelasStat ? '6' : '12' ?>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-fill me-2 text-warning"></i>Belum Ujian
                    <span class="badge bg-warning text-dark ms-1"><?= $jmlPeserta - $jmlSudah ?></span>
                </span>
                <a href="<?=BASE_URL?>/sekolah/peserta.php" class="btn btn-sm btn-outline-warning">Semua</a>
            </div>
            <div class="card-body p-0">
                <?php if ($belumUjianList): ?>
                <table class="table table-hover table-sm mb-0">
                    <thead><tr><th>Nama</th><th>Ruang</th><th>Kode Peserta</th><th>Kode Sekolah</th></tr></thead>
                    <tbody>
                    <?php foreach ($belumUjianList as $b): ?>
                    <tr>
                        <td><?= e($b['nama']) ?></td>
                        <td><?= e($b['kelas'] ?? '-') ?></td>
                        <td><code class="small"><?= e($b['kode_peserta']) ?></code></td>
                        <td><code class="small"><?= e($b['kode_sekolah'] ?? '-') ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (($jmlPeserta - $jmlSudah) > 10): ?>
                    <tr><td colspan="4" class="text-center text-muted small py-2">
                        ... dan <?= ($jmlPeserta - $jmlSudah - 10) ?> peserta lainnya
                    </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center py-4 text-success">
                    <i class="bi bi-check-circle-fill fs-2 d-block mb-2"></i>
                    Semua peserta sudah ujian! 🎉
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <!-- Tren 7 hari -->
    <?php if ($tLabels): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-graph-up me-2 text-success"></i>Tren Nilai 7 Hari
            </div>
            <div class="card-body"><canvas id="chartTren" height="160"></canvas></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Nilai Terbaru -->
    <div class="col-lg-<?= $tLabels ? '6' : '12' ?>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-2"></i>Nilai Terbaru</span>
                <a href="<?= BASE_URL ?>/sekolah/hasil.php" class="btn btn-sm btn-outline-primary">Semua</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Peserta</th><th class="text-center">Nilai</th><th class="text-center">Kode Sekolah</th><th>Waktu</th></tr></thead>
                    <tbody>
                    <?php if ($nilaiTerbaru && $nilaiTerbaru->num_rows > 0):
                          while ($r = $nilaiTerbaru->fetch_assoc()):
                              [$ph, $pt, $pb] = getPredikat((int)$r['nilai']); ?>
                    <tr>
                        <td>
                            <strong><?= e($r['nama']) ?></strong><br>
                            <span class="text-muted text-xs"><?= e($r['kelas'] ?? '') ?></span>
                            <?php if (!empty($r['nama_kategori'])): ?>
                            <span class="badge bg-info text-dark ms-1" style="font-size:10px">
                                <?= e($r['nama_kategori']) ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $pb ?> fs-6"><?= $r['nilai'] ?></span>
                        </td>
                        <td class="text-center"><code style="font-size:11px"><?= e($r['kode_sekolah'] ?? '-') ?></code></td>
                        <td class="text-muted text-xs">
                            <?= $r['waktu_selesai'] ? date('d/m H:i', strtotime($r['waktu_selesai'])) : '-' ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada ujian</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if ($tLabels): ?>
<script>
new Chart(document.getElementById('chartTren'), {
    type: 'line',
    data: {
        labels: <?= json_encode($tLabels) ?>,
        datasets: [{
            label: 'Rata-rata Nilai',
            data:  <?= json_encode($tRata) ?>,
            borderColor: '#0e9f6e', backgroundColor: 'rgba(14,159,110,.08)',
            fill: true, tension: .4, pointRadius: 4, pointBackgroundColor: '#0e9f6e',
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, max: 100 } }
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
