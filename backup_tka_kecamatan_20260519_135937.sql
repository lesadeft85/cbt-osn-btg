-- TKA Kecamatan Database Backup
-- Tanggal: 2026-05-19 13:59:37
-- Server:  127.0.0.1
-- Database: tka_kecamatan

SET FOREIGN_KEY_CHECKS=0;

-- -----------------------------------------------
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `backup_history`;
CREATE TABLE `backup_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ukuran` int DEFAULT '0',
  `dibuat_oleh` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_history` VALUES
('2','backup_tka_kecamatan_20260325_073409.sql','47051','admin','2026-03-25 07:34:09'),
('5','backup_tka_kecamatan_20260407_151821.sql','95944','admin','2026-04-07 15:18:22'),
('6','autobackup_20260416.sql','45073','auto-daily','2026-04-16 06:08:05'),
('7','autobackup_20260417.sql','45073','auto-daily','2026-04-17 07:56:43'),
('8','autobackup_20260420.sql','45029','auto-daily','2026-04-20 07:13:57'),
('9','autobackup_20260421.sql','8505','auto-daily','2026-04-21 04:34:05'),
('10','autobackup_20260426.sql','9363','auto-daily','2026-04-26 07:32:13'),
('11','autobackup_20260427.sql','9363','auto-daily','2026-04-27 10:28:21'),
('12','autobackup_20260428.sql','9864','auto-daily','2026-04-28 04:15:37'),
('13','autobackup_20260429.sql','10372','auto-daily','2026-04-29 06:41:52'),
('14','autobackup_20260430.sql','11563','auto-daily','2026-04-30 05:52:56'),
('23','autobackup_20260506.sql','19795','auto-daily','2026-05-06 06:44:19'),
('24','autobackup_20260507.sql','17660','auto-daily','2026-05-07 03:34:23'),
('32','backup_tka_kecamatan_20260512_081833.sql','59650','admin','2026-05-12 08:18:33'),
('33','autobackup_20260512.sql','43341','auto-daily','2026-05-12 08:20:51'),
('34','autobackup_20260517.sql','44208','auto-daily','2026-05-17 08:53:09'),
('35','autobackup_20260519.sql','43147','auto-daily','2026-05-19 13:58:26');

-- -----------------------------------------------
DROP TABLE IF EXISTS `export_log`;
CREATE TABLE `export_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipe` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'soal',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `dibuat_oleh` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `hasil_ujian`;
CREATE TABLE `hasil_ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ujian_id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `jadwal_id` int DEFAULT NULL,
  `kategori_id` int DEFAULT NULL,
  `total_soal` int NOT NULL DEFAULT '0',
  `jml_benar` int NOT NULL DEFAULT '0',
  `jml_salah` int NOT NULL DEFAULT '0',
  `jml_kosong` int NOT NULL DEFAULT '0',
  `nilai` decimal(6,2) NOT NULL DEFAULT '0.00',
  `ada_essay` tinyint(1) NOT NULL DEFAULT '0',
  `essay_dinilai` tinyint(1) NOT NULL DEFAULT '0',
  `nilai_essay` decimal(6,2) DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_detik` int DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hasil_ujian` (`ujian_id`,`peserta_id`),
  KEY `idx_hasil_peserta` (`peserta_id`),
  KEY `idx_hasil_jadwal` (`jadwal_id`),
  KEY `idx_hasil_nilai` (`nilai`),
  KEY `idx_hasil_peserta_jadwal` (`peserta_id`,`jadwal_id`),
  KEY `idx_hasil_kategori_nilai` (`kategori_id`,`nilai`),
  KEY `idx_hu_dinilai` (`essay_dinilai`),
  CONSTRAINT `fk_hasil_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hasil_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `jadwal_ujian`;
CREATE TABLE `jadwal_ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `durasi_menit` int NOT NULL DEFAULT '60',
  `kategori_id` int DEFAULT NULL,
  `keterangan` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'aktif',
  `tampil_pembahasan` tinyint(1) DEFAULT '0' COMMENT '0=global, 1=tampilkan, 2=sembunyikan',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `jumlah_soal` int DEFAULT NULL COMMENT 'Override jumlah soal global; NULL = pakai pengaturan global',
  `kelas_diizinkan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Kosong = semua kelas; isi cth: 5,6 untuk kelas tertentu',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `jawaban`;
CREATE TABLE `jawaban` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ujian_id` int NOT NULL,
  `peserta_id` int NOT NULL,
  `soal_id` int NOT NULL,
  `jawaban` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `teks_jawaban` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `skor_essay` decimal(5,2) DEFAULT NULL,
  `dinilai_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ujian_peserta_soal` (`ujian_id`,`peserta_id`,`soal_id`),
  KEY `fk_jawaban_soal` (`soal_id`),
  KEY `idx_jawaban_peserta` (`peserta_id`),
  KEY `idx_jawaban_ujian_soal` (`ujian_id`,`soal_id`),
  KEY `idx_jaw_skor` (`skor_essay`),
  CONSTRAINT `fk_jawaban_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jawaban_soal` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jawaban_ujian` FOREIGN KEY (`ujian_id`) REFERENCES `ujian` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `kategori_soal`;
CREATE TABLE `kategori_soal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `kategori_soal` VALUES
('34','Pendidikan Agama Islam Kelas VI'),
('35','Bahasa Indonesia');

