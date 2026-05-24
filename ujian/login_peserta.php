<?php
// ============================================================
// ujian/login_peserta.php — Login Peserta Ujian
// Peserta memasukkan: kode_peserta + token ujian
// ============================================================

// Session HARUS distart lebih dulu sebelum akses $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_name('TKA_PESERTA');   // Nama berbeda dari admin (TKA_SID) agar tidak bentrok
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/helper.php';
// Centralized external paths
if (file_exists(__DIR__ . '/../config/paths.php')) {
  require_once __DIR__ . '/../config/paths.php';
}

// Jika sudah login sebagai peserta, langsung ke halaman soal
if (!empty($_SESSION['peserta_id'])) {
    redirect(BASE_URL . '/ujian/soal.php');
}

$error = '';
$today    = date('Y-m-d');
$nowTime  = date('H:i:s');

// ── Ambil SEMUA jadwal aktif sekarang ────────────────────────
// Sengaja tidak LIMIT 1 karena bisa ada jadwal Kelas 5 dan Kelas 6 bersamaan.
// Pemilihan jadwal yang cocok dilakukan setelah kelas peserta diketahui.
$jadwal          = null;   // jadwal terpilih (diisi setelah kelas peserta diketahui)
$jadwalAktifList = [];     // semua jadwal aktif saat ini
$jadwalAktifSemuaList = []; // semua jadwal berstatus aktif di DB (fallback tampilan)

// ── Auto-nonaktif jadwal yang sudah lewat ─────────────────────
// Jalankan sebelum query jadwal aktif agar tidak terbaca lagi.
$conn->query(
    "UPDATE jadwal_ujian SET status='nonaktif'
     WHERE status='aktif'
       AND (
         tanggal < '$today'
         OR (tanggal = '$today' AND jam_selesai < '$nowTime')
       )"
);
// ─────────────────────────────────────────────────────────────

$jr = $conn->query(
    "SELECT j.*, k.nama_kategori
     FROM jadwal_ujian j
     LEFT JOIN kategori_soal k ON k.id = j.kategori_id
     WHERE j.tanggal='$today' AND j.jam_mulai<='$nowTime' AND j.jam_selesai>='$nowTime' AND j.status='aktif'
     ORDER BY j.id ASC"
);
if ($jr) { while ($jrow = $jr->fetch_assoc()) $jadwalAktifList[] = $jrow; }
// Untuk tampilan status di halaman (sebelum login), pakai jadwal pertama sementara
if (!empty($jadwalAktifList)) $jadwal = $jadwalAktifList[0];

// Fallback tampilan: kalau jadwal sedang berjalan belum terbaca oleh jendela jam,
// tetap ambil jadwal aktif hari ini agar banner status tidak kosong.
$jadwalAktifHariIniList = [];
$jrHariIni = $conn->query(
  "SELECT j.*, k.nama_kategori
   FROM jadwal_ujian j
   LEFT JOIN kategori_soal k ON k.id = j.kategori_id
   WHERE j.tanggal='$today' AND j.status='aktif'
   ORDER BY j.jam_mulai ASC, j.id ASC"
);
if ($jrHariIni) { while ($jrow = $jrHariIni->fetch_assoc()) $jadwalAktifHariIniList[] = $jrow; }
if (empty($jadwal) && !empty($jadwalAktifHariIniList)) $jadwal = $jadwalAktifHariIniList[0];

$jrAktifSemua = $conn->query(
  "SELECT j.*, k.nama_kategori
   FROM jadwal_ujian j
   LEFT JOIN kategori_soal k ON k.id = j.kategori_id
   WHERE j.status='aktif'
   ORDER BY j.tanggal ASC, j.jam_mulai ASC, j.id ASC"
);
if ($jrAktifSemua) { while ($jrow = $jrAktifSemua->fetch_assoc()) $jadwalAktifSemuaList[] = $jrow; }
$jumlahJadwalAktif = count($jadwalAktifSemuaList);

// Banner status pakai jadwal apapun yang masih aktif, agar tidak hilang ketika
// jadwal hari ini belum terdeteksi oleh filter jam.
$jadwalBanner = $jadwal ?: ($jadwalAktifHariIniList[0] ?? ($jadwalAktifSemuaList[0] ?? null));

/**
 * Konversi kelas format Romawi ke Angka.
 * Contoh: "VI" → "6", "V" → "5", "IV" → "4"
 * Jika bukan Romawi, dikembalikan apa adanya.
 */
function kelasRomawiKeAngka(string $kelas): string {
    $map = [
        'XII' => '12', 'XI' => '11', 'X' => '10',
        'IX'  => '9',  'VIII' => '8', 'VII' => '7',
        'VI'  => '6',  'V'    => '5', 'IV'  => '4',
        'III' => '3',  'II'   => '2', 'I'   => '1',
    ];
    // Coba cocokkan prefix Romawi (misal "VIA" → romawi="VI", sisa="A")
    foreach ($map as $romawi => $angka) {
        if (strpos($kelas, $romawi) === 0) {
            return $angka . substr($kelas, strlen($romawi)); // "VIA" → "6A"
        }
    }
    return $kelas;
}

