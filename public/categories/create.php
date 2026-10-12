
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$error = '';
$name = '';
$description = '';

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $error = 'Nama kategori wajib diisi.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Nama kategori maksimal 100 karakter.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO categories (name, description)
                 VALUES (:name, :description)'
            );

            $stmt->execute([
                'name' => $name,
                'description' => $description !== ''
                    ? $description
                    : null
            ]);

            header('Location: index.php?success=created');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Nama kategori sudah digunakan.';
            } else {
                error_log($e->getMessage());
                $error = 'Kategori gagal disimpan.';
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
    <title>Tambah Kategori</title>
</head>
<body>

<h1>Tambah Kategori</h1>

<p><a href="index.php">Kembali ke Data Kategori</a></p>

<?php if ($error !== ''): ?>
    <p style="color: red;"><?= e($error) ?></p>
<?php endif; ?>

<form method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e(csrfToken()) ?>"
    >

    <label for="name">Nama Kategori</label><br>
    <input
        type="text"
        id="name"
        name="name"
        maxlength="100"
        value="<?= e($name) ?>"
        required
    >
    <br><br>

    <label for="description">Deskripsi</label><br>
    <textarea
        id="description"
        name="description"
    ><?= e($description) ?></textarea>
    <br><br>

    <button type="submit">Simpan Kategori</button>
</form>

</body>
</html>