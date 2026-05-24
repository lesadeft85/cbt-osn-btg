<?php
// ============================================================
// korektor/index.php — Dashboard Korektor
// (Dimodifikasi: tambah filter Kelas & Rombel)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireKorektor();

$filterJadwal  = (int)($_GET['jadwal_id'] ?? 0);
$filterStatus  = trim($_GET['status'] ?? 'pending');
$filterKat     = (int)($_GET['kat'] ?? 0);
$filterKelas   = trim($_GET['kelas'] ?? '');
$filterRombel  = trim($_GET['rombel'] ?? '');
$filterSekolah = (int)($_GET['sekolah_id'] ?? 0);

// ── Gabungkan kelas + rombel ────────────────────────────────
$filterKelasFull = '';
if ($filterKelas !== '') {
    $filterKelasFull = $filterRombel !== ''
        ? $filterKelas . ' ' . $filterRombel
        : $filterKelas;
}

$whereUjian = "WHERE (h.ada_essay = 1 OR EXISTS (
    SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id = jx.soal_id
    WHERE jx.ujian_id = h.ujian_id AND sx.tipe_soal = 'essay'
))";
if ($filterJadwal)    $whereUjian .= " AND h.jadwal_id = $filterJadwal";
if ($filterKat)       $whereUjian .= " AND COALESCE(h.kategori_id, j.kategori_id) = $filterKat";
if ($filterStatus === 'pending') $whereUjian .= " AND h.essay_dinilai = 0";
if ($filterStatus === 'done')    $whereUjian .= " AND h.essay_dinilai = 1";
if ($filterSekolah)  $whereUjian .= " AND p.sekolah_id = $filterSekolah";

// Filter kelas / rombel
if ($filterKelasFull !== '') {
    if ($filterRombel !== '') {
        $filterKelasEsc = $conn->real_escape_string($filterKelasFull);
        $whereUjian .= " AND p.kelas = '$filterKelasEsc'";
    } else {
        $filterKelasEsc = $conn->real_escape_string($filterKelas);
        $whereUjian .= " AND (p.kelas = '$filterKelasEsc' OR p.kelas LIKE '$filterKelasEsc %')";
    }
}

$ujianList = $conn->query("
    SELECT h.ujian_id, h.essay_dinilai, h.nilai AS nilai_akhir,
           u2.soal_order,
           p.nama AS peserta_nama, p.kelas, p.kode_peserta,
           s.nama_sekolah,
           k.nama_kategori,
           j.keterangan AS jadwal_nama, j.tanggal AS jadwal_tanggal,
           h.waktu_selesai,
           (SELECT COUNT(*) FROM jawaban jw JOIN soal sl ON sl.id=jw.soal_id
            WHERE jw.ujian_id=h.ujian_id AND sl.tipe_soal='essay') AS jml_soal_essay,
           (SELECT COUNT(*) FROM jawaban jw JOIN soal sl ON sl.id=jw.soal_id
            WHERE jw.ujian_id=h.ujian_id AND sl.tipe_soal='essay'
            AND jw.skor_essay IS NOT NULL) AS jml_dinilai
    FROM hasil_ujian h
    JOIN ujian u2 ON u2.id = h.ujian_id
    JOIN peserta p ON p.id = h.peserta_id
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    LEFT JOIN jadwal_ujian j ON j.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, j.kategori_id)
    $whereUjian
    ORDER BY h.essay_dinilai ASC, h.waktu_selesai DESC
");

$jadwalList = $conn->query("SELECT id, keterangan, tanggal FROM jadwal_ujian ORDER BY tanggal DESC");

