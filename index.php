<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
session_start();
$user = $_SESSION['user'] ?? null;
if (!$user) {
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk · KasirKu</title><link rel="stylesheet" href="style.css"></head>
<body class="login-page"><main class="login-card"><div class="login-brand"><span class="brand-icon">k.</span><div><strong>KasirKu</strong><small>RETAIL POS</small></div></div><div class="eyebrow">SELAMAT DATANG</div><h1>Masuk ke KasirKu</h1><p class="login-subtitle">Silakan masuk untuk melanjutkan ke dashboard toko.</p>
<form id="loginPageForm"><label>Username<input name="username" autocomplete="username" placeholder="Masukkan username" required autofocus></label><label>Password<input name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required></label><button class="primary-button" type="submit" id="loginSubmit">Masuk ke dashboard <span>→</span></button><div id="loginError" class="login-error" role="alert"></div></form><div class="login-foot">Akses hanya untuk admin dan kasir terdaftar.</div></main>
<script>
document.getElementById('loginPageForm').addEventListener('submit',async function(e){e.preventDefault();const btn=document.getElementById('loginSubmit'),err=document.getElementById('loginError');btn.disabled=true;err.textContent='';const fd=new FormData(this);try{const r=await fetch('api.php?action=auth_login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:fd.get('username'),password:fd.get('password')})});const d=await r.json();if(!r.ok||!d.ok)throw new Error(d.error||'Login gagal');location.href='index.php';}catch(x){err.textContent=x.message;}finally{btn.disabled=false;}});
</script><script>
document.getElementById('logoutApp')?.addEventListener('click',async()=>{if(!confirm('Yakin ingin keluar dari KasirKu?'))return;await fetch('api.php?action=auth_logout',{method:'POST'});location.href='index.php';});
</script>
</body></html>
<?php exit; } ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>KasirKu — POS Toko Kelontong</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="brand-icon">k.</span><span>Kasir<span class="brand-light">Ku</span><small>RETAIL POS</small></span></div>
    <div class="side-label">MENU UTAMA</div>
    <button class="nav-item active" data-page="kasir"><span>▦</span> Kasir</button>
    <button class="nav-item" data-page="inventaris"><span>▤</span> Inventaris <b class="nav-count" id="lowCount">0</b></button>
    <button class="nav-item" data-page="riwayat"><span>◷</span> Riwayat transaksi</button>
    <button class="nav-item" data-page="laporan"><span>▥</span> Laporan</button>
    <a class="nav-item" href="admin.php" style="text-decoration:none"><span>⚙</span> Pengelolaan</a>
    <div class="sidebar-bottom"><div class="store-avatar">TK</div><div><strong><?= e($user['display_name'] ?? $user['username'] ?? 'Pengguna') ?></strong><?= e(strtoupper($user['role'])) ?></small></div><span class="online-dot"></span></div>
  </aside>
  <main class="main">
    <header class="topbar">
      <button class="mobile-menu" id="mobileMenu" aria-label="Buka menu">☰</button>
      <div><div class="eyebrow" id="todayLabel">TOKO KELONTONG</div>
        <h1><?= ($user['role'] ?? '') === 'admin' ? 'Dashboard Admin' : 'Kasir' ?></h1>
      </div>
      <div class="top-actions">
          <span class="status-pill"><i></i> Sistem aktif</span>

          <?php
          $displayName = $user['display_name'] ?? $user['username'] ?? 'Pengguna';
          $role = $user['role'] ?? 'cashier';
          $initial = strtoupper(substr((string) $displayName, 0, 2));
          ?>

          <div class="profile-user">
              <strong><?= e($user['display_name'] ?? $user['username'] ?? 'Pengguna') ?></strong>
              <small><?= e(strtoupper($role)) ?></small>
          </div>

          <div class="profile-avatar">
              <?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?>
          </div>

          <button class="logout-button" id="logoutApp" title="Keluar">
              Keluar
          </button>
      </div>
    </header>
    <section class="page active" id="page-kasir">
      <div class="welcome-row"><div><h2>Mulai transaksi <span class="wave">✦</span></h2><p>Pilih produk untuk menambahkannya ke keranjang.</p></div><div class="date-chip" id="dateChip">Hari ini</div></div>
      <div class="pos-layout">
        <div class="catalog-panel">
          <div class="search-row"><label class="search-box"><span>⌕</span><input id="searchInput" type="search" placeholder="Cari nama produk atau kode..." autocomplete="off"><kbd>/</kbd></label><button class="icon-button" id="refreshProducts" title="Muat ulang">↻</button></div>
          <div class="category-tabs" id="categoryTabs"><button class="category active" data-category="Semua">Semua produk</button></div>
          <div class="catalog-meta"><span id="productCount">Memuat produk…</span><span class="stock-hint"><i></i> Stok tersedia</span></div>
          <div class="product-grid" id="productGrid"><div class="loading">Memuat katalog produk…</div></div>
        </div>
        <aside class="cart-panel">
          <div class="cart-heading"><div><h3>Keranjang <span class="cart-bubble" id="cartCount">0</span></h3><p>Detail pesanan saat ini</p></div><button class="text-button" id="clearCart">Kosongkan</button></div>
          <div class="cart-items" id="cartItems"><div class="empty-cart"><div class="empty-icon">＋</div><strong>Keranjang masih kosong</strong><p>Klik produk untuk mulai transaksi.</p></div></div>
          <div class="cart-footer">
            <div class="discount-controls"><label for="discountType">Diskon</label><div class="discount-inputs"><select id="discountType"><option value="none">Tanpa diskon</option><option value="amount">Nominal (Rp)</option><option value="percent">Persentase (%)</option></select><input id="discountInput" type="number" min="0" value="0" aria-label="Nilai diskon" disabled></div></div>
            <div class="summary-line"><span>Subtotal</span><strong id="subtotal">Rp0</strong></div>
            <div class="summary-line"><span>PPN <em>11%</em></span><strong id="tax">Rp0</strong></div>
            <div class="summary-line discount-line"><span>Diskon</span><strong id="discountAmount">− Rp0</strong></div>
            <div class="grand-total"><span>Total pembayaran</span><strong id="grandTotal">Rp0</strong></div>
            <button class="primary-button checkout-button" id="openPayment" disabled>Proses pembayaran <span>→</span></button>
            <div class="secure-note">⌁ Transaksi tersimpan dengan aman</div>
          </div>
        </aside>
      </div>
    </section>
    <section class="page" id="page-inventaris">
      <div class="page-intro"><div><h2>Inventaris produk</h2><p>Pantau jumlah stok dan harga seluruh barang.</p></div><button class="secondary-button" id="refreshInventory">↻ Muat ulang</button></div>
      <div class="stat-grid compact-stats"><div class="stat-card"><span>Total produk</span><strong id="invTotal">—</strong><small>SKU terdaftar</small></div><div class="stat-card"><span>Stok kritis</span><strong id="invLow" class="red-text">—</strong><small>Stok di bawah 5 pcs</small></div><div class="stat-card"><span>Total unit stok</span><strong id="invUnits">—</strong><small>Seluruh produk</small></div></div>
      <div class="table-card"><div class="table-toolbar"><h3>Daftar barang</h3><input class="table-search" id="inventorySearch" placeholder="Cari produk..." type="search"></div><div class="table-scroll"><table><thead><tr><th>PRODUK</th><th>KATEGORI</th><th>HARGA</th><th>STOK</th><th>STATUS</th></tr></thead><tbody id="inventoryBody"><tr><td colspan="5">Memuat data…</td></tr></tbody></table></div></div>
    </section>
    <section class="page" id="page-riwayat">
      <div class="page-intro"><div><h2>Riwayat transaksi</h2><p>Daftar transaksi sukses yang tercatat di sistem.</p></div><button class="secondary-button" id="refreshHistory">↻ Muat ulang</button></div>
      <div class="table-card"><div class="table-toolbar"><h3>Transaksi terbaru</h3><input class="table-search" id="historySearch" placeholder="Cari nomor invoice..." type="search"></div><div class="table-scroll"><table><thead><tr><th>INVOICE / WAKTU</th><th>ITEM</th><th>METODE</th><th>TOTAL</th><th>STRUK</th></tr></thead><tbody id="historyBody"><tr><td colspan="5">Memuat data…</td></tr></tbody></table></div></div>
    </section>
    <section class="page" id="page-laporan">
      <div class="page-intro"><div><h2>Laporan penjualan</h2><p>Ringkasan kinerja toko berdasarkan transaksi tersimpan.</p></div><button class="secondary-button" id="refreshReport">↻ Perbarui laporan</button></div>
      <div class="stat-grid"><div class="stat-card report-stat"><span>Omzet hari ini</span><strong id="reportToday">Rp0</strong><small id="reportTodayCount">0 transaksi</small></div><div class="stat-card report-stat"><span>Omzet bulan ini</span><strong id="reportMonth">Rp0</strong><small id="reportMonthCount">0 transaksi</small></div><div class="stat-card"><span>Produk stok kritis</span><strong id="reportLow" class="red-text">0</strong><small>Perlu segera restock</small></div></div>
      <div class="report-note"><div class="note-icon">↗</div><div><strong>Laporan diperbarui dari database</strong><p>Nilai omzet hanya menghitung transaksi dengan status sukses. Gunakan halaman Riwayat Transaksi untuk membuka dan mencetak kembali struk.</p></div></div>
    </section>
  </main>
</div>
<div class="toast" id="toast" role="status"></div>

<div class="modal hidden" id="paymentModal" role="dialog" aria-modal="true" aria-labelledby="paymentTitle">
  <div class="modal-card payment-card">
    <div class="modal-header"><div><span class="eyebrow">LANGKAH TERAKHIR</span><h2 id="paymentTitle">Proses pembayaran</h2><p>Periksa total dan pilih metode pembayaran.</p></div><button class="close-button" id="closePayment" aria-label="Tutup">×</button></div>
    <div class="payment-layout"><div class="payment-left"><div class="payment-total-box"><span>Total yang harus dibayar</span><strong id="paymentTotal">Rp0</strong><small>Sudah termasuk PPN dan diskon</small></div><h3>Metode pembayaran</h3><div class="payment-methods"><button class="payment-method active" data-method="cash"><span class="method-icon">▣</span><span><strong>Cash / Tunai</strong><small>Bayar dengan uang tunai</small></span><i>✓</i></button><button class="payment-method" data-method="qris"><span class="method-icon">▦</span><span><strong>QRIS</strong><small>Simulasi pembayaran QR</small></span><i>✓</i></button><button class="payment-method" data-method="transfer_card"><span class="method-icon">▤</span><span><strong>Transfer / Kartu</strong><small>Simulasi pembayaran non-tunai</small></span><i>✓</i></button></div><div class="simulation-note" id="simulationNote">Pembayaran tunai akan divalidasi berdasarkan nominal uang yang diterima.</div></div>
    <div class="payment-right"><div class="cash-section" id="cashSection"><label class="field-label" for="paidInput">Uang diterima (Rp)</label><div class="money-input"><span>Rp</span><input type="number" id="paidInput" min="0" step="1" inputmode="numeric" placeholder="0"></div><div class="quick-label">Pilih nominal cepat</div><div class="quick-money"><button data-cash="exact">Uang pas</button><button data-cash="10000">10 rb</button><button data-cash="20000">20 rb</button><button data-cash="50000">50 rb</button><button data-cash="100000">100 rb</button></div><div class="change-box"><span>Kembalian</span><strong id="changeValue">Rp0</strong></div><p class="payment-error" id="paymentError"></p></div><div class="noncash-section hidden" id="noncashSection"><div class="noncash-icon">✓</div><h3>Pembayaran non-tunai</h3><p>Mode demo: klik konfirmasi untuk mencatat pembayaran simulasi. Belum terhubung ke payment gateway.</p><div class="summary-line"><span>Nominal pembayaran</span><strong id="noncashTotal">Rp0</strong></div></div>
    <button class="primary-button confirm-button" id="confirmPayment">Konfirmasi bayar <span>→</span></button><button class="cancel-button" id="cancelPayment">Kembali ke keranjang</button></div></div>
  </div>
</div>
<div class="modal hidden" id="receiptModal" role="dialog" aria-modal="true" aria-labelledby="receiptTitle"><div class="modal-card receipt-modal-card"><div class="receipt-success"><div class="success-mark">✓</div><h2 id="receiptTitle">Transaksi berhasil!</h2><p>Pembayaran berhasil dicatat dan stok telah diperbarui.</p></div><div class="receipt-paper" id="receiptContent"></div><div class="receipt-actions"><button class="primary-button" id="printReceipt">Cetak struk <span>⎙</span></button><button class="secondary-button" id="closeReceipt">Selesai</button></div></div></div>
<div id="printArea" class="print-only"></div>
<script src="app.js"></script>
<script>
document.getElementById('logoutApp')?.addEventListener('click',async()=>{if(!confirm('Yakin ingin keluar dari KasirKu?'))return;await fetch('api.php?action=auth_logout',{method:'POST'});location.href='index.php';});
</script>
</body></html>
