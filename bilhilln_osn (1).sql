-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 24 Bulan Mei 2026 pada 20.59
-- Versi server: 10.6.25-MariaDB-cll-lve
-- Versi PHP: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bilhilln_osn`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `backup_history`
--

CREATE TABLE `backup_history` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `ukuran` int(11) DEFAULT 0,
  `dibuat_oleh` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `backup_history`
--

INSERT INTO `backup_history` (`id`, `filename`, `ukuran`, `dibuat_oleh`, `created_at`) VALUES
(2, 'backup_tka_kecamatan_20260325_073409.sql', 47051, 'admin', '2026-03-25 07:34:09'),
(5, 'backup_tka_kecamatan_20260407_151821.sql', 95944, 'admin', '2026-04-07 15:18:22'),
(6, 'autobackup_20260416.sql', 45073, 'auto-daily', '2026-04-16 06:08:05'),
(7, 'autobackup_20260417.sql', 45073, 'auto-daily', '2026-04-17 07:56:43'),
(8, 'autobackup_20260420.sql', 45029, 'auto-daily', '2026-04-20 07:13:57'),
(9, 'autobackup_20260421.sql', 8505, 'auto-daily', '2026-04-21 04:34:05'),
(10, 'autobackup_20260426.sql', 9363, 'auto-daily', '2026-04-26 07:32:13'),
(11, 'autobackup_20260427.sql', 9363, 'auto-daily', '2026-04-27 10:28:21'),
(12, 'autobackup_20260428.sql', 9864, 'auto-daily', '2026-04-28 04:15:37'),
(13, 'autobackup_20260429.sql', 10372, 'auto-daily', '2026-04-29 06:41:52'),
(14, 'autobackup_20260430.sql', 11563, 'auto-daily', '2026-04-30 05:52:56'),
(23, 'autobackup_20260506.sql', 19795, 'auto-daily', '2026-05-06 06:44:19'),
(24, 'autobackup_20260507.sql', 17660, 'auto-daily', '2026-05-07 03:34:23'),
(32, 'backup_tka_kecamatan_20260512_081833.sql', 59650, 'admin', '2026-05-12 08:18:33'),
(33, 'autobackup_20260512.sql', 43341, 'auto-daily', '2026-05-12 08:20:51'),
(34, 'autobackup_20260517.sql', 44208, 'auto-daily', '2026-05-17 08:53:09'),
(35, 'autobackup_20260519.sql', 43147, 'auto-daily', '2026-05-19 13:58:26'),
(36, 'backup_tka_kecamatan_20260519_135937.sql', 52178, 'admin', '2026-05-19 13:59:37'),
(37, 'backup_tka_kecamatan_20260519_190040.sql', 44678, 'admin', '2026-05-19 19:00:40'),
(38, 'autobackup_20260520.sql', 20419, 'auto-daily', '2026-05-20 05:14:16'),
(39, 'backup_bilhilln_osn_20260520_140234.sql', 53811, 'admin', '2026-05-20 14:02:35'),
(40, 'backup_bilhilln_osn_20260520_140420.sql', 54025, 'admin', '2026-05-20 14:04:21'),
(41, 'autobackup_20260521.sql', 20171, 'auto-daily', '2026-05-21 07:54:21'),
(42, 'autobackup_20260522.sql', 21503, 'auto-daily', '2026-05-22 07:08:40'),
(43, 'autobackup_20260523.sql', 21816, 'auto-daily', '2026-05-23 02:22:52'),
(44, 'autobackup_20260524.sql', 95207, 'auto-daily', '2026-05-24 06:19:43'),
(45, 'backup_bilhilln_osn_20260524_191031.sql', 216702, 'admin', '2026-05-24 19:10:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `export_log`
--

CREATE TABLE `export_log` (
  `id` int(11) NOT NULL,
  `tipe` varchar(20) NOT NULL DEFAULT 'soal',
  `filename` varchar(255) NOT NULL,
  `dibuat_oleh` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `hasil_ujian`
--

CREATE TABLE `hasil_ujian` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) NOT NULL,
  `peserta_id` int(11) NOT NULL,
  `jadwal_id` int(11) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `total_soal` int(11) NOT NULL DEFAULT 0,
  `jml_benar` int(11) NOT NULL DEFAULT 0,
  `jml_salah` int(11) NOT NULL DEFAULT 0,
  `jml_kosong` int(11) NOT NULL DEFAULT 0,
  `nilai` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ada_essay` tinyint(1) NOT NULL DEFAULT 0,
  `essay_dinilai` tinyint(1) NOT NULL DEFAULT 0,
  `nilai_essay` decimal(6,2) DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_detik` int(11) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_ujian`
--

CREATE TABLE `jadwal_ujian` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `durasi_menit` int(11) NOT NULL DEFAULT 60,
  `kategori_id` int(11) DEFAULT NULL,
  `keterangan` varchar(200) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `tampil_pembahasan` tinyint(1) DEFAULT 0 COMMENT '0=global, 1=tampilkan, 2=sembunyikan',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `jumlah_soal` int(11) DEFAULT NULL COMMENT 'Override jumlah soal global; NULL = pakai pengaturan global',
  `kelas_diizinkan` varchar(100) DEFAULT NULL COMMENT 'Kosong = semua kelas; isi cth: 5,6 untuk kelas tertentu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_ujian`
--

INSERT INTO `jadwal_ujian` (`id`, `tanggal`, `jam_mulai`, `jam_selesai`, `durasi_menit`, `kategori_id`, `keterangan`, `status`, `tampil_pembahasan`, `created_at`, `jumlah_soal`, `kelas_diizinkan`) VALUES
(11, '2026-05-25', '09:00:00', '10:15:00', 75, 39, '', 'aktif', 0, '2026-05-24 12:21:23', 40, 'I'),
(12, '2026-05-25', '09:00:00', '10:15:00', 75, 44, '', 'aktif', 0, '2026-05-24 12:22:17', 30, 'III'),
(13, '2026-05-25', '09:00:00', '10:15:00', 60, 40, '', 'aktif', 0, '2026-05-24 12:23:03', 60, 'II');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jawaban`
--

CREATE TABLE `jawaban` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) NOT NULL,
  `peserta_id` int(11) NOT NULL,
  `soal_id` int(11) NOT NULL,
  `jawaban` varchar(20) DEFAULT NULL,
  `teks_jawaban` text DEFAULT NULL,
  `skor_essay` decimal(5,2) DEFAULT NULL,
  `dinilai_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_soal`
--

CREATE TABLE `kategori_soal` (
  `id` int(11) NOT NULL,
  `nama_kategori` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_soal`
--

