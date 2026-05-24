<?php
// ============================================================
// admin/jadwal.php — Kelola Jadwal Ujian
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

/* ── HAPUS ───────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrfVerify();
    $id = (int)$_POST['id'];

    // FIX: Hapus berurutan agar tidak ada orphan data
    // 1. Ambil semua ujian_id yang terkait jadwal ini
    $ujianIds = [];
    $_qUjian = $conn->query("SELECT id FROM ujian WHERE jadwal_id=$id");
    if ($_qUjian) while ($u = $_qUjian->fetch_assoc()) $ujianIds[] = (int)$u['id'];

    if (!empty($ujianIds)) {
        $ujianIdStr = implode(',', $ujianIds);
        $conn->query("DELETE FROM jawaban    WHERE ujian_id IN ($ujianIdStr)");
        $conn->query("DELETE FROM ujian      WHERE id IN ($ujianIdStr)");
    }
    $conn->query("DELETE FROM hasil_ujian WHERE jadwal_id=$id");
    $conn->query("DELETE FROM jadwal_ujian WHERE id=$id");

    setFlash('success', 'Jadwal beserta seluruh data ujian terkait berhasil dihapus.');
    redirect(BASE_URL . '/admin/jadwal.php');
}

/* ── TOGGLE STATUS ───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'toggle') {
    csrfVerify();
    $id    = (int)$_POST['id'];
    $_qJad = $conn->query("SELECT status FROM jadwal_ujian WHERE id=$id LIMIT 1");
    $row   = null;
    if ($_qJad && $_qJad->num_rows > 0) { $row = $_qJad->fetch_assoc(); }
    if ($_qJad) { $_qJad->free(); }
    $baru = ($row && $row['status'] === 'aktif') ? 'nonaktif' : 'aktif';
    $conn->query("UPDATE jadwal_ujian SET status='$baru' WHERE id=$id");
    setFlash('success', "Status diubah ke <strong>$baru</strong>.");
    redirect(BASE_URL . '/admin/jadwal.php');
}

/* ── MIGRASI KOLOM jumlah_soal (auto, sekali) ───────────── */
$colJml = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'jumlah_soal'");
if (!$colJml || $colJml->num_rows === 0) {
    $conn->query("ALTER TABLE jadwal_ujian ADD COLUMN jumlah_soal INT NULL DEFAULT NULL COMMENT 'Override jumlah soal global; NULL = pakai pengaturan global'");
}

/* ── MIGRASI KOLOM kelas_diizinkan (auto, sekali) ────────── */
$colKelas = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'kelas_diizinkan'");
if (!$colKelas || $colKelas->num_rows === 0) {
    $conn->query("ALTER TABLE jadwal_ujian ADD COLUMN kelas_diizinkan VARCHAR(100) NULL DEFAULT NULL COMMENT 'Kosong = semua kelas; isi cth: V,VI untuk kelas tertentu (format Romawi)'");
}

// ── Daftar kelas per jenjang untuk checkbox ──────────────────
// Format Romawi — konsisten dengan data peserta
$kelasOptions = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];

/* ── TAMBAH ──────────────────────────────────────────────── */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') csrfVerify();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $tanggal  = trim($_POST['tanggal'] ?? '');
    $mulai    = trim($_POST['jam_mulai'] ?? '');
    $selesai  = trim($_POST['jam_selesai'] ?? '');
    $durasi   = (int)($_POST['durasi_menit'] ?? 60);
    $ket      = trim($_POST['keterangan'] ?? '');
    $status   = trim($_POST['status'] ?? 'aktif');
    $katId    = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $jmlSoal  = (int)($_POST['jumlah_soal'] ?? 0) ?: null;

    // Kumpulkan kelas dari checkbox (format Romawi)
    $kelasCb   = $_POST['kelas_cb'] ?? [];
    $kelasCb   = array_filter(array_map('trim', (array)$kelasCb));
    // Validasi: pastikan hanya nilai Romawi yang valid
    $kelasCb   = array_filter($kelasCb, fn($k) => in_array($k, $kelasOptions));
    $kelasIzin = implode(',', $kelasCb);

    if (!$tanggal) $errors[] = 'Tanggal wajib diisi.';
    if (!$mulai)   $errors[] = 'Jam mulai wajib diisi.';
    if (!$selesai) $errors[] = 'Jam selesai wajib diisi.';
    if ($durasi < 1 || $durasi > 300) $errors[] = 'Durasi 1–300 menit.';
    if ($jmlSoal !== null && ($jmlSoal < 1 || $jmlSoal > 200)) $errors[] = 'Jumlah soal 1–200.';
    if (!$errors && $selesai <= $mulai) $errors[] = 'Jam selesai harus lebih besar dari jam mulai.';

    if (!$errors) {
        $jmlSoalSql = $jmlSoal ? (int)$jmlSoal : 'NULL';
        $katIdSql   = $katId   ? (int)$katId   : 'NULL';
        $kelasStr   = $kelasIzin !== '' ? "'" . $conn->real_escape_string($kelasIzin) . "'" : 'NULL';
        $conn->query(
            "INSERT INTO jadwal_ujian (tanggal,jam_mulai,jam_selesai,durasi_menit,kategori_id,keterangan,status,jumlah_soal,kelas_diizinkan)
             VALUES ('$tanggal','$mulai','$selesai',$durasi,$katIdSql,'" . $conn->real_escape_string($ket) . "','$status',$jmlSoalSql,$kelasStr)"
        );
        setFlash('success', 'Jadwal ujian berhasil ditambahkan.');
        redirect(BASE_URL . '/admin/jadwal.php');
    }
}

