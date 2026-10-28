# KasirKu — POS Toko Kelontong

Aplikasi Point of Sale berbasis PHP 8+ dan MySQL/MariaDB, dengan UI responsif, katalog produk, keranjang, diskon, PPN 11%, pembayaran tunai/QRIS/transfer-kartu (mode demo), cetak struk, inventaris, riwayat, dan laporan.

## Kebutuhan
- PHP 8.0 atau lebih baru dengan ekstensi `pdo_mysql`
- MySQL 5.7+/8.0 atau MariaDB 10.4+
- XAMPP (cara termudah di Windows), atau server PHP/MySQL lain
- Koneksi internet hanya diperlukan untuk font Google dan gambar contoh produk; fitur inti tetap berjalan jika gambar eksternal tidak tersedia.

## Instalasi dengan XAMPP
1. Salin folder `pos_toko_kelontong` ke `C:\xampp\htdocs\pos_toko_kelontong`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih tab **Import**, unggah file `database.sql`, lalu klik **Import**/Go. SQL ini membuat database `pos_toko_kelontong`, tabel, dan 10 produk contoh.
5. Periksa `config.php`. Default XAMPP biasanya:
   - Host: `127.0.0.1`
   - Database: `pos_toko_kelontong`
   - User: `root`
   - Password: kosong
   Ubah konstanta DB bila konfigurasi MySQL berbeda.
6. Buka `http://localhost/pos_toko_kelontong/`.

## Fitur tambahan (paket pengembangan)
- Jalankan `upgrade.sql` **setelah** `database.sql` untuk menambah tabel pelanggan, pemasok, pembelian stok, pengeluaran, pergerakan kas, dan pengguna. Skrip upgrade menambahkan kolom yang diperlukan tanpa menghapus transaksi lama.
- Buka `http://localhost/pos_toko_kelontong/admin.php` untuk halaman pengelolaan tambahan.
- Login wajib sebelum dashboard: `admin` / `password` dan `kasir` / `password` (tersedia setelah impor `upgrade.sql`). Sesi PHP melindungi endpoint API dan halaman pengelolaan. Identitas dan peran akun aktif ditampilkan pada header/sidebar. Ganti password demo sebelum penggunaan nyata.
- Ekspor riwayat CSV: tombol di halaman Pengelolaan. Backup SQL: tombol di halaman Pengelolaan. Backup dapat diimpor melalui phpMyAdmin; **restore otomatis dari browser belum tersedia**.
- Pembelian stok masuk menggunakan transaksi database dan menambah stok setelah pencatatan sukses.
- Barcode menggunakan SKU produk yang dapat dibaca scanner USB melalui kolom pencarian kasir.
- Laporan laba rugi adalah estimasi berdasarkan `products.cost_price`; isi harga modal dengan benar. Harga modal produk lama diinisialisasi 0 oleh migrasi sehingga HPP belum akurat sampai diperbarui.

## Struktur file
- `index.php` — semua kerangka UI dan modal
- `style.css` — gaya responsif desktop/tablet/mobile dan print
- `app.js` — interaksi katalog, keranjang, pembayaran, struk, inventaris, riwayat, laporan
- `api.php` — endpoint JSON, validasi, transaksi database, penguncian dan pengurangan stok
- `config.php` — koneksi PDO dan helper
- `database.sql` — skema dan data produk contoh
- `upgrade.sql` — migrasi tambahan yang mempertahankan data lama
- `admin.php` — halaman pengelolaan pelanggan, pemasok, pembelian stok, pengeluaran, kas, ekspor dan backup

## Logika harga dan transaksi
- Nominal harga disimpan sebagai integer Rupiah (BIGINT), bukan float.
- Subtotal = jumlah harga satuan × kuantitas.
- PPN = 11% subtotal, dibulatkan ke Rupiah terdekat dengan integer arithmetic.
- Diskon nominal dibatasi maksimal subtotal; diskon persentase dibatasi 100%.
- Total = max(0, subtotal + PPN − diskon).
- Pembayaran tunai ditolak jika uang dibayar kurang dari total; kembalian = dibayar − total.
- QRIS dan Transfer/Kartu saat ini **simulasi**: konfirmasi mencatat pembayaran sejumlah total. Tidak ada QR/payment gateway nyata.
- Checkout menggunakan transaksi InnoDB, `SELECT ... FOR UPDATE`, validasi stok server-side, dan `UPDATE products SET stock=stock-? WHERE stock>=?`. Jika salah satu validasi gagal, seluruh transaksi di-rollback. Harga dan stok yang dipercaya diambil dari database, bukan dari browser.
- Semua transaksi yang tersimpan berstatus sukses. Keranjang di browser tidak dianggap sebagai transaksi sampai checkout server sukses.

