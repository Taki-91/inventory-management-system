# Inventory Management System

## Deskripsi Project

Inventory Management System adalah aplikasi berbasis web menggunakan PHP dan MySQL untuk mengelola persediaan barang. Project ini dibuat sebagai Mini Project 3 pada materi Relational Database dan Advanced PHP.

Aplikasi ini mengelola data kategori, produk, supplier, transaksi stok masuk dan keluar, serta menyediakan dashboard untuk melihat informasi persediaan.

## Fitur Aplikasi

### 1. Authentication dan Authorization
- Login dan logout pengguna.
- Password disimpan menggunakan password hashing.
- Hak akses pengguna dibagi menjadi admin dan staff.
- Halaman internal memerlukan login.

### 2. Master Data
- Mengelola kategori barang.
- Mengelola data produk.
- Mengelola data supplier.
- Menghubungkan produk dengan supplier menggunakan relasi many-to-many.

### 3. Transaksi Persediaan
- Mencatat barang masuk.
- Mencatat barang keluar.
- Menampilkan riwayat transaksi stok.
- Menjaga jumlah stok agar tidak negatif.

### 4. Pengelolaan Data
- Pencarian produk.
- Filter data.
- Pengurutan data.
- Pagination untuk membagi data ke beberapa halaman.

### 5. Dashboard
- Menampilkan ringkasan data inventory.
- Menampilkan informasi stok produk.
- Membantu pemantauan persediaan barang.

## Teknologi yang Digunakan

- PHP
- MySQL
- HTML
- CSS
- XAMPP
- Visual Studio Code

## Struktur Folder


inventory-app/
├── config/
│   └── database.php
├── helpers/
│   ├── auth.php
│   ├── csrf.php
│   └── ...
├── public/
│   ├── login.php
│   ├── dashboard.php
│   ├── categories/
│   ├── products/
│   ├── suppliers/
│   └── stock/
├── views/
│   └── header.php
├── database/
│   └── schema.sql
├── screenshots/
└── README.md


Catatan: Struktur di atas merupakan gambaran direktori project. Folder atau file yang ditandai `...` mengikuti implementasi yang tersedia pada source code.

## Persyaratan Sistem

- XAMPP dengan Apache dan MySQL.
- PHP yang kompatibel dengan source code.
- Browser web.
- phpMyAdmin untuk mengelola database.

## Cara Instalasi dan Menjalankan

1. Instal dan jalankan XAMPP.
2. Aktifkan Apache dan MySQL.
3. Letakkan folder project `inventory-app` di dalam direktori `htdocs`.
4. Buka phpMyAdmin melalui `http://localhost/phpmyadmin`.
5. Buat database `inventory_db` jika belum tersedia.
6. Impor file `database/schema.sql` ke database yang digunakan.
7. Periksa konfigurasi koneksi pada `config/database.php`.
8. Siapkan akun demo menggunakan prosedur pembuatan akun yang tersedia pada project.
9. Buka aplikasi melalui alamat berikut:

   `http://localhost/inventory-app/public/`

## Akun Demo

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `DemoAdmin123!` |
| Staff | `staff` | `DemoStaff123!` |

Akun demo harus dibuat terlebih dahulu di database. Jika akun belum tersedia atau password berbeda, login tidak akan berhasil.

Akun ini digunakan untuk demonstrasi lokal dan tidak ditujukan untuk penggunaan produksi.

## Struktur Database

Database terdiri dari tabel-tabel utama berikut:

| Tabel | Fungsi |
|---|---|
| `users` | Menyimpan data pengguna dan role. |
| `categories` | Menyimpan kategori barang. |
| `products` | Menyimpan informasi produk dan stok. |
| `suppliers` | Menyimpan data pemasok. |
| `product_suppliers` | Menghubungkan produk dengan supplier. |
| `stock_movements` | Menyimpan riwayat barang masuk dan keluar. |

Relasi antar-tabel menggunakan foreign key untuk membantu menjaga integritas data.

## Business Rules

1. SKU setiap produk harus unik.
2. Harga dan quantity tidak boleh negatif.
3. Barang keluar tidak boleh melebihi stok yang tersedia.
4. Stok tidak boleh menjadi negatif.
5. Produk yang memiliki riwayat transaksi tidak boleh dihapus sembarangan.
6. Halaman internal hanya dapat diakses setelah login.
7. Staff tidak boleh mengakses administrasi pengguna.
8. Sorting hanya menggunakan kolom yang diizinkan aplikasi.
9. Perubahan stok dan pencatatan histori harus konsisten.
10. Input pengguna harus divalidasi.
11. Query database menggunakan prepared statements.
12. Output HTML menggunakan escaping untuk mengurangi risiko XSS.

## Pengujian Aplikasi

Pengujian dilakukan untuk memeriksa fungsi utama aplikasi, antara lain:

- Login dan logout.
- Pembatasan akses berdasarkan role.
- Validasi SKU duplikat.
- Penolakan input harga atau quantity negatif.
- Penolakan barang keluar melebihi stok.
- Konsistensi stok dan riwayat transaksi.
- Search, filter, sorting, dan pagination.
- Relasi produk dengan supplier.
- Penolakan akses ke halaman internal tanpa login.

Hasil pengujian dicatat berdasarkan hasil tes aktual. Fitur yang belum diuji tidak dinyatakan lulus.

## Dokumentasi Pengumpulan

Dokumentasi project meliputi:

1. Source code aplikasi.
2. File `database/schema.sql`.
3. Screenshot halaman login dan dashboard.
4. Screenshot halaman kategori, produk, dan supplier.
5. Screenshot relasi produk dengan supplier.
6. Screenshot transaksi stok masuk, stok keluar, dan riwayat transaksi.
7. Screenshot pengujian validasi dan pembatasan akses.
8. Penjelasan business rules yang diterapkan.

Screenshot disimpan dalam folder `screenshots/`.

## Penutup

Project ini merupakan implementasi materi Relational Database dan Advanced PHP melalui aplikasi Inventory Management System. Fokus pengembangan meliputi pengelolaan data, relasi database, transaksi persediaan, pencarian data, dashboard, autentikasi, otorisasi, serta penerapan aturan bisnis.