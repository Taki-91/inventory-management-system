<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$stmt = $pdo->query(
    'SELECT id, name, description
     FROM categories
     ORDER BY id DESC'
);

$categories = $stmt->fetchAll();

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$messages = [
    'created' => 'Kategori berhasil ditambahkan.',
    'deleted' => 'Kategori berhasil dihapus.'
];

$errors = [
    'invalid' => 'ID kategori tidak valid.',
    'notfound' => 'Kategori tidak ditemukan.',
    'related' => 'Kategori tidak bisa dihapus karena masih digunakan produk.'
];

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kategori</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f4f6f9;
            color: #222;
        }

        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #e2e8f0;
        }

        .button {
            display: inline-block;
            padding: 9px 12px;
            background: #1d4ed8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .delete-button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 7px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        .success {
            color: #166534;
            background: #dcfce7;
            padding: 10px;
        }

        .error {
            color: #b91c1c;
            background: #fee2e2;
            padding: 10px;
        }

        .table-wrapper {
            overflow-x: auto;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Data Kategori</h1>

    <p>
        <a href="../dashboard.php">Kembali ke Dashboard</a>
    </p>

    <p>
        <a class="button" href="create.php">
            + Tambah Kategori
        </a>
    </p>

    <?php if (isset($messages[$success])): ?>
        <p class="success">
            <?= e($messages[$success]) ?>
        </p>
    <?php endif; ?>

    <?php if (isset($errors[$error])): ?>
        <p class="error">
            <?= e($errors[$error]) ?>
        </p>
    <?php endif; ?>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama Kategori</th>
                    <th>Deskripsi</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4">
                            Belum ada kategori.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= e($category['id']) ?></td>

                            <td><?= e($category['name']) ?></td>

                            <td><?= e($category['description'] ?? '') ?></td>

                            <td>
                                <form
                                    method="POST"
                                    action="delete.php"
                                    onsubmit="return confirm('Yakin ingin menghapus kategori ini?');"
                                >
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e(csrfToken()) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= e($category['id']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-button"
                                    >
                                        Hapus
                                    </button>
                                </form>
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