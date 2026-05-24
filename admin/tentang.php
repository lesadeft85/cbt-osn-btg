<?php
// ============================================================
// sekolah/tentang.php — Tentang Aplikasi (Akun Sekolah)
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

require_once __DIR__ . '/../config/version.php';

$pageTitle  = 'Tentang Aplikasi';
$activeMenu = 'tentang';
require_once __DIR__ . '/../includes/header.php';
?>

<?= renderFlash() ?>

<!-- Header Card -->
<div class="card mb-4 border-0 shadow-sm" style="background:linear-gradient(135deg,#1a56db 0%,#7c3aed 60%,#0ea5e9 100%);color:#fff;overflow:hidden">
    <div class="card-body py-4 px-4">
        <div class="d-flex align-items-center gap-4">
            <?php
            $lgF = getSetting($conn,'logo_file_path','');
            $lgU = getSetting($conn,'logo_url','');
            $lgA = $lgF ? BASE_URL.'/'.$lgF : $lgU;
            ?>
            <?php if ($lgA): ?>
            <img src="<?= htmlspecialchars($lgA) ?>" alt="Logo"
                 style="width:80px;height:80px;object-fit:contain;background:rgba(255,255,255,.15);border-radius:16px;padding:8px">
            <?php else: ?>
            <div style="width:80px;height:80px;background:rgba(255,255,255,.15);border-radius:16px;display:flex;align-items:center;justify-content:center">
                <i class="bi bi-mortarboard-fill" style="font-size:2.5rem"></i>
            </div>
            <?php endif; ?>
            <div>
                <h4 class="fw-800 mb-1" style="letter-spacing:.5px">
                    <?= htmlspecialchars(getSetting($conn,'nama_aplikasi','CBT TKA Kecamatan')) ?>
                </h4>
                <div style="opacity:.85;font-size:.95rem">
                    <?= htmlspecialchars(getSetting($conn,'nama_kecamatan','')) ?>
                </div>
                <div class="mt-2 d-flex gap-2 flex-wrap">
                    <span class="badge" style="background:rgba(255,255,255,.2);font-size:.8rem">
                        <i class="bi bi-tag me-1"></i>Versi <?= APP_VERSION ?>
                    </span>
                    <span class="badge" style="background:rgba(255,255,255,.2);font-size:.8rem">
                        <i class="bi bi-calendar me-1"></i><?= APP_RELEASE_DATE ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Informasi Aplikasi -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-700">
                <i class="bi bi-info-circle-fill text-primary me-2"></i>Informasi Aplikasi
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="fw-600 text-muted" style="width:40%">Nama Aplikasi</td>
                        <td><?= htmlspecialchars(getSetting($conn,'nama_aplikasi','CBT TKA Kecamatan')) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Versi</td>
                        <td><?= APP_VERSION ?></td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Tanggal Rilis</td>
                        <td><?= APP_RELEASE_DATE ?></td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Penyelenggara</td>
                        <td><?= htmlspecialchars(getSetting($conn,'nama_kecamatan','')) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Tahun Pelajaran</td>
                        <td><?= htmlspecialchars(getSetting($conn,'tahun_pelajaran', date('Y').'/'.(date('Y')+1))) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Platform</td>
                        <td>Web (PHP + MySQL)</td>
                    </tr>
                    <tr>
                        <td class="fw-600 text-muted">Lisensi</td>
                        <td><span class="badge bg-danger">Private</span></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Developer -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-700">
                <i class="bi bi-person-fill text-success me-2"></i>Developer
            </div>
            <div class="card-body text-center py-4">
                <div style="position:relative;width:110px;height:110px;margin:0 auto 20px">
                    <img src="https://avatars.githubusercontent.com/u/30889642?v=4"
                         alt="Zaenal Arifin"
                         onerror="this.src='https://ui-avatars.com/api/?name=Zaenal+Arifin&size=110&background=1a56db&color=fff&bold=true&rounded=true'"
                         style="width:110px;height:110px;border-radius:50%;object-fit:cover;object-position:top;border:4px solid transparent;background:linear-gradient(white,white) padding-box, linear-gradient(135deg,#1a56db,#7c3aed) border-box;box-shadow:0 8px 24px rgba(124,58,237,.3)">
                    <span style="position:absolute;bottom:6px;right:6px;width:18px;height:18px;background:#22c55e;border-radius:50%;border:3px solid #fff;display:block"></span>
                </div>
                <h5 class="fw-700 mb-1">Zaenal Arifin</h5>
                <div class="mb-1">
                    <span class="badge" style="background:linear-gradient(135deg,#1a56db,#7c3aed);font-size:.78rem;padding:4px 10px">
                        <i class="bi bi-code-slash me-1"></i>Full Stack Web Developer
                    </span>
                </div>
                <div class="text-muted mb-3" style="font-size:.85rem">
                    <i class="bi bi-geo-alt me-1"></i>Indonesia
                </div>
                <div class="d-flex gap-1 justify-content-center flex-wrap mb-3">
                    <span class="badge bg-light text-dark border" style="font-size:.75rem">PHP</span>
                    <span class="badge bg-light text-dark border" style="font-size:.75rem">MySQL</span>
                    <span class="badge bg-light text-dark border" style="font-size:.75rem">JavaScript</span>
                    <span class="badge bg-light text-dark border" style="font-size:.75rem">Nginx</span>
                    <span class="badge bg-light text-dark border" style="font-size:.75rem">Bootstrap</span>
                </div>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="https://github.com/lesadeft85" target="_blank" class="btn btn-sm btn-outline-dark">
                        <i class="bi bi-github me-1"></i>lesadeft85
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Fitur Aplikasi -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header fw-700">
                <i class="bi bi-stars text-warning me-2"></i>Fitur Aplikasi
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $fitur = [
                        ['icon'=>'bi-mortarboard-fill',        'color'=>'primary', 'judul'=>'Manajemen Ujian',       'desc'=>'Kelola soal, kategori, jadwal, dan token ujian dengan mudah'],
                        ['icon'=>'bi-people-fill',             'color'=>'success', 'judul'=>'Manajemen Peserta',     'desc'=>'Import peserta dari Excel, kelola data sekolah dan kelas'],
                        ['icon'=>'bi-laptop',                  'color'=>'info',    'judul'=>'Ujian Online (CBT)',    'desc'=>'Ujian berbasis komputer dengan timer, acak soal & jawaban'],
                        ['icon'=>'bi-bar-chart-fill',          'color'=>'warning', 'judul'=>'Rekap & Statistik',    'desc'=>'Hasil nilai, rekap per sekolah, grafik distribusi predikat'],
                        ['icon'=>'bi-file-earmark-pdf-fill',   'color'=>'danger',  'judul'=>'Export PDF & Excel',   'desc'=>'Cetak hasil ujian, kartu peserta, dan sertifikat'],
                        ['icon'=>'bi-shield-lock-fill',        'color'=>'dark',    'judul'=>'Keamanan',             'desc'=>'Mode kiosk, deteksi tab switching, log aktivitas user'],
                        ['icon'=>'bi-activity',                'color'=>'danger',  'judul'=>'Monitoring Live',      'desc'=>'Pantau peserta yang sedang ujian secara realtime'],
                        ['icon'=>'bi-trophy-fill',             'color'=>'warning', 'judul'=>'Ranking Peserta',      'desc'=>'Peringkat nilai peserta dengan podium juara 1, 2, dan 3'],
                        ['icon'=>'bi-calendar-check',          'color'=>'success', 'judul'=>'Rekap Per Jadwal',     'desc'=>'Rekap nilai per ujian dan per kelas dengan % kelulusan'],
                        ['icon'=>'bi-printer-fill',            'color'=>'primary', 'judul'=>'Daftar Hadir',         'desc'=>'Cetak daftar hadir peserta dengan kolom tanda tangan'],
                        ['icon'=>'bi-moon-stars-fill',         'color'=>'dark',    'judul'=>'Dark Mode',            'desc'=>'Tampilan gelap untuk kenyamanan penggunaan malam hari'],
                        ['icon'=>'bi-display',                 'color'=>'info',    'judul'=>'Kartu Ujian',          'desc'=>'Cetak kartu ujian peserta lengkap dengan kode unik'],
                    ];
                    foreach ($fitur as $f): ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background:#f8f9fa">
                            <div class="flex-shrink-0">
                                <div class="rounded-3 d-flex align-items-center justify-content-center text-<?= $f['color'] ?>"
                                     style="width:40px;height:40px;background:var(--bs-<?= $f['color'] ?>-bg-subtle,#e9ecef)">
                                    <i class="bi <?= $f['icon'] ?> fs-5"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fw-600 mb-1" style="font-size:.9rem"><?= $f['judul'] ?></div>
                                <div class="text-muted" style="font-size:.8rem"><?= $f['desc'] ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Kontak & Bantuan -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header fw-700">
                <i class="bi bi-headset text-primary me-2"></i>Kontak & Bantuan
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <p class="mb-2">Jika ada kendala teknis atau pertanyaan seputar penggunaan aplikasi, silakan hubungi pengembang atau administrator kecamatan.</p>
                       
                    </div>
                    <div class="col-md-4 text-center">
                        <div style="font-size:3rem">🛠️</div>
                        <div class="text-muted" style="font-size:.85rem">Dukungan teknis tersedia</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