-- -----------------------------------------------
DROP TABLE IF EXISTS `log_aktivitas`;
CREATE TABLE `log_aktivitas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `username` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `aktivitas` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_waktu` (`waktu`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `log_aktivitas` VALUES
('1','1','admin','Reset Total Ujian','Semua data jawaban, ujian, hasil_ujian, dan token dihapus','192.168.1.18','2026-05-17 10:06:20'),
('2','1','admin','Logout','Keluar dari sistem','192.168.1.18','2026-05-17 10:06:52'),
('3','1','admin','Login','Berhasil login sebagai admin_kecamatan','192.168.1.18','2026-05-17 11:12:53'),
('4','1','admin','Login','Berhasil login sebagai admin_kecamatan','192.168.1.39','2026-05-17 17:29:33'),
('5','1','admin','Logout','Keluar dari sistem','192.168.1.39','2026-05-17 17:30:00'),
('6','1','admin','Login','Berhasil login sebagai admin_kecamatan','::1','2026-05-19 13:57:48'),
('7','1','admin','Login','Berhasil login sebagai admin_kecamatan','::1','2026-05-19 13:58:11'),
('8','1','admin','Backup Otomatis','Backup harian: autobackup_20260519.sql','::1','2026-05-19 13:58:26'),
('9','1','admin','Login','Berhasil login sebagai admin_kecamatan','127.0.0.1','2026-05-19 13:59:13');

-- -----------------------------------------------
DROP TABLE IF EXISTS `pengaturan`;
CREATE TABLE `pengaturan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `setting_val` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `keterangan` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=633 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pengaturan` VALUES
('1','nama_aplikasi','ASESMEN SUMATIF AKHIR SEMESTER','Nama aplikasi yang tampil di header'),
('2','nama_kecamatan','Senen','Nama kecamatan penyelenggara'),
('3','durasi_ujian','60','Durasi ujian default (menit)'),
('4','jumlah_soal','20','Jumlah soal per sesi ujian'),
('5','maks_pelanggaran','3','Maks pindah tab sebelum ujian diakhiri'),
('6','display_info','Selamat datang di Ujian TKA (Tes Kemampuan Akademik)','Teks info di layar tunggu'),
('7','display_video_url','','URL video untuk layar tunggu (YouTube embed)'),
('8','nama_penyelenggara','SDN KENARI 01',NULL),
('9','mata_pelajaran','CBT Online',NULL),
('10','tampil_pembahasan','1',NULL),
('20','logo_url','',NULL),
('71','kkm','60','Nilai KKM minimum lulus'),
('72','ujian_ulang','0','Izinkan peserta ujian ulang (0=tidak, 1=ya'),
('86','logo_file_path','assets/uploads/logo/logo_1774008807.png',NULL),
('187','tahun_pelajaran','2026/2027','Tahun pelajaran aktif'),
('202','tahun_ajaran','2025/2026','Tahun ajaran aktif'),
('386','pembahasan_mode','langsung','Kapan pembahasan ditampilkan: langsung / setelah_semua_selesai'),
('388','jenjang_default','SD','Jenjang default saat tambah sekolah baru'),
('448','wa_api_key','',NULL),
('449','wa_sender','',NULL),
('450','wa_auto_send','0',NULL),
('451','pesan_maintenance','Sistem sedang dalam pemeliharaan. Silakan tunggu.',NULL),
('452','wa_share_hasil','1',NULL),
('453','mode_maintenance','0',NULL),
('454','acak_pilihan','0',NULL),
('476','dev_nama','Cahyana Wijaya','Nama pengembang'),
('477','dev_role','Fullstack Developer','Role pengembang'),
('478','dev_bio','Berfokus pada pengembangan sistem informasi yang efisien dan solusi digital berbasis web untuk mendukung kemajuan teknologi di sektor pendidikan.','Bio pengembang'),
('479','dev_email','mrkuncen89@gmail.com','Email pengembang'),
('480','dev_wa','6287781743048','WhatsApp pengembang'),
('481','dev_tiktok','@mrkuncen','TikTok pengembang'),
('482','dev_foto','','Foto pengembang'),
('483','dev_skills','PHP 8,MySQL,Bootstrap,CBT System,Data Export','Skills pengembang'),
('631','update_check_cache','{\"ts\":1779173953,\"data\":{\"version\":\"1.0.9\",\"release_date\":\"2026-05-16\",\"download_url\":\"https:\\/\\/github.com\\/mrkuncen89-ui\\/CBT-TKA-Kecamatan\\/releases\\/latest\\/download\\/TKAKecamatan_Setup.exe\",\"changelog\":\"Rilis pertama CBT TKA Kecamatan\",\"min_version\":\"1.0.0\"}}',NULL);

-- -----------------------------------------------
DROP TABLE IF EXISTS `peserta`;
CREATE TABLE `peserta` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `kelas` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sekolah_id` int NOT NULL,
  `kode_peserta` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kode_peserta` (`kode_peserta`),
  KEY `fk_peserta_sekolah` (`sekolah_id`),
  KEY `idx_peserta_sekolah_kelas` (`sekolah_id`,`kelas`),
  KEY `idx_pes_kelas` (`kelas`),
  CONSTRAINT `fk_peserta_sekolah` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=230 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `peserta` VALUES
('71','ABHIZAR AZZAM SOPIAN','VI A','20','TKA4C4569'),
('72','ABID AQILA PRANAJA','VI A','20','TKA5B49A3'),
('73','Adelia Zahra Rohmadon','VI A','20','TKA846D7B'),
('74','ADELLIA TALITA ZAHRA','VI A','20','TKAA19B31'),
('75','ADILAH NOVIANTI','VI A','20','TKA2A1EDC'),
('76','ADZKIYYA KHAIRANI','VI A','20','TKAD99CD6'),
('77','AGUSTINA SETIA RUMANAH','VI A','20','TKAD3F692'),
('78','Cahaya Kayla Qhummahiroh','VI A','20','TKA7E9434'),
('79','Ezio Ali Gazaele','VI A','20','TKA89E625'),
('80','GIOVANO GIAT NUGRAHA','VI A','20','TKAA7E4C4'),
('81','IRAS APRILIO','VI A','20','TKA7AEB6D'),
('82','KHAFI ALFIZAR','VI A','20','TKA3229BB'),
('83','KHANZA JULIANA FATHIN','VI A','20','TKA7C607E'),
('84','MARIO HAFIZH FERDIANSYAH','VI A','20','TKADE69A4'),
('85','muhamad rifki','VI A','20','TKA00C91E'),
('86','MUHAMMAD AUFAR FAIQ','VI A','20','TKA551212'),
('87','MUHAMMAD FARREL RAMADHAN','VI A','20','TKA0CE45E'),
('88','MUHAMMAD IQNHU IQBAL','VI A','20','TKA61D874'),
('89','Muhammad Ridwan','VI A','20','TKAC19907'),
('90','NATASYA AZHA KUSHARIANI','VI A','20','TKAAA0BBA'),
('91','RADJA PRATAMA','VI A','20','TKA597CA7'),
('92','RESTU ANUGRAH PUTRA','VI A','20','TKA976A2F'),
('93','REVAL MAHESA WIDJAYA','VI A','20','TKADE57F7'),
('94','Saffa Hazira Azzahra','VI A','20','TKA46CBA8'),
('95','Safwan Pradipta Syahrul','VI A','20','TKA4768FF'),
('96','Siti Assifa','VI A','20','TKA6F494D'),
('97','YASMINE FEBRIYANI','VI A','20','TKA0AE46E'),
('98','ADRIEL PUTRA RIZKY','VI B','20','TKA6259E3'),
('99','AHMAD FATHUR ROHMAN','VI B','20','TKAEC09EC'),
('100','AHMAD SAHILY YASRI','VI B','20','TKA7C7501'),
('101','Akifa Naila Hakim','VI B','20','TKAB32B6F'),
('102','ALIFA JIHAN AZALIA','VI B','20','TKAF9E607'),
('103','ALIQHA KANZHA AZZURA','VI B','20','TKA639EF8'),
('104','ALISHA SAHIRAH','VI B','20','TKAA9510B'),
('105','Azeema Azahra','VI B','20','TKAC99AA0'),
('106','AZRIL LIHAN SAPUTRA','VI B','20','TKA93DBEF'),
('107','ENZO IBRAHIM GUMELAR','VI B','20','TKA4647AB'),
('108','FABIAN RAMADHAN','VI B','20','TKAD1E8CA'),
('109','FANISA ADWA FAKHIRAH','VI B','20','TKA2C066B'),
('110','HADI RAHMADI','VI B','20','TKA85E530'),
('111','KANAYA SALSABILA','VI B','20','TKA25B743'),
('112','KHAYYIRAH ANSARDY','VI B','20','TKA6D3928'),
('113','MOCHAMAD ATHALA SYAUQI','VI B','20','TKA610B5B'),
('114','Muhammad Bilal Ramadhan','VI B','20','TKA2A5B1B'),
('115','MUHAMMAD GIBRAN MUBARROQ','VI B','20','TKAF0E6B0'),
('116','MUHAMMAD IRZA RAISULFI','VI B','20','TKA7276B9'),
('117','NAWARTI AYAMI ANDRIANA SAPUTRA','VI B','20','TKADEA6BB'),
('118','RAFFA KHADAFI','VI B','20','TKA4AFFF5'),
('119','RHANI NUR HESTA MARTINAH','VI B','20','TKAB1C6B1'),
('120','RIZKY RAMADHAN','VI B','20','TKACE3F0C'),
('121','Salsa Novelis','VI B','20','TKAAF3F4B'),
('122','TARRA RADITHYA APRIYANTO','VI B','20','TKA57A2FE'),
('123','YUDITA APRILIA','VI B','20','TKA55E543'),
('124','ABDUL MUHIB SYAHREZA','VI C','20','TKA2DF9E1'),
('125','ALBANA HAIKAL WAHNU SALAM','VI C','20','TKADD6217'),
('126','AMANDA PUTRI MARLISSA','VI C','20','TKAC123A1'),
('127','ANANDA FEBBRIYANTI FAUZI','VI C','20','TKAC21A10'),
('128','ANNISA ALHAQ','VI C','20','TKA2EF90E'),
('129','ANNISA NUR AINI','VI C','20','TKAA4F9A0'),
('130','Azkiya Abinaya','VI C','20','TKAB1C615'),
('131','CELINE NAVISAH','VI C','20','TKA668CD2'),
('132','CHILLA ANANDA PUTRI','VI C','20','TKA185A39'),
('133','DAMAR HAQQI ALVARO','VI C','20','TKAF23F9E'),
('134','DHEVIN MUHAMMAD OZIEL ALVAREZ','VI C','20','TKAF802DC'),
('135','FAJRUL ALFARIZAN','VI C','20','TKA2C3FEE'),
('136','KAYLA SYAHVIRANI','VI C','20','TKA0D142B'),
('137','KINANTI AULYA PUTRI','VI C','20','TKAA8650D'),
('138','MOCHAMMAD FIQRI RAMADHAN','VI C','20','TKAB3F27F'),
('139','MUHAMMAD DAFFA PRATAMA','VI C','20','TKA821281'),
('140','MUHAMMAD HAEKAL AZZAMY','VI C','20','TKA008F30'),
('141','MUHAMMAD KAHFI CANTONA','VI C','20','TKAA54824'),
('142','PRABU RAFIANDRA','VI C','20','TKA386600'),
('143','Rafka Adhyastha','VI C','20','TKAB0C3BA'),
('144','RAISA JASMINE FADILLAH','VI C','20','TKA5F21DA'),
('145','RIZQY RAMADHAN','VI C','20','TKAB3A4D5'),
('146','SELLKA KAYLA SYARIF','VI C','20','TKA7BCA86'),
('147','Tegar Andryansyah','VI C','20','TKA119A30'),
('148','WISNU KAKA PUTRA PRATAMA','VI C','20','TKA218AEA'),
('149','aliya khaira ruhiyat','VI D','20','TKAA4F2F1'),
('150','alvaro raditya anwar','VI D','20','TKA78AFE9'),
('151','ALZEVAN AFANDI','VI D','20','TKAC1F22A'),
('152','andi nadhifa argys','VI D','20','TKACC3C6E'),
('153','AQILAH DITHA SALSABILLAH','VI D','20','TKAF65ECF'),
('154','Ariest Hermawan','VI D','20','TKA99F59B'),
('155','ASROB HOIRUL AZZEM','VI D','20','TKAC433AF'),
('156','Asyfha Lany Paramitha','VI D','20','TKAFE7ACC'),
('157','Aurelia Arza Putri','VI D','20','TKA893646'),
('158','azriel ilham lesmana','VI D','20','TKAF58583'),
('159','BILLAL CHAEDAR ALIEF','VI D','20','TKA342C8B'),
('160','CINTA MEDIFA UTOMO','VI D','20','TKA92A4AD'),
('161','FADHILATUL AZIZAH','VI D','20','TKACC617F'),
('162','Faqih Fahreza Hakim','VI D','20','TKA426AA2'),
('163','FATIN NUR AMIRA','VI D','20','TKAEBB24E'),
('164','HAFIZ LUTFI MUZAFFAR','VI D','20','TKA88C3CA'),
('165','IZZATUL KHAIRANI','VI D','20','TKABF90D9'),
('166','KEMALA SARI','VI D','20','TKA42C1CE'),
('167','LIYANA SYAFITRI','VI D','20','TKA92387C'),
('168','MUHAMAD FIKRI','VI D','20','TKAC3A202'),
('169','MUHAMMAD AFKHA RENO','VI D','20','TKA34D890'),
('170','MUHAMMAD AKMAL HUSEIN','VI D','20','TKA2C167C'),
('171','Muhammad Hafiz Akbar','VI D','20','TKA05D524'),
('172','MUHAMMAD NAZAM','VI D','20','TKA65A10F'),
('173','Nabila Hasna AMira','VI D','20','TKAC3D57D'),
('174','PRETY ZINTA LESTARI','VI D','20','TKAEE415C'),
('175','RAMA FATHIR RAMADHAN','VI D','20','TKAA52FFB'),
('176','ROSA TULHASANAH','VI D','20','TKAD20221'),
('177','ANDI ATHALA ANHARUDDIN CELLA','VI E','20','TKA8651C9'),
('178','ARKAN GHANI MUSHAFA','VI E','20','TKA10337D'),
('179','ARMILDA KANZANIA','VI E','20','TKA5BCF75'),
('180','ARVIN ARDAN PRADIPTA','VI E','20','TKA6967A6'),
('181','ASSIFA HAPSARI SUWARDI','VI E','20','TKAC99827'),
('182','Citra Kirana','VI E','20','TKA6703E1'),
('183','FARHAN KHAIRUL AZAM','VI E','20','TKA64FC71'),
('184','FATMAWATI','VI E','20','TKA595F06'),
('185','Ikram Buchori Murwansyah','VI E','20','TKA370D1B'),
('186','KAREL GARALT YOZAL','VI E','20','TKAD59A58'),
('187','KEZIA AMIRA RAHMANI','VI E','20','TKAA99996'),
('188','LUIGI CERRY GUNAWAN','VI E','20','TKA379BF8'),
('189','MEIDY PUTRI NESIH BAIIN','VI E','20','TKA255221'),
('190','MUHAMAD FIRDAUS','VI E','20','TKA8F596D'),
('191','MUHAMMAD ALBY RAFIF','VI E','20','TKA6146A2'),
('192','MUHAMMAD DHARMA WISESA','VI E','20','TKA56CDE9'),
('193','MUHAMMAD HAFIZ PRATAMA','VI E','20','TKAFC9AE5'),
('194','NABIL AKBAR','VI E','20','TKAD92E78'),
('195','Qiswah Putri Nurdiansyah','VI E','20','TKA6F581F'),
('196','ramadhan putra chaniago','VI E','20','TKA3EED67'),
('197','RANI AYU','VI E','20','TKA908795'),
('198','SHEIVIRAH AZYAN ALILAH FIRDAUS','VI E','20','TKACD4615'),
('199','THORIQ ALFARIZI','VI E','20','TKA68AEDF'),
('200','VINO JANUAR IBRAHIM','VI E','20','TKA78C8D7'),
('201','ZAMI ARIF AL NIZHAM','VI E','20','TKAB01EFA'),
('202','AQILLA AZZAHRA','VI F','20','TKA8DC97A'),
('203','ATHIFA KHANZA HASANAH','VI F','20','TKAF51CF0'),
('204','ATHILLAH YASSAR SUWARMAN','VI F','20','TKA4FFC29'),
('205','Auliya Putri','VI F','20','TKA4D7F27'),
('206','AZKA AR.RAHMAN','VI F','20','TKA8A532D'),
('207','CHAYRA NADHIFA','VI F','20','TKAB7F2A7'),
('208','FATIMAH NUR AISYAH','VI F','20','TKA42E9AA'),
('209','Galang Pradipta Setiawan','VI F','20','TKA5FCD25'),
('210','IQBAL RAMADHANI','VI F','20','TKAFDF17A'),
('211','KHANZA ALLEANSYAH','VI F','20','TKA7DFC78'),
('212','KINARA AYCILA ANGGRAINI','VI F','20','TKA6BB6C8'),
('213','LAYNI SORAYA ATHA','VI F','20','TKA413ADE'),
('214','MAHDI SORA AL BUKHORI','VI F','20','TKAF26B80'),
('215','MOHAMED HABIBIE MASSAQUOI','VI F','20','TKAD38949'),
('216','MUHAMAD IQBAL ZULKARNAIN','VI F','20','TKAE308F9'),
('217','MUHAMMAD ATTARIEZ NUANSYAH','VI F','20','TKA54029F'),
('218','MUHAMMAD KHENZI VIENCENT AL FAYER','VI F','20','TKACF3891'),
('219','MUHAMMAD RAIHAN SHUNNAR','VI F','20','TKAC4243B'),
('220','NABILA PUTRI AMELIKA','VI F','20','TKA83CC42'),
('221','RADITYA ILYAS','VI F','20','TKADB9BF4'),
('222','Rafael Alfiano','VI F','20','TKABB94C9'),
('223','RENO ARDANI SIWU','VI F','20','TKADA71AB'),
('224','RIZKAH AQILLAH','VI F','20','TKA4D668C'),
('225','SAFIRA ALMAHIRA','VI F','20','TKA67F644'),
('226','shaffea kirana achmad','VI F','20','TKA364DBD'),
('227','Siti Aisyah Husniyah','VI F','20','TKA7A7284'),
('228','SULTAN ODHI FIRMANSYAH','VI F','20','TKA179203'),
('229','Zahira Zalfa Maulida','VI F','20','TKAC1687C');

-- -----------------------------------------------
DROP TABLE IF EXISTS `rate_limit`;
CREATE TABLE `rate_limit` (
  `rl_key` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `first_attempt` int NOT NULL DEFAULT '0',
  `locked_until` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`rl_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `sekolah`;
CREATE TABLE `sekolah` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_sekolah` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `jenjang` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'SD' COMMENT 'SD / MI / SMP / MTS / SMA / MA / SMK',
  `npsn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `kepala_sekolah` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Nama kepala sekolah',
  `telepon` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id`),
  KEY `idx_sekolah_jenjang` (`jenjang`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sekolah` VALUES
('20','SDN KENARI 01','SD','','',NULL,'',NULL,'aktif');

-- -----------------------------------------------
DROP TABLE IF EXISTS `soal`;
CREATE TABLE `soal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kategori_id` int NOT NULL,
  `tipe_soal` enum('pg','bs','mcma','essay') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pg',
  `pertanyaan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `gambar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `teks_bacaan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pilihan_a` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gambar_pilihan_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pilihan_b` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gambar_pilihan_b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pilihan_c` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gambar_pilihan_c` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pilihan_d` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `gambar_pilihan_d` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jawaban_benar` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pembahasan` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `essay_bobot` tinyint unsigned NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`),
  KEY `fk_soal_kategori` (`kategori_id`),
  KEY `idx_soal_kategori_tipe` (`kategori_id`,`tipe_soal`),
  KEY `idx_soal_tipe` (`tipe_soal`),
  CONSTRAINT `fk_soal_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_soal` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=854 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `soal` VALUES
('806','34','pg','Potongan ayat Q.S. al-Hujurat/49:13 terdapat pada …',NULL,'Perhatikan potongan ayat Al-Qur’an berikut!\n( قُلْ آيَٰهْلَ الْكِتٓبِ تَ عَالَوْا اِلٰٓ كَلِمَةٍ سَوَاۤ ءٍ ( 1\n(2) اِانَّ خَلَقْنٓكُمْ منْ ذكََرٍ اواُنْ ثٓ ى\n(3) وَجَعَلْنٓكُمْ شُعُوْابً اوقَ بَإِۤىلَ لِتَ عَارَف وُْ ا\n(4) فَاِنْ تَ وَلاوْا فَ قُوْلُوا اشْهَدُوْا بًَِانَّ مُسْلِمُوْنَ\n(5) اِ ا ن اكَْرَمَكُمْ عِنْدَ ا للِّ اَتْ قٓىكُمْ','(1), (2), dan (4)',NULL,'(2), (3), dan (5)',NULL,'(3), (4), dan (2)',NULL,'(4), (5), dan (1)',NULL,'b',NULL,'10'),
('807','34','pg','Perilaku yang sesuai dengan hadis tersebut adalah …',NULL,'Perhatikan hadis berikut!\nوَمَنْ كَانَ ي ؤُْمِنُ بًِ للِّ وَالْيَ وْمِ الْْخِرِ فَ لْيَصِلْ رَحَِِ هُ','mengerjakan salat tepat waktu',NULL,'berbakti kepada kedua orang tua',NULL,'menyambung tali silaturahmi dengan sanak saudara',NULL,'bersikap santun terhadap sesama',NULL,'c',NULL,'10'),
('808','34','pg','Pesan pokok dari ayat tersebut adalah …',NULL,'Perhatikan ayat dari Q.S. al-Ma’un berikut! \nفَذٓلِكَ الاذِيْ يَدُعُّ الْيَتِيْمَ','orang-orang yang ingkar terhadap hari kiamat',NULL,'Allah mengancam orang yang salat tetapi tidak sampai ke hatinya',NULL,'menyambung tali silaturahmi dengan sanak saudara',NULL,'bersikap santun terhadap sesama',NULL,'c',NULL,'10'),
('809','34','pg','Dari narasi tersebut, akibat jika kita tidak menghormati keragaman adalah. . . .',NULL,'Perhatikan Narasi berikut!\nHari ini adalah hari yang istimewa di sekolahku. Semua murid berkumpul di halaman sekolah dengan memakai pakaian adat dari berbagai daerah di Indonesia. Ada yang memakai baju batik, baju kebaya, pakaian adat dari Sumatera, Jawa, Kalimantan, dan masih banyak lagi. Selain pakaian adat, kami juga membawa makanan khas dari daerah masing-masing. Ibu guru menjelaskan bahwa Indonesia adalah negara yang kaya akan keragaman. Ada banyak suku, agama, bahasa, dan budaya yang berbeda-beda, meskipun berbeda, kita harus tetap bersatu dan saling menghormati. Keragaman adalah anugerah dari Tuhan yang harus kita syukuri.\nفَذٓلِكَ الاذِيْ يَدُعُّ الْيَتِيْمَ','hidup menjadi damai diantara sesama',NULL,'akan terjadi konflik dan perpecahan',NULL,'akan saling menghargai perbedaan yang ada',NULL,'akan saling percaya satu sama lain',NULL,'b',NULL,'10'),
('810','34','pg','Terjemah dari ayat tersebut adalah …',NULL,'Perhatikan ayat Q.S. ad-Duha berikut! \nفَاَاما الْيَتِيْمَ فَلََ تَ قْهَرْ','dan mendapatimu sebagai seorang yang fakir, lalu Dia memberimu kecukupan?',NULL,'terhadap anak yatim, janganlah engkau berlaku sewenang-wenang',NULL,'terhadap orang yang meminta-minta, janganlah engkau menghardik',NULL,'terhadap nikmat Tuhanmu, nyatakanlah (dengan bersyukur)',NULL,'b',NULL,'10'),
('811','34','pg','Hukum bacaan yang terdapat pada lafal yang bergaris bawah adalah …',NULL,'Perhatikan ayat Q.S. ad-Duha berikut! \nا ََل َ ْ َيَ ِد ْك َ ي َ ت ِ ي ْ م ا ا ف','Qolqolah sugra',NULL,'Qolqolah kubra',NULL,'Izhar syafawi',NULL,'Ikhfa syafawi',NULL,'a',NULL,'10'),
('812','34','pg','Pesan pokok surat ad-Duha yang sesuai dengan sikap Aisyah adalah ….','','Perhatikan narasi berikut! \r\nAisyah adalah seorang anak yang rajin belajar dan selalu bersemangat dalam meraih cita citanya. \r\nMeskipun berasal dari keluarga yang sederhana, ia tidak pernah menyerah dan \r\nselalu percaya bahwa Allah Swt. akan memberikan jalan baginya jika bersungguh\r\nsungguh.','Allah Swt. akan memberikan kemudahan bagi orang-orang yang semangat dan sungguh-sungguh','','Allah Swt. tidak akan pernah meninggalkan hamba-Nya yang sedang dalam kesulitan','','Allah Swt. akan memberikan rezeki yang berlimpah bagi orang-orang yang rajin bekerja','','Allah Swt. akan memberikan azab bagi orang-orang yang berputus asa','','a','','10'),
('813','34','pg','Potongan ayat al Qur’an yang termasuk surat al-A’la adalah . . . .',NULL,'Perhatikan ayat al Qur’an berikut ini! \n  ال اذ ِي ْ خ َ لَق َ ف َس َ و ٓى   )1\n( م َ ا و َ داع َكَ ر َ بُّكَ و َ م َ ا ق َل ٓى   )2\n( و َ ال اذ ِي ْ ا ا َخ ْ ر َ ج َ الْم َ ر ْ عٓى   )3\n(  س َ ن ُق ْر ِئ ُكَ ف ََلَ ت َ ن ْسٓى ا  )4\n( و َ ا َم اا الساا ۤ ىِٕل َ ف','(1), (2), dan (4)',NULL,'(2), (3), dan (5)',NULL,'(1), (3), dan (4)',NULL,'(4), (5), dan (1)',NULL,'c',NULL,'10'),
('815','34','pg','Berdasarkan tabel tersebut yang sesuai antara sifat rasul dan artinya ditunjukkan nomor \r\n...','soal_6a01b5c17ca0c.png','','1 dan 2','','2 dan 3','','1 dan 4','','2 dan 4','','c','','10'),
('816','34','pg','Pasangan asmaul husna dan artinya yang benar ditunjukkan pada …','soal_6a01b74a2f01c.png','','1-A, 2-D, 3-B, 4-C','','1-C, 2-B, 3-D, 4-A','','1-B, 2-C, 3-A, 4-D','','1-D, 2-A, 3-C, 4-B','','b','','10'),
('817','34','pg','Adapun hari pembalasan amal dan ibadah yang kita lakukan disebut dengan …',NULL,'Perhatikan narasi berikut! Hari kiamat adalah hari hancurnya alam semesta beserta isinya. Pada hari itu, semua makhluk akan mati, kemudian Allah SWT membangkitkannya kembali dan dikumpulkan di padang Mahsyar. Semua manusia akan ditimbang dan dihitung segala perbuatannya di dunia, kemudian mereka akan mendapatkan balasan sesuai amal dan ibadahnya.','Yaumul Jaza’',NULL,'Yaumul Akhir',NULL,'Yaumul Hisab',NULL,'Yaumul Ba’ats',NULL,'a',NULL,'10'),
('819','34','pg','Perilaku yang sesuai dengan asmaul husna as-Samad ditunjukkan pada nomor …','','Perhatikan beberapa perilaku berikut! \r\n(1) memaafkan kesalahan orang lain \r\n(2) bersabar dalam menghadapi cobaan \r\n(3) Allah Swt. satu-satunya tempat meminta (4) selalu berdoa setiap akan memulai aktifitas \r\n(5) memohon pertolongan hanya kepada Allah Swt.','(1), (2), dan (3)','','(2), (3), dan (4)','','(1), (3), dan (5)','','(3), (4), dan (5)','','d','','10'),
('820','34','pg','Berikut ini yang termasuk contoh takdir mubram adalah…','','Perhatikan narasi berikut! \r\nSetiap manusia di dunia ini telah ditetapkan takdir oleh Allah Swt., ada takdir mubram dan takdir mu’allaq. Sebagai seorang muslim, kita harus menerima takdir ini dengan penuh keikhlasan dan tetap berusaha menjadi pribadi yang lebih baik.','keberhasilan dalam berbisnis','','kesuksesan dalam meraih cita-cita','','kematian seseorang pada waktunya','','kepandaian seorang dalam suatu bidang','','c','','10'),
('821','34','pg','Pernyataan yang menunjukkan tanda-tanda besar hari akhir terdapat pada …','','Perhatikan beberapa pernyataan berikut! \r\n(1) turunnya nabi Isa a.s. \r\n(2) munculnya fitnah dajjal \r\n(3) matahari terbit dari barat \r\n(4) banyak muncul nabi palsu \r\n(5) banyak terjadi perbuatan riba','(1), (2), dan (3)','','(2), (3), dan (4)','','(3), (4), dan (5)','','(2), (4), dan (5)','','a','','10'),
('822','34','pg','Perilaku yang menunjukkan sikap meneladani asmaul husna Al-Qayyum terdapat pada …',NULL,'Perhatikan beberapa keteladanan berikut! \n(1) memberikan semangat kepada teman-teman yang malas \n(2) mensyukuri nikmat hidup dengan memperbanyak amal baik \n(3) tidak tergantung kepada orang lain \n(4) merapihkan dan menyiapkan perlengkapan sekolah sendiri','(1) dan (2)',NULL,'(1) dan (3)',NULL,'(2) dan (4)',NULL,'(3) dan (4)',NULL,'d',NULL,'10'),
('823','34','pg','Pernyataan  tersebut  yang sesuai dengan makna asmaul husna al-Malik terdapat pada \r\nnomor…','','Perhatikan pernyataan berikut! \r\n(1) saling tolong menolong \r\n(2) memiliki sikap rendah hati \r\n(3) mendahulukan kepentingan bersama \r\n(4) amanah dalam menjalankan tanggung jawab','(1) dan (2)','','(2) dan (3)','','(3) dan (4)','','(2) dan (4)','','d','','10'),
('824','34','pg','Sikap yang harus ditunjukkan Ridwan sebagai ketua kelas adalah …','','Perhatikan deskripsi berikut!\r\nDalam rapat kelas untuk menentukan kegiatan akhir tahun, murid diberi kebebasan untuk \r\nmengusulkan ide. Ridwan sebagai ketua kelas mengusulkan kegiatan \"Wisata Sejarah\" di \r\nkota terdekat karena menurutnya kegiatan tersebut akan edukatif dan memberikan \r\npengalaman yang bermanfaat bagi semua. Namun, Zaid dan beberapa temannya tidak \r\nsetuju dengan usulan tersebut. Ia berpendapat bahwa kegiatan seperti \"Camping Alam\" \r\nakan lebih seru dan memberikan kesempatan untuk mempererat hubungan antar murid .','menganggap usulan sendiri adalah usulan yang terbaik','','menghargai usulan temannya dan melakukan musyawarah','','tetap mengusulkan kegiatan “Wisata Sejarah” karena itu lebih baik','','mengabaikan usulan Zaid dan temannya karena dianggap tidak penting','','b','','10'),
('825','34','pg','Sikap saling menghormati yang ditunjukkan Anton adalah …','','Perhatikan deskripsi berikut!\r\nMeskipun Toha dan Anton berbeda agama, mereka tetap bermain bersama. Ketika mereka \r\nsedang bermain di lapangan, terdengar suara adzan. Toha meminta izin kepada Anton \r\nuntuk menunaikan salat berjama’ah di masjid.','ikut bersama Toha salat berjama’ah di masjid','','mengajak Toha untuk tetap bermain di lapangan','','melarang Toha untuk salat berjama’ah di masjid','','mempersilahkan Toha untuk salat berjama’ah di masjid','','d','','10'),
('826','34','pg','Hikmah berteman tanpa membedakan agama ditunjukkan pada nomor …','','Perhatikan beberapa pernyataan berikut!\r\n(1) menjadikan pribadi yang rendah hati\r\n(2) melatih kesabaran dan kedisiplinan diri\r\n(3) meningkatkan keimanan dan ketakwaan\r\n(4) menciptakan perdamaian antar umat beragama','(1) dan (2)','','(2) dan (3)','','(3) dan (4)','','(4) dan (1)','','d','','10'),
('827','34','pg','Pernyataan yang menunjukan sikap memaafkan ditunjukkan pada …','','Perhatikan beberapa pernyataan berikut!\r\n(1) Asih memaafkan teman-teman yang sering mengejeknya\r\n(2) Keysha sering berbagi makanan kepada teman-temannya di sekolah\r\n(3) Balqis meminta maaf kepada temannya karena tidak sengaja menjatuhkan botol \r\nminumnya\r\n(4) Ali memaafkan temannya yang tidak berterimakasih atas hadiah yang diberikan \r\nkepadanya','(1) dan (2)','','(3) dan (4)','','(2) dan (3)','','(1) dan (4)','','d','','10'),
('828','34','pg','Pernyataan yang merupakan hikmah menyatakan penyesalan ditunjukkan pada ,,,','','Perhatikan beberapa pernyataan berikut!\r\n(1) mempererat persaudaraan\r\n(2) menghapuskan rasa bersalah\r\n(3) membersihkan hati dan jiwa kita\r\n(4) mencegah dari perbuatan keji dan mungkar\r\n(5) terbebas dari rasa dendam dan penyakit hati lainnya','(1), (2), dan (3)','','(3), (4), dan (5)','','(2), (3), dan (5)','','(1), (3), dan (4)','','c','','10'),
('829','34','pg','Pernyataan yang merupakan cara menjaga kelestarian lingkungan ditunjukkan pada …','','Perhatikan beberapa pernyataan berikut!\r\n(1) Mandi sehari dua kali\r\n(2) makan bergizi setiap hari\r\n(3) mengolah sampah plastik dengan baik\r\n(4) melakukan pengolahan tanah dengan baik\r\n(5) melakukan penghijauan dengan menanam kembali pohon','(1), (2), dan (5)','','(2), (3), dan (4)','','(3), (4), dan (5)','','(1), (3), dan (5)','','c','','10'),
('830','34','pg','Manfaat menjaga lingkungan dengan baik adalah …','','Perhatikan deskripsi berikut!\r\nPeduli terhadap lingkungan termasuk perbuatan yang terpuji atau akhlak mahmudah. \r\nPeduli terhadap lingkungan berarti ikut melestarikan lingkungan dengan sebaik-baiknya, \r\nbisa dengan cara memelihara, mengelola, memulihkan serta menjaga lingkungan.','embudayakan menanam pohon di depan rumah','','mendapatkan keuntungan yang banyak untuk diri sendiri','','memberikan kemudahan dan bantuan dalam kehidupan manusia','','membuat sampah lebih mudah menumpuk di berbagai tempat','','c','','10'),
('831','34','pg','Berdasarkan deskripsi tersebut, tanda-tanda usia balig menurut ilmu fikih adalah …','','Perhatikan deskripsi berikut!\r\nSuatu hari, Ali yang berusia 12 tahun bertanya kepada ayahnya tentang tanda-tanda \r\nseseorang dikatakan balig. Ayahnya pun menjelaskan bahwa balig adalah kondisi di mana \r\nseseorang telah mencapai kedewasaan menurut syariat Islam, yang ditandai dengan \r\nperubahan fisik tertentu.','mimpi basah bagi laki-laki dan menstruasi bagi perempuan','','bisa membaca Al-Qur’an dengan lancar tanpa kesalahan','','mampu menghafal hadis beserta maknanya','','sudah berusia 10 tahun','','a','','10'),
('832','34','pg','Pernyataan yang merupakan syarat wajib salat Jum’at ditunjukkan pada …','','Perhatikan beberapa pernyataan berikut!\r\n(1) suci\r\n(2) balig\r\n(3) laki-laki\r\n(4) mampu\r\n(5) berakal sehat','(2), (3), dan (5)','','(3), (4), dan (5)','','(4), (5), dan (1)','','(5), (1), dan (2)','','a','','10'),
('833','34','pg','Berdasarkan narasi tersebut, jumlah zakat fitrah yang wajib dikeluarkan adalah …','','Perhatikan narasi berikut!\r\nSaat bulan Ramadan, Salim dan keluarganya bersiap untuk membayar zakat fitrah. Zakat \r\nfitrah yang harus dibayarkan setiap orang adalah 2,5 kg beras. Dalam keluarga Salim ada \r\n7 orang (kakek, nenek, ayah, ibu, Salim, dan dua orang adiknya).','12,5 kg','','15 kg','','17,5 kg','','20 kg','','c','','10'),
('834','34','pg','Pengertian dari tawaf adalah …','','Perhatikan deskripsi berikut!\r\nHaji adalah salah satu rukun Islam yang kelima. Umat Islam yang telah mampu wajib \r\nmenunaikannya. Rukun haji diantaranya adalah ihram, wuquf, tawaf, sa’i, tahallul, tertib.','berlari-lari kecil antara bukit safa dan bukit marwa','','mengelilingi Ka’bah sebanyak tujuh kali','','berdiam diri di padang Arafah','','memotong rambut minimal 3 helai','','b','','10'),
('835','34','pg','Dasar hukum penentuan halal dan haram ditunjukkan pada nomor …','','Perhatikan beberapa dasar hukum berikut!\r\n(1) Al-Qur’an\r\n(2) UUD 1945\r\n(3) Pancasila\r\n(4) Hadis\r\n(5) Ijtihad','(1), (2), dan (3)','','(2), (3), dan (4)','','(1), (4), dan (5)','','(2), (4), dan (5)','','c','','10'),
('836','34','pg','Manfaat  menjaga lingkungan dengan baik adalah …','','Perhatikan deskripsi berikut! \r\nPeduli terhadap lingkungan termasuk perbuatan yang terpuji atau akhlak mahmudah. \r\nPeduli terhadap lingkungan berarti ikut melestarikan lingkungan dengan sebaik-baiknya, \r\nbisa dengan cara memelihara, mengelola, memulihkan serta menjaga lingkungan.','embudayakan menanam pohon di depan rumah','','mendapatkan keuntungan yang banyak untuk diri sendiri','','memberikan kemudahan dan bantuan dalam kehidupan manusia','','membuat sampah lebih mudah menumpuk di berbagai tempat','','c','','10'),
('837','34','pg','Berdasarkan pernyataan tersebut, yang merupakan hikmah dari puasa sunnah terdapat pada \r\n…','','Perhatikan beberapa pernyataan berikut! \r\n(1) mencegah dari perbuatan keji dan mungkar \r\n(2) menumbuhkan kepedulian sosial \r\n(3) menjalin tali silaturahmi antar sesama \r\n(4) penyempurna kekurangan ibadah wajib \r\n(5) menjaga kesehatan dan kebugaran','(1), (2), dan (5)','','(2), (3), dan (4)','','(2), (4), dan (5)','','(3), (4), dan (5)','','c','','10'),
('838','34','pg','Jenis puasa tersebut yang termasuk puasa sunnah ditunjukkan pada …','','Perhatikan beberapa jenis puasa berikut! \r\n(1) puasa Ramadan \r\n(2) puasa 1 Syawal \r\n(3) puasa ayyamul bid \r\n(4) puasa nabi Daud \r\n(5) puasa senin kamis','(1), (2), dan (5)','','(2), (3), dan (4)','','(3), (4), dan (5)','','(1), (3), dan (4)','','c','','10'),
('839','34','pg','Berdasarkan ayat tersebut, makanan yang diharamkan adalah …','soal_6a01e7ca5b216.png','','bangkai, darah, ikan, hewan yang disembelih dengan menyebut nama Allah','','bangkai, darah, daging babi, hewan yang disembelih dengan menyebut nama selain  Allah','','bangkai ikan, darah, daging babi, hewan yang disembelih dengan menyebut nama  selain Allah','','bangkai belalang, darah, daging babi, hewan yang disembelih dengan menyebut nama  Allah','','b','','10'),
('840','34','pg','Berdasarkan deskripsi tersebut, yang merupakan hikmah hijrah Nabi Muhammad Saw. ke \r\nMadinah bagi seorang muslim adalah ….','','Perhatikan deskripsi berikut! \r\nHijrah yang dilakukan Nabi Muhammad saw. bukan sekedar untuk melepaskan diri dari \r\ncengkraman kekejaman kaum kafir Quraisy. Hijrah yang dilakukan oleh beliau adalah batu \r\nloncatan untuk mendirikan masyarakat baru di negeri yang aman. Dengan demikian, Islam \r\nakan memiliki pondasi yang kuat.','mengerjakan salat tepat pada waktunya','','selalu bersedekah dalam kondisi apapun','','membaca Al-Qur’an secara rutin setiap hari','','berusaha sekuat tenaga menjalankan perbuatan yang terpuji','','d','','10'),
('841','34','pg','Berikut ini yang merupakan isi perjanjian Hudaibiyah adalah ...','','Perhatikan deskripsi berikut! \r\nPerjanjian Hudaibiyah merupakan perjanjian perdamaian antara Kaum Quraisy Makkah \r\ndan penduduk muslim Madinah. Perjanjian Hudaibiyah telah membuat Madinah dan \r\nMakkah menjadi aman karena tidak ada pertikaian dan peperangan. Namun dalam jangka \r\nwaktu yang tidak lama, suku Quraisy melanggar isi perjanjian Hudaibiyah. Kaum Quraisy \r\nmembantu Bani Bakar menyerang Bani Khuza’ah yang telah memeluk Islam.','memaksa suku-suku Arab untuk bergabung dengan Kaum Quraisy Makkah','','menghentikan permusuhan dan tidak saling menyerang dalam waktu 10 tahun','','pengikut Nabi Muhammad Saw. boleh menjalankan ibadah umrah setiap tahun','','mengizinkan Kaum Quraisy yang hendak menjadi pengikut Nabi Muhammad Saw.  ke Madinah','','b','','10'),
('842','34','pg','Hikmah dari kisah hijrah nabi Muhammad Saw. yang harus kita contoh adalah …','','Perhatikan kisah nabi berikut!  \r\nDalam perjalanan hijrah dari kota Mekkah ke Yatsrib (Madinah), Nabi Muhammad Saw. \r\nbersama Abu Bakar dijemput oleh Abdullah bin Uraiqiṭ guna mengantar mereka menuju \r\nMadinah. Ketika itu juga Asma’ putri Abu Bakar datang dengan bawaan bekal perjalanan, \r\nnamun waktu bekal itu akan digantung di unta, dia tidak punya tali untuk mengikat, lalu \r\ndia memotong ikat pinggangnya dengan cermat. Dalam perjalanan mereka berjumpa \r\ndengan beberapa orang, antara lain Suraqah. Dia awalnya berniat buruk terhadap Nabi \r\nMuhammad Saw., tetapi pada akhirnya justru melindungi beliau.','disiplin ketika akan belajar saja','','pemimpin yang tegas dan pemberani','','menghindar dari cobaan yang dihadapi','','perlunya keterlibatan semua kelompok','','d','','10'),
('843','34','pg','Perilaku yang sesuai dengan deskripsi tersebut adalah .….','','Perhatikan deskripsi berikut! \r\nKetika khalifah Umar bin Khattab ra. melihat ada salah satu rakyatnya yang kelaparan, \r\nbeliau memanggul sendiri gandum untuk diberikan kepadanya. Ketika pengawalnya \r\nmenawarkan diri untuk membantu memanggul gandum tersebut, beliau menolaknya.','mengkhatamkan Al-Qur’an secara bersama-sama','','menyisihkan sebagian uang jajan untuk ditabung','','mengembalikan barang temuan kepada pemiliknya','','menjalankan tugas piket sesuai dengan jadwal yang telah ditentukan','','d','','10'),
('844','34','pg','Pasangan sahabat Nabi Muhammad Saw. dan jasanya yang sesuai ditunjukkan pada nomor ….','soal_6a01e953c0578.png','','1-D, 2-A, 3-B, 4-C','','1-C, 2-D, 3-A, 4-B','','1-A, 2-D, 3-C, 4-B','','1-B, 2-C, 3-D, 4-A','','b','','10'),
('845','34','pg','Berdasarkan deskripisi tersebut, gelar yang didapatkan Abu Bakar ra. adalah …','','Perhatikan deskripsi berikut! \r\nAbu Bakar ra. adalah salah satu sahabat Nabi Muhammad Saw. yang berani membenarkan \r\nperistiwa Isra Mi’raj. Bahkan beliau berkata “Aku akan terus membenarkan meskipun \r\nbeliau mengatakan yang lebih jauh dari itu. Aku membenarkan dengan kabar langit, baik \r\ndi pagi maupun malam hari”. Setelah peristiwa tersebut, beliau mendapatkan gelar dari \r\nRasulullah.','Al-Faruq','','As-Shiddiq','','Babul Ilmu','','Dzunnurain','','b','','10'),
('846','34','pg','Diantara wilayah perluasan islam pada kepemimpinan Umar bin Khattab ra. adalah ….','','Perhatikan deskripsi berikut! \r\nUmar bin Khattab ra. adalah seorang sahabat Nabi Muhammad Saw. Yang berasal dari \r\nBani ‘Adiy. Beliau adalah seorang Khalifah kedua setelah Abu Bakar ra., di masa \r\nkepemimpinannya kejayaan islam berkembang sangat pesat. Kedaulatan umat islam \r\nmeluas di berbagai wilayah.','Mesir','','Turki','','Libanon','','Palestina','','a','','10'),
('847','34','pg','Gelar Dzun Nurain diberikan kepada khalifah Utsman bin Affan r.a. karena …','','Perhatikan deskripsi berikut! \r\nKhalifah Utsman bin Affan r.a. terkenal dengan kedermawanannya, kelemahlembutan \r\nserta kerendahan hatinya. Beliau tidak ragu menggunakan hartanya untuk kepentingan \r\nIslam. Beliau juga sering menggunakan hartanya untuk anak-anak yatim dan orang-orang \r\nyang kekurangan. Sementara beliau senantiasa hidup sederhana dan tidak bersikap \r\nsombong. beliau juga mendapat gelar Dzun Nurain.','menikah dengan dua putri Nabi Muhammad saw.','','selalu hidup sederhana dan tidak bersikap sombong','','sifat kedermawanan dan kelemahlembutan serta kerendahan hatinya','','sering membantu anak-anak yatim dan orang-orang yang kekurangan','','a','','10'),
('849','34','essay','Jelaskan pesan pokok yang terkandung dalam ayat tersebut!','soal_6a0258b5a83ef.jpg','','','','','','','','','','1.\r\nAllah menciptakan manusia untuk saling mengenal\r\n2.\r\nTingkat kemuliaan manusia diukur dari ketakwaannya','','10'),
('850','34','essay','Berdasarkan deskripsi tersebut, tuliskan hikmah beriman kepada hari akhir!','','Perhatikan deskripsi berikut!\r\nBeriman kepada hari akhir akan membuat kita berhati-hati dalam bertindak sekaligus \r\nbahagia dalam menjalani kehidupan tanpa ada paksaan. Allah Swt. akan memberikan \r\nbalasan atas perbuatan baik yang kita lakukan.','','','','','','','','','1. Mendorong, meningkatkan keimanan dan ketakwaan kepada Allah \r\nSWT\r\n2. Menumbuhkan sikap tanggung jawab atas perbuatan\r\n3. Menguatkan semangat beramal saleh','','10'),
('851','34','essay','Jelaskan pesan pokok dari ayat tersebut!','soal_6a025f7220972.jpg','Perhatikan deskripsi berikut!\r\nKita harus menjaga lingkungan serta mengelolanya dengan baik untuk memenuhi \r\nkebutuhan hidup dan makhluk lainnya. Kita dilarang oleh Allah Swt. melakukan perbuatan \r\nyang dapat merusak lingkungan. Sebagaimana firman Allah Swt. dalam surah al-A’raf/7 : \r\n56 berikut','','','','','','','','','1. Larangan merusak bumi dalam bentuk apapun\r\n2. Perintah menjaga dan memelihara kebaikan yang telah \r\nAllah ciptakan\r\n3. Anjuran berdoa dengan takut akan azab dan berharap \r\nrahmat Allah','','10'),
('852','34','essay','Berdasarkan narasi tersebut, tuliskan 3 macam puasa sunah!','','Perhatikan narasi berikut!\r\nPuasa sunah merupakan ibadah puasa yang dianjurkan untuk dikerjakan pada waktu-waktu \r\ntertentu sebagai tambahan amalan, serta penyempurna ibadah wajib lainnya. \r\nMelaksanakan puasa sunnah merupakan bentuk ketaatan kepada Allah Swt., karena puasa \r\nmerupakan salah satu ibadah yang paling utama.','','','','','','','','','1.\r\nPuasa senin kamis\r\n2.\r\nPuasa Arafah\r\n3.\r\nPuasa Syawal','','10'),
('853','34','essay','Berdasarkan deskripsi tersebut, tuliskan 3 jasa kepemimpinan Khalifah Ali bin Abi Thalib \r\nr.a.!','','Perhatikan narasi berikut!\r\nAli bin Abi Thalib r.a. merupakan seorang khalifah yang memiliki sifat zuhud dan hidup \r\nsederhana. Beliau merupakan seorang perwira yang sangat cerdas, cekatan, teguh \r\npendirian dan sangat pemberani, sehingga beliau dikenal dengan “Asadullah” yang artinya \r\n“Singa Allah”. Beliau juga merupakan orang yang sangat cerdas sehingga dikenal dengan \r\njulukan “Babul Ilmi”.','','','','','','','','','1. Sumber rujukan utama ilmu agama dikalangan sahabat\r\n2. Peletak dasar pengembangan ilmu fikih dan peradilan islam\r\n3. Membangun kota Kuffah','','10');

-- -----------------------------------------------
DROP TABLE IF EXISTS `token_ujian`;
CREATE TABLE `token_ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `keterangan` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('aktif','nonaktif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `ujian`;
CREATE TABLE `ujian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `peserta_id` int NOT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `nilai` int DEFAULT NULL,
  `token_id` int DEFAULT NULL,
  `jadwal_id` int DEFAULT NULL,
  `kategori_id` int DEFAULT NULL,
  `soal_order` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `pelanggaran` int NOT NULL DEFAULT '0',
  `last_activity` datetime DEFAULT NULL,
  `cache_version` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_ujian_peserta` (`peserta_id`),
  KEY `idx_ujian_peserta_selesai` (`peserta_id`,`waktu_selesai`),
  KEY `idx_ujian_status` (`waktu_selesai`,`waktu_mulai`),
  KEY `idx_ujian_peserta_jadwal` (`peserta_id`,`jadwal_id`),
  KEY `idx_ujian_jadwal` (`jadwal_id`),
  CONSTRAINT `fk_ujian_peserta` FOREIGN KEY (`peserta_id`) REFERENCES `peserta` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `foto_profil` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'sekolah',
  `kelas_diampu` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sekolah_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `fk_users_sekolah` (`sekolah_id`),
  CONSTRAINT `fk_users_sekolah` FOREIGN KEY (`sekolah_id`) REFERENCES `sekolah` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES
('1','admin','Edi',NULL,'$2y$10$ZWAuLHc2KMt7zbNmrfAX9.Vo3rbnBI3sHGXEgxsxRdpu9XwQtdkVK','admin_kecamatan',NULL,NULL),
('21','korektor','Tim Korektor',NULL,'$2y$10$jQMD2sB5C8.1d2RM0So5qOEy8JFWNHJfQbWo2tHqnvt.ic4k5kSNK','korektor',NULL,NULL),
('26','Kenari01',NULL,NULL,'$2y$10$7eduWLtWBpBvZHxSL96TMuRwV5elT3DEx8QpEieu8byCHokCR2hey','sekolah',NULL,'20');

SET FOREIGN_KEY_CHECKS=1;
