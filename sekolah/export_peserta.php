<?php
// ============================================================
// sekolah/export_peserta.php — Export Daftar Peserta ke Excel
// Hanya bisa export peserta milik sekolah yang login
// ============================================================
ob_start();
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');
ob_end_clean();

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

$filterKelas = trim($_GET['kelas'] ?? '');
$q           = trim($_GET['q']     ?? '');

// ── Query data peserta ────────────────────────────────────────
$where = "WHERE p.sekolah_id = $sekolahId";
if ($filterKelas) $where .= " AND p.kelas = '" . $conn->real_escape_string($filterKelas) . "'";
if ($q)           $where .= " AND (p.nama LIKE '%" . $conn->real_escape_string($q) . "%' OR p.kode_peserta LIKE '%" . $conn->real_escape_string($q) . "%')";

$res = $conn->query("
    SELECT p.nama, p.kelas, p.kode_peserta, s.nama_sekolah,
           (SELECT COUNT(*) FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL) AS sdh_ujian,
           (SELECT nilai FROM ujian WHERE peserta_id=p.id AND waktu_selesai IS NOT NULL ORDER BY id DESC LIMIT 1) AS nilai_terakhir
    FROM peserta p
    LEFT JOIN sekolah s ON s.id = p.sekolah_id
    $where
    ORDER BY p.kelas, p.nama
");

$rows = [];
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

$namaAplikasi   = getSetting($conn, 'nama_aplikasi',   'TKA Kecamatan');
$namaKecamatan  = getSetting($conn, 'nama_kecamatan',  'Kecamatan');
$tahunPelajaran = getSetting($conn, 'tahun_pelajaran', date('Y') . '/' . (date('Y')+1));
$kkm            = (int)getSetting($conn, 'kkm', '60');

// Nama sekolah untuk nama file
$namaSekolahFile = '';
$namaSekolahJudul = 'Sekolah';
if (!empty($rows)) {
    $namaSekolahJudul = $rows[0]['nama_sekolah'] ?? 'Sekolah';
    $namaSekolahFile  = '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $namaSekolahJudul);
} else {
    // Ambil dari DB jika tidak ada peserta
    $sr = $conn->query("SELECT nama_sekolah FROM sekolah WHERE id=$sekolahId LIMIT 1");
    if ($sr && $sr->num_rows > 0) {
        $namaSekolahJudul = $sr->fetch_assoc()['nama_sekolah'] ?? 'Sekolah';
        $namaSekolahFile  = '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $namaSekolahJudul);
    }
}

$filename = 'daftar_peserta' . $namaSekolahFile . '_' . date('Ymd') . '.xls';

// ── Header download ───────────────────────────────────────────
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: public');
header('Cache-Control: max-age=0');

// ── Output HTML Table (format paling kompatibel Excel) ────────
// Format ini 100% bisa dibuka Excel tanpa error "Style"
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8">
<!--[if gte mso 9]><xml>
 <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
  <x:Name>Daftar Peserta</x:Name>
  <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
 </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
</xml><![endif]-->
<style>
  table { border-collapse: collapse; font-family: Arial; font-size: 11pt; }
  th {
    background-color: #1F4E79;
    color: #FFFFFF;
    font-weight: bold;
    text-align: center;
    padding: 6px 10px;
    border: 1px solid #FFFFFF;
  }
  td { padding: 4px 10px; border: 1px solid #D9D9D9; vertical-align: middle; }
  tr:nth-child(even) td { background-color: #EBF3FB; }
  .judul { font-size: 14pt; font-weight: bold; color: #1F4E79; text-align: center; }
  .sub   { font-size: 10pt; color: #595959; font-style: italic; text-align: center; }
  .kode  { font-family: "Courier New"; font-weight: bold; color: #1F4E79; text-align: center; }
  .center { text-align: center; }
  .lulus     { color: #166534; font-weight: bold; text-align: center; }
  .tdk_lulus { color: #991B1B; font-weight: bold; text-align: center; }
  .footer    { font-size: 9pt; color: #94A3B8; font-style: italic; text-align: right; }
</style>
</head><body>';

echo '<table>';

// Judul
echo '<tr><td colspan="6" class="judul">' . htmlspecialchars($namaAplikasi) . ' — Daftar Peserta ' . htmlspecialchars($namaSekolahJudul) . '</td></tr>';
echo '<tr><td colspan="6" class="sub">' . htmlspecialchars($namaKecamatan) . ' &middot; Tahun Pelajaran ' . htmlspecialchars($tahunPelajaran) . ' &middot; Dicetak: ' . date('d/m/Y H:i') . '</td></tr>';
echo '<tr><td colspan="6"></td></tr>';

// Header kolom
echo '<tr>
  <th>No</th>
  <th>Nama Peserta</th>
  <th>Kode Peserta</th>
  <th>Kelas</th>
  <th>Status Ujian</th>
  <th>Nilai Terakhir</th>
</tr>';

// Data
if (empty($rows)) {
    echo '<tr><td colspan="6" style="text-align:center;color:#94A3B8">Belum ada peserta terdaftar</td></tr>';
} else {
    $no = 1;
    foreach ($rows as $r) {
        $sdh      = (int)$r['sdh_ujian'] > 0;
        $nilai    = $r['nilai_terakhir'];
        $nilaiStr = $nilai !== null ? (string)(int)$nilai : '-';
        $nilaiKls = $nilai !== null ? ($nilai >= $kkm ? 'lulus' : 'tdk_lulus') : 'center';
        $statusKls = $sdh ? 'lulus' : 'center';

        echo '<tr>
          <td class="center">' . $no . '</td>
          <td>' . htmlspecialchars($r['nama']) . '</td>
          <td class="kode">' . htmlspecialchars($r['kode_peserta'] ?? '-') . '</td>
          <td class="center">' . htmlspecialchars($r['kelas'] ?? '-') . '</td>
          <td class="' . $statusKls . '">' . ($sdh ? 'Sudah' : 'Belum') . '</td>
          <td class="' . $nilaiKls . '">' . $nilaiStr . '</td>
        </tr>';
        $no++;
    }
}

// Footer
echo '<tr><td colspan="6"></td></tr>';
echo '<tr><td colspan="6" class="footer">Total: ' . count($rows) . ' peserta &middot; Diekspor dari ' . htmlspecialchars($namaAplikasi) . ' &middot; ' . date('d F Y H:i') . '</td></tr>';

echo '</table></body></html>';
exit;