/**
 * Pilih jadwal terbaik untuk peserta berdasarkan kelasnya.
 * Prioritas: jadwal dengan kelas_diizinkan cocok > jadwal tanpa batasan kelas.
 */
function pilihJadwalUntukPeserta(array $list, string $kelasPeserta): ?array {
    // Normalisasi: konversi Romawi ke Angka agar "VI" == "6", "VIA" == "6A"
    $kelasPeserta = kelasRomawiKeAngka($kelasPeserta);
    $fallback = null;
    foreach ($list as $j) {
        if (empty($j['kelas_diizinkan'])) {
            if ($fallback === null) $fallback = $j;
            continue;
        }
        $izinArr = array_filter(array_map('trim', explode(',', $j['kelas_diizinkan'])));
        foreach ($izinArr as $izin) {
            // Normalisasi kelas_diizinkan juga — bisa tersimpan "VI" atau "6"
            $izin = kelasRomawiKeAngka(strtoupper(trim($izin)));
            if ($kelasPeserta === $izin || strpos($kelasPeserta, $izin) === 0) {
                return $j; // jadwal spesifik cocok — prioritas utama
            }
        }
    }
    return $fallback; // tidak ada yang spesifik, pakai jadwal tanpa batasan kelas
}

// ── Auto-migrasi kolom kelas_diizinkan jika belum ada ────────
$_colCekKelas = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'kelas_diizinkan'");
if ($_colCekKelas && $_colCekKelas->num_rows === 0) {
    $conn->query("ALTER TABLE jadwal_ujian ADD COLUMN kelas_diizinkan VARCHAR(200) NULL COMMENT 'Kelas yang boleh ikut, misal: 6A,6B,6C. Kosong = semua kelas.' AFTER kategori_id");
}

