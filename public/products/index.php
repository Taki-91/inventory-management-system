<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireLogin();

$user = $_SESSION['user'];
$isAdmin = $user['role'] === 'admin';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Proses hapus produk hanya untuk admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('admin');
    verifyCsrfToken();

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        header('Location: index.php?error=invalid');
        exit;
    }

    // Proses penghapusan sebenarnya ada di delete.php
    // Halaman delete.php juga memeriksa CSRF dan riwayat transaksi.
    $_SESSION['product_delete_csrf'] = csrfToken();

    header('Location: delete.php?id=' . $id);
    exit;
}

// Data kategori untuk filter
$categoryStmt = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name ASC'
);
$categories = $categoryStmt->fetchAll();

// Ambil filter dan pencarian
$search = trim($_GET['search'] ?? '');
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$categoryId = ($categoryId && $categoryId > 0) ? $categoryId : 0;

// Sorting dibatasi pada kolom yang diizinkan
$allowedSort = ['name', 'sku', 'price', 'stock'];
$sort = $_GET['sort'] ?? 'name';

if (!in_array($sort, $allowedSort, true)) {
    $sort = 'name';
}

$direction = strtoupper($_GET['direction'] ?? 'ASC');

if (!in_array($direction, ['ASC', 'DESC'], true)) {
    $direction = 'ASC';
}

// Pagination
$limit = 10;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = ($page && $page > 0) ? $page : 1;
$offset = ($page - 1) * $limit;

// Kondisi pencarian dan filter
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku)';
    $params['search_name'] = '%' . $search . '%';
    $params['search_sku'] = '%' . $search . '%';
}

if ($categoryId > 0) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Hitung jumlah produk untuk pagination
$countSql = "
    SELECT COUNT(*)
    FROM products p
    $whereSql
";

$countStmt = $pdo->prepare($countSql);

foreach ($params as $key => $value) {
    $countStmt->bindValue(':' . $key, $value);
}

$countStmt->execute();
$totalProducts = (int) $countStmt->fetchColumn();