/* ── EDIT ────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id      = (int)$_POST['id'];
    $tanggal = trim($_POST['tanggal'] ?? '');
    $mulai   = trim($_POST['jam_mulai'] ?? '');
    $selesai = trim($_POST['jam_selesai'] ?? '');
    $durasi  = (int)($_POST['durasi_menit'] ?? 60);
    $ket     = trim($_POST['keterangan'] ?? '');
    $status  = trim($_POST['status'] ?? 'aktif');
    $katId   = (int)($_POST['kategori_id'] ?? 0) ?: null;
    $jmlSoal = (int)($_POST['jumlah_soal'] ?? 0) ?: null;

    // Kumpulkan kelas dari checkbox (format Romawi)
    $kelasCb   = $_POST['kelas_cb'] ?? [];
    $kelasCb   = array_filter(array_map('trim', (array)$kelasCb));
    $kelasCb   = array_filter($kelasCb, fn($k) => in_array($k, $kelasOptions));
    $kelasIzin = implode(',', $kelasCb);

    $jmlSoalSql = $jmlSoal   ? (int)$jmlSoal   : 'NULL';
    $katIdSql   = $katId     ? (int)$katId      : 'NULL';
    $kelasStr   = $kelasIzin !== '' ? "'" . $conn->real_escape_string($kelasIzin) . "'" : 'NULL';
    $ketEsc     = $conn->real_escape_string($ket);

    $conn->query(
        "UPDATE jadwal_ujian
         SET tanggal='$tanggal', jam_mulai='$mulai', jam_selesai='$selesai',
             durasi_menit=$durasi, kategori_id=$katIdSql,
             keterangan='$ketEsc', status='$status',
             jumlah_soal=$jmlSoalSql, kelas_diizinkan=$kelasStr
         WHERE id=$id"
    );
    setFlash('success', 'Jadwal berhasil diperbarui.');
    redirect(BASE_URL . '/admin/jadwal.php');
}

/* ── DATA ────────────────────────────────────────────────── */
$katList = [];
$kr = $conn->query("SELECT id, nama_kategori FROM kategori_soal ORDER BY nama_kategori");
if ($kr) { while ($k = $kr->fetch_assoc()) $katList[$k['id']] = $k['nama_kategori']; $kr->free(); }

