<?php
// ============================================================
// sekolah/rekap_nilai.php — Rekap Nilai Per Jadwal/Ujian
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

$filterKelas = trim($_GET['kelas'] ?? '');
$filterKat   = (int)($_GET['kategori_id'] ?? 0);

// ── Data sekolah ──────────────────────────────────────────────
$st = $conn->prepare("SELECT nama_sekolah, npsn, jenjang FROM sekolah WHERE id=? LIMIT 1");
$st->bind_param('i', $sekolahId); $st->execute();
$sekolah = $st->get_result()->fetch_assoc(); $st->close();

$jenjang = $sekolah['jenjang'] ?? 'SD';
$kodeSekolahRes = $conn->query("SELECT DISTINCT kode_sekolah FROM peserta WHERE sekolah_id=$sekolahId AND kode_sekolah IS NOT NULL AND kode_sekolah<>'' ORDER BY kode_sekolah LIMIT 1");
$kodeSekolah = ($kodeSekolahRes && $kodeSekolahRes->num_rows > 0) ? ($kodeSekolahRes->fetch_assoc()['kode_sekolah'] ?? '-') : '-';

// ── Daftar kelas & kategori untuk filter ─────────────────────
$kelasRes  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE sekolah_id=$sekolahId AND kelas IS NOT NULL ORDER BY kelas");
$kelasList = [];
if ($kelasRes) while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];

$katRes  = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katList = [];
if ($katRes) while ($k = $katRes->fetch_assoc()) $katList[] = $k;

$kkm = (int)getSetting($conn, 'kkm', '60');