$totalPages = max(1, (int) ceil($totalProducts / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Ambil data produk
$sql = "
    SELECT
        p.id,
        p.sku,
        p.name,
        p.price,
        p.stock,
        p.minimum_stock,
        c.name AS category_name
    FROM products p
    INNER JOIN categories c ON c.id = p.category_id
    $whereSql
    ORDER BY p.$sort $direction
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$products = $stmt->fetchAll();

// URL pagination tetap mempertahankan filter dan pencarian
$queryParams = [
    'search' => $search,
    'category_id' => $categoryId,
    'sort' => $sort,
    'direction' => $direction
];

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #222;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
        }

        h2 { margin-top: 0; }

        a, button {
            display: inline-block;
            padding: 9px 12px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .primary { background: #163b65; color: #fff; }
        .edit { background: #e7a52b; color: #222; }
        .delete { background: #c0392b; color: #fff; }
        .secondary { background: #e5e7eb; color: #222; }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }

        input, select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #163b65;
            color: #fff;
        }

        .message {
            padding: 12px;
            margin-top: 15px;
            border-radius: 5px;
        }

        .ok { background: #d9f3df; color: #205b2c; }
        .err { background: #ffe1e1; color: #8b1e1e; }

        .actions { white-space: nowrap; }
        .actions form { display: inline; }

        .pagination {
            display: flex;
            gap: 8px;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .current {
            background: #163b65;
            color: white;
        }

        .stock-low {
            color: #c0392b;
            font-weight: bold;
        }

        .stock-normal {
            color: #237a3b;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        @media (max-width: 700px) {
            body { padding: 10px; }
            .container { padding: 15px; }
        }
    </style>
</head>

<body>
<div class="container">
    <h2>Data Produk</h2>

    <p>
        Login sebagai:
        <strong><?= e($user['full_name'] ?? $user['username']) ?></strong>
        (<?= e($user['role']) ?>)
    </p>

    <a class="secondary" href="../dashboard.php">Kembali ke Dashboard</a>

    <?php if ($isAdmin): ?>
        <a class="primary" href="create.php">+ Tambah Produk</a>
    <?php endif; ?>

    <?php if ($success === 'created'): ?>
        <div class="message ok">Produk berhasil ditambahkan.</div>
    <?php elseif ($success === 'updated'): ?>
        <div class="message ok">Produk berhasil diperbarui.</div>
    <?php elseif ($success === 'deleted'): ?>
        <div class="message ok">Produk berhasil dihapus.</div>
    <?php endif; ?>

    <?php if ($error === 'invalid'): ?>
        <div class="message err">ID produk tidak valid.</div>
    <?php elseif ($error === 'history' || $error === 'related'): ?>
        <div class="message err">
            Produk tidak dapat dihapus karena masih memiliki riwayat transaksi
            atau hubungan dengan supplier.
        </div>
    <?php elseif ($error === 'notfound'): ?>
        <div class="message err">Produk tidak ditemukan.</div>
    <?php endif; ?>

    <form method="GET" class="filters">
        <input
            type="text"
            name="search"
            placeholder="Cari nama atau SKU..."
            value="<?= e($search) ?>"
        >

        <select name="category_id">
            <option value="">Semua Kategori</option>
            <?php foreach ($categories as $category): ?>
                <option
                    value="<?= (int) $category['id'] ?>"
                    <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>
                >
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="sort">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>
                Nama
            </option>
            <option value="sku" <?= $sort === 'sku' ? 'selected' : '' ?>>
                SKU
            </option>
            <option value="price" <?= $sort === 'price' ? 'selected' : '' ?>>
                Harga
            </option>
            <option value="stock" <?= $sort === 'stock' ? 'selected' : '' ?>>
                Stok
            </option>
        </select>

        <select name="direction">
            <option value="ASC" <?= $direction === 'ASC' ? 'selected' : '' ?>>
                Naik (A-Z)
            </option>
            <option value="DESC" <?= $direction === 'DESC' ? 'selected' : '' ?>>
                Turun (Z-A)
            </option>
        </select>

        <button class="primary" type="submit">Terapkan</button>
        <a class="secondary" href="index.php">Reset</a>
    </form>

    <p>
        Total produk ditemukan: <strong><?= $totalProducts ?></strong>
    </p>

    <div class="table-wrapper">
        <table>
            <thead>
            <tr>
                <th>No.</th>
                <th>SKU</th>
                <th>Nama Produk</th>
                <th>Kategori</th>
                <th>Harga</th>
                <th>Stok</th>
                <th>Stok Minimum</th>
                <?php if ($isAdmin): ?>
                    <th>Aksi</th>
                <?php endif; ?>
            </tr>
            </thead>

            <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="<?= $isAdmin ? 8 : 7 ?>">
                        Tidak ada produk yang cocok dengan pencarian.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $i => $product): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td><?= e($product['sku']) ?></td>
                        <td><?= e($product['name']) ?></td>
                        <td><?= e($product['category_name']) ?></td>
                        <td>
                            Rp<?= number_format((float) $product['price'], 0, ',', '.') ?>
                        </td>
                        <td class="<?= (int) $product['stock'] <= (int) $product['minimum_stock'] ? 'stock-low' : 'stock-normal' ?>">
                            <?= (int) $product['stock'] ?>
                        </td>
                        <td><?= (int) $product['minimum_stock'] ?></td>

                        <?php if ($isAdmin): ?>
                            <td class="actions">
                                <a
                                    class="edit"
                                    href="edit.php?id=<?= (int) $product['id'] ?>"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    action="delete.php"
                                    onsubmit="return confirm('Yakin ingin menghapus produk ini?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrfToken()) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= (int) $product['id'] ?>"
                                    >
                                    <button class="delete" type="submit">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a
                class="secondary"
                href="?<?= e(http_build_query(array_merge($queryParams, ['page' => $page - 1]))) ?>"
            >
                &laquo; Sebelumnya
            </a>
        <?php endif; ?>

        <span>
            Halaman <?= $page ?> dari <?= $totalPages ?>
        </span>

        <?php if ($page < $totalPages): ?>
            <a
                class="secondary"
                href="?<?= e(http_build_query(array_merge($queryParams, ['page' => $page + 1]))) ?>"
            >
                Berikutnya &raquo;
            </a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>