<?php
// ============================================================
// sekolah/kartu_ujian.php — Cetak Kartu Ujian (Akun Sekolah)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

// ── Filter ────────────────────────────────────────────────────
$filterKelas = trim($_GET['kelas'] ?? '');
$filterId    = (int)($_GET['peserta_id'] ?? 0);

// ── Query peserta ─────────────────────────────────────────────
$conds = ["p.sekolah_id = $sekolahId"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterId)    $conds[] = "p.id = $filterId";
$where = 'WHERE ' . implode(' AND ', $conds);

$res = $conn->query("
  SELECT p.id, p.nama, p.kelas, p.kode_peserta, p.kode_sekolah, s.nama_sekolah, s.npsn
    FROM peserta p LEFT JOIN sekolah s ON s.id = p.sekolah_id
    $where ORDER BY p.kelas, p.nama
");
$peserta = [];
if ($res) while ($r = $res->fetch_assoc()) $peserta[] = $r;

// ── Kelas unik ────────────────────────────────────────────────
$kelasRes  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE sekolah_id=$sekolahId AND kelas IS NOT NULL ORDER BY kelas");
$kelasList = [];
if ($kelasRes) while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];

// ── Pengaturan ────────────────────────────────────────────────
$namaAplikasi    = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaKecamatan   = getSetting($conn, 'nama_kecamatan', 'Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$tahunAjaran       = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.((int)date('Y')+1));
$jumlahSoal        = (int)getSetting($conn, 'jumlah_soal', '40');
$logoFilePath      = getSetting($conn, 'logo_file_path', '');
$logoUrl           = getSetting($conn, 'logo_url', '');
$logoAktif         = $logoFilePath ? BASE_URL.'/'.$logoFilePath : $logoUrl;

// ── Jadwal aktif ──────────────────────────────────────────────
$jadwalList = [];
$jr = $conn->query("
    SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.durasi_menit,
           j.kategori_id, k.nama_kategori
    FROM jadwal_ujian j
    LEFT JOIN kategori_soal k ON k.id = j.kategori_id
    WHERE j.status='aktif'
    ORDER BY j.tanggal ASC, j.jam_mulai ASC
");
if ($jr) {
    while ($r = $jr->fetch_assoc()) {
        $jml = 0;
        if ($r['kategori_id']) {
            $qs = $conn->query("SELECT COUNT(*) as c FROM soal WHERE kategori_id = " . (int)$r['kategori_id']);
            if ($qs) $jml = (int)$qs->fetch_assoc()['c'];
        }
        $r['jml_soal'] = $jml ?: $jumlahSoal;
        $jadwalList[] = $r;
    }
}

// ── Helper tanggal ────────────────────────────────────────────
if (!function_exists('tgl_indo')) {
    function tgl_indo($tanggal) {
        $bln = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
        $t   = explode('-', $tanggal);
        return $t[2] . ' ' . ($bln[(int)$t[1]] ?? '') . ' ' . $t[0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kartu Ujian — <?= e($namaAplikasi) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --navy-light:#eff6ff;--navy-border:#bfdbfe;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g700:#334155;--g800:#1e293b;
  --gold:#f59e0b;--gold-light:#fef3c7;--gold-border:#fcd34d;
}

body{
  font-family:'Plus Jakarta Sans',sans-serif;
  background:var(--g100);color:var(--g800);font-size:12px;
}

/* ══ TOOLBAR ══ */
@media screen{
  .toolbar{
    background:#fff;padding:10px 24px;
    display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    position:sticky;top:0;z-index:100;
    border-bottom:3px solid var(--navy);
    box-shadow:0 2px 8px rgba(30,58,138,.10);
  }
  .toolbar-title{font-size:15px;font-weight:800;color:var(--navy);flex:1;display:flex;align-items:center;gap:8px}
  .toolbar select{
    padding:7px 12px;border-radius:7px;border:1.5px solid var(--g200);
    font-size:12px;font-family:inherit;color:var(--g700);background:var(--g50);cursor:pointer;
  }
  .toolbar select:focus{outline:none;border-color:var(--navy)}
  .btn{padding:7px 16px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .15s;white-space:nowrap;font-family:inherit}
  .btn-primary{background:var(--navy);color:#fff;box-shadow:0 2px 8px rgba(30,58,138,.25)}
  .btn-primary:hover{background:var(--navy-h)}
  .btn-secondary{background:var(--g50);color:var(--g600);border:1.5px solid var(--g200)}
  .btn-secondary:hover{background:var(--g200);color:var(--g800)}
  .cnt-badge{background:var(--navy-light);color:var(--navy);font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;border:1.5px solid var(--navy-border)}

  .jadwal-bar{background:#fff;border-bottom:1px solid var(--g200);padding:8px 24px;display:flex;align-items:flex-start;gap:8px;flex-wrap:wrap}
  .jadwal-bar-label{font-size:11px;font-weight:800;color:var(--g400);text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
  .jadwal-chips{display:flex;flex-wrap:wrap;gap:6px}
  .jchip{background:var(--navy-light);border:1px solid var(--navy-border);border-radius:6px;padding:4px 10px;font-size:11px;color:var(--navy);font-weight:600;display:flex;align-items:center;gap:5px}
  .jchip-mapel{background:var(--navy);color:#fff;border-radius:4px;padding:1px 7px;font-size:10px;font-weight:800}
  .no-jadwal-info{font-size:12px;color:var(--gold);font-weight:600}

  .page-wrap{padding:28px;max-width:900px;margin:0 auto}
  .grid-wrap{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}

  @media(max-width:600px){
    .toolbar{padding:8px 12px;gap:6px}
    .toolbar-title{font-size:13px}
    .page-wrap{padding:12px}
    .grid-wrap{grid-template-columns:1fr;gap:14px}
  }
}

/* ══ PRINT ══ */
@media print{
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  body{background:#fff}
  .toolbar,.jadwal-bar{display:none!important}
  .page-wrap{padding:0;max-width:100%}
  .grid-wrap{display:grid;grid-template-columns:repeat(2,1fr);gap:5mm;padding:6mm}
  @page{size:A4 portrait;margin:0}
  .kartu{break-inside:avoid;page-break-inside:avoid;box-shadow:none!important}
  .kartu:nth-child(6n){page-break-after:always;break-after:page}
}

/* ══ KARTU ══ */
.kartu{
  width:100%;background:#fff;border-radius:12px;overflow:hidden;
  border:1.5px solid var(--navy-border);
  box-shadow:0 4px 16px rgba(30,58,138,.10);
  display:flex;flex-direction:column;
}

.k-header{
  background:linear-gradient(135deg,var(--navy-m) 0%,var(--navy) 60%,var(--navy-d) 100%);
  padding:10px 13px;display:flex;align-items:center;gap:10px;
  position:relative;overflow:hidden;
}
.k-header::before{content:'';position:absolute;right:-20px;top:-20px;width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.07)}
.k-header::after{content:'';position:absolute;right:14px;bottom:-24px;width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.05)}

.logo-box{
  width:38px;height:38px;flex-shrink:0;border-radius:50%;
  background:#fff;display:flex;align-items:center;justify-content:center;
  overflow:hidden;padding:3px;
  box-shadow:0 1px 6px rgba(0,0,0,.18);border:1.5px solid rgba(255,255,255,.4);
}
.logo-box img{width:100%;height:100%;object-fit:contain;border-radius:50%}
.logo-txt{font-size:16px;font-weight:900;color:var(--navy);line-height:1}

.head-text{flex:1;color:#fff;z-index:1;min-width:0}
.head-title{font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.head-sub{font-size:8.5px;opacity:.8;margin-top:1px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

.head-badge{
  flex-shrink:0;z-index:1;
  background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);
  border-radius:5px;padding:3px 8px;
  font-size:7px;font-weight:900;color:#fff;
  text-transform:uppercase;letter-spacing:.7px;white-space:nowrap;
}

.k-sesi{
  background:var(--navy-light);border-bottom:1px solid var(--navy-border);
  padding:5px 13px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;
}
.sesi-mapel-tag{background:var(--navy);color:#fff;font-size:7.5px;font-weight:800;padding:2px 8px;border-radius:20px;letter-spacing:.3px;white-space:nowrap}
.sesi-mapel-tag.all{background:var(--g600)}
.sesi-info-txt{font-size:7.5px;color:var(--navy);font-weight:700;display:flex;align-items:center;gap:4px;flex-wrap:wrap}
.sesi-info-txt .sep{color:var(--navy-border)}
.no-sesi-txt{font-size:8px;color:var(--g400);font-style:italic}

.k-body{padding:10px 13px 8px;display:flex;gap:11px;align-items:flex-start}

.foto-init{
  width:48px;height:58px;flex-shrink:0;border-radius:7px;
  background:linear-gradient(135deg,var(--navy-light),#dbeafe);
  border:1.5px solid var(--navy-border);
  display:flex;align-items:center;justify-content:center;
  font-size:18px;font-weight:900;color:var(--navy);letter-spacing:-1px;
}

/* Kolom Info Siswa */
.info-col{flex:1;min-width:0}
.info-nama{font-size:12.5px;font-weight:900;color:var(--g800);line-height:1.2;margin-bottom:5px;text-transform:uppercase}
.info-row{display:flex;align-items:baseline;margin-bottom:2.5px}
.info-lbl{font-size:7px;font-weight:800;color:var(--g400);text-transform:uppercase;letter-spacing:.5px;width:44px;flex-shrink:0}
.info-sep{font-size:8px;color:var(--g300);margin-right:4px;flex-shrink:0}
.info-val{font-size:9px;font-weight:700;color:var(--g700);line-height:1.3}

.k-jadwal-list{margin-top:6px;padding-top:5px;border-top:1px dashed var(--g200)}
.k-jadwal-row{display:flex;align-items:baseline;gap:5px;margin-bottom:2px}
.k-jadwal-mapel{font-size:7.5px;font-weight:800;color:var(--navy);white-space:nowrap;flex-shrink:0;background:var(--navy-light);padding:1px 5px;border-radius:3px}
.k-jadwal-detail{font-size:7px;color:var(--g600);font-weight:600;line-height:1.3}
.k-jadwal-detail b{color:var(--g800)}
.no-jadwal-row{font-size:7.5px;color:var(--g400);font-style:italic}

/* Bagian Kode dan QR (Kunci Proporsional) */
.k-kode{
  margin:0 13px 11px;
  display:flex;gap:10px;align-items:stretch;
}
.kode-inner{
  flex:1;background:var(--navy-light);
  border:1.5px solid var(--navy-border);border-radius:9px;padding:7px 10px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.kode-lbl{font-size:7px;font-weight:800;color:var(--navy);text-transform:uppercase;letter-spacing:.8px;margin-bottom:3px}
.kode-val{
  font-family:'Courier New',monospace;
  font-size:16px;
  font-weight:900;
  color:var(--navy-d);letter-spacing:2px;line-height:1;margin-bottom:4px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ttd-line{width:100%;height:15px;border-bottom:1.5px dashed var(--navy-border);margin-bottom:2px}
.ttd-lbl{font-size:6.5px;color:var(--g400);font-weight:600;text-align:center}

.qr-box{
  width: 72px;
  flex-shrink:0;display:flex;flex-direction:column;align-items:center;
  justify-content:center;gap:3px;padding:5px;
  background:var(--navy-light);border:1.5px solid var(--navy-border);border-radius:9px;
}
.qr-box canvas{
  max-width: 100% !important;
  height: auto !important;
  border-radius:4px;display:block
}
.qr-lbl{font-size:6px;color:var(--g400);font-weight:700;text-align:center;letter-spacing:.4px;text-transform:uppercase}

.k-foot{
  background:linear-gradient(90deg,var(--navy-light),#fff);
  border-top:1px solid var(--navy-border);
  padding:4px 13px;display:flex;align-items:center;justify-content:space-between;
}
.foot-txt{font-size:7px;color:var(--g400);font-weight:700}
.foot-txt strong{color:var(--g600)}

.empty-wrap{grid-column:1/-1;background:#fff;border-radius:12px;border:2px dashed var(--g200);text-align:center;padding:60px 20px}
.empty-ic{font-size:36px;margin-bottom:10px}
.empty-ttl{font-size:15px;font-weight:800;color:var(--g600)}
.empty-sub{font-size:12px;color:var(--g400);margin-top:4px}
</style>
</head>

<body>
<!-- ══ TOOLBAR ══ -->
<div class="toolbar">
  <div class="toolbar-title">&#128219; Cetak Kartu Ujian</div>
  <span class="cnt-badge"><?= count($peserta) ?> peserta</span>

  <form method="get" style="display:contents">
    <select name="kelas" onchange="this.form.submit()">
      <option value="">&#8212; Semua Ruang</option>
      <?php foreach ($kelasList as $k): ?>
      <option value="<?= e($k) ?>" <?= $filterKelas===$k?'selected':'' ?>> <?= e($k) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filterId): ?><input type="hidden" name="peserta_id" value="<?= $filterId ?>"><?php endif; ?>
  </form>

  <button class="btn btn-primary" onclick="window.print()">&#128424; Cetak</button>
  <a href="<?= BASE_URL ?>/sekolah/peserta.php" class="btn btn-secondary">&#8592; Kembali</a>
</div>

<!-- ══ JADWAL BAR ══ -->
<div class="jadwal-bar">
  <span class="jadwal-bar-label">Jadwal Aktif:</span>
  <?php if (empty($jadwalList)): ?>
    <span class="no-jadwal-info">&#9888; Belum ada jadwal aktif</span>
  <?php else: ?>
  <div class="jadwal-chips">
    <?php foreach($jadwalList as $j): ?>
    <span class="jchip">
      <?php if ($j['nama_kategori']): ?>
      <span class="jchip-mapel"><?= e($j['nama_kategori']) ?></span>
      <?php endif; ?>
      <?= tgl_indo($j['tanggal']) ?>
      &nbsp;<?= substr($j['jam_mulai'],0,5) ?>&ndash;<?= substr($j['jam_selesai'],0,5) ?> WIB
      &middot; <?= $j['durasi_menit'] ?> mnt
    </span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══ GRID ══ -->
<div class="page-wrap">
<div class="grid-wrap">

<?php if (empty($peserta)): ?>
<div class="empty-wrap">
  <div class="empty-ic">&#128219;</div>
  <div class="empty-ttl">Tidak ada peserta ditemukan</div>
  <div class="empty-sub">Tambahkan peserta terlebih dahulu di menu Data Peserta.</div>
</div>

<?php else:
$mapelSingle = count($jadwalList) === 1 ? ($jadwalList[0]['nama_kategori'] ?? null) : null;
foreach ($peserta as $p):
  $inisial2     = mb_strtoupper(mb_substr($p['nama'], 0, 2));
  $kelasStr     = $p['kelas'] ? ' ' . $p['kelas'] : '-';
  $sekolah      = $p['nama_sekolah'] ?? '-';
  $sekolahShort = mb_strlen($sekolah) > 34 ? mb_substr($sekolah, 0, 32) . '…' : $sekolah;
  $kode         = $p['kode_peserta'] ?? '------';
?>

<div class="kartu">
  <!-- Header -->
  <div class="k-header">
    <div class="logo-box">
      <?php if ($logoAktif): ?>
        <img src="<?= e($logoAktif) ?>" alt="Logo" onerror="this.style.display='none'">
      <?php else: ?>
        <span class="logo-txt"><?= e(mb_strtoupper(mb_substr($namaAplikasi,0,1))) ?></span>
      <?php endif; ?>
    </div>
    <div class="head-text">
      <div class="head-title"><?= e($namaAplikasi) ?></div>
      <div class="head-sub"><?= e($namaPenyelenggara ?: $namaKecamatan) ?></div>
    </div>
    <div class="head-badge">KARTU UJIAN</div>
  </div>

  <!-- Sesi Strip -->
  <?php if (!empty($jadwalList)): ?>
  <div class="k-sesi">
    <?php if ($mapelSingle): ?>
      <span class="sesi-mapel-tag">&#128218; <?= e($mapelSingle) ?></span>
    <?php elseif (count($jadwalList) > 1): ?>
      <span class="sesi-mapel-tag all">Multi Sesi</span>
    <?php else: ?>
      <span class="sesi-mapel-tag all">Semua Mapel</span>
    <?php endif; ?>
    <div class="sesi-info-txt">
      <?php $j0 = $jadwalList[0]; ?>
      <span>&#128197; <?= tgl_indo($j0['tanggal']) ?></span>
      <span class="sep">&middot;</span>
      <span>&#9200; <?= substr($j0['jam_mulai'],0,5) ?>&ndash;<?= substr($j0['jam_selesai'],0,5) ?></span>
      <span class="sep">&middot;</span>
      <span>&#9203; <?= $j0['durasi_menit'] ?> mnt</span>
    </div>
  </div>
  <?php else: ?>
  <div class="k-sesi"><span class="no-sesi-txt">Jadwal belum ditetapkan</span></div>
  <?php endif; ?>

  <!-- Body -->
  <div class="k-body">
    <div class="foto-init"><?= e($inisial2) ?></div>
    <div class="info-col">
      <div class="info-nama"><?= e($p['nama']) ?></div>
      <div class="info-row">
        <span class="info-lbl">Ruang</span>
        <span class="info-sep">:</span>
        <span class="info-val"><?= e($kelasStr) ?></span>
      </div>
      <div class="info-row">
        <span class="info-lbl">Jenis Tes</span>
        <span class="info-sep">:</span>
        <span class="info-val"><?= e($sekolahShort) ?></span>
      </div>
 
      <div class="info-row">
        <span class="info-lbl">Sekolah</span>
        <span class="info-sep">:</span>
        <span class="info-val"><?= e($p['kode_sekolah'] ?? '-') ?></span>
      </div>

      <!-- Sesi dalam kartu -->
      <div class="k-jadwal-list">
        <?php if (empty($jadwalList)): ?>
          <span class="no-jadwal-row">Jadwal belum tersedia</span>
        <?php else: foreach($jadwalList as $j): ?>
        <div class="k-jadwal-row">
          <?php if ($j['nama_kategori']): ?>
          <span class="k-jadwal-mapel"><?= e($j['nama_kategori']) ?></span>
          <?php endif; ?>
          <span class="k-jadwal-detail">
            <b><?= tgl_indo($j['tanggal']) ?></b>
            &nbsp;<?= substr($j['jam_mulai'],0,5) ?>&ndash;<?= substr($j['jam_selesai'],0,5) ?>
            &middot; <?= $j['jml_soal'] ?> soal &middot; <?= $j['durasi_menit'] ?> mnt
          </span>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <!-- Kode + QR -->
  <div class="k-kode">
    <div class="kode-inner">
      <div class="kode-lbl">Kode Login Peserta</div>
      <div class="kode-val"><?= e($kode) ?></div>

    </div>
    <div class="qr-box">
      <canvas id="qr-<?= $p['id'] ?>"></canvas>
      <div class="qr-lbl">SCAN LOGIN</div>
    </div>
  </div>

  <!-- Footer -->
  <div class="k-foot">
    <span class="foot-txt">No. <strong>#<?= $p['id'] ?></strong></span>
    <span class="foot-txt">Wajib dibawa saat ujian</span>
    <span class="foot-txt">TA <strong><?= e($tahunAjaran) ?></strong></span>
  </div>
</div><!-- .kartu -->

<?php endforeach; endif; ?>
</div><!-- .grid-wrap -->
</div><!-- .page-wrap -->

<!-- ══ PUSTAKA QR CODE (external) ══ -->
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js"></script>

<!-- ══ LOGIK RENDER QR UTAMA ══ -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Mengambil BASE_URL dari PHP untuk disematkan secara dinamis di JS
  var baseUrl = '<?= BASE_URL ?>';
  
  // Mengambil data array peserta dari PHP
  var list = <?= json_encode(array_map(fn($p) => ['id'=>$p['id'],'kode'=>$p['kode_peserta']], $peserta)) ?>;
  
  list.forEach(function (p) {
    var c = document.getElementById('qr-' + p.id);
    if (!c || typeof QRCode === 'undefined') return;
    
    // Gabungkan URL tujuan secara dinamis ke /ujian/login_peserta.php?kode=xxxxxx
    var loginUrl = baseUrl + '/ujian/login_peserta.php?kode=' + encodeURIComponent(p.kode);
    
    // Generate QR Code langsung menggunakan pustaka internal di atas
    QRCode.toCanvas(c, loginUrl, { 
      width: 60, 
      margin: 1, 
      color: { dark: '#1e1b4b', light: '#ffffff' } 
    });
  });
});

const params = new URLSearchParams(window.location.search);
if (params.get('print')==='1') window.addEventListener('load',()=>setTimeout(()=>window.print(),600));
</script>
</body>
</html>