<?php
// ============================================================
// sekolah/cetak_daftar_hadir.php — Cetak Daftar Hadir Peserta
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

$st = $conn->prepare("SELECT nama_sekolah, npsn, jenjang, alamat FROM sekolah WHERE id=? LIMIT 1");
$st->bind_param('i', $sekolahId); $st->execute();
$sekolah = $st->get_result()->fetch_assoc(); $st->close();

$kelasRes  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE sekolah_id=$sekolahId AND kelas IS NOT NULL ORDER BY kelas");
$kelasList = [];
if ($kelasRes) while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];

$katRes  = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katList = [];
if ($katRes) while ($k = $katRes->fetch_assoc()) $katList[] = $k;

// ── Query peserta ─────────────────────────────────────────────
$conds = ["p.sekolah_id = $sekolahId"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
$where = buildWhere($conds);

$pesertaRes = $conn->query("
    SELECT p.nama, p.kelas, p.kode_peserta, p.kode_sekolah
    FROM peserta p $where
    ORDER BY p.kelas, p.nama
");
$pesertaList = [];
if ($pesertaRes) while ($r = $pesertaRes->fetch_assoc()) $pesertaList[] = $r;

$namaAplikasi   = getSetting($conn, 'nama_aplikasi',   'TKA Kecamatan');
$namaKecamatan  = getSetting($conn, 'nama_kecamatan',  'Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$lgF = getSetting($conn, 'logo_file_path', '');
$lgU = getSetting($conn, 'logo_url', '');
$logoAktif = $lgF ? BASE_URL.'/'.$lgF : $lgU;

$mapelLabel = '';
if ($filterKat) {
    $kr = $conn->query("SELECT nama_kategori FROM kategori_soal WHERE id=$filterKat LIMIT 1");
    if ($kr && $kr->num_rows > 0) $mapelLabel = $kr->fetch_assoc()['nama_kategori'];
}

$pageTitle  = 'Cetak Daftar Hadir';
$activeMenu = 'cetak_daftar_hadir';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .sidebar, .topbar, .main-wrapper > *:not(.content-area) { display: none !important; }
    .content-area { padding: 0 !important; margin: 0 !important; }
    @page { margin: 10mm 14mm; size: A4 portrait; }
    .print-card { border: none !important; box-shadow: none !important; }
}
</style>

<div class="page-header no-print">
    <div>
        <h2><i class="bi bi-printer me-2 text-primary"></i>Cetak Daftar Hadir</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Daftar Hadir</li>
        </ol></nav>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary">
            <i class="bi bi-printer me-1"></i>Cetak / Simpan PDF
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4 no-print">
    <div class="card-body py-2">
        <form class="d-flex gap-2 flex-wrap align-items-center" method="GET">
            <select name="kelas" class="form-select form-select-sm" style="width:150px">
                <option value="">Semua Ruang</option>
                <?php foreach ($kelasList as $kls): ?>
                <option value="<?=e($kls)?>" <?=$filterKelas===$kls?'selected':''?>>Ruang <?=e($kls)?></option>
                <?php endforeach; ?>
            </select>
            <select name="kategori_id" class="form-select form-select-sm" style="width:180px">
                <option value="">Semua Tes</option>
                <?php foreach ($katList as $kat): ?>
                <option value="<?=$kat['id']?>" <?=$filterKat==$kat['id']?'selected':''?>><?=e($kat['nama_kategori'])?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
        </form>
    </div>
</div>

<!-- Daftar Hadir -->
<div class="card print-card">
    <div class="card-body">

        <!-- Kop -->
        <div style="display:flex;align-items:center;gap:16px;border-bottom:3px solid #1e40af;padding-bottom:12px;margin-bottom:16px">
            <?php if ($logoAktif): ?>
            <img src="<?=e($logoAktif)?>" style="width:60px;height:60px;object-fit:contain" alt="Logo">
            <?php else: ?>
            <div style="width:60px;height:60px;background:#1e40af;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:28px;color:#fff">🏫</div>
            <?php endif; ?>
            <div>
                <div style="font-size:15px;font-weight:800;color:#1e40af;text-transform:uppercase"><?=e($namaAplikasi)?></div>
                <div style="font-size:12px;color:#374151;font-weight:600"><?=e($sekolah['nama_sekolah']??'')?><?=!empty($sekolah['npsn'])?' — NPSN: '.e($sekolah['npsn']):''?></div>
                <div style="font-size:10px;color:#6b7280"><?=e($sekolah['alamat']??'')?> &nbsp;·&nbsp; Tahun Pelajaran <?=e($tahunPelajaran)?></div>
            </div>
        </div>

        <!-- Judul -->
        <div style="text-align:center;margin-bottom:16px">
            <div style="font-size:15px;font-weight:800;text-transform:uppercase;color:#1e293b">Daftar Hadir Peserta Ujian</div>
            <div style="font-size:12px;color:#475569;margin-top:4px">
                <?=e($namaKecamatan)?>
                <?php if ($mapelLabel): ?> &nbsp;·&nbsp; <?=e($mapelLabel)?><?php endif; ?>
                <?php if ($filterKelas): ?> &nbsp;·&nbsp; Sekolah <?=e($filterKelas)?><?php endif; ?>
                &nbsp;·&nbsp; <?=date('d F Y')?>
            </div>
        </div>

        <!-- Info -->
        <div style="display:flex;gap:24px;margin-bottom:14px;font-size:12px;flex-wrap:wrap">
            <div><span style="color:#64748b">Lomba Mapel:</span> <strong><?=e($sekolah['nama_sekolah']??'')?></strong></div>
            <div><span style="color:#64748b">Jumlah Peserta:</span> <strong><?=count($pesertaList)?></strong></div>
            <div><span style="color:#64748b">Tanggal:</span> <strong><?=date('d F Y')?></strong></div>
        </div>

        <!-- Tabel -->
        <table style="width:100%;border-collapse:collapse;font-size:11.5px">
            <thead>
                <tr style="background:#1e40af;color:#fff">
                    <th style="padding:8px 10px;text-align:center;width:40px;border:1px solid #1547b8">No</th>
                    <th style="padding:8px 10px;border:1px solid #1547b8">Nama Peserta</th>
                    <th style="padding:8px 10px;text-align:center;width:110px;border:1px solid #1547b8">Kode Peserta</th>
                    <th style="padding:8px 10px;text-align:center;width:110px;border:1px solid #1547b8"> Sekolah</th>
                    <th style="padding:8px 10px;text-align:center;width:80px;border:1px solid #1547b8">Ruang</th>
                    <th style="padding:8px 10px;text-align:center;width:80px;border:1px solid #1547b8">Tanda Tangan</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($pesertaList): $no=1; foreach ($pesertaList as $p):
                $bg = $no%2===0 ? '#f8fafc' : '#fff';
            ?>
            <tr style="background:<?=$bg?>">
                <td style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0;font-weight:700"><?=$no++?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0;font-weight:600"><?=e($p['nama'])?></td>
                <td style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0;font-family:monospace;font-weight:700;color:#1e40af;font-size:11px"><?=e($p['kode_peserta']??'-')?></td>
                <td style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0"><?=e($p['kode_sekolah']??'-')?></td>
                <td style="padding:7px 10px;text-align:center;border:1px solid #e2e8f0"><?=e($p['kelas']??'-')?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0;height:32px">&nbsp;</td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;padding:20px;color:#94a3b8">Belum ada peserta terdaftar</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- TTD -->
        <div style="display:flex;justify-content:space-between;margin-top:32px;font-size:11px">
            <div>
                <p style="margin:0;color:#6b7280">Catatan:</p>
                <div style="border:1px solid #e2e8f0;width:200px;height:60px;border-radius:4px;margin-top:4px"></div>
            </div>
            <div style="text-align:center">
                <p style="margin:0"><?=e($namaKecamatan)?>, <?=date('d F Y')?></p>
                <p style="margin:4px 0">Pengawas Ujian</p>
                <div style="height:52px"></div>
                <div style="border-bottom:1px solid #374151;width:180px;margin:0 auto"></div>
                <p style="margin:4px 0">(______________________)</p>
            </div>
            <div style="text-align:center">
                <p style="margin:0">Mengetahui,</p>
                <p style="margin:4px 0">Panitia Ujian</p>
                <div style="height:52px"></div>
                <div style="border-bottom:1px solid #374151;width:180px;margin:0 auto"></div>
            </div>
        </div>

        <div style="margin-top:16px;border-top:1px solid #e2e8f0;padding-top:8px;font-size:9px;color:#94a3b8;text-align:right">
            Dicetak dari <?=e($namaAplikasi)?> &nbsp;·&nbsp; <?=date('d F Y H:i')?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
