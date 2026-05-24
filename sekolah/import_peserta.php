<?php
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
require_once __DIR__ . '/../vendor/simplexlsx/SimpleXLSX.php';
requireLogin('sekolah');
$user = getCurrentUser();
$sekolahId = (int)$user['sekolah_id'];

function genKodeSek(mysqli $db): string {
    do { $k='TKA'.strtoupper(substr(md5(uniqid()),0,6));
         $c=$db->query("SELECT id FROM peserta WHERE kode_peserta='$k' LIMIT 1");
    } while($c&&$c->num_rows>0); return $k;
}

// ── Download template Excel ───────────────────────────────────
if (isset($_GET['template'])) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_peserta.xls"');
    header('Cache-Control: max-age=0');
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
<head><meta charset="UTF-8">
<style>
body { font-family: Calibri, Arial, sans-serif; font-size: 11pt; }
table { border-collapse: collapse; }
th { background-color: #1F4E79; color: #FFFFFF; font-weight: bold; text-align: center; border: 1px solid #FFFFFF; padding: 6px 12px; }
td { border: 1px solid #D9D9D9; padding: 4px 8px; }
.contoh { color: #888888; font-style: italic; }
.info { background-color: #FFF3CD; font-size: 10pt; color: #856404; }
</style></head><body>
<table>
<tr>
  <td colspan="2" style="background:#1F4E79;color:#fff;font-weight:bold;font-size:13pt;padding:8px;text-align:center">
    Template Import Peserta — TKA Kecamatan
  </td>
</tr>
<tr>
  <td colspan="3" class="info" style="padding:6px">
    ⚠ Jangan ubah baris header (baris ke-3). Isi data mulai baris ke-4. Simpan sebagai .xlsx sebelum diupload.
  </td>
</tr>
<tr>
    <th width="220">nama</th>
    <th width="100">kelas</th>
    <th width="120">kode_sekolah</th>
</tr>
<tr><td class="contoh">Andi Pratama</td><td class="contoh">VI A</td><td class="contoh">Bantargebang 1</td></tr>
<tr><td class="contoh">Budi Santoso</td><td class="contoh">VI B</td><td class="contoh">Bantargebang 1</td></tr>
<tr><td class="contoh">Citra Dewi</td><td class="contoh">V A</td><td class="contoh">Bantargebang 1</td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
<tr><td></td><td></td></tr>
</table>
</body></html>';
    exit;
}

$results = null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrfVerify();
    if (empty($_FILES['file_excel']['name'])) { setFlash('error','Pilih file.'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }
    $ext = strtolower(pathinfo($_FILES['file_excel']['name'],PATHINFO_EXTENSION));
    if ($ext!=='xlsx') { setFlash('error','Format harus .xlsx'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }
    $xlsx = SimpleXLSX::parse($_FILES['file_excel']['tmp_name']);
    if (!$xlsx) { setFlash('error','File tidak bisa dibaca.'); redirect(BASE_URL.'/sekolah/import_peserta.php'); }

    // Ambil jenjang sekolah untuk validasi kelas
    $stJenj = $conn->prepare("SELECT jenjang FROM sekolah WHERE id=? LIMIT 1");
    $stJenj->bind_param('i', $sekolahId); $stJenj->execute();
    $jenjangSekolah = strtoupper($stJenj->get_result()->fetch_assoc()['jenjang'] ?? 'SD');
    $stJenj->close();

    $kelasValid = [];
    $romawi = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $sub    = ['','A','B','C','D','E','F'];
    if (in_array($jenjangSekolah, ['SD','MI'])) $romawiBoleh = array_slice($romawi, 0, 6);
    elseif (in_array($jenjangSekolah, ['SMP','MTS'])) $romawiBoleh = array_slice($romawi, 6, 3);
    else $romawiBoleh = array_slice($romawi, 9, 3);
    foreach ($romawiBoleh as $r) foreach ($sub as $s) $kelasValid[] = trim("$r $s");

    $rows=$xlsx->rows(0); $berhasil=$gagal=0; $log=[];
    // Skip baris header (jika ada) — cek baris pertama atau kedua
    $startRow=0;
    foreach ([0,1,2] as $ri) {
        $val = strtolower(trim($rows[$ri][0] ?? ''));
        if ($val === 'nama' || $val === 'template import peserta') { $startRow = $ri+1; }
    }

    $stmt=$conn->prepare("INSERT IGNORE INTO peserta (nama,kelas,sekolah_id,kode_sekolah,kode_peserta) VALUES (?,?,?,?,?)");
    for($i=$startRow;$i<count($rows);$i++){
        $row=$rows[$i]; while(count($row)<3)$row[]='';
        $nama=trim($row[0]??''); $kelas=trim($row[1]??''); $kodeSek=trim($row[2]??'');
        if(!$nama) continue;

        // Validasi kelas
        if ($kelas && !in_array($kelas, $kelasValid)) {
            $gagal++;
            $log[]=['no'=>$i+1,'status'=>'gagal','nama'=>$nama,'kode'=>'-','pesan'=>"Kelas \"$kelas\" tidak valid untuk jenjang $jenjangSekolah"];
            continue;
        }

        $kode=genKodeSek($conn);
        $stmt->bind_param('ssiss',$nama,$kelas,$sekolahId,$kodeSek,$kode);
        if($stmt->execute() && $conn->affected_rows > 0){
            $berhasil++;
            $log[]=['no'=>$i+1,'status'=>'ok','nama'=>$nama,'kode'=>$kode,'kode_sekolah'=>$kodeSek,'pesan'=>''];
        } else {
            $gagal++;
            $log[]=['no'=>$i+1,'status'=>'gagal','nama'=>$nama,'kode'=>'-','pesan'=>$conn->affected_rows===0?'Nama sudah terdaftar':$conn->error];
        }
    }
    $stmt->close();
    $results=compact('berhasil','gagal','log');
}
$pageTitle='Import Peserta'; $activeMenu='importpeserta';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div><h2><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import Peserta</h2>
    <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?=BASE_URL?>/sekolah/peserta.php">Peserta</a></li>
        <li class="breadcrumb-item active">Import</li>
    </ol></nav></div>
    <a href="<?=BASE_URL?>/sekolah/peserta.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?= renderFlash() ?>
<div class="row g-4">
<div class="col-lg-5">
    <div class="card"><div class="card-header">Upload File Excel</div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="mb-4"><label class="form-label fw-semibold">File Excel (.xlsx)</label>
                <input type="file" name="file_excel" class="form-control" accept=".xlsx" required></div>
            <button type="submit" class="btn btn-success w-100"><i class="bi bi-upload me-2"></i>Proses Import</button>
        </form>
    </div></div>
    <div class="card mt-3"><div class="card-header d-flex justify-content-between align-items-center">
        <span>Format</span>
        <a href="?template=1" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Unduh Template
        </a>
    </div>
    <div class="card-body small">
        <table class="table table-bordered table-sm">
            <thead class="table-primary"><tr><th>Kolom A (nama)</th><th>Kolom B (kelas)</th><th>Kolom C (Kode Sekolah)</th></tr></thead>
            <tbody>
                <tr><td>Andi Pratama</td><td>VI A</td><td>SDN Bantargebang 1</td></tr>
                <tr><td>Budi Santoso</td><td>VI B</td><td>SDN Bantargebang 1</td></tr>
                <tr><td>Citra Dewi</td><td>V A</td><td>SDN Bantargebang 1</td></tr>
            </tbody>
        </table>
        <p class="text-muted mb-0 small">SD/MI: I–VI &nbsp;|&nbsp; SMP/MTs: VII–IX &nbsp;|&nbsp; SMA/MA/SMK: X–XII</p>
    </div></div>
</div>
<div class="col-lg-7">
<?php if($results): ?>
<div class="card"><div class="card-header">
    Hasil <span class="badge bg-success ms-2"><?=$results['berhasil']?> OK</span>
    <?php if($results['gagal']>0):?><span class="badge bg-danger ms-1"><?=$results['gagal']?> Gagal</span><?php endif;?>
</div>
<div class="card-body p-0" style="max-height:400px;overflow-y:auto">
<table class="table table-sm mb-0"><thead><tr><th>#</th><th>Nama</th><th>Kelas</th><th>Kode</th><th>Status</th><th>Keterangan</th></tr></thead>
<tbody>
<?php foreach($results['log'] as $l):?>
<tr class="<?=$l['status']==='ok'?'table-success':'table-danger'?>">
    <td><?=$l['no']?></td>
    <td><?=htmlspecialchars($l['nama'])?></td>
    <td><?=htmlspecialchars($l['kelas']??'-')?></td>
    <td><code><?=htmlspecialchars($l['kode']??'-')?></code></td>
    <td><?=$l['status']==='ok'?'<span class="badge bg-success">OK</span>':'<span class="badge bg-danger">Gagal</span>'?></td>
    <td class="small text-muted"><?=htmlspecialchars($l['pesan']??'')?></td>
</tr>
<?php endforeach;?>
</tbody></table>
</div></div>
<?php endif;?>
</div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
