<?php
// ============================================================
// sekolah/monitoring.php — Monitoring Peserta Ujian Live
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

// ── Peserta sedang ujian ──────────────────────────────────────
$sedangRes = $conn->query("
    SELECT u.id AS ujian_id, u.waktu_mulai, u.last_activity, u.pelanggaran,
           p.nama, p.kelas, p.kode_peserta, p.kode_sekolah,
           COALESCE(k.nama_kategori,'-') AS mapel,
           jd.jam_selesai, jd.tanggal
    FROM ujian u
    JOIN peserta p ON p.id = u.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = u.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = jd.kategori_id
    WHERE p.sekolah_id = $sekolahId
      AND u.waktu_selesai IS NULL
      AND u.waktu_mulai IS NOT NULL
    ORDER BY u.waktu_mulai ASC
");
$sedang = [];
if ($sedangRes) while ($r = $sedangRes->fetch_assoc()) $sedang[] = $r;

// ── Peserta selesai hari ini ──────────────────────────────────
$today     = date('Y-m-d');
$selesaiRes = $conn->query("
    SELECT h.nilai, h.waktu_selesai, h.jml_benar, h.total_soal,
           p.nama, p.kelas, p.kode_peserta, p.kode_sekolah,
           COALESCE(k.nama_kategori,'-') AS mapel
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    WHERE p.sekolah_id = $sekolahId
      AND DATE(h.waktu_selesai) = '$today'
    ORDER BY h.waktu_selesai DESC
");
$selesai = [];
if ($selesaiRes) while ($r = $selesaiRes->fetch_assoc()) $selesai[] = $r;

// ── Belum mulai ───────────────────────────────────────────────
$belumRes = $conn->query("
    SELECT p.nama, p.kelas, p.kode_peserta, p.kode_sekolah
    FROM peserta p
    WHERE p.sekolah_id = $sekolahId
      AND p.id NOT IN (
        SELECT DISTINCT peserta_id FROM ujian WHERE DATE(waktu_mulai) = '$today'
      )
    ORDER BY p.kelas, p.nama
");
$belum = [];
if ($belumRes) while ($r = $belumRes->fetch_assoc()) $belum[] = $r;

$kkm          = (int)getSetting($conn, 'kkm', '60');
$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');

$pageTitle  = 'Monitoring Ujian';
$activeMenu = 'monitoring';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-activity me-2 text-danger"></i>Monitoring Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Monitoring</li>
        </ol></nav>
    </div>
    <div>
        <button onclick="location.reload()" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
        <span class="badge bg-secondary ms-2" id="waktuUpdate">–</span>
    </div>
</div>

<?= renderFlash() ?>

<!-- Stat -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#ef4444,#dc2626)"><i class="bi bi-activity"></i></div>
            <div><div class="stat-label">Sedang Ujian</div><div class="stat-value text-danger"><?=count($sedang)?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
            <div><div class="stat-label">Selesai Hari Ini</div><div class="stat-value text-success"><?=count($selesai)?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-hourglass"></i></div>
            <div><div class="stat-label">Belum Mulai</div><div class="stat-value text-warning"><?=count($belum)?></div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
            <div><div class="stat-label">Total Hari Ini</div><div class="stat-value"><?=count($sedang)+count($selesai)?></div></div>
        </div>
    </div>
</div>

<!-- Sedang Ujian -->
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <span class="live-badge">● LIVE</span>
        <span>Peserta Sedang Mengerjakan Ujian</span>
        <span class="badge bg-danger ms-auto"><?=count($sedang)?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>#</th><th>Nama</th><th class="text-center">Kode Peserta</th><th class="text-center">Kode Sekolah</th>
                    <th class="text-center">Ruang</th><th>Mapel</th><th class="text-center">Mulai</th>
                    <th class="text-center">Durasi</th><th class="text-center">Aktif Terakhir</th>
                    <th class="text-center">Pelanggaran</th><th class="text-center">Status</th>
                </tr></thead>
                <tbody>
                <?php if ($sedang): $no=1; foreach ($sedang as $r):
                    $durasi    = (int)floor((time() - strtotime($r['waktu_mulai'])) / 60);
                    $lastAct   = $r['last_activity'] ? (int)floor((time() - strtotime($r['last_activity'])) / 60) : null;
                    $isIdle    = $lastAct !== null && $lastAct > 5;
                    $sisaMenit = null;
                    if ($r['jam_selesai'] && $r['tanggal']) {
                        $sisaDetik = strtotime($r['tanggal'].' '.$r['jam_selesai']) - time();
                        $sisaMenit = max(0, (int)ceil($sisaDetik/60));
                    }
                ?>
                <tr <?=$isIdle?"style='background:#fef9c3'":''?>>
                    <td><?=$no++?></td>
                    <td>
                        <strong><?=e($r['nama'])?></strong>
                    </td>
                    <td class="text-center"><code style="font-size:10px;color:#64748b"><?=e($r['kode_peserta'])?></code></td>
                    <td class="text-center"><code style="font-size:10px;color:#64748b"><?=e($r['kode_sekolah']??'-')?></code></td>
                    <td class="text-center"><?=e($r['kelas']??'-')?></td>
                    <td><span class="badge bg-info text-dark" style="font-size:11px"><?=e($r['mapel'])?></span></td>
                    <td class="text-center"><small><?=date('H:i',strtotime($r['waktu_mulai']))?></small></td>
                    <td class="text-center"><span class="badge bg-primary"><?=$durasi?> mnt</span></td>
                    <td class="text-center">
                        <?php if ($lastAct !== null): ?>
                        <span class="badge <?=$isIdle?'bg-warning text-dark':'bg-success'?>">
                            <?=$lastAct?>m lalu
                        </span>
                        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($r['pelanggaran'] > 0): ?>
                        <span class="badge bg-danger"><?=$r['pelanggaran']?>x</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($sisaMenit !== null): ?>
                        <span class="badge <?=$sisaMenit<=5?'bg-danger':'bg-success'?>">
                            Sisa <?=$sisaMenit?> mnt
                        </span>
                        <?php else: ?>
                        <span class="badge bg-primary">Aktif</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="10" class="text-center text-muted py-4">
                    <i class="bi bi-check2-circle fs-2 d-block mb-2 text-success"></i>
                    Tidak ada peserta yang sedang ujian saat ini
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
<!-- Selesai Hari Ini -->
<div class="col-lg-7">
<div class="card">
    <div class="card-header"><i class="bi bi-check-circle-fill me-2 text-success"></i>Selesai Hari Ini <span class="badge bg-success ms-1"><?=count($selesai)?></span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Nama</th><th class="text-center">Kode Peserta</th><th class="text-center">Kode Sekolah</th>
                    <th class="text-center">Ruang</th><th class="text-center">Nilai</th><th class="text-center">Selesai</th>
                </tr></thead>
                <tbody>
                <?php if ($selesai): foreach ($selesai as $r):
                    [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['nilai']);
                ?>
                <tr>
                    <td><strong><?=e($r['nama'])?></strong></td>
                    <td class="text-center"><code style="font-size:10px;color:#64748b"><?=e($r['kode_peserta'])?></code></td>
                    <td class="text-center"><code style="font-size:10px;color:#64748b"><?=e($r['kode_sekolah']??'-')?></code></td>
                    <td class="text-center"><?=e($r['kelas']??'-')?></td>
                    <td class="text-center">
                        <strong style="color:<?=$pcolor?>;font-size:15px"><?=$r['nilai']?></strong>
                        <div style="font-size:10px">
                            <?php if ($r['nilai']>=$kkm): ?>
                            <span class="text-success">✓ Lulus</span>
                            <?php else: ?>
                            <span class="text-danger">✗ Tidak</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center"><small class="text-muted"><?=date('H:i',strtotime($r['waktu_selesai']))?></small></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" class="text-center text-muted py-3">Belum ada yang selesai</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Belum Mulai -->
<div class="col-lg-5">
<div class="card">
    <div class="card-header"><i class="bi bi-hourglass me-2 text-warning"></i>Belum Mulai Hari Ini <span class="badge bg-warning text-dark ms-1"><?=count($belum)?></span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Nama</th><th class="text-center">Kode Peserta</th><th class="text-center">Kode Sekolah</th><th class="text-center">Ruang</th></tr></thead>
                <tbody>
                <?php if ($belum): foreach ($belum as $r): ?>
                <tr>
                    <td><strong><?=e($r['nama'])?></strong></td>
                    <td class="text-center"><code style="font-size:10px;color:#94a3b8"><?=e($r['kode_peserta'])?></code></td>
                    <td class="text-center"><code style="font-size:10px;color:#94a3b8"><?=e($r['kode_sekolah']??'-')?></code></td>
                    <td class="text-center"><?=e($r['kelas']??'-')?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" class="text-center text-muted py-3">
                    <i class="bi bi-check2-all text-success me-1"></i>Semua peserta sudah mulai
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</div>

<script>
// Update waktu
function updateWaktu() {
    const now = new Date();
    document.getElementById('waktuUpdate').textContent =
        'Update: ' + now.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
updateWaktu();
// Auto refresh setiap 60 detik
setTimeout(() => location.reload(), 60000);
setInterval(updateWaktu, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
