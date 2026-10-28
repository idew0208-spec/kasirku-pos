<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

session_start();

if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'];

$displayName = $user['display_name']
    ?? $user['username']
    ?? 'Pengguna';

$initials = strtoupper(substr((string) $displayName, 0, 2));
$role = strtoupper((string) ($user['role'] ?? ''));
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>KasirKu · Pengelolaan</title>

    <link rel="stylesheet" href="style.css">

    <style>
        .admin-wrap {
            max-width: 1200px;
            margin: auto;
            padding: 28px;
        }

        .admin-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(
                auto-fit,
                minmax(280px, 1fr)
            );
            gap: 16px;
            margin-top: 22px;
        }

        .admin-card {
            background: #fff;
            border: 1px solid #e4ebe7;
            border-radius: 16px;
            padding: 20px;
        }

        .admin-card h2 {
            font-size: 18px;
            margin: 0 0 14px;
        }

        .admin-card input,
        .admin-card select,
        .admin-card textarea {
            width: 100%;
            border: 1px solid #dce5df;
            border-radius: 9px;
            padding: 11px;
            margin: 5px 0 10px;
            font: inherit;
            box-sizing: border-box;
        }

        .admin-card button,
        .admin-link {
            background: #16845b;
            color: white;
            border: 0;
            border-radius: 9px;
            padding: 11px 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        .admin-card .light {
            background: #eef7f1;
            color: #137a53;
        }

        .admin-list {
            font-size: 13px;
            color: #52645a;
            max-height: 220px;
            overflow: auto;
        }

        .hint {
            font-size: 12px;
            color: #7d8b83;
            line-height: 1.5;
        }

        .admin-card form {
            margin: 0;
        }

        .admin-toast {
            padding: 10px;
            background: #eaf7ef;
            border-radius: 8px;
            margin-top: 12px;
            display: none;
        }

        .full {
            grid-column: 1 / -1;
        }

        .inline-fields {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        @media (max-width: 600px) {
            .admin-wrap {
                padding: 14px;
            }

            .inline-fields {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<main class="admin-wrap">

    <!-- Header -->
    <div class="admin-head">
        <div>
            <div class="eyebrow">KASIRKU · ADMINISTRASI</div>
            <h1>Pengelolaan Toko</h1>
            <p>
                Data pelanggan, pemasok, pembelian stok,
                kas, dan laporan.
            </p>
        </div>

        <div>
            <span class="hint">
                Login: <?= e($displayName) ?>
                · <?= e($initials) ?>
                <?= e($role) ?>
            </span>

            <a class="admin-link" href="index.php">
                ← Kembali ke kasir
            </a>

            <button
                class="admin-link"
                id="adminLogout"
                type="button"
            >
                Keluar
            </button>
        </div>
    </div>

    <!-- Kumpulan fitur administrasi -->
    <div class="admin-grid">

        <!-- Login -->
        <section class="admin-card">
            <h2>Login admin / kasir</h2>

            <form id="loginForm">
                <input
                    name="username"
                    placeholder="Username (admin / kasir)"
                    required
                >

                <input
                    name="password"
                    type="password"
                    placeholder="Password"
                    required
                >

                <button type="submit">Masuk</button>

                <button
                    type="button"
                    class="light"
                    id="logoutBtn"
                >
                    Keluar
                </button>
            </form>

            <p class="hint">
                Akun demo setelah impor upgrade.sql:
                admin / password atau kasir / password.
                Ganti password sebelum penggunaan nyata.
            </p>

            <div id="loginStatus" class="admin-toast"></div>
        </section>

        <!-- Pelanggan -->
        <section class="admin-card">
            <h2>Tambah pelanggan</h2>

            <form id="customerForm">
                <input
                    name="name"
                    placeholder="Nama pelanggan"
                    required
                >

                <input
                    name="phone"
                    placeholder="Nomor telepon"
                >

                <input
                    name="notes"
                    placeholder="Catatan"
                >

                <button type="submit">Simpan pelanggan</button>
            </form>

            <div id="customersList" class="admin-list"></div>
        </section>

        <!-- Pemasok -->
        <section class="admin-card">
            <h2>Tambah pemasok</h2>

            <form id="supplierForm">
                <input
                    name="name"
                    placeholder="Nama pemasok"
                    required
                >

                <input
                    name="phone"
                    placeholder="Nomor telepon"
                >

                <input
                    name="address"
                    placeholder="Alamat"
                >

                <button type="submit">Simpan pemasok</button>
            </form>

            <div id="suppliersList" class="admin-list"></div>
        </section>

        <!-- Pembelian stok -->
        <section class="admin-card">
            <h2>Pembelian / stok masuk</h2>

            <form id="purchaseForm">
                <select name="supplier_id" id="supplierSelect">
                    <option value="">
                        Pilih pemasok (opsional)
                    </option>
                </select>

                <input
                    name="product_id"
                    type="number"
                    min="1"
                    placeholder="ID produk (lihat inventaris)"
                    required
                >

                <div class="inline-fields">
                    <input
                        name="quantity"
                        type="number"
                        min="1"
                        placeholder="Jumlah"
                        required
                    >

                    <input
                        name="unit_cost"
                        type="number"
                        min="0"
                        placeholder="Harga modal/unit"
                        required
                    >
                </div>

                <input
                    name="notes"
                    placeholder="Catatan pembelian"
                >

                <button type="submit">Catat stok masuk</button>
            </form>

            <p class="hint">
                Setiap pembelian yang berhasil menambah stok
                produk secara transaksional.
            </p>
        </section>

        <!-- Pengeluaran -->
        <section class="admin-card">
            <h2>Pengeluaran toko</h2>

            <form id="expenseForm">
                <input
                    name="description"
                    placeholder="Contoh: listrik toko"
                    required
                >

                <div class="inline-fields">
                    <input
                        name="amount"
                        type="number"
                        min="1"
                        placeholder="Nominal Rupiah"
                        required
                    >

                    <input
                        name="category"
                        placeholder="Kategori"
                        value="Operasional"
                    >
                </div>

                <button type="submit">Simpan pengeluaran</button>
            </form>

            <div id="expensesList" class="admin-list"></div>
        </section>

        <!-- Kas -->
        <section class="admin-card">
            <h2>Kas masuk / keluar</h2>

            <form id="cashForm">
                <select name="movement_type">
                    <option value="opening">Modal awal</option>
                    <option value="in">Kas masuk</option>
                    <option value="out">Kas keluar</option>
                    <option value="closing">Kas akhir</option>
                </select>

                <input
                    name="amount"
                    type="number"
                    min="0"
                    placeholder="Nominal Rupiah"
                    required
                >

                <input
                    name="note"
                    placeholder="Catatan"
                >

                <button type="submit">
                    Catat pergerakan kas
                </button>
            </form>

            <div id="cashList" class="admin-list"></div>
        </section>

        <!-- Laporan laba rugi -->
        <section class="admin-card">
            <h2>Laporan laba rugi (estimasi)</h2>

            <form id="profitForm">
                <div class="inline-fields">
                    <input
                        name="from"
                        type="date"
                        value="<?= date('Y-m-01') ?>"
                        required
                    >

                    <input
                        name="to"
                        type="date"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <button type="submit">Hitung laporan</button>
            </form>

            <div id="profitResult" class="admin-list"></div>

            <p class="hint">
                Akurasi laba bergantung pada harga modal produk
                yang diisi di database. Estimasi laba bersih =
                penjualan − HPP − pengeluaran.
            </p>
        </section>

        <!-- Ekspor dan backup -->
        <section class="admin-card">
            <h2>Ekspor dan backup</h2>

            <p>
                <a
                    class="admin-link"
                    href="api.php?action=export_sales"
                >
                    Unduh riwayat CSV
                </a>
            </p>

            <p>
                <a
                    class="admin-link"
                    href="api.php?action=backup"
                >
                    Unduh backup SQL
                </a>
            </p>

            <p class="hint">
                Backup berisi data dan struktur tabel aplikasi.
                Simpan di lokasi aman. File backup belum
                menyediakan restore otomatis dari browser.
            </p>
        </section>

        <!-- Barcode -->
        <section class="admin-card">
            <h2>Barcode produk</h2>

            <p class="hint">
                SKU yang ada di inventaris dapat digunakan
                sebagai barcode produk. Scanner USB biasanya
                bertindak seperti keyboard: fokuskan kolom
                pencarian kasir lalu pindai barcode/SKU.
            </p>

            <a class="admin-link" href="index.php">
                Buka pencarian kasir
            </a>
        </section>

    </div>

    <!-- Catatan keamanan -->
    <p class="hint" style="margin-top: 24px">
        Penting: login di halaman ini disediakan sebagai
        pengelolaan akun, tetapi belum memblokir halaman
        kasir utama. Jangan gunakan di server publik sebelum
        autentikasi diterapkan menyeluruh, CSRF protection
        dan HTTPS ditambahkan.
    </p>

</main>

<script>
    // Helper
    const $ = (selector) => document.querySelector(selector);

    const money = (amount) =>
        'Rp' + Number(amount || 0).toLocaleString('id-ID');

    async function req(action, body) {
        const response = await fetch(
            'api.php?action=' + action,
            {
                method: body ? 'POST' : 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: body
                    ? JSON.stringify(body)
                    : undefined
            }
        );

        const data = await response.json();

        if (!response.ok || !data.ok) {
            throw Error(data.error || 'Permintaan gagal');
        }

        return data;
    }

    function formData(form) {
        return Object.fromEntries(
            new FormData(form).entries()
        );
    }

    function toast(message) {
        const element = $('#loginStatus');

        element.textContent = message;
        element.style.display = 'block';
    }

    // Memuat data pelanggan, pemasok, pengeluaran, dan kas
    async function loadLists() {
        try {
            let data = await req('customers');

            $('#customersList').innerHTML =
                data.customers
                    .map((item) =>
                        `${item.name} · ${item.phone || 'tanpa telepon'}<br>`
                    )
                    .join('') || 'Belum ada pelanggan';

            data = await req('suppliers');

            $('#suppliersList').innerHTML =
                data.suppliers
                    .map((item) =>
                        `${item.name} · ${item.phone || 'tanpa telepon'}<br>`
                    )
                    .join('') || 'Belum ada pemasok';

            $('#supplierSelect').innerHTML =
                '<option value="">Pilih pemasok (opsional)</option>' +
                data.suppliers
                    .map((item) =>
                        `<option value="${item.id}">${item.name}</option>`
                    )
                    .join('');

            data = await req('expenses');

            $('#expensesList').innerHTML =
                data.expenses
                    .slice(0, 10)
                    .map((item) =>
                        `${item.description}: ${money(item.amount)}<br>`
                    )
                    .join('') || 'Belum ada pengeluaran';

            data = await req('cash');

            $('#cashList').innerHTML =
                data.movements
                    .slice(0, 10)
                    .map((item) =>
                        `${item.movement_type}: ${money(item.amount)} · ${item.created_at}<br>`
                    )
                    .join('') || 'Belum ada pergerakan kas';

        } catch (error) {
            toast(
                error.message +
                ' Pastikan upgrade.sql sudah diimpor.'
            );
        }
    }

    // Login
    $('#loginForm').addEventListener('submit', async (event) => {
        event.preventDefault();

        try {
            const data = await req(
                'login',
                formData(event.target)
            );

            toast(
                'Login berhasil: ' +
                data.user.name +
                ' (' + data.user.role + ')'
            );
        } catch (error) {
            toast(error.message);
        }
    });

    // Logout dari form login
    $('#logoutBtn').addEventListener('click', async () => {
        try {
            await req('logout');
            toast('Anda sudah keluar.');
        } catch (error) {
            toast(error.message);
        }
    });

    // Form pelanggan, pemasok, pengeluaran, dan kas
    const formActions = [
        ['customerForm', 'customers'],
        ['supplierForm', 'suppliers'],
        ['expenseForm', 'expenses'],
        ['cashForm', 'cash']
    ];

    for (const [formId, action] of formActions) {
        $('#' + formId).addEventListener(
            'submit',
            async (event) => {
                event.preventDefault();

                try {
                    await req(
                        action,
                        formData(event.target)
                    );

                    event.target.reset();

                    toast('Data berhasil disimpan.');
                    await loadLists();

                } catch (error) {
                    toast(error.message);
                }
            }
        );
    }

    // Pembelian dan stok masuk
    $('#purchaseForm').addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            const form = formData(event.target);

            try {
                const data = await req('purchase', {
                    supplier_id: form.supplier_id || null,
                    notes: form.notes,
                    items: [
                        {
                            product_id: Number(form.product_id),
                            quantity: Number(form.quantity),
                            unit_cost: Number(form.unit_cost)
                        }
                    ]
                });

                toast(
                    'Stok masuk berhasil: ' +
                    data.reference_no +
                    ' · ' + money(data.total_cost)
                );

                event.target.reset();
                await loadLists();

            } catch (error) {
                toast(error.message);
            }
        }
    );

    // Laporan laba rugi
    $('#profitForm').addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            try {
                const form = formData(event.target);

                const params = new URLSearchParams({
                    action: 'profit_report',
                    from: form.from,
                    to: form.to
                });

                const response = await fetch(
                    'api.php?' + params
                );

                const data = await response.json();

                if (!response.ok || !data.ok) {
                    throw Error(
                        data.error || 'Laporan gagal'
                    );
                }

                const report = data.report;

                $('#profitResult').innerHTML = `
                    Penjualan: ${money(report.gross_sales)}<br>
                    HPP tercatat: ${money(report.cogs)}<br>
                    Laba kotor: ${money(report.gross_profit)}<br>
                    Pengeluaran: ${money(report.expenses)}<br>
                    <strong>
                        Estimasi laba bersih:
                        ${money(report.net_estimate)}
                    </strong>
                `;

            } catch (error) {
                toast(error.message);
            }
        }
    );

    // Logout utama
    $('#adminLogout')?.addEventListener(
        'click',
        async () => {
            try {
                const response = await fetch(
                    'api.php?action=auth_logout',
                    {
                        method: 'POST'
                    }
                );

                if (!response.ok) {
                    throw Error('Logout gagal.');
                }

                location.href = 'index.php';

            } catch (error) {
                toast(error.message);
            }
        }
    );

    // Muat data saat halaman dibuka
    loadLists();
</script>

</body>
</html>
