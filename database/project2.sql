-- Database project2 — Toko Victory Inventori
CREATE DATABASE IF NOT EXISTS `project2` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `project2`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('owner','admin','karyawan') NOT NULL DEFAULT 'karyawan',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `supplier` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(150) NOT NULL,
  `kontak` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `barang` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_barang` VARCHAR(150) NOT NULL,
  `kategori` VARCHAR(80) NOT NULL DEFAULT 'Umum',
  `stok_saat_ini` INT NOT NULL DEFAULT 0,
  `rop` INT NOT NULL DEFAULT 10,
  `lokasi` VARCHAR(100) DEFAULT 'Gudang A',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transaksi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_transaksi` VARCHAR(20) DEFAULT NULL,
  `id_barang` INT UNSIGNED NOT NULL,
  `jenis` ENUM('masuk','keluar') NOT NULL,
  `jumlah` INT NOT NULL,
  `stok_sebelum` INT NOT NULL DEFAULT 0,
  `stok_sesudah` INT NOT NULL DEFAULT 0,
  `keterangan` TEXT,
  `id_user` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_transaksi_barang` (`id_barang`),
  KEY `idx_transaksi_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pemesanan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_pesanan` VARCHAR(20) NOT NULL,
  `id_barang` INT UNSIGNED NOT NULL,
  `id_supplier` INT UNSIGNED NOT NULL,
  `jumlah_pesan` INT NOT NULL,
  `status` ENUM('pending','diproses','diterima','dibatalkan') NOT NULL DEFAULT 'pending',
  `catatan` TEXT,
  `tanggal_pesan` DATE NOT NULL,
  `tanggal_estimasi` DATE DEFAULT NULL,
  `tanggal_diterima` DATE DEFAULT NULL,
  `id_user` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kode_pesanan` (`kode_pesanan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `log_aktivitas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_user` INT UNSIGNED DEFAULT NULL,
  `nama_user` VARCHAR(100) NOT NULL,
  `aksi` VARCHAR(50) NOT NULL,
  `keterangan` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