// ── Proses form login ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $kode  = strtoupper(trim($_POST['kode_peserta'] ?? ''));
    $token = strtoupper(trim($_POST['token'] ?? ''));

    if ($kode === '' || $token === '') {
        $error = 'Kode peserta dan token ujian wajib diisi.';
    } else {
        // 1. Cek token valid (aktif + tanggal hari ini)
        $tk      = $conn->real_escape_string($token);
        $nowTime = date('H:i:s');
        $tokenRow = $conn->query(
            "SELECT * FROM token_ujian WHERE token='$tk' AND tanggal='$today' AND status='aktif' LIMIT 1"
        );
        if (!$tokenRow || $tokenRow->num_rows === 0) {
            $error = 'Token ujian tidak valid atau belum aktif untuk hari ini.';
        } else {
            $tokenData = $tokenRow->fetch_assoc();

            // Cek jam sesi jika token punya batasan jam
            if (!empty($tokenData['jam_mulai']) && !empty($tokenData['jam_selesai'])) {
                if ($nowTime < $tokenData['jam_mulai']) {
                    $mulai = substr($tokenData['jam_mulai'], 0, 5);
                    $error = "Sesi ujian belum dimulai. Token ini aktif mulai jam <strong>$mulai</strong>.";
                    $tokenData = null;
                } elseif ($nowTime > $tokenData['jam_selesai']) {
                    $selesai = substr($tokenData['jam_selesai'], 0, 5);
                    $error = "Waktu sesi ujian sudah berakhir (batas jam <strong>$selesai</strong>).";
                    $tokenData = null;
                }
            }
        }
        if ($tokenData !== null && empty($error)) {

            // 2. Cek ada jadwal aktif sama sekali
            if (empty($jadwalAktifList)) {
                $error = 'Ujian belum dimulai atau sudah berakhir. Silakan hubungi pengawas.';
            }

            if (!$error) {
                $kd = $conn->real_escape_string($kode);
                $pRow = $conn->query(
                    "SELECT p.*, s.nama_sekolah FROM peserta p
                     LEFT JOIN sekolah s ON s.id = p.sekolah_id
                     WHERE p.kode_peserta='$kd' LIMIT 1"
                );
                if (!$pRow || $pRow->num_rows === 0) {
                    $error = 'Kode peserta tidak ditemukan. Periksa kembali kartu ujian Anda.';
                } else {
                    $peserta = $pRow->fetch_assoc();

                    // ── 3. PILIH JADWAL YANG COCOK DENGAN KELAS PESERTA ─────────
                    $kelasPeserta = strtoupper(trim($peserta['kelas'] ?? ''));
                    $jadwal = pilihJadwalUntukPeserta($jadwalAktifList, $kelasPeserta);

                    if (!$jadwal) {
                        $error = 'Tidak ada jadwal ujian yang sesuai untuk kelas Anda. Hubungi pengawas.';
                    }
                }
            }

            if (!$error && $jadwal) {
                // Pastikan $peserta sudah di-set (dari blok di atas)

                // 2b. Validasi jumlah soal di bank cukup
                $jumlahSoalGlobal = (int)getSetting($conn, 'jumlah_soal', '0');
                $_colCekL = $conn->query("SHOW COLUMNS FROM jadwal_ujian LIKE 'jumlah_soal'");
                if ($_colCekL && $_colCekL->num_rows > 0) {
                    $_qJdL = $conn->query("SELECT jumlah_soal FROM jadwal_ujian WHERE id=" . (int)$jadwal['id'] . " LIMIT 1");
                    if ($_qJdL && $_qJdL->num_rows > 0) {
                        $jdL = $_qJdL->fetch_assoc();
                        if (!empty($jdL['jumlah_soal'])) $jumlahSoalGlobal = (int)$jdL['jumlah_soal'];
                    }
                }
                $katFilter = $jadwal['kategori_id'] ? "WHERE kategori_id=" . (int)$jadwal['kategori_id'] : '';
                $qBank     = $conn->query("SELECT COUNT(*) AS c FROM soal $katFilter");
                $jmlBank   = $qBank ? (int)$qBank->fetch_assoc()['c'] : 0;
                if ($jmlBank === 0) {
                    $namaMapel = !empty($jadwal['nama_kategori']) ? $jadwal['nama_kategori'] : 'semua mapel';
                    $error = "Bank soal <strong>$namaMapel</strong> masih kosong. Hubungi pengawas.";
                } elseif ($jumlahSoalGlobal > 0 && $jmlBank < $jumlahSoalGlobal) {
                    $namaMapel = !empty($jadwal['nama_kategori']) ? $jadwal['nama_kategori'] : 'semua mapel';
                    $error = "Bank soal <strong>$namaMapel</strong> hanya punya <strong>$jmlBank</strong> soal, kurang dari target <strong>$jumlahSoalGlobal</strong> soal. Hubungi pengawas.";
                }
            }

            if (!$error && $jadwal && isset($peserta)) {
                        // 4. Cek apakah sudah ujian pada jadwal/mapel yang SAMA (bukan semua ujian)
                        //    Fix: peserta yang sudah ujian Matematika tetap bisa ikut Bahasa Indonesia
                        $jadwalIdCek = (int)$jadwal['id'];
                        $sudahSelesai = $conn->query(
                            "SELECT id FROM ujian
                             WHERE peserta_id={$peserta['id']}
                               AND jadwal_id=$jadwalIdCek
                               AND waktu_selesai IS NOT NULL
                             LIMIT 1"
                        );
                        if ($sudahSelesai && $sudahSelesai->num_rows > 0) {
                            $namaMapel = !empty($jadwal['nama_kategori']) ? ' (' . $jadwal['nama_kategori'] . ')' : '';
                            $error = "Anda sudah menyelesaikan ujian sesi ini{$namaMapel}. Hubungi pengawas jika ada masalah.";
                        } else {
                            // 5. Cek apakah ujian pada jadwal yang SAMA sedang berlangsung (belum selesai)
                            $ujianAktif = $conn->query(
                                "SELECT id FROM ujian
                                 WHERE peserta_id={$peserta['id']}
                                   AND jadwal_id=$jadwalIdCek
                                   AND waktu_selesai IS NULL
                                 LIMIT 1"
                            );

                            $jadwalIdVal = $jadwal ? (int)$jadwal['id'] : 'NULL';
                            if ($ujianAktif && $ujianAktif->num_rows > 0) {
                                // Lanjutkan ujian yang sudah dimulai
                                $ujianId = $ujianAktif->fetch_assoc()['id'];
                                // Update jadwal_id jika belum terisi
                                if ($jadwal) {
                                    $conn->query("UPDATE ujian SET jadwal_id = $jadwalIdVal WHERE id = $ujianId AND jadwal_id IS NULL");
                                }
                            } else {
                                // Buat sesi ujian baru
                                $conn->query(
                                    "INSERT INTO ujian (peserta_id, token_id, jadwal_id, waktu_mulai, last_activity)
                                     VALUES ({$peserta['id']}, {$tokenData['id']}, $jadwalIdVal, NOW(), NOW())"
                                );
                                $ujianId = $conn->insert_id;
                            }

                            // Simpan ke session peserta
                            $_SESSION['peserta_id']          = $peserta['id'];
                            $_SESSION['peserta_nama']        = $peserta['nama'];
                            $_SESSION['peserta_kelas']       = $peserta['kelas'];
                            $_SESSION['peserta_sekolah']     = $peserta['nama_sekolah'] ?? '';
                            $_SESSION['kode_peserta']        = $peserta['kode_peserta'];
                            $_SESSION['ujian_id']            = $ujianId;
                            $_SESSION['jadwal_id']           = $jadwal['id'];
                            $_SESSION['jam_selesai']         = $jadwal['jam_selesai'];
                            $_SESSION['tanggal_ujian']       = $jadwal['tanggal']; // BUG FIX #4: simpan tanggal ujian untuk validasi lintas malam
                            $_SESSION['durasi_menit']        = $jadwal['durasi_menit'];
                            $_SESSION['jadwal_kategori_id']  = $jadwal['kategori_id'] ?? null;
                            $_SESSION['jadwal_mapel']        = $jadwal['nama_kategori'] ?? null;

                            // Log
                            logActivity($conn, 'Login Peserta', "Peserta {$peserta['nama']} ({$kode}) login ujian");

                            redirect(BASE_URL . '/ujian/soal.php');
                        }
            } // end if (!$error && $jadwal && isset($peserta))
        } // end if ($tokenData !== null)
    }
}

