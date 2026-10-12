
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
$stmt->execute(['id' => $id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php?error=notfound');
    exit;
}

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$error = '';
$sku = $product['sku'];
$name = $product['name'];
$categoryId = (string) $product['category_id'];
$price = (string) $product['price'];
$minimumStock = (string) $product['minimum_stock'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT
    );
    $price = trim($_POST['price'] ?? '');
    $minimumStock = trim($_POST['minimum_stock'] ?? '');

    if ($sku === '' || mb_strlen($sku) > 50) {
        $error = 'SKU wajib diisi dan maksimal 50 karakter.';
    } elseif ($name === '' || mb_strlen($name) > 150) {
        $error = 'Nama produk wajib diisi dan maksimal 150 karakter.';
    } elseif (!$categoryId || $categoryId < 1) {
        $error = 'Pilih kategori yang valid.';
    } elseif (!is_numeric($price) || (float) $price < 0) {
        $error = 'Harga harus berupa angka nonnegatif.';
    } elseif (
        !preg_match('/^\d+$/', $minimumStock) ||
        (float) $minimumStock > 2147483647
    ) {
        $error = 'Stok minimum harus berupa bilangan bulat nonnegatif.';
    } else {
        try {
            $checkCategory = $pdo->prepare(
                'SELECT id FROM categories WHERE id = :id'
            );
            $checkCategory->execute(['id' => $categoryId]);

            if (!$checkCategory->fetch()) {
                $error = 'Kategori tidak ditemukan.';
            } else {
                $update = $pdo->prepare(
                    'UPDATE products
                     SET category_id = :category_id,
                         sku = :sku,
                         name = :name,
                         price = :price,
                         minimum_stock = :minimum_stock
                     WHERE id = :id'
                );

                $update->execute([
                    'category_id' => $categoryId,
                    'sku' => $sku,
                    'name' => $name,
                    'price' => $price,
                    'minimum_stock' => $minimumStock,
                    'id' => $id
                ]);

                header('Location: index.php?success=updated');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'SKU sudah digunakan atau data terkait tidak valid.';
            } else {
                error_log($e->getMessage());
                $error = 'Produk gagal diperbarui.';
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
    <title>Edit Produk</title>
</head>
<body>
<h1>Edit Produk</h1>
<p><a href="index.php">Kembali ke Data Produk</a></p>

<?php if ($error !== ''): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

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
                <?= (string) $categoryId === (string) $category['id'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="price">Harga</label><br>
    <input type="number" id="price" name="price" min="0" step="0.01" value="<?= e($price) ?>" required>
    <br><br>

    <label for="minimum_stock">Stok Minimum</label><br>
    <input type="number" id="minimum_stock" name="minimum_stock" min="0" step="1" value="<?= e($minimumStock) ?>" required>
    <br><br>

    <p>Stok saat ini: <strong><?= e($product['stock']) ?></strong></p>
    <p>Perubahan stok dilakukan melalui menu Transaksi Stok.</p>

    <button type="submit">Simpan Perubahan</button>
</form>
</body>
</html>
