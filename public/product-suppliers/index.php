
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);

        if (!$productId || !$supplierId) {
            $message = 'Data relasi yang akan dihapus tidak valid.';
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare(
                'DELETE FROM product_suppliers WHERE product_id = ? AND supplier_id = ?'
            );
            $stmt->execute([$productId, $supplierId]);

            header('Location: index.php?success=' . urlencode(
                $stmt->rowCount()
                    ? 'Relasi produk-supplier berhasil dihapus.'
                    : 'Relasi tidak ditemukan.'
            ));
            exit;
        }
    } else {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $supplierId = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
        $priceRaw = trim($_POST['supplier_price'] ?? '');

        if (!$productId || !$supplierId || $priceRaw === ''
            || !is_numeric($priceRaw) || (float)$priceRaw < 0) {
            $message = 'Pilih produk, supplier, dan masukkan harga yang valid.';
            $messageType = 'error';
        } else {
            try {
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM product_suppliers
                     WHERE product_id = ? AND supplier_id = ?'
                );
                $check->execute([$productId, $supplierId]);

                if ((int)$check->fetchColumn() > 0) {
                    $message = 'Kombinasi produk dan supplier tersebut sudah terdaftar.';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO product_suppliers
                         (product_id, supplier_id, supplier_price)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->execute([
                        $productId,
                        $supplierId,
                        number_format((float)$priceRaw, 2, '.', '')
                    ]);

                    header('Location: index.php?success=' . urlencode(
                        'Relasi produk-supplier berhasil ditambahkan.'
                    ));
                    exit;
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $message = 'Relasi gagal disimpan. Pastikan produk dan supplier tersedia.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['success'])) {
    $message = (string) $_GET['success'];
}

$products = $pdo->query(
    'SELECT id, sku, name FROM products ORDER BY name ASC'
)->fetchAll();

$suppliers = $pdo->query(
    'SELECT id, name FROM suppliers ORDER BY name ASC'
)->fetchAll();

$stats = $pdo->query("
    SELECT COUNT(*) AS total_relations,
           COUNT(DISTINCT product_id) AS products_linked,
           COUNT(DISTINCT supplier_id) AS suppliers_linked
    FROM product_suppliers
")->fetch();

$relations = $pdo->query("
    SELECT ps.product_id, ps.supplier_id, ps.supplier_price,
           p.sku, p.name AS product_name, s.name AS supplier_name
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN suppliers s ON s.id = ps.supplier_id
    ORDER BY p.name ASC, s.name ASC
")->fetchAll();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produk-Supplier | InventoryPro</title>
<style>
:root {
    --navy:#14213d; --blue:#2563eb; --bg:#f4f7fb;
    --muted:#718096; --line:#e6ebf2; --green:#12805c;
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
.sub { margin:0; color:var(--muted); font-size:14px; line-height:1.5; }
.actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn {
    display:inline-flex; align-items:center; justify-content:center;
    border:0; border-radius:10px; padding:11px 15px;
    background:var(--blue); color:white; text-decoration:none;
    font-weight:700; font-size:13px; cursor:pointer;
}
.btn.secondary { background:#e8eefb; color:var(--navy); }
.btn.dark { background:var(--navy); }
.btn.danger { background:#fff0ee; color:#b33838; padding:8px 10px; }
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
    place-items:center; font-size:21px; font-weight:800;
    background:#eaf1ff; color:var(--blue);
}
.stat-icon.green { background:#e5f7ef; color:var(--green); }
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
.field input,.field select {
    width:100%; border:1px solid #dce3ed; border-radius:10px;
    padding:12px 13px; background:#fff; color:#172033;
    font:inherit; font-size:14px; outline:none;
}
.field input:focus,.field select:focus {
    border-color:var(--blue); box-shadow:0 0 0 3px #2563eb18;
}
.hint { font-size:12px; color:var(--muted); margin-top:7px; line-height:1.45; }
.submit { width:100%; padding:13px; }
.alert {
    padding:13px 15px; border-radius:11px;
    margin-bottom:18px; font-size:13px; font-weight:600;
}
.alert.success { background:#e8f8f0; color:#126c4c; border:1px solid #c5eedb; }
.alert.error { background:#fff0ef; color:#a83232; border:1px solid #ffd6d2; }
.table-wrap { overflow-x:auto; }
table { border-collapse:collapse; width:100%; min-width:640px; }
th {
    text-align:left; padding:12px 13px; background:#f7f9fc;
    color:#718096; font-size:11px; text-transform:uppercase; letter-spacing:.6px;
}
td { padding:14px 13px; border-bottom:1px solid #edf0f5; font-size:13px; }
tbody tr:hover { background:#fafcff; }
.product-name { font-weight:700; color:#202d45; }
.sku { font-size:11px; color:var(--muted); margin-top:4px; }
.price { font-weight:800; color:var(--navy); white-space:nowrap; }
.supplier-tag {
    display:inline-flex; padding:6px 9px; background:#e8f8f0;
    color:#126c4c; border-radius:20px; font-size:11px; font-weight:800;
}
.empty { text-align:center; color:var(--muted); padding:30px; }
.table-title {
    display:flex; justify-content:space-between; align-items:center;
    gap:10px; margin-bottom:17px;
}
.count {
    background:#edf2ff; color:#244fc4; border-radius:20px;
    padding:6px 10px; font-size:11px; font-weight:800;
}
.delete-form { margin:0; }
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
            <h1>Relasi Produk-Supplier</h1>
            <p class="sub">Kelola supplier untuk setiap produk dan simpan harga penawaran mereka.</p>
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
            <div class="stat-icon">⇄</div>
            <div>
                <div class="stat-label">Total Relasi</div>
                <div class="stat-value"><?= number_format((int)$stats['total_relations'],0,',','.') ?></div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon green">▦</div>
            <div>
                <div class="stat-label">Produk Terhubung</div>
                <div class="stat-value"><?= number_format((int)$stats['products_linked'],0,',','.') ?></div>
            </div>
        </div>
        <div class="stat">
            <div class="stat-icon">♙</div>
            <div>
                <div class="stat-label">Supplier Terhubung</div>
                <div class="stat-value"><?= number_format((int)$stats['suppliers_linked'],0,',','.') ?></div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="panel">
            <div class="panel-head">
                <h2>Hubungkan Produk</h2>
                <p>Satu produk dapat memiliki beberapa supplier. Setiap pasangan hanya bisa didaftarkan satu kali.</p>
            </div>

            <form method="POST" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="add">

                <div class="field">
                    <label for="product_id">Pilih Produk</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">Pilih produk</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= (int)$product['id'] ?>">
                                <?= e($product['sku']) ?> · <?= e($product['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="supplier_id">Pilih Supplier</label>
                    <select id="supplier_id" name="supplier_id" required>
                        <option value="">Pilih supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int)$supplier['id'] ?>">
                                <?= e($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="supplier_price">Harga dari Supplier (Rp)</label>
                    <input id="supplier_price" type="number" name="supplier_price"
                           min="0" step="0.01" placeholder="Contoh: 125000" required>
                    <div class="hint">Masukkan harga satuan dari supplier.</div>
                </div>

                <button class="btn submit" type="submit">＋ Simpan Relasi</button>

                <?php if (empty($products) || empty($suppliers)): ?>
                    <p class="hint">Pastikan sudah ada minimal satu produk dan satu supplier.</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <div class="table-title">
                <div>
                    <h2>Daftar Relasi</h2>
                    <p class="sub">Produk yang terhubung dengan supplier beserta harga satuannya.</p>
                </div>
                <span class="count"><?= count($relations) ?> relasi</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produk</th><th>Supplier</th><th>Harga Supplier</th>
                        <th>Status</th><th>Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($relations)): ?>
                        <tr><td colspan="5" class="empty">Belum ada relasi. Gunakan form untuk menghubungkan produk dengan supplier.</td></tr>
                    <?php else: foreach ($relations as $relation): ?>
                        <tr>
                            <td>
                                <div class="product-name"><?= e($relation['product_name']) ?></div>
                                <div class="sku"><?= e($relation['sku']) ?></div>
                            </td>
                            <td><?= e($relation['supplier_name']) ?></td>
                            <td class="price">Rp <?= number_format((float)$relation['supplier_price'],0,',','.') ?></td>
                            <td><span class="supplier-tag">Terhubung</span></td>
                            <td>
                                <form class="delete-form" method="POST" action="index.php"
                                      onsubmit="return confirm('Hapus relasi produk dan supplier ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= (int)$relation['product_id'] ?>">
                                    <input type="hidden" name="supplier_id" value="<?= (int)$relation['supplier_id'] ?>">
                                    <button class="btn danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="backline">Relasi menggunakan kunci gabungan produk dan supplier untuk mencegah data ganda.</div>
        </div>
    </section>
</div>
</body>
</html>