$namaAplikasi      = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
$namaPenyelenggara = getSetting($conn, 'nama_penyelenggara', '');
$jumlahSoal        = (int)getSetting($conn, 'jumlah_soal', '40');
$durasi            = getSetting($conn, 'durasi_ujian', '60');
$tahunPelajaran    = getSetting($conn, 'tahun_pelajaran', date('Y').'/'.(date('Y')+1));
$logoFilePath      = getSetting($conn, 'logo_file_path', '');
$logoUrl           = getSetting($conn, 'logo_url', '');
$logoAktif         = $logoFilePath ? BASE_URL . '/' . $logoFilePath : $logoUrl;

// ── Cek mode maintenance ──────────────────────────────────────
$modeMaintenance = getSetting($conn, 'mode_maintenance', '0');
if ($modeMaintenance === '1') {
    $pesanMaintenance = getSetting($conn, 'pesan_maintenance', 'Sistem sedang dalam pemeliharaan. Silakan tunggu.');
    $error = '🔧 <strong>Sistem Maintenance:</strong> ' . htmlspecialchars($pesanMaintenance);
}

// Ambil mata pelajaran dari kategori soal yang ada di bank soal
$mapelList = [];
$mapelRes = $conn->query(
    "SELECT DISTINCT k.nama_kategori
     FROM kategori_soal k
     INNER JOIN soal s ON s.kategori_id = k.id
     ORDER BY k.nama_kategori"
);
if ($mapelRes) while ($m = $mapelRes->fetch_assoc()) {
    $mapelList[] = $m['nama_kategori'];
}
$mataPelajaran = implode(' • ', $mapelList);

// Ambil tipe soal yang ada
$tipeList = [];
$tipeRes = $conn->query("SELECT DISTINCT tipe_soal FROM soal WHERE tipe_soal IS NOT NULL");
if ($tipeRes) while ($t = $tipeRes->fetch_assoc()) {
    $tipeList[] = strtoupper($t['tipe_soal']);
}
$tipeStr = implode(' • ', $tipeList) ?: 'PG';

// Ambil daftar sekolah beserta jenjang
$sekolahList = [];
$sl = $conn->query("SELECT id, nama_sekolah, jenjang FROM sekolah ORDER BY jenjang, nama_sekolah");
if ($sl) while ($r = $sl->fetch_assoc()) $sekolahList[] = $r;

// ── Ambil info kelas yang diizinkan untuk ditampilkan di halaman ─
$kelasInfoTampil = '';
$kelasInfoList = [];
foreach ($jadwalAktifList as $jadwalAktif) {
  if (empty($jadwalAktif['kelas_diizinkan'])) continue;
  foreach (array_filter(array_map('trim', explode(',', $jadwalAktif['kelas_diizinkan']))) as $kls) {
    $kelasInfoList[] = $kls;
  }
}
if (!empty($kelasInfoList)) {
  $kelasInfoList = array_values(array_unique($kelasInfoList));
  $kelasInfoTampil = implode(', ', $kelasInfoList);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Peserta — <?= e($namaAplikasi) ?></title>
<link href="<?= defined('CDN_BOOTSTRAP_CSS') ? CDN_BOOTSTRAP_CSS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' ?>" rel="stylesheet">
<link href="<?= defined('CDN_BOOTSTRAP_ICONS') ? CDN_BOOTSTRAP_ICONS : 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css' ?>" rel="stylesheet">
<link href="<?= defined('FONTS_PLUS_JAKARTA') ? FONTS_PLUS_JAKARTA : 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap' ?>" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#1e3a8a;--navy-h:#1e40af;--navy-d:#172e6e;--navy-m:#2348a8;
  --blue:#3b82f6;
  --g50:#f8fafc;--g100:#f1f5f9;--g200:#e2e8f0;--g300:#cbd5e1;
  --g400:#94a3b8;--g600:#475569;--g800:#1e293b;
  --red-bg:#fef2f2;--red-br:#fca5a5;--red-tx:#dc2626;
  --grn-bg:#f0fdf4;--grn-br:#bbf7d0;--grn-tx:#15803d;
  --ylw-bg:#fefce8;--ylw-br:#fde68a;--ylw-tx:#854d0e;
}

