
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireLogin();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $type = $_POST['type'] ?? '';
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $note = trim($_POST['note'] ?? '');

    if (!$productId || !in_array($type, ['IN', 'OUT'], true)
        || !$quantity || $quantity < 1) {
        $message = 'Periksa produk, jenis transaksi, dan jumlah.';
        $messageType = 'error';
    } elseif (strlen($note) > 255) {
        $message = 'Catatan maksimal 255 karakter.';
        $messageType = 'error';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                throw new RuntimeException('Produk tidak ditemukan.');
            }

            if ($type === 'OUT' && $quantity > (int) $product['stock']) {
                throw new RuntimeException(
                    'Stok tidak cukup. Stok tersedia: ' . (int) $product['stock']
                );
            }

            $sql = $type === 'IN'
                ? 'UPDATE products SET stock = stock + ? WHERE id = ?'
                : 'UPDATE products SET stock = stock - ? WHERE id = ?';

            $update = $pdo->prepare($sql);
            $update->execute([$quantity, $productId]);

            $insert = $pdo->prepare(
                'INSERT INTO stock_movements
                (product_id, user_id, type, quantity, note)
                VALUES (?, ?, ?, ?, ?)'
            );

            $insert->execute([
                $productId,
                (int) $_SESSION['user']['id'],
                $type,
                $quantity,
                $note !== '' ? $note : null
            ]);

            $pdo->commit();

            $successMessage = $type === 'IN'
                ? 'Transaksi stok masuk berhasil disimpan.'
                : 'Transaksi stok keluar berhasil disimpan.';

            header('Location: index.php?success=' . urlencode($successMessage));
            exit;
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $e->getMessage();
            $messageType = 'error';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $message = 'Transaksi gagal disimpan. Periksa data dan coba kembali.';
            $messageType = 'error';
        }
    }
}

if (isset($_GET['success'])) {
    $message = (string) $_GET['success'];
}

$products = $pdo->query(
    'SELECT id, sku, name, stock FROM products ORDER BY name ASC'
)->fetchAll();

$stats = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out,
        COUNT(*) AS total_transactions
    FROM stock_movements
")->fetch();

