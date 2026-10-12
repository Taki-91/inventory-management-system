
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$error = '';
$name = '';
$phone = '';
$address = '';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || mb_strlen($name) > 150) {
        $error = 'Nama supplier wajib diisi dan maksimal 150 karakter.';
    } elseif (mb_strlen($phone) > 20) {
        $error = 'Nomor telepon maksimal 20 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO suppliers (name, phone, address)
                 VALUES (:name, :phone, :address)'
            );

            $stmt->execute([
                'name' => $name,
                'phone' => $phone !== '' ? $phone : null,
                'address' => $address !== '' ? $address : null
            ]);

            header('Location: index.php?success=created');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Supplier gagal disimpan.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Supplier</title>
</head>
<body>
<h1>Tambah Supplier</h1>
<p><a href="index.php">Kembali ke Data Supplier</a></p>

<?php if ($error !== ''): ?>
    <p style="color:red;"><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

    <label for="name">Nama Supplier</label><br>
    <input id="name" name="name" maxlength="150" value="<?= e($name) ?>" required>
    <br><br>

    <label for="phone">Nomor Telepon</label><br>
    <input id="phone" name="phone" maxlength="20" value="<?= e($phone) ?>">
    <br><br>

    <label for="address">Alamat</label><br>
    <textarea id="address" name="address"><?= e($address) ?></textarea>
    <br><br>

    <button type="submit">Simpan Supplier</button>
</form>
</body>
</html>