body{
  font-family:'Plus Jakarta Sans','Segoe UI',sans-serif;
  min-height:100vh;
  display:flex;align-items:center;justify-content:center;
  padding:24px 16px;
  background:var(--g100);
}

/* ══ Card ══ */
.login-card{
  display:flex;width:100%;max-width:720px;min-height:500px;
  border-radius:20px;overflow:hidden;
  box-shadow:0 8px 40px rgba(30,58,138,.18),0 2px 8px rgba(0,0,0,.06);
}

/* ══ PANEL KIRI ══ */
.panel-left{
  width:45%;
  background:linear-gradient(155deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
  position:relative;display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  padding:36px 24px;overflow:hidden;
}
.panel-left::before{content:'';position:absolute;width:320px;height:320px;border-radius:50%;border:1px solid rgba(255,255,255,.08);top:-90px;left:-90px}
.panel-left::after {content:'';position:absolute;width:200px;height:200px;border-radius:50%;border:1px solid rgba(255,255,255,.07);bottom:-55px;right:-55px}
.deco-ring{position:absolute;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,.04);bottom:48px;left:-34px}

.icon-circle{
  width:90px;height:90px;border-radius:50%;
  background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.35);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:14px;position:relative;z-index:1;flex-shrink:0;
  overflow:hidden;
}
.icon-circle i{font-size:36px;color:#fff}
.icon-circle img{width:80px;height:80px;object-fit:contain;border-radius:50%;}

.left-app{
  font-size:19px;font-weight:800;letter-spacing:.4px;
  color:rgba(255,255,255,.95);text-transform:uppercase;
  position:relative;z-index:1;text-align:center;margin-bottom:6px;
  line-height:1.35;
}
.left-school{
  font-size:21px;font-weight:900;
  color:#fff;text-align:center;line-height:1.2;
  position:relative;z-index:1;margin-bottom:6px;
  letter-spacing:-.2px;
}
.left-sub{
  font-size:11px;color:rgba(255,255,255,.58);
  text-align:center;line-height:1.65;
  position:relative;z-index:1;margin-bottom:12px;max-width:200px;
}
.left-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
  border-radius:20px;padding:4px 13px;
  font-size:10.5px;font-weight:700;color:rgba(255,255,255,.8);
  position:relative;z-index:1;
}
.left-badge i{font-size:11px}
.left-divider{width:100%;height:1px;background:rgba(255,255,255,.13);margin:16px 0;position:relative;z-index:1}
.left-features{
  list-style:none;padding:0;width:100%;
  position:relative;z-index:1;
  display:flex;flex-direction:column;gap:9px;margin-bottom:14px;
}
.left-features li{
  display:flex;align-items:center;gap:9px;
  font-size:11.5px;font-weight:600;color:rgba(255,255,255,.82);line-height:1.4;
}
.left-features li i{font-size:13px;color:rgba(255,255,255,.5);flex-shrink:0}
.left-quote{
  font-size:10.5px;font-style:italic;color:rgba(255,255,255,.42);line-height:1.65;
  position:relative;z-index:1;
  border-left:2px solid rgba(255,255,255,.17);padding-left:10px;align-self:flex-start;
}

/* ══ PANEL KANAN ══ */
.panel-right{
  flex:1;background:#fff;
  display:flex;flex-direction:column;justify-content:center;
  padding:38px 34px 30px;
}
.form-title  {font-size:25px;font-weight:900;color:var(--g800);margin-bottom:4px;letter-spacing:-.3px}
.form-tagline{font-size:12.5px;color:var(--g400);margin-bottom:22px}

/* Alert */
.alert-box{display:flex;align-items:flex-start;gap:8px;border-radius:8px;padding:9px 12px;font-size:12.5px;margin-bottom:14px;line-height:1.5}
.alert-box.error  {background:var(--red-bg);border:1px solid var(--red-br);color:var(--red-tx)}
.alert-box.warn   {background:var(--ylw-bg);border:1px solid var(--ylw-br);color:var(--ylw-tx)}
.alert-box.success{background:var(--grn-bg);border:1px solid var(--grn-br);color:var(--grn-tx)}
.alert-box .ai{font-size:15px;flex-shrink:0;margin-top:1px}

