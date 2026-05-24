<?php
// ============================================================
// admin/cetak_sertifikat.php — Template Sertifikat
// ============================================================
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helper.php';
requireLogin('admin_kecamatan');

$ujianId = (int)($_GET['id'] ?? 0);

$sql = "SELECT u.nilai, u.waktu_selesai, 
         p.nama, p.kode_peserta, p.kode_sekolah, s.nama_sekolah, k.nama_kategori
        FROM ujian u
        JOIN peserta p ON p.id = u.peserta_id
        JOIN sekolah s ON s.id = p.sekolah_id
        JOIN kategori_soal k ON k.id = u.kategori_id
        WHERE u.id = $ujianId LIMIT 1";

$res = $conn->query($sql);
$data = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

if (!$data) die("Data tidak ditemukan.");

[$ph, $pt, $pb] = getPredikat((int)$data['nilai']);
$namaAplikasi = getSetting($conn, 'nama_aplikasi', 'TKA Kecamatan');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat - <?= e($data['nama']) ?></title>
    <style>
        @import url('<?= defined('FONTS_CERTIFICATE') ? FONTS_CERTIFICATE : 'https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Great+Vibes&family=Montserrat:wght@400;600;700&display=swap' ?>');
        
        :root {
            --ink: #102a43;
            --ink-soft: #334e68;
            --gold: #c8a14a;
            --gold-soft: #f2e7c9;
            --blue: #1d4ed8;
            --blue-deep: #0f2d5c;
            --paper: #fbfdff;
        }

        body {
            margin: 0;
            padding: 0;
            background:
                radial-gradient(circle at top left, rgba(29, 78, 216, .08), transparent 28%),
                radial-gradient(circle at bottom right, rgba(200, 161, 74, .12), transparent 24%),
                linear-gradient(180deg, #edf4ff 0%, #f7fafc 100%);
            font-family: 'Montserrat', sans-serif;
        }
        
        .certificate-container {
            width: 297mm;
            height: 210mm;
            padding: 16mm 18mm;
            margin: 10mm auto;
            background: linear-gradient(145deg, #ffffff 0%, var(--paper) 100%);
            box-shadow: 0 24px 70px rgba(16, 42, 67, 0.16);
            position: relative;
            box-sizing: border-box;
            border: 14px solid var(--blue-deep);
            overflow: hidden;
            border-radius: 10px;
        }
        
        .certificate-container::before,
        .certificate-container::after {
            content: '';
            position: absolute;
            pointer-events: none;
        }

        .certificate-container::before {
            inset: 5px;
            border: 2px solid rgba(200, 161, 74, 0.85);
        }

        .certificate-container::after {
            width: 210mm;
            height: 210mm;
            right: -90mm;
            top: -110mm;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(29, 78, 216, 0.08), transparent 60%);
        }

        .accent-bar {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 12px;
            background: linear-gradient(90deg, var(--gold), #f7d98b, var(--blue));
        }

        .accent-strip {
            position: absolute;
            inset: 18px;
            border: 1px solid rgba(200, 161, 74, 0.28);
            pointer-events: none;
        }

        .inner-border {
            position: absolute;
            inset: 24px;
            border: 1px solid rgba(16, 42, 67, 0.12);
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
        }

        .header {
            margin-top: 2mm;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            font-size: 46px;
            color: var(--blue-deep);
            margin: 0;
            letter-spacing: 5px;
        }

        .header h2 {
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: var(--gold);
            margin-top: 5px;
            font-weight: 700;
        }

        .sub-header {
            margin-top: 8mm;
            font-size: 14px;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .student-name {
            font-family: 'Great Vibes', cursive;
            font-size: 62px;
            color: var(--blue-deep);
            margin: 8mm 0 6mm;
            border-bottom: 3px solid var(--gold);
            display: inline-block;
            padding: 0 46px 4px;
        }

        .details {
            font-size: 16px;
            line-height: 1.7;
            color: var(--ink-soft);
            max-width: 78%;
            margin: 0 auto;
        }

        .details strong {
            color: var(--ink);
        }

        .meta-grid {
            margin-top: 7mm;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            width: 100%;
            max-width: 180mm;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(16, 42, 67, 0.12);
            border-radius: 12px;
            padding: 10px 12px;
            box-shadow: 0 8px 18px rgba(16, 42, 67, 0.04);
        }

        .meta-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #7a8ca0;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            word-break: break-word;
        }

        .score-box {
            margin-top: 8mm;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 28px 14px;
            background: linear-gradient(180deg, #fff 0%, #f8fbff 100%);
            border: 1px solid rgba(200, 161, 74, 0.35);
            border-radius: 18px;
            box-shadow: 0 14px 28px rgba(16, 42, 67, 0.08);
        }

        .score-box .label {
            font-size: 11px;
            text-transform: uppercase;
            color: #718096;
            margin-bottom: 5px;
            letter-spacing: 1.1px;
        }

        .score-box .value {
            font-size: 34px;
            font-weight: 800;
            color: var(--blue-deep);
        }

        .footer {
            margin-top: 14mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            gap: 16px;
        }

        .signature {
            width: 62mm;
            text-align: center;
        }

        .signature .line {
            border-top: 1.5px solid rgba(16, 42, 67, 0.8);
            margin-bottom: 5px;
        }

        .signature .name {
            font-weight: 800;
            font-size: 13px;
            color: var(--ink);
        }

        .signature .title {
            font-size: 11px;
            color: #718096;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 138px;
            opacity: 0.05;
            z-index: 0;
            pointer-events: none;
            white-space: nowrap;
            color: var(--blue-deep);
        }

        @media print {
            body { background: none; }
            .certificate-container { margin: 0; box-shadow: none; }
            .no-print { display: none; }
        }

        .no-print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, var(--blue-deep), var(--blue));
            color: #fff;
            border: none;
            padding: 12px 18px;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            z-index: 100;
            box-shadow: 0 14px 28px rgba(29, 78, 216, 0.28);
        }

        .seal {
            position: absolute;
            right: 26mm;
            top: 24mm;
            width: 28mm;
            height: 28mm;
            border-radius: 50%;
            border: 2px solid rgba(200, 161, 74, 0.9);
            color: var(--gold);
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .5px;
            background: rgba(255,255,255,.86);
            box-shadow: 0 10px 20px rgba(16, 42, 67, 0.06);
        }
    </style>
</head>
<body>

<button class="no-print-btn no-print" onclick="window.print()">
    🖨️ Cetak Sertifikat
</button>

<div class="certificate-container">
    <div class="accent-bar"></div>
    <div class="accent-strip"></div>
    <div class="inner-border"></div>
    <div class="watermark">TKA KECAMATAN</div>
    <div class="seal">MERIT<br>SERTIFIKAT</div>
    
    <div class="content">
        <div class="header">
            <h1>SERTIFIKAT</h1>
            <h2>PENGHARGAAN</h2>
        </div>
        
        <div class="sub-header">
            Diberikan kepada:
        </div>
        
        <div class="student-name">
            <?= e($data['nama']) ?>
        </div>
        
        <div class="details">
            Atas keberhasilannya dalam mengikuti <strong>Ujian <?= e($data['nama_kategori']) ?></strong><br>
            yang diselenggarakan oleh <strong><?= e($namaAplikasi) ?></strong><br>
            pada tanggal <?= date('d F Y', strtotime($data['waktu_selesai'])) ?>.
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Kode Peserta</div>
                <div class="meta-value"><?= e($data['kode_peserta']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Kode Sekolah</div>
                <div class="meta-value"><?= e($data['kode_sekolah'] ?? '-') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Sekolah</div>
                <div class="meta-value"><?= e($data['nama_sekolah']) ?></div>
            </div>
        </div>
        
        <div class="score-box">
            <div class="label">Nilai Akhir</div>
            <div class="value"><?= $data['nilai'] ?></div>
            <div style="font-size:14px; color:var(--gold); font-weight:800; margin-top:5px; letter-spacing:1px;"><?= $ph ?></div>
        </div>
        
        <div class="footer">
            <div class="signature">
                <div style="height: 20mm;"></div>
                <div class="line"></div>
                <div class="name">Kepala Sekolah</div>
                <div class="title"><?= e($data['nama_sekolah']) ?></div>
            </div>
            
            <div style="text-align: center;">
                <div style="width: 30mm; height: 30mm; border: 2px solid #c5a059; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #c5a059; font-weight: 700;">
                    STEMPEL<br>RESMI
                </div>
            </div>

            <div class="signature">
                <div style="height: 20mm;"></div>
                <div class="line"></div>
                <div class="name">Panitia Pelaksana</div>
                <div class="title">Kecamatan TKA</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
