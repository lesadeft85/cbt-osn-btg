<?php
// ============================================================
// sekolah/ranking.php — Ranking Nilai Peserta Sekolah
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

$st = $conn->prepare("SELECT nama_sekolah, npsn, jenjang FROM sekolah WHERE id=? LIMIT 1");
$st->bind_param('i', $sekolahId); $st->execute();
$sekolah = $st->get_result()->fetch_assoc(); $st->close();

$kelasRes  = $conn->query("SELECT DISTINCT kelas FROM peserta WHERE sekolah_id=$sekolahId AND kelas IS NOT NULL ORDER BY kelas");
$kelasList = [];
if ($kelasRes) while ($k = $kelasRes->fetch_assoc()) $kelasList[] = $k['kelas'];

$katRes  = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
$katList = [];
if ($katRes) while ($k = $katRes->fetch_assoc()) $katList[] = $k;

$kkm   = (int)getSetting($conn, 'kkm', '60');
$conds = ["p.sekolah_id = $sekolahId", "h.nilai IS NOT NULL"];
if ($filterKelas) $conds[] = "p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($filterKat)   $conds[] = "COALESCE(h.kategori_id, jd.kategori_id) = $filterKat";
$where = buildWhere($conds);

$res = $conn->query("
    SELECT p.nama, p.kelas, p.kode_peserta, p.kode_sekolah,
           ROUND(AVG(h.nilai), 1)  AS rata_nilai,
           MAX(h.nilai)            AS nilai_terbaik,
           COUNT(h.id)             AS jml_ujian,
           SUM(h.nilai >= $kkm)    AS jml_lulus,
           COALESCE(k.nama_kategori,'-') AS mapel_terakhir,
           MAX(h.waktu_selesai)    AS ujian_terakhir
    FROM peserta p
    JOIN hasil_ujian h   ON h.peserta_id = p.id
    LEFT JOIN jadwal_ujian jd ON jd.id = h.jadwal_id
    LEFT JOIN kategori_soal k ON k.id = COALESCE(h.kategori_id, jd.kategori_id)
    $where
    GROUP BY p.id
    ORDER BY rata_nilai DESC, nilai_terbaik DESC
");

$rows = []; $rank = 1;
if ($res) while ($r = $res->fetch_assoc()) { $r['rank'] = $rank++; $rows[] = $r; }

$namaAplikasi   = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));

$pageTitle  = 'Ranking Peserta';
$activeMenu = 'ranking';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-trophy-fill me-2 text-warning"></i>Ranking Peserta</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Ranking</li>
        </ol></nav>
    </div>
    <div>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Cetak
        </button>
    </div>
</div>

<?= renderFlash() ?>

<!-- Filter -->
<div class="card mb-4">
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

<!-- Top 3 -->
<?php if (count($rows) >= 3): ?>
<div class="row g-3 mb-4 justify-content-center">
    <?php
    $medals = [
        1 => ['🥇','#f59e0b','Juara 1'],
        2 => ['🥈','#94a3b8','Juara 2'],
        3 => ['🥉','#cd7f32','Juara 3'],
    ];
    $podium = [2, 1, 3]; // urutan tampil: 2, 1, 3
    foreach ($podium as $pos):
        $r = $rows[$pos-1] ?? null;
        if (!$r) continue;
        [$med,$clr,$title] = $medals[$pos];
        [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['rata_nilai']);
    ?>
    <div class="col-md-3 col-6">
        <div class="card text-center border-0 shadow-sm" style="border-top:4px solid <?=$clr?>!important">
            <div class="card-body py-3">
                <div style="font-size:40px;line-height:1"><?=$med?></div>
                <div class="fw-bold mt-1" style="font-size:13px"><?=e($r['nama'])?></div>
                <div class="text-muted" style="font-size:11px">Ruang <?=e($r['kelas']??'-')?></div>
                <div class="text-muted" style="font-size:10px"><code><?=e($r['kode_sekolah']??'-')?></code></div>
                <div style="font-size:28px;font-weight:900;color:<?=$pcolor?>;margin-top:6px"><?=$r['rata_nilai']?></div>
                <div><span class="badge" style="background:<?=$pcolor?>;font-size:10px"><?=$pred?> – <?=$pteks?></span></div>
                <div class="text-muted mt-1" style="font-size:10px"><?=$title?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabel Ranking -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ol me-2 text-warning"></i>Peringkat Lengkap</span>
        <span class="badge bg-primary"><?=count($rows)?> peserta</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover datatable mb-0">
                <thead><tr>
                    <th class="text-center" style="width:60px">Rank</th>
                    <th>Nama Peserta</th>
                    <th class="text-center">Ruang</th>
                    <th class="text-center">Rata-rata</th>
                    <th class="text-center">Terbaik</th>
                    <th class="text-center">Jml Ujian</th>
                    <th class="text-center">Lulus</th>
                    <th class="text-center">Predikat</th>
                    <th>Ujian Terakhir</th>
                </tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $r):
                    [$pred,$pteks,$pbadge,$pcolor] = getPredikat((int)$r['rata_nilai']);
                    $lulusRow = $r['rata_nilai'] >= $kkm;
                ?>
                <tr <?=$r['rank']<=3?"style='background:#fffbeb'":''?>>
                    <td class="text-center fw-bold" style="font-size:16px">
                        <?php if ($r['rank']===1) echo '🥇';
                        elseif ($r['rank']===2) echo '🥈';
                        elseif ($r['rank']===3) echo '🥉';
                        else echo $r['rank']; ?>
                    </td>
                    <td>
                        <strong><?=e($r['nama'])?></strong>
                        <div><code style="font-size:10px;color:#64748b"><?=e($r['kode_peserta'])?></code></div>
                        <div><code style="font-size:10px;color:#94a3b8"><?=e($r['kode_sekolah'] ?? '-')?></code></div>
                    </td>
                    <td class="text-center"><?=e($r['kelas']??'-')?></td>
                    <td class="text-center">
                        <strong style="font-size:16px;color:<?=$pcolor?>"><?=$r['rata_nilai']?></strong>
                    </td>
                    <td class="text-center text-success fw-bold"><?=$r['nilai_terbaik']?></td>
                    <td class="text-center"><?=$r['jml_ujian']?>x</td>
                    <td class="text-center">
                        <span class="badge <?=$r['jml_lulus']>0?'bg-success':'bg-secondary'?>"><?=$r['jml_lulus']?>/<?=$r['jml_ujian']?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge" style="background:<?=$pcolor?>;font-size:11px"><?=$pred?> – <?=$pteks?></span>
                        <div style="font-size:10px;margin-top:2px">
                            <?php if ($lulusRow): ?>
                            <span class="text-success"><i class="bi bi-check-circle-fill"></i> Lulus</span>
                            <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle-fill"></i> Tdk Lulus</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><small class="text-muted"><?=$r['ujian_terakhir']?date('d/m/Y',strtotime($r['ujian_terakhir'])):'-'?></small></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="9" class="text-center text-muted py-5">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada data ranking
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
