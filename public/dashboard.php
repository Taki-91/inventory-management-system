
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';

requireLogin();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(price * stock), 0) AS inventory_value,
        COALESCE(SUM(CASE WHEN stock <= minimum_stock THEN 1 ELSE 0 END), 0) AS low_stock
    FROM products
")->fetch();

$movementStats = $pdo->query("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out
    FROM stock_movements
")->fetch();

$recentProducts = $pdo->query("
    SELECT p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    ORDER BY p.id DESC
    LIMIT 5
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
    <title>Dashboard Inventory</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2937;
        }
        .container { max-width: 1100px; margin: auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .menu { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 24px; }
        a, button {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 6px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }
        .logout { background: #dc2626; }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .card, .panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px #0000000d;
        }
        .card p { color: #6b7280; margin-bottom: 8px; }
        .card h2 { margin: 0; font-size: 25px; }
        .panel { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; }
        .warning { color: #dc2626; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <h1>Dashboard Inventory</h1>
            <p>
                Selamat datang, <?= e($_SESSION['user']['full_name']) ?>.
                Role: <?= e($_SESSION['user']['role']) ?>
            </p>
        </div>

        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button class="logout" type="submit">Logout</button>
        </form>
    </div>

    <div class="menu">
        <a href="products/index.php">Data Produk</a>
        <a href="stock/index.php">Transaksi Stok</a>

        <?php if ($_SESSION['user']['role'] === 'admin'): ?>
            <a href="categories/index.php">Kategori</a>
            <a href="suppliers/index.php">Supplier</a>
            <a href="product-suppliers/index.php">Produk-Supplier</a>
        <?php endif; ?>
    </div>

    <div class="cards">
        <div class="card">
            <p>Total Produk</p>
            <h2><?= e($stats['total_products']) ?></h2>
        </div>
        <div class="card">
            <p>Nilai Persediaan</p>
            <h2>Rp <?= number_format((float) $stats['inventory_value'], 0, ',', '.') ?></h2>
        </div>
        <div class="card">
            <p>Produk Stok Menipis</p>
            <h2><?= e($stats['low_stock']) ?></h2>
        </div>
        <div class="card">
            <p>Total Stok Masuk</p>
            <h2><?= e($movementStats['total_in']) ?></h2>
        </div>
        <div class="card">
            <p>Total Stok Keluar</p>
            <h2><?= e($movementStats['total_out']) ?></h2>
        </div>
    </div>

    <div class="panel">
        <h2>5 Produk Terbaru</h2>
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Stok</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recentProducts)): ?>
                <tr>
                    <td colspan="5">Belum ada data produk.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentProducts as $product): ?>
                    <tr>
                        <td><?= e($product['sku']) ?></td>
                        <td><?= e($product['name']) ?></td>
                        <td><?= e($product['category_name']) ?></td>
                        <td><?= e($product['stock']) ?></td>
                        <td>
                            <?php if ((int) $product['stock'] <= (int) $product['minimum_stock']): ?>
                                <span class="warning">Stok menipis</span>
                            <?php else: ?>
                                Aman
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