## Pengujian tambahan
13. Impor `upgrade.sql` setelah database lama tersedia; pastikan transaksi dan produk lama tetap ada.
14. Buka `admin.php`, uji login demo, tambah pelanggan dan pemasok.
15. Catat stok masuk untuk ID produk yang ada; cek stok bertambah di inventaris.
16. Catat pengeluaran dan pergerakan kas; pastikan tampil di daftar.
17. Unduh CSV riwayat dan backup SQL; pastikan berkas terunduh.
18. Isi harga modal produk di kolom `cost_price`, lalu uji laporan laba rugi dengan rentang tanggal yang sesuai.

## Pengujian manual
1. **Katalog:** cari `beras`, pilih kategori `Minuman`, cari SKU `KL-004`. Hasil harus mengikuti pencarian/filter.
2. **Keranjang:** tambah produk berulang kali, kurangi, hapus item, kosongkan keranjang. Jumlah tidak boleh melebihi stok.
3. **Perhitungan:** masukkan barang dengan subtotal Rp100.000 dan tanpa diskon. PPN seharusnya Rp11.000 dan total Rp111.000.
4. **Diskon nominal:** subtotal Rp100.000, PPN Rp11.000, diskon Rp10.000 → total Rp101.000.
5. **Diskon persentase:** subtotal Rp100.000, diskon 10% = Rp10.000, PPN Rp11.000 → total Rp101.000. Diskon lebih dari 100% ditolak/dibatasi.
6. **Tunai kurang:** jika total Rp25.000, masukkan Rp20.000. Tombol konfirmasi harus nonaktif dan muncul peringatan.
7. **Kembalian:** total Rp25.000, bayar Rp50.000 → kembalian Rp25.000.
8. **Stok:** catat stok produk sebelum checkout, lakukan transaksi, pastikan stok database turun sesuai kuantitas. Coba checkout melebihi stok lewat UI/API; transaksi harus gagal tanpa mengubah stok.
9. **QRIS/transfer:** pilih metode non-tunai dan konfirmasi. Sistem mencatat mode simulasi, total dibayar sama dengan grand total, kembalian Rp0.
10. **Struk:** cetak struk setelah transaksi; gunakan dialog print browser dan pilih printer atau Save as PDF.
11. **Riwayat/laporan:** invoice muncul di riwayat; omzet hari ini/bulan ini bertambah sesuai total transaksi.
12. **Stok kritis:** produk dengan stok kurang dari 5 ditandai merah, termasuk stok 0–4.

## Catatan untuk penggunaan nyata
- Tambahkan autentikasi dan hak akses kasir/admin, CSRF protection, audit log, backup terjadwal, dan HTTPS sebelum dipakai produksi.
- Jika perlu pembayaran QRIS/kartu sungguhan, integrasikan payment gateway dan verifikasi callback/webhook server-side sebelum menandai transaksi lunas.
- PPN di aplikasi ini diterapkan sebagai perhitungan praktikum sesuai permintaan (11% subtotal). Pastikan perlakuan pajak sesuai aturan yang berlaku untuk usaha Anda.


## Login dan profil pengguna
- Halaman pertama aplikasi adalah form login; dashboard tidak ditampilkan sebelum autentikasi sukses.
- Login memverifikasi hash password pada tabel `users`, meregenerasi ID sesi, dan menampilkan nama serta peran pengguna.
- Tombol Keluar menghapus sesi. Endpoint API selain login/logout/status memerlukan sesi aktif.
- Pastikan `upgrade.sql` sudah dijalankan agar tabel `users` tersedia. Akun demo: `admin` / `password` dan `kasir` / `password`.
- Uji: buka aplikasi dalam jendela privat, pastikan halaman login tampil; coba password salah; login dengan akun demo; buka `admin.php`; logout; lalu coba akses `admin.php` langsung dan pastikan diarahkan ke login.