$_listRes = $conn->query("
    SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.durasi_menit,
           j.kategori_id, IFNULL(j.keterangan,'') AS keterangan, j.status, j.created_at,
           IFNULL(j.kelas_diizinkan,'') AS kelas_diizinkan,
           IFNULL(j.jumlah_soal, 0) AS jumlah_soal,
           k.nama_kategori
    FROM jadwal_ujian j
    LEFT JOIN kategori_soal k ON k.id = j.kategori_id
    ORDER BY j.tanggal DESC, j.jam_mulai ASC
");
if (!$_listRes) {
    $conn->query("SELECT 1");
    $_listRes = $conn->query("
        SELECT j.id, j.tanggal, j.jam_mulai, j.jam_selesai, j.durasi_menit,
               j.kategori_id, '' AS keterangan, j.status, j.created_at,
               '' AS kelas_diizinkan, 0 AS jumlah_soal, k.nama_kategori
        FROM jadwal_ujian j
        LEFT JOIN kategori_soal k ON k.id = j.kategori_id
        ORDER BY j.tanggal DESC, j.jam_mulai ASC
    ");
}
$list = [];
if ($_listRes) { while ($row = $_listRes->fetch_assoc()) $list[] = $row; $_listRes->free(); }

$editData = null;
if (isset($_GET['edit'])) {
    $eid      = (int)$_GET['edit'];
    $er       = $conn->query("SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, keterangan, status, kategori_id, jumlah_soal, IFNULL(kelas_diizinkan,'') AS kelas_diizinkan FROM jadwal_ujian WHERE id=$eid LIMIT 1");
    $editData = $er ? $er->fetch_assoc() : null;
    if ($er) { $er->free(); }
}

$nowDate    = date('Y-m-d');
$nowTime    = date('H:i:s');

// ── Auto-nonaktif jadwal yang sudah lewat ─────────────────────
// Jadwal dianggap selesai jika: tanggalnya sudah lewat ATAU
// tanggalnya hari ini tapi jam selesainya sudah terlampaui.
$conn->query(
    "UPDATE jadwal_ujian SET status='nonaktif'
     WHERE status='aktif'
       AND (
         tanggal < '$nowDate'
         OR (tanggal = '$nowDate' AND jam_selesai < '$nowTime')
       )"
);
// ─────────────────────────────────────────────────────────────
$_res = $conn->query(
    "SELECT id, tanggal, jam_mulai, jam_selesai, durasi_menit, status, kategori_id,
            IFNULL(keterangan, '') AS keterangan
     FROM jadwal_ujian
     WHERE tanggal='$nowDate'
       AND jam_mulai <= '$nowTime'
       AND jam_selesai >= '$nowTime'
       AND status='aktif'
     LIMIT 1"
);
$jadwalAktif = null;
if ($_res && $_res->num_rows > 0) { $jadwalAktif = $_res->fetch_assoc(); }
if ($_res) { $_res->free(); }

$pageTitle  = 'Jadwal Ujian';
$activeMenu = 'jadwal';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-calendar-event-fill me-2 text-primary"></i>Jadwal Ujian</h2>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/admin/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active">Jadwal Ujian</li>
        </ol></nav>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
        <i class="bi bi-plus-lg me-1"></i>Tambah Jadwal
    </button>
</div>

<?= renderFlash() ?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
    <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if (is_array($jadwalAktif)): ?>
<div class="alert alert-success d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-calendar-check-fill fs-4"></i>
    <div>
        <strong>Ujian sedang berlangsung!</strong>
        Jadwal: <?= date('d/m/Y', strtotime($jadwalAktif['tanggal'])) ?>
        pukul <?= substr($jadwalAktif['jam_mulai'], 0, 5) ?> – <?= substr($jadwalAktif['jam_selesai'], 0, 5) ?>
        (<?= $jadwalAktif['durasi_menit'] ?> menit)
        <?php if ($jadwalAktif['keterangan']): ?>
        — <?= htmlspecialchars($jadwalAktif['keterangan']) ?>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-clock"></i>
    <span>Tidak ada ujian aktif saat ini.
    Waktu sekarang: <strong><?= date('d/m/Y H:i:s') ?></strong></span>
</div>
<?php endif; ?>

<div class="alert alert-info d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div class="small">
        <strong>Cara kerja Jadwal Ujian:</strong>
        Peserta hanya bisa memulai ujian jika ada jadwal yang berstatus
        <span class="badge bg-success">Aktif</span> dengan waktu sesuai hari dan jam sekarang.
        <br>Kolom <strong>Durasi</strong> menentukan berapa menit timer ujian berjalan.
        <br><strong>Ruang yang Diizinkan:</strong> centang Ruang yang boleh ikut. Kosong = semua kelas.
        Format ruang menggunakan angka Romawi (I, II, III, IV, V, VI, dst).
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-2"></i>Daftar Jadwal</span>
        <span class="badge bg-primary"><?= count($list) ?> jadwal</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tblJadwal" class="table table-hover datatable mb-0">
                <thead><tr>
                    <th>#</th><th>Tanggal</th><th>Jam Mulai</th><th>Jam Selesai</th>
                    <th class="text-center">Durasi</th><th class="text-center">Soal</th><th>Tes</th>
                    <th class="text-center">Ruang</th><th>Keterangan</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width:110px">Aksi</th>
                </tr></thead>
                <tbody>
                <?php if (count($list) > 0):
                      $no = 1; foreach ($list as $row):
                      $isHariIni  = $row['tanggal'] === date('Y-m-d');
                      $isBerjalan = $isHariIni
                                 && $row['jam_mulai'] <= $nowTime
                                 && $row['jam_selesai'] >= $nowTime
                                 && $row['status'] === 'aktif';
                ?>
                <tr class="<?= $isBerjalan ? 'table-success' : ($row['tanggal'] < date('Y-m-d') ? 'table-light' : '') ?>">
                    <td><?= $no++ ?></td>
                    <td>
                        <strong><?= date('d F Y', strtotime($row['tanggal'])) ?></strong>
                        <?php if ($isHariIni): ?>
                        <span class="badge bg-primary ms-1">Hari Ini</span>
                        <?php endif; ?>
                        <?php if ($isBerjalan): ?>
                        <span class="badge bg-success ms-1">
                            <i class="bi bi-broadcast me-1"></i>LIVE
                        </span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= substr($row['jam_mulai'], 0, 5) ?></strong></td>
                    <td><strong><?= substr($row['jam_selesai'], 0, 5) ?></strong></td>
                    <td class="text-center">
                        <span class="badge bg-info"><?= $row['durasi_menit'] ?> menit</span>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($row['jumlah_soal'])): ?>
                        <span class="badge bg-warning text-dark"><?= (int)$row['jumlah_soal'] ?></span>
                        <?php else: ?>
                        <span class="text-muted small">Global</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['nama_kategori']): ?>
                        <span class="badge bg-primary"><?= e($row['nama_kategori']) ?></span>
                        <?php else: ?>
                        <span class="text-muted small">Semua Mapel</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($row['kelas_diizinkan'])): ?>
                        <?php foreach (array_filter(array_map('trim', explode(',', $row['kelas_diizinkan']))) as $kl): ?>
                        <span class="badge bg-info me-1">Ruang <?= e($kl) ?></span>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <span class="text-muted small">Semua</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($row['keterangan'] ?? '-') ?></td>
                    <td class="text-center">
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                            <button type="submit"
                                class="badge border-0 text-decoration-none bg-<?= $row['status']==='aktif'?'success':'secondary' ?>"
                                style="cursor:pointer">
                                <?= $row['status'] === 'aktif' ? '● Aktif' : '○ Nonaktif' ?>
                            </button>
                        </form>
                    </td>
                    <td class="text-center">
                        <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                            <i class="bi bi-pencil"></i></a>
                        <form method="POST" style="display:inline"
                              onsubmit="return confirm('Hapus jadwal ini?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id"   value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="11" class="text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>Belum ada jadwal ujian
                </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── MODAL TAMBAH ── -->