/* Status ujian */
.status-ujian{border-radius:8px;padding:8px 14px;font-size:12px;font-weight:600;text-align:center;margin-bottom:14px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.status-ujian.aktif{background:var(--grn-bg);color:var(--grn-tx);border:1px solid var(--grn-br)}
.status-ujian.nonaktif{background:var(--ylw-bg);color:var(--ylw-tx);border:1px solid var(--ylw-br)}
.live-dot{width:7px;height:7px;border-radius:50%;background:#16a34a;flex-shrink:0;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}

/* Kelas badge */
.kelas-info{background:var(--ylw-bg);border:1px solid var(--ylw-br);border-radius:8px;padding:7px 12px;font-size:12px;font-weight:600;color:var(--ylw-tx);margin-bottom:14px;text-align:center}
.kelas-badge{display:inline-block;background:#f59e0b;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;margin:2px 3px}

/* Field */
.field-lbl{
  display:block;
  font-size:11px;font-weight:800;color:var(--g600);
  text-transform:uppercase;letter-spacing:.65px;margin-bottom:5px;
}
.field-wrap{position:relative;margin-bottom:14px}
.field-input{
  width:100%;background:var(--g50);
  border:1.5px solid var(--g200);border-radius:9px;
  padding:10px 12px;
  font-size:14px;font-weight:700;font-family:'Courier New',monospace;
  letter-spacing:2px;text-transform:uppercase;text-align:center;
  color:var(--g800);transition:border .15s,box-shadow .15s;outline:none;
}
.field-input:focus{border-color:var(--navy);background:#eff6ff;box-shadow:0 0 0 3px rgba(30,58,138,.09)}
.field-input::placeholder{color:var(--g300);font-size:12px;letter-spacing:3px}
.field-hint{font-size:11px;color:var(--g400);text-align:center;margin-top:5px}

.peserta-info{margin-top:12px;padding:14px;border-radius:12px;background:var(--g50);border:1px solid var(--g200);color:var(--g800);text-align:center;display:none;box-shadow:0 8px 18px rgba(16,24,40,.06)}
.peserta-info .pi-name{font-size:18px;font-weight:800;line-height:1.2;margin-bottom:6px;color:var(--grn-tx);text-transform:uppercase}
.peserta-info .pi-school{display:block;text-align:center}
.peserta-info .pi-school .label{display:block;font-size:13px;color:var(--g600);font-weight:800;margin-bottom:6px}
.peserta-info .pi-school code{display:inline-block;font-size:20px;background:transparent;color:#000;padding:6px 14px;border-radius:10px;border:1px solid rgba(0,0,0,.06);font-weight:900}
  .peserta-info .pi-school .school-name{display:block;margin-top:8px;font-size:14px;color:var(--red-tx);font-weight:700}
.peserta-info.valid{background:var(--grn-bg);border-color:var(--grn-br);color:var(--grn-tx)}
.peserta-info.invalid{background:var(--red-bg);border-color:var(--red-br);color:var(--red-tx)}

@media(max-width:600px){
  .peserta-info{padding:12px;border-radius:10px}
  .peserta-info .pi-name{font-size:16px}
  .peserta-info .pi-school{font-size:12px}
}

.btn-mulai{
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;background:var(--navy);border:none;border-radius:9px;
  padding:12px 16px;font-size:14.5px;font-weight:800;font-family:inherit;color:#fff;
  cursor:pointer;transition:background .15s;margin-top:4px;
  box-shadow:0 3px 12px rgba(30,58,138,.28);
}
.btn-mulai:hover:not(:disabled){background:var(--navy-h)}
.btn-mulai:disabled{background:var(--g400);box-shadow:none;cursor:not-allowed}
.btn-icon{width:20px;height:20px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px}

/* Sekolah toggle */
.sekolah-toggle{
  width:100%;background:var(--g50);border:1.5px solid var(--g200);border-radius:9px;
  padding:9px 13px;font-size:12px;font-weight:700;color:var(--g600);
  cursor:pointer;display:flex;align-items:center;justify-content:space-between;
  font-family:inherit;margin-bottom:14px;transition:border .15s;
}
.sekolah-toggle:hover{border-color:var(--navy);color:var(--navy)}
.sekolah-panel{
  display:none;background:var(--g50);
  border:1.5px solid var(--g200);border-top:none;
  border-radius:0 0 9px 9px;padding:10px 13px;
  max-height:150px;overflow-y:auto;margin-top:-14px;margin-bottom:14px;
}

.footer-inner{text-align:center;margin-top:18px;font-size:11px;color:var(--g400)}
.footer-inner strong{color:var(--g600)}
.footer-inner a{color:var(--navy);font-weight:700;text-decoration:none}
.footer-inner a:hover{text-decoration:underline}

/* ══ MOBILE ══ */
@media(max-width:600px){
  html,body{height:100%;overflow:hidden;}
  body{
    padding:0;align-items:stretch;flex-direction:column;
    background:linear-gradient(160deg,var(--navy-m) 0%,var(--navy) 55%,var(--navy-d) 100%);
  }
  body::before{content:'';position:fixed;z-index:0;width:280px;height:280px;border-radius:50%;border:1px solid rgba(255,255,255,.1);top:-70px;left:-70px;pointer-events:none;}
  body::after{content:'';position:fixed;z-index:0;width:200px;height:200px;border-radius:50%;border:1px solid rgba(255,255,255,.08);top:60px;right:-60px;pointer-events:none;}

  .login-card{flex-direction:column;border-radius:0;height:100vh;min-height:unset;box-shadow:none;position:relative;z-index:1;overflow:hidden;}

  .panel-left{
    width:100%;background:transparent;
    padding:40px 24px 12px;
    align-items:center;justify-content:flex-end;
    flex-shrink:0;min-height:unset;height:auto;
  }
  .panel-left::before,.panel-left::after,.deco-ring{display:none}
  .icon-circle{width:70px;height:70px;margin-bottom:8px}
  .icon-circle i{font-size:26px}
  .icon-circle img{width:62px;height:62px}
  .left-app{font-size:14px;font-weight:800;letter-spacing:.4px;margin-bottom:2px}
  .left-school{font-size:15px;margin-bottom:2px}
  .left-sub{font-size:9.5px;margin-bottom:6px;max-width:260px}
  .left-badge{font-size:9px;padding:2px 9px}
  .left-divider,.left-features,.left-quote{display:none}

  .panel-right{
    background:#fff;border-radius:22px 22px 0 0;
    padding:22px 20px 0;
    box-shadow:0 -6px 30px rgba(0,0,0,.18);
    flex:1;min-height:0;overflow-y:auto;
    -webkit-overflow-scrolling:touch;
    justify-content:flex-start;
  }
  .form-title{font-size:21px}
  .form-tagline{font-size:12px;margin-bottom:16px}
  .field-input{font-size:14px;padding:11px 12px}
  .btn-mulai{padding:12px;font-size:14.5px}
  .footer-inner{margin-top:14px;padding-bottom:calc(env(safe-area-inset-bottom,0px) + 24px)}
}
</style>
</head>
<body>

<div class="login-card">

  <!-- Panel Kiri -->
  <div class="panel-left">
    <div class="deco-ring"></div>
    <div class="icon-circle">
      <?php if ($logoAktif): ?>
      <img src="<?= htmlspecialchars($logoAktif) ?>" alt="Logo">
      <?php else: ?>
      <i class="bi bi-pencil-square"></i>
      <?php endif; ?>
    </div>
    <div class="left-app"><?= e($namaAplikasi) ?></div>
    <?php if ($namaPenyelenggara): ?>
    <div class="left-school"><?= e($namaPenyelenggara) ?></div>
    <div class="left-sub">Sistem Computer Based Test</div>
    <?php endif; ?>
    <div class="left-badge"><i class="bi bi-calendar3"></i> T.P. <?= e($tahunPelajaran) ?></div>
    <div class="left-divider"></div>
    <ul class="left-features">
      <li><i class="bi bi-shield-check-fill"></i> Sistem ujian aman &amp; terenkripsi</li>
      <li><i class="bi bi-lightning-charge-fill"></i> Penilaian otomatis &amp; real-time</li>
      <li><i class="bi bi-bar-chart-line-fill"></i> Laporan hasil ujian terperinci</li>
      <li><i class="bi bi-people-fill"></i> Manajemen peserta &amp; soal terpadu</li>
    </ul>
    <div class="left-quote">&ldquo;Asesmen yang baik adalah kunci<br>pembelajaran yang bermakna.&rdquo;</div>
  </div>

  <!-- Panel Kanan -->
  <div class="panel-right">

    <div class="form-title">Login Peserta</div>
    <div class="form-tagline">Masuk menggunakan kode peserta &amp; token ujian.</div>

    <?php if ($error !== ''): ?>
    <div class="alert-box error"><span class="ai">⚠️</span><span><?= $error ?></span></div>
    <?php endif; ?>

    <?php if ($jumlahJadwalAktif > 0): ?>
    <div class="status-ujian aktif">
      <span class="live-dot"></span>
      <span>Ujian sedang berlangsung — <?= (int)$jumlahJadwalAktif ?> jadwal aktif</span>
    </div>
    <?php else: ?>
    <div class="status-ujian nonaktif">⏳ Belum ada ujian aktif saat ini</div>
    <?php endif; ?>

    <?php if ($kelasInfoTampil): ?>
    <div class="kelas-info">
      Ruang yang diizinkan:
      <?php foreach (array_filter(array_map('trim', explode(',', $kelasInfoTampil))) as $kls): ?>
      <span class="kelas-badge"><?= e($kls) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>



    <form method="POST" autocomplete="off" novalidate>
      <?= csrfField() ?>

      <label class="field-lbl">Kode Peserta</label>
      <div class="field-wrap">
        <input type="text" name="kode_peserta" class="field-input"
               placeholder="• • • • • • • •"
               value="<?= e(strtoupper($_POST['kode_peserta'] ?? '')) ?>"
               maxlength="20" required autofocus>
        <div class="field-hint">Lihat kode di kartu ujian Anda</div>
        <div id="pesertaInfo" class="peserta-info" aria-live="polite"></div>
      </div>

      <label class="field-lbl">Token Ujian</label>
      <div class="field-wrap">
        <input type="text" name="token" class="field-input"
               placeholder="DARI PENGAWAS"
               value="<?= e(strtoupper($_POST['token'] ?? '')) ?>"
               maxlength="20" required>
        <div class="field-hint">Token diberikan oleh pengawas ujian</div>
      </div>

      <button type="submit" class="btn-mulai" <?= !$jadwal || $modeMaintenance === '1' ? 'disabled' : '' ?>>
        <div class="btn-icon">▶</div>
        Mulai Ujian
      </button>
    </form>

    <div class="footer-inner">
      <a href="<?= BASE_URL ?>/login.php">← Login Admin</a>
      &nbsp;|&nbsp;
      <a href="<?= BASE_URL ?>/ujian/cek_nilai.php">🔍 Cek Nilai Saya</a><br>
      &copy; <?= date('Y') ?> <?= e($namaAplikasi) ?> &mdash;
      Dikembangkan oleh <strong>KKOPS-BTG</strong>&nbsp;
   
    </div>

  </div><!-- /panel-right -->

</div><!-- /login-card -->

<script>
document.querySelectorAll('.field-input').forEach(el => {
    el.addEventListener('input', () => el.value = el.value.toUpperCase());
});
function toggleSekolah() {
    const panel = document.getElementById('sekolahPanel');
    const arrow = document.getElementById('sekolahArrow');
    const open  = panel.style.display === 'block';
    panel.style.display = open ? 'none' : 'block';
    arrow.style.transform = open ? '' : 'rotate(180deg)';
}

// ── Fetch peserta info saat memasukkan kode peserta ─────────────────
;(function(){
  const kodeEl = document.querySelector('input[name="kode_peserta"]');
  const infoEl = document.getElementById('pesertaInfo');
  const baseUrl = '<?= rtrim(BASE_URL, "\/") ?>';
  let tmr = null;

  function showInfoMessage(text, state){
    infoEl.innerHTML = '';
    const msg = document.createElement('div');
    msg.style.fontWeight = '700';
    msg.textContent = text;
    infoEl.appendChild(msg);
    infoEl.classList.remove('valid','invalid');
    if (state) infoEl.classList.add(state);
    infoEl.style.display = 'block';
  }

  function showPeserta(p, state){
    infoEl.innerHTML = '';
    const name = document.createElement('div');
    name.className = 'pi-name';
    name.textContent = (p.nama || '-').toString();

    const school = document.createElement('div');
    school.className = 'pi-school';
    const label = document.createElement('div');
    label.className = 'label';
    label.textContent = 'Sekolah:';
    const code = document.createElement('code');
    code.textContent = p.kode_sekolah || '-';
    school.appendChild(label);
    school.appendChild(code);
    if (p.nama_sekolah) {
      const name = document.createElement('div');
      name.className = 'school-name';
      name.textContent = p.nama_sekolah;
      school.appendChild(name);
    }

    infoEl.appendChild(name);
    infoEl.appendChild(school);
    infoEl.classList.remove('valid','invalid');
    if (state) infoEl.classList.add(state);
    infoEl.style.display = 'block';
  }

  function fetchPeserta(kode){
    if (!kode) { showInfo('', null); return; }
    fetch(baseUrl + '/ujian/ajax_get_peserta.php?kode=' + encodeURIComponent(kode))
      .then(r => r.ok ? r.json() : null)
      .then(j => {
        if (!j) return showInfoMessage('Gagal memuat data peserta.', 'invalid');
        if (j.success) {
          showPeserta(j.data, 'valid');
        } else {
          showInfoMessage(j.message || 'Peserta tidak ditemukan', 'invalid');
        }
      })
      .catch(() => showInfo('Gagal memuat data peserta.', 'invalid'));
  }

  if (kodeEl) {
    kodeEl.addEventListener('input', function(){
      const val = this.value.trim().toUpperCase();
      clearTimeout(tmr);
      if (val.length < 3) { showInfo('', null); return; }
      tmr = setTimeout(() => fetchPeserta(val), 350);
    });

    // If server-rendered value exists, trigger fetch on load
    if (kodeEl.value && kodeEl.value.trim().length >= 3) fetchPeserta(kodeEl.value.trim().toUpperCase());
  }
})();
</script>
</body>
</html>