$movements = $pdo->query("
    SELECT sm.id, sm.type, sm.quantity, sm.note, sm.created_at,
           p.sku, p.name AS product_name, u.full_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    ORDER BY sm.id DESC
    LIMIT 100
")->fetchAll();

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaksi Stok | InventoryPro</title>
<style>
:root {
    --navy:#14213d; --blue:#2563eb; --bg:#f4f7fb;
    --muted:#718096; --line:#e6ebf2; --green:#12805c; --red:#c24141;
}
* { box-sizing:border-box; }
body {
    margin:0; background:var(--bg); font-family:Segoe UI,Arial,sans-serif;
    color:#172033;
}
.layout { max-width:1240px; margin:auto; padding:30px 24px 48px; }
.top {
    display:flex; justify-content:space-between; align-items:center;
    gap:16px; flex-wrap:wrap; margin-bottom:26px;
}
.eyebrow {
    font-size:12px; letter-spacing:1.5px; text-transform:uppercase;
    color:var(--blue); font-weight:800;
}
.top h1 { font-size:30px; margin:7px 0 6px; color:var(--navy); }
.sub { margin:0; color:var(--muted); font-size:14px; }
.actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn {
    display:inline-flex; align-items:center; justify-content:center;
    border:0; border-radius:10px; padding:11px 15px;
    background:var(--blue); color:white; text-decoration:none;
    font-weight:700; font-size:13px; cursor:pointer;
}
.btn.secondary { background:#e8eefb; color:var(--navy); }
.btn.dark { background:var(--navy); }
.stats {
    display:grid; grid-template-columns:repeat(3,minmax(0,1fr));
    gap:16px; margin-bottom:22px;
}
.stat,.panel {
    background:#fff; border:1px solid var(--line); border-radius:17px;
    box-shadow:0 5px 20px #1b2a4408;
}
.stat { padding:21px; display:flex; align-items:center; gap:15px; }
.stat-icon {
    width:46px; height:46px; border-radius:13px; display:grid;
    place-items:center; font-size:22px; font-weight:800;
    background:#eaf1ff; color:var(--blue);
}
.stat-icon.green { background:#e5f7ef; color:var(--green); }
.stat-icon.red { background:#fff0ee; color:var(--red); }
.stat-label { font-size:13px; color:var(--muted); margin-bottom:6px; }
.stat-value { font-size:25px; font-weight:800; color:var(--navy); }
.content {
    display:grid; grid-template-columns:minmax(280px,360px) minmax(0,1fr);
    gap:20px; align-items:start;
}
.panel { padding:23px; }
.panel-head { margin-bottom:20px; }
.panel h2 { margin:0 0 7px; color:var(--navy); font-size:18px; }
.panel-head p { margin:0; color:var(--muted); font-size:13px; line-height:1.5; }
.field { margin-bottom:16px; }
.field label {
    display:block; font-size:13px; font-weight:700;
    margin-bottom:8px; color:#344054;
}
.field input,.field select,.field textarea {
    width:100%; border:1px solid #dce3ed; border-radius:10px;
    padding:12px 13px; background:#fff; color:#172033;
    font:inherit; font-size:14px; outline:none;
}
.field input:focus,.field select:focus,.field textarea:focus {
    border-color:var(--blue); box-shadow:0 0 0 3px #2563eb18;
}
.field textarea { resize:vertical; min-height:84px; }
.hint { font-size:12px; color:var(--muted); margin-top:7px; line-height:1.45; }
.submit { width:100%; padding:13px; }
.alert {
    padding:13px 15px; border-radius:11px;
    margin-bottom:18px; font-size:13px; font-weight:600;
}
.alert.success { background:#e8f8f0; color:#126c4c; border:1px solid #c5eedb; }
.alert.error { background:#fff0ef; color:#a83232; border:1px solid #ffd6d2; }
.table-wrap { overflow-x:auto; }
table { border-collapse:collapse; width:100%; min-width:700px; }
th {
    text-align:left; padding:12px 13px; background:#f7f9fc;
    color:#718096; font-size:11px; text-transform:uppercase; letter-spacing:.6px;
}
td { padding:14px 13px; border-bottom:1px solid #edf0f5; font-size:13px; }
tbody tr:hover { background:#fafcff; }
.product-name { font-weight:700; color:#202d45; }
.sku { font-size:11px; color:var(--muted); margin-top:4px; }
.pill {
    display:inline-flex; border-radius:20px; padding:6px 9px;
    font-size:11px; font-weight:800;
}
.pill.in { background:#e5f7ef; color:#11724e; }
.pill.out { background:#fff0ee; color:#b33838; }
.empty { text-align:center; color:var(--muted); padding:30px; }
.table-title {
    display:flex; justify-content:space-between; align-items:center;
    gap:10px; margin-bottom:17px;
}
.count {
    background:#edf2ff; color:#244fc4; border-radius:20px;
    padding:6px 10px; font-size:11px; font-weight:800;
}
.backline { margin-top:20px; color:var(--muted); font-size:12px; }
@media(max-width:850px) {
    .content,.stats { grid-template-columns:1fr; }
    .top h1 { font-size:25px; }
    .layout { padding:22px 14px 35px; }
    .panel { padding:18px; }
}
</style>
</head>
<body>
<div class="layout">
    <header class="top">
        <div>
            <div class="eyebrow">Inventory Management System</div>
            <h1>Transaksi Stok</h1>
            <p class="sub">Catat pergerakan barang dan pantau riwayat persediaan.</p>
        </div>
        <div class="actions">
            <a class="btn secondary" href="../dashboard.php">← Dashboard</a>
            <a class="btn dark" href="../products/index.php">Lihat Produk ↗</a>
        </div>
    </header>

    <?php if ($message !== ''): ?>
        <div class="alert <?= $messageType === 'error' ? 'error' : 'success' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat">
            <div class="stat-icon green">↓</div>
            <div>
                <div class="stat-label">Total Stok Masuk</div>
                <div class="stat-value"><?= number_format((int)$stats['total_in'],0,',','.') ?></div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon red">↑</div>
            <div>
                <div class="stat-label">Total Stok Keluar</div>
                <div class="stat-value"><?= number_format((int)$stats['total_out'],0,',','.') ?></div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon">▤</div>
            <div>
                <div class="stat-label">Jumlah Transaksi</div>
                <div class="stat-value"><?= number_format((int)$stats['total_transactions'],0,',','.') ?></div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="panel">
            <div class="panel-head">
                <h2>Tambah Transaksi</h2>
                <p>Pilih produk, tentukan jenis pergerakan, lalu masukkan jumlah barang.</p>
            </div>

            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div class="field">
                    <label for="product_id">Produk</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">Pilih produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int)$product['id'] ?>">
                                <?= e($product['sku']) ?> · <?= e($product['name']) ?>
                                (stok: <?= (int)$product['stock'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="type">Jenis Transaksi</label>
                    <select id="type" name="type" required>
                        <option value="IN">Stok Masuk</option>
                        <option value="OUT">Stok Keluar</option>
                    </select>
                    <div class="hint">Stok masuk menambah persediaan. Stok keluar mengurangi persediaan.</div>
                </div>

                <div class="field">
                    <label for="quantity">Jumlah Barang</label>
                    <input id="quantity" type="number" name="quantity"
                           min="1" step="1" placeholder="Contoh: 10" required>
                </div>

                <div class="field">
                    <label for="note">Catatan <span style="font-weight:400;color:#718096">(opsional)</span></label>
                    <textarea id="note" name="note" maxlength="255"
                              placeholder="Contoh: Pembelian barang dari supplier"></textarea>
                </div>

                <button class="btn submit" type="submit">Simpan Transaksi</button>
                <?php if (empty($products)): ?>
                    <p class="hint">Belum ada produk. Tambahkan produk terlebih dahulu.</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <div class="table-title">
                <div>
                    <h2>Riwayat Transaksi</h2>
                    <p class="sub">Menampilkan maksimal 100 transaksi terbaru.</p>
                </div>
                <span class="count"><?= count($movements) ?> catatan</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produk</th><th>Jenis</th><th>Jumlah</th>
                        <th>Petugas</th><th>Waktu</th><th>Catatan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="6" class="empty">Belum ada transaksi stok.</td></tr>
                    <?php else: foreach ($movements as $movement): ?>
                        <tr>
                            <td>
                                <div class="product-name"><?= e($movement['product_name']) ?></div>
                                <div class="sku"><?= e($movement['sku']) ?></div>
                            </td>
                            <td>
                                <span class="pill <?= $movement['type'] === 'IN' ? 'in' : 'out' ?>">
                                    <?= $movement['type'] === 'IN' ? '↓ Masuk' : '↑ Keluar' ?>
                                </span>
                            </td>
                            <td><strong><?= number_format((int)$movement['quantity'],0,',','.') ?></strong></td>
                            <td><?= e($movement['full_name']) ?></td>
                            <td><?= e(date('d-m-Y H:i',strtotime($movement['created_at']))) ?></td>
                            <td><?= e($movement['note'] ?: 'Tidak ada catatan') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="backline">Riwayat disimpan sebagai catatan setiap perubahan stok.</div>
        </div>
    </section>
</div>
</body>
</html>