<div class="modal fade <?= $errors ? 'show' : '' ?>" id="modalTambah" tabindex="-1"
     <?= $errors ? 'style="display:block"' : '' ?>>
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="formTambah">
            <input type="hidden" name="aksi" value="tambah">
            <?= csrfField() ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Tambah Jadwal Ujian</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Tanggal Ujian <span class="text-danger">*</span></label>
                        <input type="date" name="tanggal" class="form-control" required
                               value="<?= htmlspecialchars($_POST['tanggal'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
                        <input type="time" name="jam_mulai" class="form-control" required
                               value="<?= htmlspecialchars($_POST['jam_mulai'] ?? '08:00') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
                        <input type="time" name="jam_selesai" class="form-control" required
                               value="<?= htmlspecialchars($_POST['jam_selesai'] ?? '10:00') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Durasi Ujian (menit) <span class="text-danger">*</span></label>
                        <input type="number" name="durasi_menit" class="form-control" required min="1" max="300"
                               value="<?= htmlspecialchars($_POST['durasi_menit'] ?? '60') ?>">
                        <div class="form-text">Timer ujian peserta (bisa berbeda dengan rentang jam).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Jumlah Soal
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <input type="number" name="jumlah_soal" class="form-control" min="1" max="200"
                               value="<?= htmlspecialchars($_POST['jumlah_soal'] ?? '') ?>"
                               placeholder="Kosongkan = pakai pengaturan global">
                        <div class="form-text">Override jumlah soal untuk jadwal ini saja.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Tes
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <select name="kategori_id" class="form-select">
                            <option value="">— Semua Tes —</option>
                            <?php foreach ($katList as $kid => $knm): ?>
                            <option value="<?= $kid ?>" <?= ($_POST['kategori_id'] ?? 0) == $kid ? 'selected' : '' ?>>
                                <?= e($knm) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Kosongkan jika semua mapel ikut ujian bersamaan.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Ruang yang Diizinkan
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <?php
                        // Kelompokkan per jenjang untuk tampilan lebih rapi
                        $kelasGroups = [
                            'SD / MI'        => ['I','II','III','IV','V','VI'],
                            'SMP / MTs'      => ['VII','VIII','IX'],
                            'SMA / MA / SMK' => ['X','XI','XII'],
                        ];
                        $postKelas = (array)($_POST['kelas_cb'] ?? []);
                        ?>
                        <?php foreach ($kelasGroups as $grupLabel => $grupKelas): ?>
                        <div class="mb-1 mt-2">
                            <small class="text-muted fw-semibold"><?= $grupLabel ?></small>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <?php foreach ($grupKelas as $kl): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="kelas_cb[]" value="<?= $kl ?>"
                                           id="tambah_kelas_<?= $kl ?>"
                                           <?= in_array($kl, $postKelas) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tambah_kelas_<?= $kl ?>">
                                        Ruang <?= $kl ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="form-text mt-1">Kosongkan = semua ruang boleh ikut ujian ini.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Keterangan <span class="text-muted">(opsional)</span></label>
                        <input type="text" name="keterangan" class="form-control" maxlength="200"
                               placeholder="cth: Ujian Semester Ganjil Kelas VI"
                               value="<?= htmlspecialchars($_POST['keterangan'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Simpan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── MODAL EDIT ── -->