// ── Query rekap per jadwal ────────────────────────────────────
$conds = ["p.sekolah_id = $sekolahId"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
$where = buildWhere($conds);

$rekapRes = $conn->query("
    SELECT
        COALESCE(k.nama_kategori, 'Umum') AS mapel,
        jd.tanggal,
        jd.jam_mulai,
        jd.jam_selesai,
        COUNT(h.id)                        AS jml_peserta,
        ROUND(AVG(h.nilai), 1)             AS rata,
        MAX(h.nilai)                       AS tertinggi,
        MIN(h.nilai)                       AS terendah,
        SUM(h.nilai >= $kkm)               AS lulus,
        COUNT(h.id) - SUM(h.nilai >= $kkm) AS tidak_lulus
    FROM hasil_ujian h
    JOIN peserta p   ON p.id  = h.peserta_id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id  = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    GROUP BY jd.id, k.id
    ORDER BY jd.tanggal DESC, k.nama_kategori
");
$rekapRows = [];
if ($rekapRes) while ($r = $rekapRes->fetch_assoc()) $rekapRows[] = $r;

// ── Rekap per kelas ───────────────────────────────────────────
$kelasRekapRes = $conn->query("
    SELECT
        p.kelas,
        COUNT(h.id)                       AS jml_peserta,
        ROUND(AVG(h.nilai), 1)            AS rata,
        MAX(h.nilai)                      AS tertinggi,
        MIN(h.nilai)                      AS terendah,
        SUM(h.nilai >= $kkm)             AS lulus,
        COUNT(h.id) - SUM(h.nilai >= $kkm) AS tidak_lulus
    FROM hasil_ujian h
    JOIN peserta p ON p.id = h.peserta_id
    $where
    GROUP BY p.kelas
    ORDER BY p.kelas
");
$kelasRekap = [];
if ($kelasRekapRes) while ($r = $kelasRekapRes->fetch_assoc()) $kelasRekap[] = $r;

$namaAplikasi   = getSetting($conn, 'nama_aplikasi',   'TKA Kecamatan');

// ── Handle Export Excel ───────────────────────────────────────
if (($_GET['export'] ?? '') === 'excel') {
    $namaSekolah = $sekolah['nama_sekolah'] ?? 'Sekolah';
    $filename    = 'rekap_nilai_' . preg_replace('/[^a-zA-Z0-9]/', '_', $namaSekolah) . '_' . date('Ymd') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8">
<style>
body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
table { border-collapse: collapse; width: 100%; }
.judul { font-size: 14pt; font-weight: bold; color: #1F4E79; text-align: center; }
.sub   { font-size: 10pt; color: #595959; font-style: italic; text-align: center; }
th { background-color: #1F4E79; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #FFFFFF; padding: 6px 8px; }
td { border: 1px solid #D9D9D9; padding: 4px 8px; vertical-align: middle; }
.center { text-align: center; }
.row-alt { background-color: #EBF3FB; }
.lulus { text-align: center; font-weight: bold; color: #166534; }
.gagal { text-align: center; font-weight: bold; color: #991B1B; }
.section-header { background-color: #E8F4E8; font-weight: bold; font-size: 12pt; color: #166534; padding: 6px 8px; }
</style></head><body>';

    // ── Tabel 1: Per Jadwal ──
    echo '<table>';
    echo '<tr><td colspan="11" class="judul">' . htmlspecialchars($namaAplikasi) . ' &mdash; Rekap Nilai</td></tr>';
    echo '<tr><td colspan="11" class="sub">' . htmlspecialchars($namaSekolah) . ' &middot; Kode Sekolah: ' . htmlspecialchars($kodeSekolah) . ' &middot; Dicetak: ' . date('d/m/Y H:i') .
         ($filterKelas ? ' &middot; Kelas: ' . htmlspecialchars($filterKelas) : '') . '</td></tr>';
    echo '<tr><td colspan="11"></td></tr>';
    echo '<tr><td colspan="11" class="section-header">Rekap Per Jadwal Ujian</td></tr>';
    echo '<tr>
        <th width="30">No</th>
        <th width="120">Mata Pelajaran</th>
        <th width="90">Tanggal</th>
        <th width="90">Jam</th>
        <th width="60">Peserta</th>
        <th width="70">Rata-rata</th>
        <th width="70">Tertinggi</th>
        <th width="70">Terendah</th>
        <th width="60">Lulus</th>
        <th width="70">Tdk Lulus</th>
        <th width="70">% Lulus</th>
    </tr>';
    $no = 1;
    foreach ($rekapRows as $r) {
        $alt = $no % 2 === 0 ? ' class="row-alt"' : '';
        $pct = $r['jml_peserta'] > 0 ? round($r['lulus'] / $r['jml_peserta'] * 100) : 0;
        $jam = $r['jam_mulai'] ? substr($r['jam_mulai'],0,5).'-'.substr($r['jam_selesai'],0,5) : '-';
        echo '<tr' . $alt . '>
            <td class="center">' . $no++ . '</td>
            <td>' . htmlspecialchars($r['mapel']) . '</td>
            <td class="center">' . ($r['tanggal'] ? date('d/m/Y', strtotime($r['tanggal'])) : '-') . '</td>
            <td class="center">' . $jam . '</td>
            <td class="center">' . $r['jml_peserta'] . '</td>
            <td class="center">' . $r['rata'] . '</td>
            <td class="lulus">' . $r['tertinggi'] . '</td>
            <td class="gagal">' . $r['terendah'] . '</td>
            <td class="lulus">' . $r['lulus'] . '</td>
            <td class="gagal">' . $r['tidak_lulus'] . '</td>
            <td class="center">' . $pct . '%</td>
        </tr>';
    }
    if (empty($rekapRows)) echo '<tr><td colspan="11" class="center">Tidak ada data</td></tr>';

    // ── Tabel 2: Per Kelas ──
    echo '<tr><td colspan="11"></td></tr>';
    echo '<tr><td colspan="8" class="section-header">Rekap Per Kelas</td></tr>';
    echo '<tr>
        <th>Kelas</th>
        <th width="60">Peserta</th>
        <th width="70">Rata-rata</th>
        <th width="70">Tertinggi</th>
        <th width="70">Terendah</th>
        <th width="60">Lulus</th>
        <th width="70">Tdk Lulus</th>
        <th width="70">% Lulus</th>
    </tr>';
    foreach ($kelasRekap as $i => $r) {
        $alt = $i % 2 === 0 ? '' : ' class="row-alt"';
        $pct = $r['jml_peserta'] > 0 ? round($r['lulus'] / $r['jml_peserta'] * 100) : 0;
        echo '<tr' . $alt . '>
            <td><strong>Kelas ' . htmlspecialchars($r['kelas'] ?? '-') . '</strong></td>
            <td class="center">' . $r['jml_peserta'] . '</td>
            <td class="center">' . $r['rata'] . '</td>
            <td class="lulus">' . $r['tertinggi'] . '</td>
            <td class="gagal">' . $r['terendah'] . '</td>
            <td class="lulus">' . $r['lulus'] . '</td>
            <td class="gagal">' . $r['tidak_lulus'] . '</td>
            <td class="center">' . $pct . '%</td>
        </tr>';
    }
    if (empty($kelasRekap)) echo '<tr><td colspan="8" class="center">Tidak ada data</td></tr>';

    echo '</table></body></html>';
    exit;
}


$activeMenu = 'rekap_nilai';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Rekap Nilai</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Rekap Nilai</li>
        </ol></nav>
    </div>
    <div>
        <?php $exportParams = http_build_query(array_filter(['kelas'=>$filterKelas,'kategori_id'=>$filterKat,'export'=>'excel'])); ?>
        <a href="?<?=$exportParams?>" class="btn btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Unduh Excel
        </a>
    </div>
</div>

<div class="alert alert-info py-2 mb-4">
    <strong>Kode Sekolah:</strong> <?= e($kodeSekolah) ?>
</div>

<?= renderFlash() ?>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap align-items-center" method="GET" id="formFilter">
            <select name="kelas" class="form-select form-select-sm" style="width:150px"
                    onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $kls): ?>
                <option value="<?=e($kls)?>" <?=$filterKelas===$kls?'selected':''?>>Kelas <?=e($kls)?></option>
                <?php endforeach; ?>
            </select>
            <select name="kategori_id" class="form-select form-select-sm" style="width:180px"
                    onchange="this.form.submit()">
                <option value="">Semua Mata Pelajaran</option>
                <?php foreach ($katList as $kat): ?>
                <option value="<?=$kat['id']?>" <?=$filterKat==$kat['id']?'selected':''?>><?=e($kat['nama_kategori'])?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Rekap per Jadwal -->
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-calendar-check me-2 text-primary"></i>Rekap Per Jadwal Ujian</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0">
                <thead><tr>
                    <th>#</th>
                    <th>Mata Pelajaran</th>
                    <th class="text-center">Tanggal</th>
                    <th class="text-center">Jam</th>
                    <th class="text-center">Peserta</th>
                    <th class="text-center">Rata-rata</th>
                    <th class="text-center">Tertinggi</th>
                    <th class="text-center">Terendah</th>
                    <th class="text-center">Lulus</th>
                    <th class="text-center">Tdk Lulus</th>
                    <th class="text-center">% Lulus</th>
                </tr></thead>
                <tbody>
                <?php if ($rekapRows): $no=1; foreach ($rekapRows as $r):
                    $pctLulus = $r['jml_peserta'] > 0 ? round($r['lulus']/$r['jml_peserta']*100) : 0;
                    [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['rata']);
                ?>
                <tr>
                    <td><?=$no++?></td>
                    <td><span class="badge bg-info text-dark"><?=e($r['mapel'])?></span></td>
                    <td class="text-center"><?=$r['tanggal']?date('d/m/Y',strtotime($r['tanggal'])):'-'?></td>
                    <td class="text-center"><?=$r['jam_mulai']?substr($r['jam_mulai'],0,5).'-'.substr($r['jam_selesai'],0,5):'-'?></td>
                    <td class="text-center fw-bold"><?=$r['jml_peserta']?></td>
                    <td class="text-center"><strong style="color:<?=$pcolor?>"><?=$r['rata']?></strong></td>
                    <td class="text-center text-success fw-bold"><?=$r['tertinggi']?></td>
                    <td class="text-center text-danger fw-bold"><?=$r['terendah']?></td>
                    <td class="text-center text-success fw-bold"><?=$r['lulus']?></td>
                    <td class="text-center text-danger fw-bold"><?=$r['tidak_lulus']?></td>
                    <td class="text-center">
                        <div class="progress" style="height:16px;min-width:80px">
                            <div class="progress-bar <?=$pctLulus>=60?'bg-success':'bg-danger'?>" style="width:<?=$pctLulus?>%">
                                <span style="font-size:10px"><?=$pctLulus?>%</span>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="11" class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data rekap
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Rekap per Kelas -->
<?php if ($kelasRekap): ?>
<div class="card">
    <div class="card-header"><i class="bi bi-people-fill me-2 text-success"></i>Rekap Per Kelas</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Kelas</th>
                    <th class="text-center">Peserta</th>
                    <th class="text-center">Rata-rata</th>
                    <th class="text-center">Tertinggi</th>
                    <th class="text-center">Terendah</th>
                    <th class="text-center">Lulus</th>
                    <th class="text-center">Tdk Lulus</th>
                    <th class="text-center">% Lulus</th>
                </tr></thead>
                <tbody>
                <?php foreach ($kelasRekap as $r):
                    $pct = $r['jml_peserta']>0 ? round($r['lulus']/$r['jml_peserta']*100) : 0;
                    [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['rata']);
                ?>
                <tr>
                    <td><strong>Kelas <?=e($r['kelas']??'-')?></strong></td>
                    <td class="text-center"><?=$r['jml_peserta']?></td>
                    <td class="text-center"><strong style="color:<?=$pcolor?>"><?=$r['rata']?></strong></td>
                    <td class="text-center text-success fw-bold"><?=$r['tertinggi']?></td>
                    <td class="text-center text-danger fw-bold"><?=$r['terendah']?></td>
                    <td class="text-center text-success fw-bold"><?=$r['lulus']?></td>
                    <td class="text-center text-danger fw-bold"><?=$r['tidak_lulus']?></td>
                    <td class="text-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:14px">
                                <div class="progress-bar <?=$pct>=60?'bg-success':'bg-danger'?>" style="width:<?=$pct?>%"></div>
                            </div>
                            <span style="font-size:12px;font-weight:700;min-width:36px"><?=$pct?>%</span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
