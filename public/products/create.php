
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$error = '';
$sku = '';
$name = '';
$categoryId = '';
$price = '';
$stock = '';
$minimumStock = '5';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $minimumStock = trim($_POST['minimum_stock'] ?? '5');

    if ($sku === '' || mb_strlen($sku) > 50) {
        $error = 'SKU wajib diisi dan maksimal 50 karakter.';
    } elseif ($name === '' || mb_strlen($name) > 150) {
        $error = 'Nama produk wajib diisi dan maksimal 150 karakter.';
    } elseif (!$categoryId || $categoryId < 1) {
        $error = 'Pilih kategori yang valid.';
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = 'Harga harus berupa angka nonnegatif.';
    } elseif (
        !ctype_digit($stock) ||
        (float)$stock > 2147483647
    ) {
        $error = 'Stok harus berupa bilangan bulat nonnegatif.';
    } elseif (
        !ctype_digit($minimumStock) ||
        (float)$minimumStock > 2147483647
    ) {
        $error = 'Stok minimum harus berupa bilangan bulat nonnegatif.';
    } else {
        try {
            $check = $pdo->prepare(
                'SELECT id FROM categories WHERE id = :id'
            );
            $check->execute(['id' => $categoryId]);

            if (!$check->fetch()) {
                $error = 'Kategori tidak ditemukan.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO products
                    (category_id, sku, name, price, stock, minimum_stock)
                    VALUES
                    (:category_id, :sku, :name, :price, :stock, :minimum_stock)'
                );

                $stmt->execute([
                    'category_id' => $categoryId,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'stock' => $stock,
                    'minimum_stock' => $minimumStock
                ]);

                header('Location: index.php?success=created');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'SKU sudah digunakan atau data terkait tidak valid.';
            } else {
                error_log($e->getMessage());
                $error = 'Produk gagal disimpan.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk</title>
</head>
<body>
<h1>Tambah Produk</h1>
<p><a href="index.php">Kembali ke Data Produk</a></p>

<?php if ($error !== ''): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<?php if (!$categories): ?>
    <p>Tambahkan kategori terlebih dahulu sebelum membuat produk.</p>
<?php else: ?>
<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <label for="sku">SKU</label><br>
    <input id="sku" name="sku" maxlength="50" value="<?= e($sku) ?>" required>
    <br><br>

    <label for="name">Nama Produk</label><br>
    <input id="name" name="name" maxlength="150" value="<?= e($name) ?>" required>
    <br><br>

    <label for="category_id">Kategori</label><br>
    <select id="category_id" name="category_id" required>
        <option value="">Pilih kategori</option>
        <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['id']) ?>"
                <?= (string)$categoryId === (string)$category['id'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="price">Harga</label><br>
    <input type="number" id="price" name="price" min="0" step="0.01" value="<?= e($price) ?>" required>
    <br><br>

    <label for="stock">Stok Awal</label><br>
    <input type="number" id="stock" name="stock" min="0" step="1" value="<?= e($stock) ?>" required>
    <br><br>

    <label for="minimum_stock">Stok Minimum</label><br>
    <input type="number" id="minimum_stock" name="minimum_stock" min="0" step="1" value="<?= e($minimumStock) ?>" required>
    <br><br>

    <button type="submit">Simpan Produk</button>
</form>
<?php endif; ?>
</body>
</html>