<?php if ($editData): ?>
<?php $kelasAktif = array_filter(array_map('trim', explode(',', $editData['kelas_diizinkan'] ?? ''))); ?>
<div class="modal fade show" id="modalEdit" tabindex="-1" style="display:block">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content" id="formEdit">
            <input type="hidden" name="aksi" value="edit">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $editData['id'] ?>">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">Edit Jadwal</h5>
                <a href="<?= BASE_URL ?>/admin/jadwal.php" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control"
                               value="<?= $editData['tanggal'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control"
                               value="<?= substr($editData['jam_mulai'], 0, 5) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control"
                               value="<?= substr($editData['jam_selesai'], 0, 5) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Durasi (menit)</label>
                        <input type="number" name="durasi_menit" class="form-control"
                               value="<?= $editData['durasi_menit'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Jumlah Soal
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <input type="number" name="jumlah_soal" class="form-control" min="1" max="200"
                               value="<?= htmlspecialchars($editData['jumlah_soal'] ?? '') ?>"
                               placeholder="Kosongkan = pakai pengaturan global">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="aktif"    <?= $editData['status']==='aktif'    ?'selected':''?>>Aktif</option>
                            <option value="nonaktif" <?= $editData['status']==='nonaktif' ?'selected':''?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Tes
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <select name="kategori_id" class="form-select">
                            <option value="">— Semua Tes —</option>
                            <?php foreach ($katList as $kid => $knm): ?>
                            <option value="<?= $kid ?>" <?= ($editData['kategori_id'] ?? 0) == $kid ? 'selected' : '' ?>>
                                <?= e($knm) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            Ruang yang Diizinkan
                            <span class="badge bg-secondary ms-1" style="font-size:10px">Opsional</span>
                        </label>
                        <?php foreach ($kelasGroups as $grupLabel => $grupKelas): ?>
                        <div class="mb-1 mt-2">
                            <small class="text-muted fw-semibold"><?= $grupLabel ?></small>
                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <?php foreach ($grupKelas as $kl): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="kelas_cb[]" value="<?= $kl ?>"
                                           id="edit_kelas_<?= $kl ?>"
                                           <?= in_array($kl, $kelasAktif) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_kelas_<?= $kl ?>">
                                        Ruang <?= $kl ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="form-text mt-1">Kosongkan = semua ruang boleh ikut ujian ini.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Keterangan</label>
                        <input type="text" name="keterangan" class="form-control"
                               value="<?= htmlspecialchars($editData['keterangan'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="<?= BASE_URL ?>/admin/jadwal.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-save me-1"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
<?php endif; ?>

<?php if ($errors): ?>
<script>document.addEventListener('DOMContentLoaded',()=>new bootstrap.Modal(document.getElementById('modalTambah')).show())</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
