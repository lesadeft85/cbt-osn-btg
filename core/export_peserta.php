<?php
// ============================================================
// sekolah/export_peserta.php — Export Daftar Peserta ke Excel
// Hanya bisa export peserta milik sekolah yang login
// ============================================================
ob_start(); // Tangkap semua output sebelumnya agar header tidak bentrok
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('sekolah');
ob_end_clean(); // Buang semua output yang sudah terlanjur dikirim

$user      = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

$filterKelas = trim($_GET['kelas'] ?? '');
$q           = trim($_GET['q']     ?? '');

// ── Query data peserta (hanya milik sekolah ini) ──────────────
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
if (!empty($rows[0]['nama_sekolah'])) {
    $namaSekolahFile = '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $rows[0]['nama_sekolah']);
}
$filename = 'daftar_peserta' . $namaSekolahFile . '_' . date('Ymd') . '.xls';

// ── Export sebagai Excel XML (tidak butuh library) ────────────
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// xlEsc() sudah tersedia dari core/helper.php

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
      xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

echo '<Styles>
  <Style ss:ID="header">
    <Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/>
    <Interior ss:Color="#1F4E79" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/>
    </Borders>
  </Style>
  <Style ss:ID="judul">
    <Font ss:Bold="1" ss:Size="14" ss:Color="#1F4E79"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="sub">
    <Font ss:Size="10" ss:Color="#595959" ss:Italic="1"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="data">
    <Alignment ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="data_alt">
    <Interior ss:Color="#EBF3FB" ss:Pattern="Solid"/>
    <Alignment ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="kode">
    <Font ss:Color="#1F4E79" ss:Bold="1" ss:Name="Courier New"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="kode_alt">
    <Font ss:Color="#1F4E79" ss:Bold="1" ss:Name="Courier New"/>
    <Interior ss:Color="#EBF3FB" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="center">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="center_alt">
    <Interior ss:Color="#EBF3FB" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#D9D9D9"/>
    </Borders>
  </Style>
  <Style ss:ID="lulus">
    <Font ss:Color="#166534" ss:Bold="1"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="tidak_lulus">
    <Font ss:Color="#991B1B" ss:Bold="1"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>
  <Style ss:ID="footer_style">
    <Font ss:Italic="1" ss:Size="9" ss:Color="#94A3B8"/>
    <Alignment ss:Horizontal="Right"/>
  </Style>
</Styles>' . "\n";

echo '<Worksheet ss:Name="Daftar Peserta"><Table>' . "\n";

// Lebar kolom
echo '<Column ss:Width="35"/>';   // No
echo '<Column ss:Width="180"/>'; // Nama
echo '<Column ss:Width="120"/>'; // Kode
echo '<Column ss:Width="70"/>'; // Kelas
echo '<Column ss:Width="90"/>'; // Status Ujian
echo '<Column ss:Width="80"/>'; // Nilai

// Judul
$namaSekolahJudul = !empty($rows[0]['nama_sekolah']) ? $rows[0]['nama_sekolah'] : 'Sekolah';
echo '<Row ss:Height="30">
  <Cell ss:MergeAcross="5" ss:StyleID="judul">
    <Data ss:Type="String">' . xlEsc($namaAplikasi) . ' — Daftar Peserta ' . xlEsc($namaSekolahJudul) . '</Data>
  </Cell>
</Row>';
echo '<Row ss:Height="18">
  <Cell ss:MergeAcross="5" ss:StyleID="sub">
    <Data ss:Type="String">' . xlEsc($namaKecamatan) . ' · Tahun Pelajaran ' . xlEsc($tahunPelajaran) . ' · Dicetak: ' . date('d/m/Y H:i') . '</Data>
  </Cell>
</Row>';
echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';

// Header tabel
echo '<Row ss:Height="24">
  <Cell ss:StyleID="header"><Data ss:Type="String">No</Data></Cell>
  <Cell ss:StyleID="header"><Data ss:Type="String">Nama Peserta</Data></Cell>
  <Cell ss:StyleID="header"><Data ss:Type="String">Kode Peserta</Data></Cell>
  <Cell ss:StyleID="header"><Data ss:Type="String">Kelas</Data></Cell>
  <Cell ss:StyleID="header"><Data ss:Type="String">Status Ujian</Data></Cell>
  <Cell ss:StyleID="header"><Data ss:Type="String">Nilai Terakhir</Data></Cell>
</Row>' . "\n";

// Data baris
$no = 1;
foreach ($rows as $r) {
    $alt      = $no % 2 === 0 ? '_alt' : '';
    $sdh      = (int)$r['sdh_ujian'] > 0;
    $nilai    = $r['nilai_terakhir'];
    $nilaiStr = $nilai !== null ? (string)(int)$nilai : '-';

    echo '<Row ss:Height="18">
      <Cell ss:StyleID="center' . $alt . '"><Data ss:Type="Number">' . $no . '</Data></Cell>
      <Cell ss:StyleID="data'   . $alt . '"><Data ss:Type="String">' . xlEsc($r['nama']) . '</Data></Cell>
      <Cell ss:StyleID="kode'   . $alt . '"><Data ss:Type="String">' . xlEsc($r['kode_peserta'] ?? '-') . '</Data></Cell>
      <Cell ss:StyleID="center' . $alt . '"><Data ss:Type="String">' . xlEsc($r['kelas'] ?? '-') . '</Data></Cell>
      <Cell ss:StyleID="' . ($sdh ? 'lulus' : 'center') . '"><Data ss:Type="String">' . ($sdh ? 'Sudah' : 'Belum') . '</Data></Cell>
      <Cell ss:StyleID="' . ($nilai !== null ? ($nilai >= $kkm ? 'lulus' : 'tidak_lulus') : 'center') . '">
        <Data ss:Type="String">' . $nilaiStr . '</Data>
      </Cell>
    </Row>' . "\n";
    $no++;
}

// Footer
echo '<Row ss:Height="6"><Cell><Data ss:Type="String"></Data></Cell></Row>';
echo '<Row>
  <Cell ss:MergeAcross="5" ss:StyleID="footer_style">
    <Data ss:Type="String">Total: ' . count($rows) . ' peserta · Diekspor dari ' . xlEsc($namaAplikasi) . ' · ' . date('d F Y H:i') . '</Data>
  </Cell>
</Row>';

echo '</Table></Worksheet></Workbook>';
exit;