// ── Mapel: hanya yang ada ujian essay untuk kelas yang dipilih ──
// Jika kelas dipilih → filter mapel sesuai kelas itu
// Jika tidak → tampil semua mapel
if ($filterKelasFull !== '') {
    if ($filterRombel !== '') {
        $kfe = $conn->real_escape_string($filterKelasFull);
        $kelasCond = "AND p.kelas = '$kfe'";
    } else {
        $kfe = $conn->real_escape_string($filterKelas);
        $kelasCond = "AND (p.kelas = '$kfe' OR p.kelas LIKE '$kfe %')";
    }
    $kategoriList = $conn->query("
        SELECT DISTINCT k.id, k.nama_kategori
        FROM kategori_soal k
        WHERE k.id IN (
            SELECT DISTINCT COALESCE(h.kategori_id, j.kategori_id)
            FROM hasil_ujian h
            JOIN peserta p ON p.id = h.peserta_id
            LEFT JOIN jadwal_ujian j ON j.id = h.jadwal_id
            WHERE (h.ada_essay = 1 OR EXISTS (
                SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id = jx.soal_id
                WHERE jx.ujian_id = h.ujian_id AND sx.tipe_soal = 'essay'
            ))
            $kelasCond
        )
        ORDER BY k.nama_kategori
    ");
} else {
    $kategoriList = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
}

// ── Ambil daftar kelas unik dari tabel peserta ───────────────
// Pisahkan angka romawi (kelas) dari huruf (rombel)
// Contoh nilai di DB: "IV", "IV A", "IV B", "V", "V A"
$kelasRawRes = $conn->query("
    SELECT DISTINCT kelas FROM peserta
    WHERE kelas IS NOT NULL AND kelas != ''
    ORDER BY kelas
");
$allKelasRaw = [];
if ($kelasRawRes) while ($kr = $kelasRawRes->fetch_assoc()) $allKelasRaw[] = $kr['kelas'];

// Pisahkan: ambil bagian pertama sebagai "kelas utama", bagian kedua sebagai "rombel"
$kelasUtamaList = []; // ["I","II","III","IV","V","VI",...]
$rombelPerKelas = []; // ["IV" => ["A","B","C"], "V" => ["A","B"]]
foreach ($allKelasRaw as $kRaw) {
    $parts = explode(' ', trim($kRaw), 2);
    $ku    = trim($parts[0]); // kelas utama, contoh: "IV"
    $ro    = isset($parts[1]) ? trim($parts[1]) : ''; // rombel, contoh: "A"
    if ($ku && !in_array($ku, $kelasUtamaList)) {
        $kelasUtamaList[] = $ku;
    }
    if ($ku && $ro && !in_array($ro, $rombelPerKelas[$ku] ?? [])) {
        $rombelPerKelas[$ku][] = $ro;
    }
}

// ── Daftar sekolah untuk dropdown filter ─────────────────────
$sekolahList = $conn->query("SELECT id, nama_sekolah FROM sekolah ORDER BY nama_sekolah");

// ── Nama sekolah terpilih untuk badge ────────────────────────
$namaSekolahTerpilih = '';
if ($filterSekolah) {
    $rs = $conn->query("SELECT nama_sekolah FROM sekolah WHERE id=$filterSekolah LIMIT 1");
    if ($rs && $rs->num_rows > 0) $namaSekolahTerpilih = $rs->fetch_assoc()['nama_sekolah'];
}

// ── Nama mapel terpilih untuk badge ──────────────────────────
$namaKatTerpilih = '';
if ($filterKat) {
    $r = $conn->query("SELECT nama_kategori FROM kategori_soal WHERE id=$filterKat LIMIT 1");
    if ($r && $r->num_rows > 0) $namaKatTerpilih = $r->fetch_assoc()['nama_kategori'];
}

// ── Stat ringkasan — ikuti filter kelas/rombel ───────────────
if ($filterKelasFull !== '') {
    $statBase = "FROM hasil_ujian h JOIN peserta p ON p.id = h.peserta_id
        WHERE (h.ada_essay=1 OR EXISTS(SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id=jx.soal_id WHERE jx.ujian_id=h.ujian_id AND sx.tipe_soal='essay'))";
    if ($filterRombel !== '') {
        $kfe2 = $conn->real_escape_string($filterKelasFull);
        $statBase .= " AND p.kelas = '$kfe2'";
    } else {
        $kfe2 = $conn->real_escape_string($filterKelas);
        $statBase .= " AND (p.kelas = '$kfe2' OR p.kelas LIKE '$kfe2 %')";
    }
    $statPending = $conn->query("SELECT COUNT(*) AS c $statBase AND h.essay_dinilai=0")->fetch_assoc()['c'] ?? 0;
    $statDone    = $conn->query("SELECT COUNT(*) AS c $statBase AND h.essay_dinilai=1")->fetch_assoc()['c'] ?? 0;
} else {
    $statPending = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE essay_dinilai=0 AND (ada_essay=1 OR EXISTS(SELECT 1 FROM jawaban jx JOIN soal sx ON sx.id=jx.soal_id WHERE jx.ujian_id=hasil_ujian.ujian_id AND sx.tipe_soal='essay'))")->fetch_assoc()['c'] ?? 0;
    $statDone    = $conn->query("SELECT COUNT(*) AS c FROM hasil_ujian WHERE essay_dinilai=1")->fetch_assoc()['c'] ?? 0;
}

$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Korektor — <?= e($namaAplikasi) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;min-height:100vh}
.topbar{background:#1e3a8a;padding:0 20px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.topbar-brand{font-size:16px;font-weight:900;color:#fff;letter-spacing:.5px}
.topbar-brand span{background:rgba(255,255,255,.15);padding:2px 10px;border-radius:20px;font-size:12px;font-weight:700;margin-left:8px;color:#bfdbfe}
.topbar-right{display:flex;align-items:center;gap:12px}
.user-info{color:rgba(255,255,255,.85);font-size:13px}
.btn-logout{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;padding:5px 14px;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s}
.btn-logout:hover{background:rgba(255,255,255,.25);color:#fff}

.wrap{max-width:1100px;margin:0 auto;padding:24px 16px}

/* Stat cards */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:12px;padding:16px 20px;box-shadow:0 1px 6px rgba(0,0,0,.07);display:flex;align-items:center;gap:14px}
.stat-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-icon.orange{background:#fef3c7;color:#d97706}
.stat-icon.green{background:#dcfce7;color:#16a34a}
.stat-icon.blue{background:#dbeafe;color:#1e3a8a}
.stat-val{font-size:26px;font-weight:900;color:#1e293b;line-height:1}
.stat-lbl{font-size:11px;color:#94a3b8;font-weight:600;margin-top:2px}

/* Filter */
.filter-card{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 1px 6px rgba(0,0,0,.07);margin-bottom:16px}
.filter-label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}

/* Badge filter aktif */
.filter-active-bar{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px}
.filter-tag{background:#dbeafe;color:#1e3a8a;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
.filter-tag a{color:#1e3a8a;text-decoration:none;font-weight:900}

/* Tabel */
.table-card{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.07);overflow:hidden}
.table-head{padding:14px 20px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center}
.table-head-title{font-size:14px;font-weight:700;color:#1e293b}

.tbl{width:100%;border-collapse:collapse}
.tbl th{background:#f8fafc;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;padding:10px 14px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
.tbl td{padding:12px 14px;border-bottom:1px solid #f8fafc;font-size:13px;color:#1e293b;vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:#fafbfc}

.badge-pending{background:#fef3c7;color:#92400e;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.badge-done{background:#dcfce7;color:#166534;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:4px}
.badge-kelas{background:#ede9fe;color:#5b21b6;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700}
.badge-rombel{background:#fce7f3;color:#9d174d;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700}
.progress-mini{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-top:4px}
.progress-mini-bar{height:100%;border-radius:3px;background:#16a34a}

.btn-nilai{background:#1e3a8a;color:#fff;border:none;border-radius:8px;padding:6px 16px;font-size:12px;font-weight:700;text-decoration:none;transition:background .15s;display:inline-flex;align-items:center;gap:5px}
.btn-nilai:hover{background:#1e40af;color:#fff}
.btn-nilai.edit{background:#f59e0b}
.btn-nilai.edit:hover{background:#d97706}

.empty-state{text-align:center;padding:48px 20px;color:#94a3b8}
.empty-state i{font-size:40px;display:block;margin-bottom:10px}

.stat-filter-lbl{font-size:9px;color:#3b82f6;font-weight:700;margin-top:1px}

@media(max-width:600px){
  .stat-row{grid-template-columns:1fr 1fr}
  .tbl th:nth-child(4),.tbl td:nth-child(4){display:none}
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-brand">
    <?= e($namaAplikasi) ?>
    <span>✏️ Korektor</span>
  </div>
  <div class="topbar-right">
    <span class="user-info"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['nama']) ?></span>
    <a href="<?= BASE_URL ?>/logout.php" class="btn-logout">
      <i class="bi bi-box-arrow-right me-1"></i>Keluar
    </a>
  </div>
</div>

<div class="wrap">

  <?= renderFlash() ?>

  <!-- Stat ringkasan -->
  <div class="stat-row">
    <div class="stat-card">
      <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-val"><?= $statPending ?></div>
        <div class="stat-lbl">Menunggu Koreksi</div>
        <?php if($filterKelas): ?><div class="stat-filter-lbl">Kelas <?= e($filterKelas) ?><?= $filterRombel ? ' '.e($filterRombel) : '' ?></div><?php endif; ?>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i class="bi bi-check-circle-fill"></i></div>
      <div>
        <div class="stat-val"><?= $statDone ?></div>
        <div class="stat-lbl">Selesai Dikoreksi</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="stat-val"><?= $statPending + $statDone ?></div>
        <div class="stat-lbl">Total Ujian</div>
      </div>
    </div>
  </div>

  <!-- Filter -->
  <div class="filter-card">
    <form class="d-flex flex-wrap gap-2 align-items-end" method="GET" id="formFilter">
      <input type="hidden" name="kelas"  id="inputKelas"  value="<?= e($filterKelas) ?>">
      <input type="hidden" name="rombel" id="inputRombel" value="<?= e($filterRombel) ?>">

      <!-- 1. Filter Sekolah -->
      <div>
        <div class="filter-label">Sekolah</div>
        <select name="sekolah_id" class="form-select form-select-sm" style="width:210px"
                onchange="document.getElementById('formFilter').submit()">
          <option value="">Semua Sekolah</option>
          <?php if($sekolahList) while($sk=$sekolahList->fetch_assoc()): ?>
          <option value="<?= $sk['id'] ?>" <?= $filterSekolah==$sk['id']?'selected':'' ?>>
            <?= e($sk['nama_sekolah']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- 2. Filter Kelas -->
      <div>
        <div class="filter-label">Kelas</div>
        <select class="form-select form-select-sm" style="width:130px"
                onchange="onKelasChange(this.value)">
          <option value="">Semua Kelas</option>
          <?php foreach ($kelasUtamaList as $ku): ?>
          <option value="<?= e($ku) ?>" <?= $filterKelas===$ku?'selected':'' ?>>
            Kelas <?= e($ku) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- 3. Filter Rombel (muncul jika kelas dipilih) -->
      <div id="rombelWrap" style="<?= $filterKelas===''?'display:none':'' ?>">
        <div class="filter-label">Rombel</div>
        <select class="form-select form-select-sm" style="width:120px"
                id="selectRombel" onchange="onRombelChange(this.value)">
          <option value="">Semua Rombel</option>
          <?php if ($filterKelas && isset($rombelPerKelas[$filterKelas])):
            foreach ($rombelPerKelas[$filterKelas] as $ro): ?>
          <option value="<?= e($ro) ?>" <?= $filterRombel===$ro?'selected':'' ?>>
            Rombel <?= e($ro) ?>
          </option>
          <?php endforeach; endif; ?>
        </select>
      </div>

      <!-- 4. Filter Mapel (dinamis sesuai kelas) -->
      <div>
        <div class="filter-label">
          Mata Pelajaran
          <?php if ($filterKelas): ?>
          <span style="color:#3b82f6;font-size:9px;font-weight:700"> — Kelas <?= e($filterKelas) ?></span>
          <?php endif; ?>
        </div>
        <select name="kat" class="form-select form-select-sm" style="width:190px">
          <option value="">
            <?= $filterKelas ? 'Semua Mapel (Kelas '.e($filterKelas).')' : 'Semua Mapel' ?>
          </option>
          <?php if($kategoriList) while($k=$kategoriList->fetch_assoc()): ?>
          <option value="<?= $k['id'] ?>" <?= $filterKat==$k['id']?'selected':'' ?>>
            <?= e($k['nama_kategori']) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- 5. Filter Jadwal -->
      <div>
        <div class="filter-label">Jadwal</div>
        <select name="jadwal_id" class="form-select form-select-sm" style="width:190px">
          <option value="">Semua Jadwal</option>
          <?php if($jadwalList) while($j=$jadwalList->fetch_assoc()): ?>
          <option value="<?= $j['id'] ?>" <?= $filterJadwal==$j['id']?'selected':'' ?>>
            <?= e($j['keterangan'] ?: date('d/m/Y', strtotime($j['tanggal']))) ?>
          </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- 6. Filter Status -->
      <div>
        <div class="filter-label">Status</div>
        <select name="status" class="form-select form-select-sm" style="width:150px">
          <option value="">Semua Status</option>
          <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>⏳ Belum Dikoreksi</option>
          <option value="done"    <?= $filterStatus==='done'?'selected':'' ?>>✅ Sudah Dikoreksi</option>
        </select>
      </div>

      <div style="margin-top:auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filter</button>
        <a href="?" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>

    <!-- Badge filter aktif -->
    <?php $adaFilter = $filterJadwal || $filterKat || $filterKelas || $filterStatus || $filterSekolah; ?>
    <?php if ($adaFilter): ?>
    <div class="filter-active-bar mt-3">
      <span style="font-size:11px;color:#94a3b8;align-self:center">Filter aktif:</span>
      <?php if ($filterSekolah && $namaSekolahTerpilih): ?>
      <span class="filter-tag">
        <i class="bi bi-building"></i> <?= e($namaSekolahTerpilih) ?>
        <a href="?<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'kat'=>$filterKat,'kelas'=>$filterKelas,'rombel'=>$filterRombel,'status'=>$filterStatus])) ?>">×</a>
      </span>
      <?php endif; ?>
      <?php if ($filterKelas): ?>
      <span class="filter-tag">
        <i class="bi bi-mortarboard-fill"></i>
        Kelas <?= e($filterKelas) ?>
        <?= $filterRombel ? ' Rombel ' . e($filterRombel) : '(Semua Rombel)' ?>
        <a href="?<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'kat'=>$filterKat,'sekolah_id'=>$filterSekolah,'status'=>$filterStatus])) ?>">×</a>
      </span>
      <?php endif; ?>
      <?php if ($filterKat && $namaKatTerpilih): ?>
      <span class="filter-tag"><i class="bi bi-book-fill"></i> <?= e($namaKatTerpilih) ?>
        <a href="?<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'kelas'=>$filterKelas,'rombel'=>$filterRombel,'sekolah_id'=>$filterSekolah,'status'=>$filterStatus])) ?>">×</a>
      </span>
      <?php endif; ?>
      <?php if ($filterStatus): ?>
      <span class="filter-tag">
        <?= $filterStatus === 'pending' ? '⏳ Belum Dikoreksi' : '✅ Sudah Dikoreksi' ?>
        <a href="?<?= http_build_query(array_filter(['jadwal_id'=>$filterJadwal,'kat'=>$filterKat,'kelas'=>$filterKelas,'rombel'=>$filterRombel,'sekolah_id'=>$filterSekolah])) ?>">×</a>
      </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tabel daftar ujian -->
  <div class="table-card">
    <div class="table-head">
      <span class="table-head-title">
        <i class="bi bi-list-check me-2 text-primary"></i>
        Daftar Ujian Essay
        <?php if ($filterKelas): ?>
        <span class="badge-kelas ms-1">Kelas <?= e($filterKelas) ?></span>
        <?php if ($filterRombel): ?>
        <span class="badge-rombel ms-1">Rombel <?= e($filterRombel) ?></span>
        <?php endif; ?>
        <?php endif; ?>
      </span>
      <span style="font-size:12px;color:#94a3b8"><?= $ujianList ? $ujianList->num_rows : 0 ?> ujian</span>
    </div>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th>
            <th>Peserta</th>
            <th>Kelas / Rombel</th>
            <th>Sekolah</th>
            <th>Mapel / Jadwal</th>
            <th class="text-center">Progress</th>
            <th class="text-center">Status</th>
            <th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($ujianList && $ujianList->num_rows > 0):
          $no = 1; while ($row = $ujianList->fetch_assoc()):
          $jmlEssay   = (int)($row['jml_soal_essay'] ?? 0);
          $jmlDinilai = (int)($row['jml_dinilai'] ?? 0);
          $pct        = $jmlEssay > 0 ? round($jmlDinilai / $jmlEssay * 100) : 0;

          // Pisahkan kelas dan rombel untuk tampilan
          $kelasParts  = explode(' ', trim($row['kelas'] ?? ''), 2);
          $kelasUtama  = $kelasParts[0] ?? '-';
          $rombelParts = $kelasParts[1] ?? '';
        ?>
        <tr>
          <td style="color:#94a3b8;font-weight:600"><?= $no++ ?></td>
          <td>
            <div style="font-weight:700"><?= e($row['peserta_nama']) ?></div>
            <div style="font-size:11px;color:#94a3b8;font-family:'Courier New',monospace"><?= e($row['kode_peserta']) ?></div>
          </td>
          <td>
            <span class="badge-kelas">Kelas <?= e($kelasUtama) ?></span>
            <?php if ($rombelParts): ?>
            <span class="badge-rombel ms-1">Rombel <?= e($rombelParts) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-size:12px;color:#475569"><?= e($row['nama_sekolah'] ?? '-') ?></div>
          </td>
          <td>
            <div><?= e($row['nama_kategori'] ?? 'Umum') ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= e($row['jadwal_nama'] ?? '-') ?></div>
          </td>
          <td class="text-center" style="min-width:100px">
            <div style="font-size:12px;font-weight:700;color:#1e293b"><?= $jmlDinilai ?>/<?= $jmlEssay ?> soal</div>
            <div class="progress-mini">
              <div class="progress-mini-bar" style="width:<?= $pct ?>%"></div>
            </div>
          </td>
          <td class="text-center">
            <?php if ($row['essay_dinilai']): ?>
            <span class="badge-done"><i class="bi bi-check-circle-fill"></i> Selesai</span>
            <?php else: ?>
            <span class="badge-pending"><i class="bi bi-hourglass-split"></i> Pending</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php
            $koreksiParams = array_filter([
                'ujian_id'   => $row['ujian_id'],
                'kelas'      => $filterKelas,
                'rombel'     => $filterRombel,
                'kat'        => $filterKat    ?: null,
                'jadwal_id'  => $filterJadwal ?: null,
                'status'     => $filterStatus,
                'sekolah_id' => $filterSekolah ?: null,
            ]);
            ?>
            <a href="<?= BASE_URL ?>/korektor/koreksi.php?<?= http_build_query($koreksiParams) ?>"
               class="btn-nilai <?= $row['essay_dinilai'] ? 'edit' : '' ?>">
              <i class="bi bi-pencil-square"></i>
              <?= $row['essay_dinilai'] ? 'Edit' : 'Koreksi' ?>
            </a>
          </td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <?php if ($filterKelas): ?>
              Tidak ada ujian essay untuk
              <strong>Kelas <?= e($filterKelas) ?><?= $filterRombel ? ' Rombel ' . e($filterRombel) : '' ?></strong>.
            <?php elseif ($filterStatus === 'pending'): ?>
              Semua ujian sudah dikoreksi! 🎉
            <?php else: ?>
              Belum ada data ujian essay.
            <?php endif; ?>
          </div>
        </td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
// ── Data rombel per kelas (dari PHP) ────────────────────────
const rombelData = <?= json_encode($rombelPerKelas) ?>;

function onKelasChange(kelas) {
    document.getElementById('inputKelas').value  = kelas;
    document.getElementById('inputRombel').value = '';

    // Reset filter mapel saat kelas berubah (mapel akan di-reload sesuai kelas baru)
    const katSelect = document.querySelector('select[name="kat"]');
    if (katSelect) katSelect.value = '';

    const wrap    = document.getElementById('rombelWrap');
    const select  = document.getElementById('selectRombel');

    // Reset pilihan rombel
    select.innerHTML = '<option value="">Semua Rombel</option>';

    if (!kelas || !rombelData[kelas] || rombelData[kelas].length === 0) {
        wrap.style.display = 'none';
    } else {
        rombelData[kelas].forEach(ro => {
            const opt = document.createElement('option');
            opt.value = ro;
            opt.textContent = 'Rombel ' + ro;
            select.appendChild(opt);
        });
        wrap.style.display = 'block';
    }

    // Submit form otomatis
    document.getElementById('formFilter').submit();
}

function onRombelChange(rombel) {
    document.getElementById('inputRombel').value = rombel;
    document.getElementById('formFilter').submit();
}
</script>
</body>
</html>