INSERT INTO `kategori_soal` (`id`, `nama_kategori`) VALUES
(36, 'MTK-SIMULASI'),
(37, 'IPA-SIMULASI'),
(38, 'IPS-SIMULASI'),
(39, 'OSN-IPA'),
(40, 'OSN-IPS'),
(44, 'OSN-MATEMATIKA');

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `user_id`, `username`, `aktivitas`, `detail`, `ip_address`, `waktu`) VALUES
(1315, 1, 'admin', 'Reset Ujian', 'Semua data ujian dihapus oleh admin', '125.164.123.77', '2026-05-24 19:24:53'),
(1316, NULL, 'guest', 'Login Peserta', 'Peserta ALICE CLEMIRA TANISHA (TKA92BE58) login ujian', '101.255.168.6', '2026-05-24 19:26:12'),
(1317, NULL, 'guest', 'Login Peserta', 'Peserta KANAYA ARDANI (TKA3B00FD) login ujian', '182.2.180.33', '2026-05-24 19:27:24'),
(1318, NULL, 'guest', 'Submit Ujian', 'Peserta ID 383, benar: 12/40, nilai: 30', '182.2.180.33', '2026-05-24 19:30:22'),
(1319, NULL, 'guest', 'Submit Ujian', 'Peserta ID 404, benar: 5/30, nilai: 16.67', '101.255.168.6', '2026-05-24 19:33:29'),
(1320, 1, 'admin', 'Reset Ujian', 'Semua data ujian dihapus oleh admin', '101.255.168.6', '2026-05-24 19:34:21'),
(1321, 1, 'admin', 'Login', 'Berhasil login sebagai admin_kecamatan', '103.171.31.202', '2026-05-24 20:47:23'),
(1322, 1, 'admin', 'Edit Soal', 'ID 997 | Kategori ID 44 | Tipe pg', '103.171.31.202', '2026-05-24 20:49:45'),
(1323, 1, 'admin', 'Edit Soal', 'ID 999 | Kategori ID 44 | Tipe pg', '103.171.31.202', '2026-05-24 20:50:16'),
(1324, 1, 'admin', 'Login', 'Berhasil login sebagai admin_kecamatan', '125.164.123.77', '2026-05-24 20:52:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_val` text DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`id`, `setting_key`, `setting_val`, `keterangan`) VALUES
(1, 'nama_aplikasi', 'OLIMPIADE SAINS NASIONAL (OSN)   TINGKAT KECAMATAN  BANTARGEBANG 2026', 'Nama aplikasi yang tampil di header'),
(2, 'nama_kecamatan', 'Bantargebang', 'Nama kecamatan penyelenggara'),
(3, 'durasi_ujian', '44', 'Durasi ujian default (menit)'),
(4, 'jumlah_soal', '10', 'Jumlah soal per sesi ujian'),
(5, 'maks_pelanggaran', '3', 'Maks pindah tab sebelum ujian diakhiri'),
(6, 'display_info', 'Selamat datang di lomba Olimpiade Sains Nasional  (OSN) 2026 tingkat Kecamatan Bantargebang', 'Teks info di layar tunggu'),
(7, 'display_video_url', '', 'URL video untuk layar tunggu (YouTube embed)'),
(8, 'nama_penyelenggara', 'KKKS Bantargebang', NULL),
(9, 'mata_pelajaran', 'CBT OSN Online', NULL),
(10, 'tampil_pembahasan', '0', NULL),
(20, 'logo_url', '', NULL),
(71, 'kkm', '0', 'Nilai KKM minimum lulus'),
(72, 'ujian_ulang', '0', 'Izinkan peserta ujian ulang (0=tidak, 1=ya'),
(86, 'logo_file_path', 'assets/uploads/logo/logo_1779202143.png', NULL),
(187, 'tahun_pelajaran', '2026/2027', 'Tahun pelajaran aktif'),
(202, 'tahun_ajaran', '2025/2026', 'Tahun ajaran aktif'),
(386, 'pembahasan_mode', 'langsung', 'Kapan pembahasan ditampilkan: langsung / setelah_semua_selesai'),
(388, 'jenjang_default', 'SD', 'Jenjang default saat tambah sekolah baru'),
(448, 'wa_api_key', '', NULL),
(449, 'wa_sender', '081291858580', NULL),
(450, 'wa_auto_send', '0', NULL),
(451, 'pesan_maintenance', 'Sistem sedang dalam pemeliharaan. Silakan tunggu.', NULL),
(452, 'wa_share_hasil', '0', NULL),
(453, 'mode_maintenance', '0', NULL),
(454, 'acak_pilihan', '1', NULL),
(476, 'dev_nama', 'Cahyana Wijaya', 'Nama pengembang'),
(477, 'dev_role', 'Fullstack Developer', 'Role pengembang'),
(478, 'dev_bio', 'Berfokus pada pengembangan sistem informasi yang efisien dan solusi digital berbasis web untuk mendukung kemajuan teknologi di sektor pendidikan.', 'Bio pengembang'),
(479, 'dev_email', 'mrkuncen89@gmail.com', 'Email pengembang'),
(480, 'dev_wa', '6287781743048', 'WhatsApp pengembang'),
(481, 'dev_tiktok', '@mrkuncen', 'TikTok pengembang'),
(482, 'dev_foto', '', 'Foto pengembang'),
(483, 'dev_skills', 'PHP 8,MySQL,Bootstrap,CBT System,Data Export', 'Skills pengembang'),
(631, 'update_check_cache', '{\"ts\":1779551964,\"data\":[]}', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `peserta`
--

CREATE TABLE `peserta` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(20) DEFAULT NULL,
  `sekolah_id` int(11) NOT NULL,
  `kode_sekolah` varchar(100) DEFAULT NULL,
  `kode_peserta` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `peserta`
--

INSERT INTO `peserta` (`id`, `nama`, `kelas`, `sekolah_id`, `kode_sekolah`, `kode_peserta`) VALUES
(367, 'M. AGIS KENZA', 'I', 22, 'SDN Sumurbatu IV', 'TKA3C3164'),
(368, 'AFIKA FATHUL FAUZIYYAH', 'I', 22, 'SDN Sumurbatu II', 'TKA142C38'),
(369, 'ZAHDAN KHOIRUL UMAM', 'I', 22, 'SDN Bantargebang VI', 'TKA0AB39A'),
(370, 'GHAILAN DAIVA AL-GHIFFARY', 'I', 22, 'SDN Bantargebang IV', 'TKAF50A78'),
(371, 'FINA NAILATUL IZZAH', 'I', 22, 'SDIT ROUDHOTUL JANNAH', 'TKA33F8CC'),
(372, 'FATHIYA NAMIRA SAKHIY', 'I', 22, 'SDN Ciketingudik III', 'TKADFD89A'),
(374, 'ASSYIFA WINATA PUTRI', 'I', 22, 'SDN Bantargebang II', 'TKA56EF30'),
(375, 'BAATSUL KHOIROT RIYADILJANNAH', 'I', 22, 'SDN Cikiwul IV', 'TKA590BD6'),
(376, 'NAURA SYAFINAH RIZQIYYAH', 'I', 22, 'SDN Cikiwul I', 'TKA32E3D6'),
(378, 'ZAHRA FITRIAH', 'I', 22, 'SDN Bantargebang I', 'TKAF64AD6'),
(379, 'ANDARA TANISYA ALVIENA', 'I', 22, 'SDI IBNU HAJAR', 'TKAA1BDC3'),
(380, 'GHALLENA LABIBAH LUVENOV', 'I', 22, 'SDN Ciketingudik I', 'TKA0B9CCD'),
(381, 'PUTRA GHANTA MAHESA', 'I', 22, 'SDIT NURUL IMAN', 'TKA935809'),
(382, 'AKHTAR ALVARENDRA', 'I', 22, 'SDIT INSAN KAMIL', 'TKA625F7C'),
(383, 'KANAYA ARDANI', 'I', 22, 'SDN Ciketingudik II', 'TKA3B00FD'),
(384, 'SHOFIA NUHA ZAHIRA', 'I', 22, 'SDN Cikiwul III', 'TKAC2A5D5'),
(385, 'INTAN MULIYA ZAHIRAH', 'I', 22, 'SDN Sumurbatu I', 'TKA19D430'),
(386, 'HERRA SHAIFA SABELLA', 'I', 22, 'SDN Ciketingudik IV', 'TKA8B85C3'),
(387, 'KANAYA FEBI NURIAWAN', 'I', 22, 'SDN Bantargebang V', 'TKA032DC3'),
(388, 'YAQTA HUSAIBA URFAN', 'III', 25, 'SDN Sumurbatu IV', 'TKA4E2372'),
(389, 'MUHAMMAD IBNU FIRNAS AL HIJRI', 'III', 25, 'SDN Sumurbatu II', 'TKAE401B8'),
(390, 'KEISHA RAMADHINA', 'III', 25, 'SDN Bantargebang VI', 'TKA812058'),
(391, 'MUHAMMAD FAJRI RAMADHAN', 'III', 25, 'SDN Bantargebang IV', 'TKA72692A'),
(392, 'AFIQAH FATHIA HUMAIRA', 'III', 25, 'SDIT ROUDHOTUL JANNAH', 'TKAC61C1A'),
(393, 'MUHAMAD ILYAS HERMAWAN', 'III', 25, 'SDN Ciketingudik III', 'TKAF80DB1'),
(394, 'NINDYA AIDA SOFHIA', 'III', 25, 'SDN Bantargebang III', 'TKAEF9BA4'),
(395, 'ANNISA PUTRI', 'III', 25, 'SDN Bantargebang II', 'TKA06A616'),
(396, 'INEU MAULIDA', 'III', 25, 'SDN Cikiwul IV', 'TKAAF77B5'),
(397, 'CARISSA DHASA MONIFA YAHYA', 'III', 25, 'SDN Cikiwul I', 'TKA469EC5'),
(398, 'ASSYIFA SALSABILA', 'III', 25, 'SDN Cikiwul II', 'TKA6B77C2'),
(399, 'BIA SAKHI RAFANI', 'III', 25, 'SDN Bantargebang I', 'TKA2CEA21'),
(400, 'ALYSSA HUMAIRA DZAHIN', 'III', 25, 'SDI IBNU HAJAR', 'TKA06A647'),
(401, 'FALEXI ADIRA DARMA PRAJA', 'III', 25, 'SDN Ciketingudik I', 'TKA098F34'),
(402, 'ALIFA NUHA ZAHIRA', 'III', 25, 'SDIT NURUL IMAN', 'TKA704E35'),
(403, 'MUHAMAD ALFI HUSNI', 'III', 25, 'SDIT INSAN KAMIL', 'TKADDB5C0'),
(404, 'ALICE CLEMIRA TANISHA', 'III', 25, 'SDN Ciketingudik II', 'TKA92BE58'),
(405, 'NAUFAL AHMAD NAJID', 'III', 25, 'SDN Cikiwul III', 'TKAF645D7'),
(406, 'TIRTA', 'III', 25, 'SDN Sumurbatu I', 'TKA1B0151'),
(407, 'RIZQIE ANDI NOVRIYANTO', 'III', 25, 'SDN Ciketingudik IV', 'TKA6D38A3'),
(408, 'MUHAMMAD DAFA WIDIANTO', 'III', 25, 'SDN Bantargebang V', 'TKA2AAC2C'),
(409, 'ALISYA SURYAWATI', 'II', 23, 'SDN Sumurbatu IV', 'TKAECEEDB'),
(410, 'M. AL RASYA', 'II', 23, 'SDN Sumurbatu II', 'TKA3633CE'),
(411, 'INDAH PERMATA SARI', 'II', 23, 'SDN Bantargebang VI', 'TKAE9E757'),
(412, 'ARKIYA SENA', 'II', 23, 'SDN Bantargebang IV', 'TKABA3E3E'),
(413, 'DARREL SYAHIR HATTA', 'II', 23, 'SDIT ROUDHOTUL JANNAH', 'TKA488B2D'),
(414, 'AZKA ALFHARAZKY', 'II', 23, 'SDN Ciketingudik III', 'TKA84D32A'),
(415, 'MUHAMMAD ZAINI ABDUL MANAF', 'II', 23, 'SDN Bantargebang III', 'TKA196B67'),
(416, 'MUHAMMAD AZZAM NURWAHID', 'II', 23, 'SDN Bantargebang II', 'TKAC950F4'),
(417, 'KEISHA USWATUN HASANAH', 'II', 23, 'SDN Cikiwul IV', 'TKA845EBB'),
(418, 'FIRLY SUCI HIDAYAH', 'II', 23, 'SDN Cikiwul I', 'TKA7E08BA'),
(419, 'KAYSHA INDAH MARHAMAH', 'II', 23, 'SDN Cikiwul II', 'TKA55A01A'),
(420, 'RANIA MARVA', 'II', 23, 'SDN Bantargebang I', 'TKAF480AC'),
(421, 'RADINKA AINUHA TSURAYYA', 'II', 23, 'SDI IBNU HAJAR', 'TKA4499FC'),
(422, 'MUHAMAD HAIKAL SAPUTRA', 'II', 23, 'SDN Ciketingudik I', 'TKA9BF507'),
(423, 'MUHAMMAD ZIDAN AR RAFIF', 'II', 23, 'SDIT NURUL IMAN', 'TKA05ADA2'),
(424, 'MYEISHA ASRI SYARIF', 'II', 23, 'SDIT INSAN KAMIL', 'TKA8CAE1A'),
(425, 'HAIL MALIK SAMSUDIN', 'II', 23, 'SDN Ciketingudik II', 'TKAFDD386'),
(426, 'NAVIRA SYAHFITRI', 'II', 23, 'SDN Cikiwul III', 'TKA1FE14B'),
(427, 'AURA LATISHA AQUINA', 'II', 23, 'SDN Sumurbatu I', 'TKA6B9C42'),
(428, 'ALANNA KAMILA NUR FATIMAH', 'II', 23, 'SDN Ciketingudik IV', 'TKACCEC18'),
(429, 'UWAIS IBNU MARDI', 'II', 23, 'SDN Bantargebang V', 'TKAE30113'),
(432, 'LUBNA FAQIHA MUIZ', 'I', 22, 'SD ISLAM CIKIWUL', 'TKAEAAB87'),
(433, 'ADELIA ZAHRA NURASYIFA', 'II', 23, 'SD ISLAM CIKIWUL', 'TKA378F07'),
(434, 'ASYILA NUR RIZKY', 'III', 25, 'SD ISLAM CIKIWUL', 'TKAC10BC7'),
(441, 'ADHYASTHA PRASRAYA WINATA', 'I', 22, 'SDN CIKIWUL II', 'TKA0731CC'),
(442, 'DALIILAH KAMALA FADIYAH', 'I', 22, 'SDN BANTARGEBANG III', 'TKA6C0B33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rate_limit`
--

CREATE TABLE `rate_limit` (
  `rl_key` varchar(200) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `first_attempt` int(11) NOT NULL DEFAULT 0,
  `locked_until` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `rate_limit`
--

INSERT INTO `rate_limit` (`rl_key`, `attempts`, `first_attempt`, `locked_until`) VALUES
('login_110.137.59.107', 1, 1779420567, 0),
('login_114.10.64.98', 2, 1779416504, 0),
('login_114.8.199.26', 2, 1779323213, 0),
('login_125.160.236.180', 1, 1779432364, 0),
('login_125.164.120.106', 1, 1779419047, 0),
('login_14.137.225.168', 4, 1779413167, 0),
('login_140.213.35.1', 4, 1779247361, 0),
('login_140.213.9.94', 1, 1779248735, 0),
('login_182.2.185.120', 3, 1779247323, 0),
('login_182.6.43.44', 2, 1779247450, 0),
('login_182.6.47.44', 1, 1779419805, 0),
('login_182.6.7.5', 3, 1779247332, 0),
('login_218.33.120.79', 4, 1779415173, 0),
('login_38.210.85.95', 3, 1779414228, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `sekolah`
--

CREATE TABLE `sekolah` (
  `id` int(11) NOT NULL,
  `nama_sekolah` varchar(150) NOT NULL,
  `jenjang` varchar(10) DEFAULT 'SD' COMMENT 'SD / MI / SMP / MTS / SMA / MA / SMK',
  `npsn` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `kepala_sekolah` varchar(100) DEFAULT NULL COMMENT 'Nama kepala sekolah',
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `sekolah`
--

INSERT INTO `sekolah` (`id`, `nama_sekolah`, `jenjang`, `npsn`, `alamat`, `kepala_sekolah`, `telepon`, `email`, `status`) VALUES
(22, 'Ilmu Pengetahuan Alam', 'SD', 'IPA', '', NULL, '', NULL, 'aktif'),
(23, 'Ilmu Pengetahuan Sosial', 'SD', 'IPS', '', NULL, '', NULL, 'aktif'),
(25, 'Matematika', 'SD', 'MTK', '', NULL, '', NULL, 'aktif');

-- --------------------------------------------------------

--
-- Struktur dari tabel `soal`
--

CREATE TABLE `soal` (
  `id` int(11) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `tipe_soal` enum('pg','bs','mcma','essay') NOT NULL DEFAULT 'pg',
  `pertanyaan` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `teks_bacaan` text DEFAULT NULL,
  `pilihan_a` text DEFAULT NULL,
  `gambar_pilihan_a` varchar(255) DEFAULT NULL,
  `pilihan_b` text DEFAULT NULL,
  `gambar_pilihan_b` varchar(255) DEFAULT NULL,
  `pilihan_c` text DEFAULT NULL,
  `gambar_pilihan_c` varchar(255) DEFAULT NULL,
  `pilihan_d` text DEFAULT NULL,
  `gambar_pilihan_d` varchar(255) DEFAULT NULL,
  `jawaban_benar` text DEFAULT NULL,
  `pembahasan` text DEFAULT NULL,
  `essay_bobot` tinyint(3) UNSIGNED NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `soal`
--

INSERT INTO `soal` (`id`, `kategori_id`, `tipe_soal`, `pertanyaan`, `gambar`, `teks_bacaan`, `pilihan_a`, `gambar_pilihan_a`, `pilihan_b`, `gambar_pilihan_b`, `pilihan_c`, `gambar_pilihan_c`, `pilihan_d`, `gambar_pilihan_d`, `jawaban_benar`, `pembahasan`, `essay_bobot`) VALUES
(854, 37, 'pg', 'Tanaman pada gambar di atas dapat diolah menjadi sayur-sayuran. Salah satu ciri-cirinya yaitu...', 'soal_6a0c58e2cc573.jpg', 'Perhatikan gambar ini !', 'Batang berongga dan lunak', '', 'Batangnya beruas-ruas', '', 'Batangnya keras dan kuat', '', 'Batangnya berkayu', '', 'a', '', 10),
(855, 37, 'pg', 'Gaya gesek yang terjadi pada suatu benda dipengaruhi oleh dua hal. Dua hal tersebut ditunjukkan oleh pernyataan nomor .', 'soal_6a0c5a240d550.jpg', 'Perhatikan tabel berikut!', '1 dan 2', '', '1 dan 3', '', '2 dan 3', '', '3 dan 4', '', 'c', '', 10),
(856, 37, 'pg', 'Tim merah menarik tali ke kanan, sedangkan tim putih menarik tali ke kiri. Apabila tim merah menarik lebih kuat daripada tim\r\nputih, maka . .', 'soal_6a0c5aa31f706.jpg', 'Perhatikan gambar di bawah!', 'Tidak ada yang bergerak', '', 'Kedua tim akan bergerak', '', 'Tim merah akan bergerak mendekati tim putih', '', 'Tim putih akan bergerak mendekati tim merah', '', 'd', '', 10),
(857, 37, 'pg', 'Gambar di atas menunjukkan adanya perpindahan energi. Sumber energi utama pada gambar tersebut yaitu. . .', 'soal_6a0c5b7b06252.jpg', 'Perhatikan gambar di bawah!', 'Manusia', '', 'Tumbuhan', '', 'Hewan', '', 'Sinar matahari', '', 'd', '', 10),
(858, 37, 'pg', 'Benda yang mengalami perubahan energi kimia menjadi energi listrik ditunjukkan oleh gambar nomor. . . .', 'soal_6a0c5b8e5ae18.jpg', '', '1', '', '2', '', '3', '', '4', '', 'c', '', 10),
(859, 37, 'pg', 'Siswa kelas IV melaksanakan percobaan seperti gambar di atas. Setelah 15-30 menit, siswa melihat adanya gelembunggelembung pada daun. Percobaan tersebut bertujuan untuk menunjukkan bahwa fotosintesis . . .', 'soal_6a0c5bddc9561.jpg', 'Perhatikan gambar berikut!', 'Menghasilkan karbohidrat', '', 'Menghasilkan oksigen', '', 'Memerlukan karbon dioksida', '', 'Memerlukan air', '', 'b', '', 10),
(860, 37, 'pg', 'Bahan bakar dari benda di atas adalah bensin. Bensin akan diubah menjadi energi gerak dan energi lainnya. Energi lainnya yang\r\ndimaksud yaitu . . .', 'soal_6a0c5c6dbab69.jpg', 'Perhatikan gambar berikut!', 'Energi panas dan energi kimia', '', 'Energi panas dan energi listrik', '', 'Energi listrik dan energi kimia', '', 'Energi listrik dan energi cahaya', '', 'b', '', 10),
(861, 37, 'pg', 'Pengaruh gaya terhadap benda pada gambar di atas yaitu. . .', 'soal_6a0c5ccf64f73.jpg', 'Perhatikan gambar berikut!', 'Mengubah bentuk benda', '', 'Membuat benda bergerak', '', 'Mengubah arah gerak benda', '', 'Membuat benda diam menjadi bergerak', '', 'c', '', 10),
(862, 37, 'pg', 'Peristiwa yang terjadi selama musim panas ditunjukkan oleh nomor . . .', 'soal_6a0c5d1aa9ba2.jpg', 'Perhatikan tabel berikut!', '1', '', '2', '', '3', '', '4', '', 'a', '', 10),
(863, 37, 'pg', 'Baterai menyimpan energi ....', 'soal_6a0c5d717548f.jpg', 'Perhatikan gambar berikut!', 'Cahaya', '', 'Gerak', '', 'Panas', '', 'Kimia', '', 'd', '', 10),
(864, 38, 'pg', 'Keragaman budaya bangsa Indonesia salah satunya terlihat dari\r\npakaian adat. Pakaian adat pada gambar di atas berasal dari\r\ndaerah...', 'soal_6a0c5e0d02934.jpg', 'Perhatikan gambar pakaian adat di bawah ini!', 'Sumatera Barat', '', 'Jawa Tengah', '', 'Papua', '', 'Sulawesi Selatan', '', 'b', '', 10),
(865, 38, 'pg', 'Berdasarkan alur kegiatan ekonomi, aktivitas yang dilakukan oleh\r\norang-orang pada gambar di atas termasuk dalam kegiatan...', 'soal_6a0c5e5c3d7b4.jpg', 'Perhatikan gambar kegiatan ekonomi berikut!', 'Konsumsi', '', 'Distribusi', '', 'Transportasi', '', 'Produksi', '', 'd', '', 10),
(866, 38, 'pg', 'Peninggalan sejarah yang sangat megah pada gambar di atas\r\nmerupakan bukti pengaruh dari kerajaan bercorak...', 'soal_6a0c5eb113911.jpg', 'Perhatikan gambar peninggalan sejarah di bawah ini!', 'Budhha', '', 'Islam', '', 'Kristen', '', 'Hindu', '', 'a', '', 10),
(867, 38, 'pg', 'Kenampakan alam pada gambar di atas terletak di daerah\r\ndataran tinggi. Aktivitas ekonomi masyarakat yang paling cocok\r\nuntuk daerah tersebut adalah...', 'soal_6a0c5f1527302.jpg', 'Perhatikan gambar kenampakan alam berikut!', 'Menjadi nelayan', '', 'Bertani padi', '', 'Perkebunan teh', '', 'Membuat garam', '', 'a', '', 10),
(868, 38, 'pg', 'Komponen peta yang ditunjukkan oleh tanda panah berfungsi\r\nuntuk...', 'soal_6a0c5f705e673.jpg', 'Perhatikan gambar peta di bawah ini!', 'Mengetahui jarak tempat', '', 'Menentukan arah mata angin', '', 'Membaca arti simbol peta', '', 'Mengetahui judul peta', '', 'b', '', 10),
(869, 38, 'pg', 'Uang pada gambar di atas digunakan sebagai alat pembayaran\r\nyang sah. Nilai nominal dari uang tersebut adalah...', 'soal_6a0c5fce11d7d.jpg', 'Perhatikan gambar uang di bawah ini!', 'Lima ribu rupiah', '', 'Sepuluh ribu rupiah', '', 'Dua puluh ribu rupiah', '', 'Lima puluh ribu rupiah', '', 'b', '', 10),
(870, 38, 'pg', 'Jenis pekerjaan yang ditunjukkan oleh gambar di atas\r\nmenghasilkan...', 'soal_6a0c601f3ad03.jpg', 'Perhatikan gambar pekerjaan berikut!', 'Barang', '', 'Makanan', '', 'Jasa', '', 'Kerajinan', '', 'c', '', 10),
(871, 38, 'pg', 'Kegiatan jual beli pada gambar di atas biasanya terjadi di...', 'soal_6a0c60734b826.jpg', 'Perhatikan gambar di bawah ini!', 'Supermarket', '', 'Pasar tradisional', '', 'Toko Online', '', 'Apotek', '', 'b', '', 10),
(872, 38, 'pg', 'Gambar tersebut merupakan salah satu rumah adat yang ada di\r\nIndonesia. Rumah adat termasuk ke dalam lingkungan...', 'soal_6a0c60c7693f0.jpg', 'Perhatikan gambar berikut!', 'Alamiah', '', 'Buatan Manusia', '', 'Gaib', '', 'Angkasa', '', 'b', '', 10),
(873, 38, 'pg', 'Gambar di atas menunjukkan denah sebuah sekolah. Jika kamu\r\nberada di gerbang sekolah dan ingin pergi ke lapangan sepak\r\nbola, arah mata angin yang harus kamu tuju berdasarkan denah\r\nadalah...', 'soal_6a0c6124ca6f2.jpg', 'Perhatikan gambar di bawah ini!', 'Utara', '', 'Selatan', '', 'Timur', '', 'Barat', '', 'c', '', 10),
(874, 36, 'pg', 'Seutas tali sepanjang 15 meter dipotong menjadi dua bagian. Jika bagian pertama\r\npanjangnya 725 cm, maka panjang bagian kedua adalah...', 'soal_6a0c61b220860.jpg', '', '7,75', '', '7,25', '', '8,25', '', '8,75', '', 'a', '', 10),
(875, 36, 'pg', 'Sebuah mobil menempuh perjalanan selama 2 jam 45 menit, kemudian berhenti\r\nselama 20 menit, dan lanjut lagi selama 1 jam 15 menit. Total waktu perjalanan\r\ntersebut adalah...', 'soal_6a0c62080194a.jpg', '', '4 jam 10 menit', '', '4 jam 20 menit', '', '4 jam 30 menit', '', '4 jam 40 menit', '', 'a', '', 10),
(876, 36, 'pg', 'Ibu membeli 2 kg tepung terigu, 500 gram mentega, dan 25 ons gula pasir. Berat\r\ntotal belanjaan Ibu adalah...', 'soal_6a0c625613f2f.jpg', '', '3 kg', '', '4 kg', '', '5 kg', '', '6 kg', '', 'a', '', 10),
(877, 36, 'pg', 'Manakah di antara pecahan berikut yang nilainya paling mendekati 1/2?', '', '', '2/5', '', '3/7', '', '4/9', '', '5/11', '', 'd', '', 10),
(878, 36, 'pg', 'Bentuk pecahan paling sederhana dari 0, 363636 adala', '', '', '36/100', '', '4/11', '', '9/25', '', '12/33', '', 'a', '', 10),
(879, 36, 'pg', 'Hasil dari 1/2 + 1/4 + 1/8 + 1/16 adalah...', '', '', '13/16', '', '15/16', '', '7/8', '', '1', '', 'b', '', 10),
(880, 36, 'pg', 'Sebuah bilangan jika dibagi 7 memberikan hasil 12 dan sisa 4. Bilangan tersebut\r\nadalah...', '', '', '80', '', '84', '', '88', '', '92', '', 'c', '', 10),
(881, 36, 'pg', 'Perhatikan barisan bilangan cacah berikut:\r\n3, 7, 11, 15, ….\r\nSuku ke-10 dari barisan tersebut adalah...', '', '', '35', '', '39', '', '41', '', '43', '', 'b', '', 10),
(882, 36, 'pg', 'Seorang dermawan ingin membagikan 1.440 buku tulis kepada anak-anak di panti\r\nasuhan. Jika setiap anak mendapatkan 15 buku, dan buku tersebut dikemas dalam\r\nkardus yang masing-masing berisi 120 buku, berapa banyak anak yang akan\r\nmenerima bantuan tersebut?', '', '', '84 anak', '', '90 anak', '', '96 anak', '', '102 anak', '', 'c', '', 10),
(883, 36, 'pg', 'Di sebuah kompetisi matematika, panitia menyediakan 12 kotak berisi medali.\r\nSetiap kotak memiliki isi yang sama yaitu 36 buah medali. Panitia ingin\r\nmendistribusikan seluruh medali tersebut secara merata kepada sejumlah siswa\r\nberprestasi. Jika terdapat 24 siswa, tentukan berapakah total medali yang diterima\r\noleh setiap siswa?', '', '', '12 Medali', '', '16 Medali', '', '18 Medali', '', '20 Medali', '', 'c', '', 10),
(894, 40, 'pg', 'Melihat gambar denah di atas, benda yang terletak di paling depan kelas dan digunakan guru untuk menulis adalah...', 'soal_6a0dde2e46e19.png', 'Perhatikan gambar di bawah ini!', 'Meja murid', '', 'Lemari buku', '', 'Papan tulis', '', 'Jendela', '', 'c', '', 10),
(895, 40, 'pg', 'Lingkungan yang dibuat oleh manusia untuk mempermudah kehidupan sehari-hari disebut lingkungan...', '', '', 'Alami', '', 'Buatan', '', 'Gaib', '', 'Semesta', '', 'b', '', 10),
(896, 40, 'pg', 'Gambar di atas menunjukkan salah satu contoh lingkungan alam, yaitu...', 'soal_6a11d1255c22c.jpg', 'Perhatikan gambar di bawah ini!', 'Sawah', '', 'Waduk', '', 'Sungai', '', 'Kolam', '', 'c', '', 10),
(897, 40, 'pg', 'Berikut ini yang termasuk contoh lingkungan buatan di sekitar sekolah adalah...', '', '', 'Gunung', '', 'Gedung sekolah dan lapangan upacara', '', 'Hutan Rimba', '', 'Lautan', '', 'b', '', 10),
(898, 40, 'pg', 'Berdasarkan gambar arah mata angin tersebut, arah yang berada di antara Utara dan Timur adalah...', 'soal_6a11d222270c5.png', 'Perhatikan gambar di bawah ini!', 'Selatan', '', 'Barat Daya', '', 'TImur Laut', '', 'Barat Laut', '', 'c', '', 10),
(900, 40, 'pg', 'Alat bantu yang digunakan untuk menunjukkan letak suatu tempat atau ruangan secara terperinci disebut...', '', '', 'Kompas', '', 'Denah', '', 'Barometer', '', 'Termometer', '', 'b', '', 10),
(901, 40, 'pg', 'Kegiatan membersihkan rumah bersama-sama dengan ayah, ibu, dan kakak merupakan bentuk kerja sama di lingkungan...', '', '', 'Sekolah', '', 'Rumah/Keluarga', '', 'Kelurahan', '', 'Pasar', '', 'b', '', 10),
(902, 40, 'pg', 'Manfaat utama dari kegiatan kerja sama pada gambar di atas adalah...', 'soal_6a11d34d5c189.png', 'Perhatikan gambar di bawah ini!', 'Mendapatkan uang jajan tambahan', '', 'Membuat kelas menjadi cepat bersih dan rapi', '', 'Supaya bisa bermain di dalam kelas', '', 'Menunda waktu pulang sekolah', '', 'b', '', 10),
(903, 40, 'pg', 'Pekerjaan berikut ini yang menghasilkan barang berupa makanan pokok adalah...', '', '', 'Guru', '', 'Dokter', '', 'Petani', '', 'Sopir', '', 'c', '', 10),
(904, 40, 'pg', 'Orang yang bekerja memberikan layanan kesehatan kepada pasien di rumah sakit disebut...', '', '', 'Polisi', '', 'Dokter', '', 'Pemadam Kebakaran', '', 'Montir', '', 'b', '', 10),
(905, 40, 'pg', 'Pada peta, simbol segitiga berwarna merah seperti gambar di atas digunakan untuk menunjukkan...', 'soal_6a11d69be2cdc.jpeg', 'Perhatikan gambar di bawah ini!', 'Gunung berapi yang aktif', '', 'Danau air tawar', '', 'Ibu kota provinsi', '', 'Bandar udara', '', 'a', '', 10),
(906, 40, 'pg', 'Jika kamu sedang menghadap ke arah Barat, maka punggungmu menghadap ke arah...', '', '', 'Utara', '', 'Selatan', '', 'Timur', '', 'Barat Daya', '', 'c', '', 10),
(907, 40, 'pg', 'Lingkungan buatan pada gambar di atas memiliki fungsi utama bagi masyarakat sekitar untuk...', 'soal_6a11d9f990945.jpg', 'Perhatikan gambar di bawah ini!', 'Tempat membuang limbah pabrik', '', 'Sarana irigasi pertanian dan pembangkit listrik', '', 'Tempat memperluas lahan pemukiman', '', 'Mencari kayu bakar', '', 'b', '', 10),
(908, 40, 'pg', 'Salah satu cara menjaga kelestarian lingkungan alam di sekitar rumah kita adalah...', '', '', 'Menebang pohon sembarangan', '', 'Membuang sampah di selokan depan rumah', '', 'Menanam tanaman hias atau apotek hidup di pekarangan', '', 'Menutup seluruh permukaan tanah dengan semen', '', 'c', '', 10),
(909, 40, 'pg', 'Kerja sama sangat penting dilakukan dalam kehidupan bermasyarakat. Contoh kerja sama di lingkungan warga tingkat RT adalah...', '', '', 'Mengerjakan ujian sekolah bersama-sama', '', 'Melaksanakan ronda malam dan kerja bakti membersihkan selokan', '', 'Membantu ibu memasak di dapur', '', 'Berbelanja bersama ke supermarket', '', 'b', '', 10),
(911, 40, 'pg', 'Nilai luhur bangsa Indonesia yang tercermin pada kegiatan gambar di atas adalah...', 'soal_6a11dbcb73377.jpg', '', 'Individualisme', '', 'Gotong royong', '', 'Bersaing sehat', '', 'Konsumtif', '', 'b', '', 10),
(912, 40, 'pg', 'Seseorang bekerja untuk memenuhi kebutuhan hidup keluarganya. Kebutuhan paling mendasar yang harus dipenuhi terlebih dahulu adalah...', '', '', 'Mobil mewah dan perhiasan', '', 'Handphone baru dan kuota internet', '', 'Makanan, pakaian, dan tempat tinggal (pangan, sandang, papan)', '', 'Liburan ke luar negeri', '', 'c', '', 10),
(913, 40, 'pg', 'Pak Badu memiliki keahlian memperbaiki sepeda motor yang rusak. Pekerjaan Pak Badu termasuk jenis pekerjaan yang menghasilkan...', '', '', 'Barang dagangan', '', 'Jasa perbaikan', '', 'Bahan makanan', '', 'Kerajinan tangan', '', 'b', '', 10),
(914, 40, 'pg', 'Uang seperti pada gambar di atas dikeluarkan secara resmi oleh bank sentral negara kita, yaitu...', 'soal_6a11dd376e668.jpg', 'Perhatikan gambar di bawah ini!', 'Bank Asia', '', 'Bank Indonesia', '', 'Bank Rakyat Indonesia', '', 'Bank Mandiri', '', 'b', '', 10),
(915, 40, 'pg', 'Penggunaan uang sebagai alat tukar yang sah membuat kegiatan jual beli menjadi lebih mudah dibandingkan zaman dahulu yang menggunakan sistem tukar menukar barang yang disebut...', '', '', 'Monetisasi', '', 'Kredit', '', 'Barter', '', 'Investasi', '', 'c', '', 10),
(916, 40, 'pg', 'Masyarakat di daerah pantai sering membuat rumah berbentuk panggung seperti gambar. Hal ini merupakan bentuk adaptasi manusia terhadap lingkungan alam untuk menghindari...', 'soal_6a11dfdc944f8.jpg', 'Perhatikan gambar di bawah ini!', 'Tanah longsor dari perbukitan', '', 'Udara dingin pegunungan', '', 'Pasang air laut atau banjir rob', '', 'Serangan hewan buas di hutan', '', 'c', '', 10),
(917, 40, 'pg', 'Koperasi sekolah merupakan contoh kerja sama di lingkungan sekolah yang bertujuan untuk...', '', '', 'Mencari keuntungan sebesar-besarnya dari siswa', '', 'Melatih siswa berwirausaha dan memenuhi kebutuhan alat tulis siswa', '', 'Menggantikan tugas kantin secara keseluruhan', '', 'Memberikan pinjaman uang tunai kepada masyarakat umum', '', 'b', '', 10),
(918, 40, 'pg', 'Perhatikan pernyataan berikut!\r\n1.	Membantu teman saat menyontek ujian.\r\n2.	Membersihkan halaman rumah bersama adik.\r\n3.	Mengikuti kerja bakti membersihkan tempat ibadah.\r\n4.	Membantu pencuri melarikan diri.\r\n\r\nKerja sama yang bernilai positif dan boleh dilakukan ditunjukkan oleh nomor...', '', '', '1 dan 2', '', '2 dan 3', '', '3 dan 4', '', '1 dan 4', '', 'b', '', 10),
(919, 40, 'pg', 'Dampak negatif jangka panjang bagi kesehatan manusia jika lingkungan perkotaan berubah seperti pada gambar di atas adalah...', 'soal_6a11e161f2c74.jpeg', 'Perhatikan gambar di bawah ini!', 'Meningkatnya penyakit saluran pernapasan (ISPA)', '', 'Air bersih menjadi semakin melimpah', '', 'Suhu udara kota menjadi semakin sejuk', '', 'Angka kecelakaan menurun drastis', '', 'a', '', 10),
(920, 40, 'pg', 'Kebakaran hutan yang sering terjadi saat musim kemarau panjang dapat merusak lingkungan alam. Langkah penanggulangan yang paling tepat agar hutan tetap lestari setelah terbakar adalah...', '', '', 'Mengubah lahan hutan menjadi perkebunan kelapa sawit', '', 'Melakukan reboisasi atau penanaman hutan kembali', '', 'Membiarkan hutan mengering dan menjadi semak belukar', '', 'Membangun pemukiman baru di bekas lahan hutan', '', 'b', '', 10),
(921, 40, 'pg', 'Seseorang yang memiliki kemampuan mengelola modal, tenaga kerja, dan peluang usaha untuk menghasilkan barang atau jasa baru disebut...', '', '', 'Karyawan', '', 'Buruh', '', 'Wirausahawan', '', 'Pegawai Negeri Sipil', '', 'c', '', 10),
(922, 40, 'pg', 'Sebelum menggunakan uang kertas dan logam modern seperti sekarang, manusia pernah menggunakan \"uang barang\". Syarat utama suatu barang bisa dijadikan alat tukar pada masa itu adalah...', 'soal_6a11e2c207f32.png', 'Perhatikan gambar di bawah ini!', 'Harganya sangat mahal dan sulit ditemukan', '', 'Barang tersebut disukai, berharga, dan diterima oleh banyak orang', '', 'Barang tersebut harus berukuran sangat besar', '', 'Barang tersebut cepat busuk atau rusak', '', 'b', '', 10),
(923, 40, 'pg', 'Di dalam sebuah denah atau peta resmi, terdapat simbol arah mata angin yang biasanya menunjuk ke arah atas. Arah atas pada denah tersebut selalu menunjukkan arah...', '', '', 'Timur', '', 'Barat', '', 'Utara', '', 'Selatan', '', 'c', '', 10),
(924, 40, 'pg', 'Hubungan ketergantungan antara masyarakat kota dan masyarakat desa tercermin dalam kegiatan ekonomi. Contoh ketergantungan masyarakat kota terhadap desa adalah...', '', '', 'Masyarakat kota membutuhkan pasokan bahan makanan segar seperti sayur dan beras dari desa', '', 'Masyarakat kota mengirimkan tenaga medis ke desa', '', 'Masyarakat desa membeli barang-barang elektronik dari kota', '', 'Masyarakat desa mencari pekerjaan di kota', '', 'a', '', 10),
(925, 40, 'pg', 'Berdasarkan bagan alur kegiatan ekonomi di atas, peran toko beras dalam rantai ekonomi tersebut adalah sebagai...', 'soal_6a11e4cb4d723.png', 'Perhatikan gambar di bawah ini!', 'Produsen', '', 'Distributor', '', 'Konsumen', '', 'Pengolah Bahan Baku', '', 'b', '', 10),
(926, 40, 'pg', 'Komponen peta yang berupa daftar penjelasan simbol-simbol yang digunakan pada peta disebut...', 'soal_6a126e21e1894.jpg', 'Perhatikan gambar di bawah ini!', 'Judul peta', '', 'Skala peta', '', 'Legenda', '', 'Inset', '', 'c', '', 10),
(927, 40, 'pg', '2.	Skala peta 1 : 100.000  artinya 1 cm pada peta sama dengan... di lapangan (jarak sebenarnya)', '', '', '1 meter', '', '100 meter', '', '1 kilometer', '', '10 kilometer', '', 'c', '', 10),
(928, 40, 'pg', 'Bentuk muka bumi berupa daratan luas yang terletak pada ketinggian lebih dari 600 meter di atas permukaan laut disebut...', 'soal_6a126fc4e4e04.jpg', 'Perhatikan gambar di bawah ini!', 'Dataran rendah', '', 'Dataran tinggi', '', 'Lembah', '', 'Pantai', '', 'b', '', 10),
(929, 40, 'pg', 'Sumber daya alam yang tidak akan habis meskipun digunakan secara terus-menerus karena dapat memperbanyak diri disebut sumber daya alam yang...', '', '', 'Dapat diperbarui', '', 'Tidak dapat diperbarui', '', 'Cepat habis', '', 'Sangat langka', '', 'a', '', 10),
(930, 40, 'pg', 'Gambar di atas menunjukkan salah satu hasil pemanfaatan sumber daya alam yang berasal dari lingkungan...', 'soal_6a12708666a48.jpg', 'Perhatikan gambar di bawah ini!', 'Laut', '', 'Sungai', '', 'Hutan', '', 'Sawah', '', 'c', '', 10),
(931, 39, 'pg', 'Bagian tumbuhan yang berfungsi menyerap air adalah ....', 'soal_6a129974b0c3b.jpeg', 'Perhatikan gambar dibawah ini!', 'Daun', '', 'Bunga', '', 'Akar', '', 'Batang', '', 'c', '', 10),
(932, 39, 'pg', 'Tumbuhan membuat makanan melalui proses ....', 'soal_6a12a16d59354.jpeg', 'Perhatikan gambar berikut!', 'penguapan', '', 'fotosintesis', '', 'pernapasan', '', 'pembusukan', '', 'b', '', 10),
(933, 39, 'pg', 'Gaya dapat menyebabkan benda menjadi ....', '', '', 'diam terus', '', 'berubah gerak', '', 'hilang', '', 'pecah semua', '', 'b', '', 10),
(934, 39, 'pg', 'Pada gambar berikut, dipengaruhi oleh adanya gaya...', 'soal_6a12a2936ffbb.jpeg', 'Perhatikan gambar berikut!', 'Gravitasi Bumi', '', 'Magnet', '', 'Pegas', '', 'Gesek', '', 'a', '', 10),
(935, 39, 'pg', 'Jika diluncurkan di suatu permukaan yang halus, bola akan mengalami pengurangan gaya gesek. Hal ini menyebabkan bola meluncur secara…', '', '', 'sesuai takaran', '', 'tidak menentu', '', 'cepat', '', 'lambat', '', 'c', '', 10),
(936, 39, 'pg', 'Panel surya memanfaatkan energi ....', 'soal_6a12a3ae0e35b.jpeg', 'Perhatikan gambar dibawah ini!', 'panas bumi', '', 'air', '', 'cahaya matahari', '', 'angin', '', 'c', '', 10),
(937, 39, 'pg', 'Kipas angin mengubah energi listrik menjadi energi ....', 'soal_6a12a67be6e22.jpeg', 'Perhatikan gambar dibawah ini!', 'panas', '', 'bunyi', '', 'gerak', '', 'cahaya', '', 'c', '', 10),
(938, 39, 'pg', 'Mobil pada gambar mengalami perubahan energi dari ....', 'soal_6a12a746d92ba.jpeg', 'Perhatikan gambar dibawah ini!', 'energi listrik menjadi energi panas', '', 'energi kimia menjadi energi gerak', '', 'energi cahaya menjadi energi bunyi', '', 'energi gerak menjadi energi listrik', '', 'b', '', 10),
(939, 39, 'pg', 'Saat mengayuh sepeda, tubuh menggunakan energi ....', '', '', 'kimia dari makanan', '', 'cahaya matahari', '', 'listrik', '', 'magnet', '', 'a', '', 10),
(940, 39, 'pg', 'Contoh sumber energi alternatif adalah ....', '', '', 'minyak bumi', '', 'batu bara', '', 'matahari', '', 'bensin', '', 'c', '', 10),
(941, 40, 'pg', 'Keragaman suku bangsa dan budaya di Indonesia merupakan kekayaan bangsa yang harus kita...', '', '', 'Permusuhkan', '', 'Lestarikan dan hormati', '', 'Hilangkan agar seragam', '', 'Lupakan', '', 'b', '', 10),
(942, 39, 'pg', 'Peralatan elektronik pada gambar di atas mengubah energi listrik menjadi. . .', 'soal_6a12a867624a2.jpeg', 'Perhatikan gambar berikut!', 'energi panas', '', 'energi gerak', '', 'energi bunyi', '', 'energi cahaya dan bunyi', '', 'd', '', 10),
(943, 40, 'pg', 'Rumah adat yang memiliki atap melengkung menyerupai tanduk kerbau atau perahu pada gambar di atas berasal dari suku...', 'soal_6a12a8a22ae8f.jpg', 'Perhatikan gambar di bawah ini!', 'Minangkabau', '', 'Toraja', '', 'Dani', '', 'Jawa', '', 'b', '', 10),
(944, 39, 'pg', 'Planet tempat tinggal manusia adalah ....', '', '', 'Mars', '', 'Venus', '', 'Bumi', '', 'Jupiter', '', 'c', '', 10),
(945, 40, 'pg', 'Kegiatan manusia yang bertujuan untuk menghasilkan barang atau jasa guna memenuhi kebutuhan hidup dinamakan...', '', '', 'Kegiatan konsumsi', '', 'Kegiatan distribusi', '', 'Kegiatan ekonomi', '', 'Kegiatan rekreasi', '', 'c', '', 10),
(946, 39, 'pg', 'Benda pada gambar di atas sering kita gunakan saat listrik padam. Perubahan energi yang terjadi pada benda tersebut yaitu . . .', 'soal_6a12dba298635.jpeg', 'Perhatikan gambar berikut!', 'energi panas ➜ energi gerak ➜ energi cahaya', '', 'energi gerak ➜ energi listrik ➜ energi cahaya', '', 'energi listrik ➜ energi kimia ➜ energi cahaya', '', 'energi kimia ➜ energi listrik ➜ energi cahaya', '', 'd', '', 10),
(947, 40, 'pg', 'Orang atau pihak yang menggunakan/menghabiskan nilai guna barang dan jasa seperti pada gambar disebut..', 'soal_6a12a981d3fb4.jpg', 'Perhatikan gambar di bawah ini!', 'Produsen', '', 'Distributor', '', 'Konsumen', '', 'Agen', '', 'c', '', 10),
(948, 39, 'pg', 'Bumi mengelilingi matahari disebut ....', '', '', 'rotasi', '', 'gravitasi', '', 'revolusi', '', 'orbit', '', 'c', '', 10),
(949, 40, 'pg', 'Pahlawan nasional yang mendapat julukan \"Bapak Pendidikan Nasional\" adalah...', '', '', 'Ir. Soekarno', '', 'Ki Hajar Dewantara', '', 'Pangeran Diponegoro', '', 'Jenderal Sudirman', '', 'b', '', 10),
(950, 39, 'pg', 'Hasil dari fotosintesis yang berlebihan akan disimpan oleh tumbuhan. Bagian tumbuhan yang berfungsi menyimpannya ditunjukkan oleh nomor. . .', 'soal_6a12a9ea979e3.jpeg', 'Perhatikan gambar di bawah!', '1', '', '2', '', '3', '', '4', '', 'd', '', 10),
(951, 39, 'pg', 'Tumbuhan menghasilkan oksigen pada siang hari melalui ....', '', '', 'bernapas', '', 'fotosintesis', '', 'berkembang biak', '', 'bergerak', '', 'b', '', 10),
(952, 40, 'pg', 'Fungsi utama dari garis astronomis (garis lintang dan garis bujur) pada sebuah peta adalah untuk...', 'soal_6a12ab23f01b5.jpg', 'Perhatikan gambar di bawah ini!', 'Mengetahui keindahan suatu daerah', '', 'Menentukan letak absolut/pasti suatu tempat di permukaan bumi', '', 'Mengukur ketinggian gunung', '', 'Menghitung jumlah penduduk', '', 'b', '', 10),
(953, 39, 'pg', 'Pernyataan berikut yang sesuai dengan kedua gambar di atas yaitu. . .', 'soal_6a12aacceca23.jpeg', 'Perhatikan gambar di bawah!', 'tempat 1 menghasilkan banyak karbondiksida', '', 'lebih banyak cahaya matahari di tempat 2', '', 'ketersediaan oksigen lebih banyak di tempat 1', '', 'kabohidrat sulit dicari ditempat 2', '', 'c', '', 10),
(954, 39, 'pg', 'Kendaraan yang menghasilkan asap berlebihan dapat menyebabkan ....', '', '', 'udara segar', '', 'pencemaran udara', '', 'hujan deras', '', 'tanah subur', '', 'b', '', 10),
(955, 39, 'pg', 'Contoh hubungan saling menguntungkan adalah ....', '', '', 'benalu pada pohon', '', 'lebah dan bunga', '', 'kucing dan tikus', '', 'ular dan ayam', '', 'b', '', 10),
(956, 40, 'pg', 'Wilayah daratan yang menjorok ke arah laut dinamakan...', '', '', 'Teluk', '', 'Tanjung', '', 'Selat', '', 'Pulau', '', 'b', '', 10),
(957, 39, 'pg', 'Air hujan berasal dari ....', '', '', 'laut yang menguap', '', 'batu', '', 'tanah', '', 'api', '', 'a', '', 10),
(958, 40, 'pg', 'Batu bara merupakan sumber daya alam yang tidak dapat diperbarui karena...', 'soal_6a12ac92c6f4a.jpeg', 'Perhatikan gambar di bawah ini!', 'Proses pembentukannya membutuhkan waktu jutaan tahun', '', 'Jumlahnya di alam sangat melimpah dan tidak terbatas', '', 'Dapat dibuat kembali di laboratorium dalam waktu singkat', '', 'Harganya sangat murah di pasaran', '', 'a', '', 10),
(959, 39, 'pg', 'Tanaman yang tidak memiliki klorofil ditunjukkan oleh nomor...', 'soal_6a12ac5e522d1.jpeg', 'Perhatikan gambar di bawah!', '1 saja', '', '1 dan 4', '', '2 dan 3', '', '2 dan 4', '', 'b', '', 10),
(960, 39, 'pg', 'Gempa bumi dapat terjadi karena ....', '', '', 'angin', '', 'pergeseran lempeng bumi', '', 'panas matahari', '', 'hujan', '', 'b', '', 10),
(961, 40, 'pg', 'Upacara adat \"Rambu Solo\" merupakan upacara pemakaman adat yang sangat terkenal di Indonesia. Upacara ini berasal dari daerah...', '', '', 'Bali', '', 'Tana Toraja', '', 'Yogyakarta', '', 'Papua', '', 'b', '', 10),
(962, 40, 'pg', 'Sikap yang paling tepat dalam menghadapi keragaman budaya seperti yang ditunjukkan pada gambar di atas adalah...', 'soal_6a12aeab8e15b.jpg', 'Perhatikan gambar di bawah ini!', 'Menganggap budaya sendiri sebagai yang paling hebat dan merendahkan budaya lain', '', 'Saling menghormati dan menghargai perbedaan demi persatuan bangs', '', 'Meminta pemerintah menghapus budaya yang minoritas', '', 'Mempelajari budaya asing saja agar terlihat keren', '', 'b', '', 10),
(963, 40, 'pg', 'Kebutuhan manusia yang pemenuhannya dapat ditunda setelah kebutuhan pokok terpenuhi, seperti kebutuhan akan televisi, motor, atau meja belajar, dinamakan kebutuhan...', '', '', 'Primer', '', 'Sekunder', '', 'Tersier', '', 'Mutlak', '', 'b', '', 10),
(964, 39, 'pg', 'Menanam pohon dapat membantu ....', '', '', 'menyebabkan banjir', '', 'menjaga udara tetap bersih', '', 'membuat tanah rusak', '', 'mengurangi oksigen', '', 'b', '', 10),
(965, 40, 'pg', 'Kegiatan menyalurkan barang dari produsen kepada konsumen seperti pada gambar termasuk dalam jenis kegiatan ekonomi...', 'soal_6a12afa2c545f.jpeg', 'Perhatikan gambar di bawah ini!', 'Produksi', '', 'Konsumsi', '', 'Distribusi', '', 'Urbanisasi', '', 'c', '', 10),
(966, 39, 'pg', 'Alat untuk melihat benda langit adalah ....', '', '', 'mikroskop', '', 'stetoskop', '', 'teleskop', '', 'thermometer', '', 'c', '', 10),
(967, 40, 'pg', 'Kerajaan Kutai merupakan kerajaan Hindu tertua di Indonesia. Kerajaan ini terletak di tepi sungai Mahakam, tepatnya di provinsi...', '', '', 'Sumatra Utara', '', 'Kalimantan Timur', '', 'Jawa Barat', '', 'Sulawesi Utara', '', 'b', '', 10),
(968, 40, 'pg', 'Prasasti pada gambar merupakan peninggalan sejarah yang sangat berharga dari Kerajaan...', 'soal_6a12b0c79de41.jpg', 'Perhatikan gambar di bawah ini!', 'Tarumanagara', '', 'Majapahit', '', 'Sriwijaya', '', 'Demak', '', 'a', '', 10),
(969, 40, 'pg', 'Mahapatih Gajah Mada terkenal dengan sumpahnya yang bertekad untuk menyatukan wilayah Nusantara di bawah kekuasaan Kerajaan Majapahit. Sumpah tersebut dikenal dengan nama...', '', '', 'Sumpah Pemuda', '', 'Sumpah Palapa', '', 'Sumpah Setia', '', 'Sumpah Sakti', '', 'b', '', 10),
(970, 40, 'pg', 'Berdasarkan perbandingan kedua peta di atas, pernyataan yang paling benar adalah...', 'soal_6a12b5340d953.jpg', 'Perhatikan gambar di bawah ini!', 'Peta A menampilkan wilayah yang lebih luas tetapi kurang detail dibanding Peta B', '', 'Peta A menampilkan objek yang lebih detail dan rinci dibanding Peta B', '', 'Peta B memiliki tingkat ketelitian gambar yang lebih tinggi dibanding Peta A', '', 'Skala Peta B lebih besar nilainya daripada skala Peta A', '', 'b', '', 10),
(971, 39, 'pg', 'Satrio berangkat sekolah di antar sama keluarganya naik mobil . namun ketika di perjalanan mobilnya mogok. Saat mobil mogok berdasarkan gambar diatas termasuk jenis gaya . . . .', 'soal_6a12b330f06f2.jpeg', 'Perhatikan gambar berikut!', 'pegas', '', 'magnet', '', 'tarikan', '', 'dorongan', '', 'd', '', 10),
(972, 39, 'pg', 'Seorang peserta didik disuruh menggunakan ketapel untuk mengenai buah, gaya yang dimanfaatkan dalam tugas ini adalah…', '', '', 'Gerak', '', 'Magnet', '', 'Otot', '', 'Pegas', '', 'd', '', 10),
(973, 39, 'pg', 'Berdasarkan  gambar di atas Fajar sedang menarik barang  dengan menggunakan ….', 'soal_6a12e6f66a59a.jpeg', 'Perhatikan gambar berikut !', 'Gaya gesek dan gaya otot', '', 'Gaya Tarik  dan gaya magnet', '', 'Gaya magnet dan gaya otot', '', 'Gaya otot dan gaya gravitasi', '', 'a', '', 10),
(974, 39, 'pg', 'Hewan yang berkembang biak dengan bertelur disebut ....', '', '', 'Vivipar', '', 'Ovipar', '', 'Ovovivipar', '', 'Mamalia', '', 'b', '', 10),
(975, 40, 'pg', 'Di daerah dataran tinggi, aktivitas ekonomi masyarakat umumnya berpusat pada sektor perkebunan tanaman tertentu. Jenis tanaman yang paling cocok tumbuh subur di daerah tersebut adalah...', '', '', 'Kelapa, padi, dan bakau', '', 'Teh, kopi, dan sayur-sayuran', '', 'Tebu, kapuk, dan tembakau dataran rendah', '', 'Rumput laut dan sagu', '', 'b', '', 10),
(976, 40, 'pg', 'Upacara penanggulangan lingkungan yang paling efektif untuk mencegah bencana alam seperti pada gambar di atas adalah...', 'soal_6a12b74ce57fd.jpg', 'Perhatikan gambar di bawah ini!', 'Membangun gedung pencakar langit di tepi pantai', '', 'Menanam hutan bakau (mangrove) di sepanjang garis pantai', '', 'Melakukan pengerukan pasir pantai secara besar-besaran', '', 'Membuat tambak udang ilegal', '', 'b', '', 10),
(977, 39, 'pg', 'BerdasarkanTahap metamorposis pada gambar di atas  disebut . . . .', 'soal_6a12b76f1dd4e.jpeg', 'Perhatikan gambar berikut !', 'Berudu', '', 'Kecambah', '', 'Pupa', '', 'Larva', '', 'c', '', 10),
(978, 39, 'pg', 'Manusia bernapas menggunakan ....', '', '', 'paru-paru', '', 'insang', '', 'kulit', '', 'trakea', '', 'a', '', 10),
(979, 39, 'pg', 'Pada metamorfosis tidak sempurna , hewan muda disebut dengan istilah . . . .', '', '', 'Imago', '', 'Larva', '', 'nimfa', '', 'pupa', '', 'c', '', 10),
(980, 40, 'pg', 'Keanekaragaman suku bangsa di Indonesia dipengaruhi oleh banyak faktor fisik lingkungan. Faktor geografis utama yang menyebabkan terbentuknya banyak suku bangsa yang terisolasi dan memiliki budaya berbeda satu sama lain di Indonesia adalah...', '', '', 'Indonesia terletak di jalur perdagangan internasional', '', 'Bentuk wilayah Indonesia yang terdiri dari ribuan pulau (negara kepulauan)', '', 'Indonesia memiliki iklim tropis dengan dua musim', '', 'Adanya penjajahan bangsa asing di masa lalu', '', 'b', '', 10),
(981, 40, 'pg', 'Pekerjaan yang bergerak dalam sektor penyedia jasa ditunjukkan oleh nomor...', 'soal_6a12b8c57c7dc.jpg', 'Perhatikan tabel jenis pekerjaan berikut!', '1 dan 2', '', '2 dan 3', '', '3 dan 4', '', '1 dan 4', '', 'b', '', 10),
(982, 39, 'pg', 'Di sebuah lapangan sekolah, Ani berdiri diam sambil memegang alat penghasil bunyi yang mengeluarkan nada dengan frekuensi tetap 600 Hz. Tiba-tiba, seorang temannya berlari melewati Ani dengan kecepatan 5 m/s sambil mendengarkan suara tersebut. Kecepatan bunyi di udara adalah 340 m/s. Frekuensi bunyi yang didengar teman Ani saat berlari mendekati Ani adalah…..', '', '', 'Lebih kecil dari 600 Hz', '', 'Lebih besar dari 600 Hz', '', 'Sama dengan 600 Hz', '', 'Tidak terdengar sama sekali', '', 'b', '', 10),
(983, 40, 'pg', 'Bapak Koperasi Indonesia yang juga merupakan salah satu tokoh proklamator kemerdekaan Indonesia adalah...', 'soal_6a12b9418a004.png', 'Perhatikan gambar di bawah ini!', 'Ir. Soekarno', '', 'Drs. Mohammad Hatta', '', 'Sutan Sjahrir', '', 'Tan Malaka', '', 'b', '', 10),
(984, 39, 'pg', 'Air pada suhu 37 oC jika dikonversi ke skala Kelvin, Fahrenheit, dan Reaumur adalah….', '', '', 'Kelvin = 98,6 K, Fahrenheit = 300 oF, Reaumur = 29,6', '', 'Kelvin = 310 K, Fahrenheit = 29,6 oF, Reaumur = 98,6', '', 'Kelvin = 310 K, Fahrenheit = 98,6 oF, Reaumur = 29,6', '', 'Kelvin = 29,60 K, Fahrenheit = 98,6 oF, Reaumur = 29,6', '', 'c', '', 10),
(985, 40, 'pg', 'Kerajaan Sriwijaya berkembang menjadi kerajaan maritim yang sangat besar dan menguasai jalur perdagangan di Selat Malaka. Faktor utama yang mendukung Sriwijaya menjadi pusat perdagangan maritim adalah...', '', '', 'Memiliki tanah pertanian yang paling subur di pulau Jawa', '', 'Letaknya yang sangat strategis di jalur pelayaran internasional antara India dan Tiongkok', '', 'Kerajaan tersebut mengisolasi diri dari pedagang asing', '', 'Menghasilkan tambang emas terbesar di dunia', '', 'b', '', 10),
(986, 39, 'pg', 'Pada panel surya terdapat efisiensi yang dinyatakan dalam persentase (%). Makna dari persentase tersebut adalah …', '', '', 'Rasio sel surya yang digunakan terhadap daya listrik yang dihasilkan', '', 'Rasio energi listrik yang dihasilkan terhadap energi matahari yang diterima', '', 'Rasio energi listrik yang diterima terhadap energi matahari yang dihasilkan', '', 'Rasio energi matahari yang diterima terhadap energi listrik yang dihasilkan', '', 'b', '', 10),
(987, 39, 'pg', 'Frekuensi yang dihasilkan oleh audiosonik adalah…..', '', '', '< 20 getaran/detik', '', '> 20.000 getaran/detik', '', '20 – 20.000 getaran/detik', '', '> 20 getaran/detik', '', 'c', '', 10),
(988, 40, 'pg', 'Kompleks candi pada gambar di atas merupakan peninggalan sejarah bercorak... yang dibangun pada masa Kerajaan Mataram Kuno.', 'soal_6a12ba627ffed.jpg', 'Perhatikan gambar di bawah ini!', 'Buddha', '', 'Islam', '', 'Hindu', '', 'Kristen', '', 'c', '', 10),
(989, 40, 'pg', 'Masuknya pengaruh Islam ke Indonesia membawa perubahan dalam sistem pemerintahan, yaitu berubahnya sebutan pemimpin wilayah dari \"Raja\" menjadi...', '', '', 'Sultan', '', 'Kaisar', '', 'Presiden', '', 'Datuk', '', 'a', '', 10),
(990, 40, 'pg', 'Provinsi Papua memiliki kekayaan alam tambang yang sangat terkenal di dunia karena menghasilkan...', 'soal_6a12bb2a831a8.jpg', 'Perhatikan gambar di bawah ini!', 'Aspal alam', '', 'Tembaga dan Emas', '', 'Intan dan Permata', '', 'Gas alam cair (LNG)', '', 'b', '', 10),
(991, 39, 'pg', 'Berdasarkan tabel peristiwa di atas, peristiwa yang menunjukkan ciri-ciri musim gugur ditunjukkan oleh nomor . . . .', 'soal_6a12bbde86ab6.jpeg', 'Perhatikan tabel berikut!', '1 dan 2', '', '2 dan 3', '', '3 dan 4', '', '1 dan 4', '', 'c', '', 10),
(992, 39, 'pg', 'Perubahan air menjadi uap disebut ....', '', '', 'mencair', '', 'membeku', '', 'menguap', '', 'mengembun', '', 'c', '', 10),
(993, 39, 'pg', 'Lina menarik kereta mainan dengan benang. Ia menariknya dengan gaya konstan selama 3 detik di lantai licin. Selama itu, kereta bergerak semakin cepat. Apa yang dapat disimpulkan tentang energi dan gaya yang bekerja?', '', '', 'Gaya hanya bekerja di awal, lalu berhenti', '', 'Kereta dipercepat karena gaya tarik lebih besar dari gaya gesek', '', 'Energi kinetik tetap karena gaya tarik konstan', '', 'Kereta melambat karena energi potensial berubah', '', 'b', '', 10),
(994, 39, 'pg', 'Apa sumber energi utama pada gambar tersebut?', 'soal_6a12be50efb67.jpeg', 'Perhatikan gambar dibawah ini!', 'Air', '', 'Tanah', '', 'Matahari', '', 'Angin', '', 'c', '', 10),
(995, 39, 'pg', 'Bunyi dihasilkan oleh benda yang ....', '', '', 'diam', '', 'berputar', '', 'bergetar', '', 'tenggelam', '', 'c', '', 10),
(996, 44, 'pg', 'Berikut adalah data banyaknya jawaban benar dari 10 siswa yang mengikuti kompetisi matematika SD: 10, 12, 8, 15, 17, 18, 20, 22, 14, 24. Rata-rata banyaknya jawaban benar dari 10 siswa tersebut adalah ...', '', '', '14', '', '16', '', '18', '', '20', '', 'b', '', 10),
(997, 44, 'pg', 'Dinda memiliki tiga kotak berisi buku tulis. Kotak pertama berisi 18 buku tulis. Kotak kedua berisi empat kali dari isi kotak pertama, dan kotak ketiga berisi setengah dari isi kotak kedua. Banyak buku tulis yang dimiliki Dinda dari ketiga kotak tersebut adalah…', '', '', '108', '', '126', '', '144', '', '162', '', 'b', '', 10),
(998, 44, 'pg', 'Jika 40% dari suatu bilangan adalah 120, maka 15% dari bilangan tersebut adalah …', '', '', '35', '', '45', '', '50', '', '60', '', 'b', '', 10),
(999, 44, 'pg', 'Adit berbelanja di toko alat-alat tulis membeli beberapa barang yaitu delapan buku tulis, enam pensil, dua penggaris, dan satu kotak pensil. Harga satu buku Rp6.000,00; satu pensil atau satu penggaris Rp2.500,00; dan satu kotak pensil Rp40.000,00. Jika toko memberikan diskon 15% untuk buku dan 10% untuk kotak pensil, maka jumlah yang harus dibayar oleh Adit adalah ...', '', '', 'Rp92.600', '', 'Rp96.800', '', 'Rp98.400', '', 'Rp102.000', '', 'b', '', 10),
(1000, 44, 'pg', 'FPB dari 72, 108, dan 144 adalah…', '', '', '12', '', '18', '', '24', '', '36', '', 'd', '', 10),
(1001, 44, 'pg', 'KPK dari 14, 21, dan 28 adalah…', '', '', '42', '', '56', '', '84', '', '126', '', 'c', '', 10),
(1002, 44, 'pg', 'Luas bangun datar dibawah ini adalah….', 'soal_6a12c84e66e97.jpeg', 'Perhatikan gambar berikut!', '108', '', '112', '', '196', '', '308', '', 'd', '', 10),
(1003, 44, 'pg', 'Hasil dari 1.368 : 36 × 12 adalah…', '', '', '396', '', '420', '', '456', '', '480', '', 'c', '', 10),
(1004, 44, 'pg', 'Data hasil panen mangga dari tiga kebun milik Pak Hasan disajikan pada diagram batang berikut.', 'soal_6a12c90ab32ab.jpeg', 'Perhatikan gambar dibawah ini!', 'rata-rata hasil panen dari ketiga kebun adalah 35 kg', '', 'selisih hasil panen kebun C dan kebun A adalah 20 kg', '', 'median dari data hasil panen adalah 35 kg.', '', 'jumlah seluruh hasil panen adalah 100 kg.', '', 'c', '', 10),
(1005, 44, 'pg', 'Volume kubus tersebut adalah…', 'soal_6a12c99400950.jpeg', 'Perhatikan gambar berikut ini!', '512 cm', '', '384 cm', '', '256 cm', '', '640 cm', '', 'a', '', 10),
(1006, 44, 'pg', 'Suhu udara di Pegunungan Himalaya tanggal 10 Juni 2026 pukul 05.00 mencapai −35°C. Dua jam kemudian suhu udara mengalami kenaikan sebesar 4 derajat Celsius dan dua jam kemudian suhu mengalami penurunan sebesar 5 derajat Celsius. Pernyataan yang tepat adalah…', '', '', 'pukul 07.00 mencapai −39°C.', '', 'pukul 09.00 mencapai −36°C.', '', 'pukul 07.00 mencapai −29°C.', '', 'pukul 09.00 mencapai −26°C.', '', 'b', '', 10),
(1007, 44, 'pg', 'Jika P adalah 3 cm2, maka luas daerah ABCDEFGH adalah …. cm2', '', 'Perhatikan gambar dibawah ini!', '60', '', '40', '', '36', '', '72', '', 'a', '', 10),
(1008, 44, 'pg', 'Lisa membeli 3 3/4 kg ikan mas, 4 1/2 kg ikan mujaer, dan 6 3/4 kg ikan nila. Berat\r\nikan yang dibeli Lisa adalah...', '', '', '15 ons', '', '150 ons', '', '1.500 ons', '', '15.000 ons', '', 'b', '', 10),
(1009, 44, 'pg', 'Seorang pedagang roti membawa 80 roti untuk dijual. Harga beli setiap roti adalah Rp1.000,00. Pedagang menjual 50 roti dengan harga Rp2.000,00 per roti. Sisa roti dijual dengan harga Rp1.500,00 per roti, tetapi masih tersisa 10 roti yang tidak terjual. Pernyataan yang benar adalah…', '', '', 'Pedagang untung Rp50.000,00', '', 'Pedagang untung Rp25.000,00', '', 'Pedagang rugi Rp10.000,00', '', 'Pedagang tidak untung dan tidak rugi', '', 'a', '', 10),
(1010, 44, 'pg', 'Nina ingin membeli sebuah sepeda seharga Rp3.600.000,00. Untuk membeli sepeda tersebut, Nina menyisihkan sebagian dari uang jajannya sebesar Rp25.000,00 setiap hari sekolah. Jika Nina masuk sekolah rata-rata 20 hari setiap bulan, berapa uang yang harus ditabung Nina setiap hari agar dapat membeli sepeda setelah 12 bulan?', '', '', 'A. Rp12.500,00', '', 'Rp15.000,00', '', 'Rp16.000,00', '', 'Rp18.000,00', '', 'b', '', 10),
(1011, 44, 'pg', 'Adalah …', 'soal_6a12cc0c2731f.jpeg', 'Median dari data berikut:', '0,5', '', '0,625', '', '0,75', '', '1', '', 'b', '', 10),
(1012, 44, 'pg', 'Andi berlibur ke rumah neneknya. Dia berangkat dari rumah pada pukul 5.15 menggunakan bus, dan sampai di rumah neneknya pada pukul 12.10. Jika dalam perjalanan bus yang ditumpanginya berhenti selama 30 menit untuk beristirahat, maka lamanya perjalanan yang ditempuh Andi jika busnya tidak berhenti adalah ...', '', '', '5 jam 25 menit', '', '5 jam 45 menit', '', '6 jam 25 menit', '', '6 jam 55 menit', '', 'c', '', 10),
(1013, 44, 'pg', 'Setiap hari Rani belajar di sekolah mulai pukul 07.00 sampai pukul 12.00. Waktu istirahat dilakukan 2 kali, masing-masing selama 10 menit. Jika dalam satu hari ada 6 jam pelajaran, maka lama 1 jam pelajaran adalah .... menit.', '', '', '40 menit', '', '45 menit', '', '50 menit', '', '60 menit', '', 'b', '', 10),
(1014, 44, 'pg', 'Berapa persentase siswa yang belajar lebih dari 4 jam sehari?', 'soal_6a12cce0df2e2.jpeg', 'Sebuah tabel frekuensi menunjukkan jumlah jam belajar per hari untuk siswa kelas lima. Berikut adalah data dalam tabel:', '20%', '', '25%', '', '30%', '', '35%', '', 'd', '', 10),
(1015, 44, 'pg', 'Gambar berpola di atas terbentuk dari beberapa persegi. Banyak persegi pada gambar ke-7 adalah…', 'soal_6a12d0746820f.jpeg', 'Perhatikan gambar berikut!', '21', '', '25', '', '29', '', '46', '', 'b', '', 10),
(1016, 44, 'pg', 'Perbandingan alas dan tinggi suatu segitiga adalah 3 : 4. Jika luas segitiga tersebut 864 cm², maka panjang alas segitiga adalah … cm.', '', '', '24', '', '30', '', '36', '', '48', '', 'c', '', 10),
(1017, 44, 'pg', 'Perhatikan sifat-sifat bangun datar berikut!\r\ni. mempunyai tiga sisi sama panjang\r\nii. mempunyai tiga simetri lipat\r\niii. mempunyai tiga sudut sama besar\r\niv. besar ketiga sudutnya 90°\r\nSifat-sifat segitiga sama sisi ditunjukkan oleh….', '', '', 'i dan ii', '', 'i dan iii', '', 'ii dan iv', '', 'iii dan iv', '', 'b', '', 10),
(1018, 44, 'pg', 'Hasil dari 5 jam – 175 menit + 3.600 detik adalah….', '', '', '125 menit', '', '145 menit', '', '185 menit', '', '205 menit', '', 'c', '', 10),
(1019, 44, 'pg', 'Ada berapa rute berbeda yang dapat ditempuh oleh seseorang yang berangkat dari M ke N?', 'soal_6a12d207ca91d.jpeg', 'Perhatikan gambar berikut ini!', '9', '', '8', '', '7', '', '6', '', 'd', '', 10),
(1020, 44, 'pg', 'Hasil dari -6 x (15-9) + 24 : (8-2) – 5 adalah...', '', '', '-37', '', '-39', '', '-41', '', '-43', '', 'a', '', 10),
(1021, 44, 'pg', 'Pak Ahmad memiliki sebidang tanah berbentuk persegi panjang dengan ukuran 40 m × 24 m. Tanah tersebut akan dibuat kolam ikan berbentuk lingkaran dengan diameter 14 m. Sisa tanah yang tidak dibuat kolam akan ditanami rumput. Luas tanah yang ditanami rumput adalah… m² (π = 22/7)', '', '', '806', '', '816', '', '826', '', '836', '', 'a', '', 10),
(1022, 44, 'pg', 'Dalam suatu kompetisi, skor akhir dihitung dengan aturan: jawaban benar +4, salah –1, tidak dijawab 0. Andi menjawab 40 soal, mendapat skor 125. Berapa banyak soal yang dijawab benar oleh Andi?', '', '', '30', '', '32', '', '33', '', '35', '', 'c', '', 10),
(1023, 44, 'pg', 'Perhatikan pola bilangan berikut:\r\n2, 6, 12, 20, 30, …\r\nDua suku berikutnya adalah…', '', '', '42, 56', '', '42, 60', '', '40, 52', '', '40, 54', '', 'a', '', 10),
(1024, 44, 'pg', 'Dalam suatu kelas, 18 siswa suka matematika, 15 siswa suka IPA, dan 8 siswa suka keduanya. Jika jumlah siswa seluruhnya 30, maka banyak siswa yang tidak suka matematika maupun IPA adalah…', '', '', '3', '', '4', '', '5', '', '6', '', 'c', '', 10),
(1025, 44, 'pg', 'Seekor ayam menghabiskan 1,5 kg pakan dalam 5 hari. Jika tersedia 12 kg pakan, berapa hari pakan tersebut akan habis untuk 4 ekor ayam?', '', '', '8 hari', '', '10 hari', '', '12 hari', '', '15 hari', '', 'b', '', 10);

-- --------------------------------------------------------

--
-- Struktur dari tabel `token_ujian`
--

CREATE TABLE `token_ujian` (
  `id` int(11) NOT NULL,
  `token` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `keterangan` varchar(100) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `token_ujian`
--

INSERT INTO `token_ujian` (`id`, `token`, `tanggal`, `jam_mulai`, `jam_selesai`, `keterangan`, `status`, `created_at`) VALUES
(1, 'OSN2026', '2026-05-24', '09:00:00', '22:00:00', NULL, 'aktif', '2026-05-24 12:24:32'),
(2, 'BTGOSN2026', '2026-05-25', '09:00:00', '10:15:00', NULL, 'aktif', '2026-05-24 12:26:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian`
--

CREATE TABLE `ujian` (
  `id` int(11) NOT NULL,
  `peserta_id` int(11) NOT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `nilai` int(11) DEFAULT NULL,
  `token_id` int(11) DEFAULT NULL,
  `jadwal_id` int(11) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `soal_order` text DEFAULT NULL,
  `pelanggaran` int(11) NOT NULL DEFAULT 0,
  `last_activity` datetime DEFAULT NULL,
  `cache_version` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'sekolah',
  `kelas_diampu` varchar(20) DEFAULT NULL,
  `sekolah_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `nama_lengkap`, `foto_profil`, `password`, `role`, `kelas_diampu`, `sekolah_id`) VALUES
(1, 'admin', 'Imam Nurmana', 'assets/uploads/profil/profil_1_1779203044.jpeg', '$2y$10$bexgP4Imawk.2yCCM/6xguh03BmfN.hJ2/g7KaV2Zt0LX3hrhq5KK', 'admin_kecamatan', NULL, NULL),
(21, 'korektor', 'Tim Korektor', NULL, '$2y$10$jQMD2sB5C8.1d2RM0So5qOEy8JFWNHJfQbWo2tHqnvt.ic4k5kSNK', 'korektor', NULL, NULL),
(28, 'ipa', NULL, NULL, '$2y$10$3A3nfdVJ86lkF8ROUkh.Du55fDbKGZ97AdbFJPWos/WcsvziGwCpO', 'sekolah', NULL, 22),
(29, 'ips', NULL, NULL, '$2y$10$9KuYqseRBMkTzAumrdSFkumxDrN5JdAiDEvT6SQIFe6GqD8UVyo66', 'sekolah', NULL, 23),
(31, 'mtk', NULL, NULL, '$2y$10$cTMAH3mX/NcyJx176FsQtukYvIkmxg0HEcpeyPrZHuJxVz2VEbx5q', 'sekolah', NULL, 25);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indeks untuk tabel `backup_history`
--
ALTER TABLE `backup_history`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `export_log`
--
ALTER TABLE `export_log`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hasil_ujian` (`ujian_id`,`peserta_id`),
  ADD KEY `idx_hasil_peserta` (`peserta_id`),
  ADD KEY `idx_hasil_jadwal` (`jadwal_id`),
  ADD KEY `idx_hasil_nilai` (`nilai`),
  ADD KEY `idx_hasil_peserta_jadwal` (`peserta_id`,`jadwal_id`),
  ADD KEY `idx_hasil_kategori_nilai` (`kategori_id`,`nilai`),
  ADD KEY `idx_hu_dinilai` (`essay_dinilai`);

--
-- Indeks untuk tabel `jadwal_ujian`
--
ALTER TABLE `jadwal_ujian`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `jawaban`
--
ALTER TABLE `jawaban`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ujian_peserta_soal` (`ujian_id`,`peserta_id`,`soal_id`),
  ADD KEY `fk_jawaban_soal` (`soal_id`),
  ADD KEY `idx_jawaban_peserta` (`peserta_id`),
  ADD KEY `idx_jawaban_ujian_soal` (`ujian_id`,`soal_id`),
  ADD KEY `idx_jaw_skor` (`skor_essay`);

--
-- Indeks untuk tabel `kategori_soal`
--
ALTER TABLE `kategori_soal`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_waktu` (`waktu`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_key` (`setting_key`);

--
-- Indeks untuk tabel `peserta`
--
ALTER TABLE `peserta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kode_peserta` (`kode_peserta`),
  ADD KEY `fk_peserta_sekolah` (`sekolah_id`),
  ADD KEY `idx_peserta_sekolah_kelas` (`sekolah_id`,`kelas`),
  ADD KEY `idx_pes_kelas` (`kelas`);

--
-- Indeks untuk tabel `rate_limit`
--
ALTER TABLE `rate_limit`
  ADD PRIMARY KEY (`rl_key`);

--
-- Indeks untuk tabel `sekolah`
--
ALTER TABLE `sekolah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sekolah_jenjang` (`jenjang`);

--
-- Indeks untuk tabel `soal`
--
ALTER TABLE `soal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_soal_kategori` (`kategori_id`),
  ADD KEY `idx_soal_kategori_tipe` (`kategori_id`,`tipe_soal`),
  ADD KEY `idx_soal_tipe` (`tipe_soal`);

--
-- Indeks untuk tabel `token_ujian`
--
ALTER TABLE `token_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`);

--
-- Indeks untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ujian_peserta` (`peserta_id`),
  ADD KEY `idx_ujian_peserta_selesai` (`peserta_id`,`waktu_selesai`),
  ADD KEY `idx_ujian_status` (`waktu_selesai`,`waktu_mulai`),
  ADD KEY `idx_ujian_peserta_jadwal` (`peserta_id`,`jadwal_id`),
  ADD KEY `idx_ujian_jadwal` (`jadwal_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_username` (`username`),
  ADD KEY `fk_users_sekolah` (`sekolah_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `backup_history`
--
ALTER TABLE `backup_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT untuk tabel `export_log`
--
ALTER TABLE `export_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `jadwal_ujian`
--
ALTER TABLE `jadwal_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `jawaban`
--
ALTER TABLE `jawaban`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT untuk tabel `kategori_soal`
--
ALTER TABLE `kategori_soal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1325;

--
-- AUTO_INCREMENT untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=956;

--
-- AUTO_INCREMENT untuk tabel `peserta`
--
ALTER TABLE `peserta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=443;

--
-- AUTO_INCREMENT untuk tabel `sekolah`
--
ALTER TABLE `sekolah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `soal`
--
ALTER TABLE `soal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1026;

--
-- AUTO_INCREMENT untuk tabel `token_ujian`
--
ALTER TABLE `token_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `ujian`
--
ALTER TABLE `ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `hasil_ujian`
--
ALTER TABLE `hasil_ujian`
  ADD CONSTRAINT `fk_hasil_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hasil_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jawaban`
--
ALTER TABLE `jawaban`
  ADD CONSTRAINT `fk_jawaban_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jawaban_soal` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jawaban_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `peserta`
--
ALTER TABLE `peserta`
  ADD CONSTRAINT `fk_peserta_sekolah` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `soal`
--
ALTER TABLE `soal`
  ADD CONSTRAINT `fk_soal_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_soal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ujian`
--
ALTER TABLE `ujian`
  ADD CONSTRAINT `fk_ujian_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_sekolah` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
