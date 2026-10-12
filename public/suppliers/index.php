
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');

        if ($name === '') {
            $error = 'Nama supplier wajib diisi.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE suppliers
                         SET name = ?, phone = ?, address = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([$name, $phone, $address, $id]);
                    $success = 'Data supplier berhasil diperbarui.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO suppliers (name, phone, address)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$name, $phone, $address]);
                    $success = 'Supplier baru berhasil ditambahkan.';
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Gagal menyimpan data supplier.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        try {
            $stmt = $pdo->prepare(
                'DELETE FROM suppliers WHERE id = ?'
            );
            $stmt->execute([$id]);
            $success = 'Supplier berhasil dihapus.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Supplier tidak bisa dihapus karena masih digunakan oleh relasi produk.';
        }
    }
}

$editSupplier = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT * FROM suppliers WHERE id = ?'
    );
    $stmt->execute([(int) $_GET['edit']]);
    $editSupplier = $stmt->fetch();
}

$search = trim($_GET['search'] ?? '');

$stmt = $pdo->prepare(
    'SELECT s.*,
        (SELECT COUNT(*)
         FROM product_suppliers ps
         WHERE ps.supplier_id = s.id) AS total_products
     FROM suppliers s
     WHERE s.name LIKE ?
        OR s.phone LIKE ?
        OR s.address LIKE ?
     ORDER BY s.id DESC'
);

$keyword = '%' . $search . '%';
$stmt->execute([$keyword, $keyword, $keyword]);
$suppliers = $stmt->fetchAll();

$totalSuppliers = (int) $pdo
    ->query('SELECT COUNT(*) FROM suppliers')
    ->fetchColumn();

$totalRelations = (int) $pdo
    ->query('SELECT COUNT(*) FROM product_suppliers')
    ->fetchColumn();

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
<title>Manajemen Supplier</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#f1f5f9;color:#172033;font-family:Arial,sans-serif}
.container{max-width:1150px;margin:35px auto;padding:0 20px}
.header{background:#173b65;color:white;padding:28px 30px;border-radius:16px;margin-bottom:22px}
.header h1{margin:0 0 9px;font-size:27px}
.header p{margin:0;color:#dbeafe;line-height:1.6}
.nav{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.nav a{padding:10px 14px;background:#ffffff1c;color:white;text-decoration:none;border-radius:8px;font-size:14px}
.nav a:hover{background:#ffffff32}
.stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:22px}
.stat,.panel{background:white;border-radius:13px;padding:22px;box-shadow:0 3px 14px #0f172a0a}
.stat span{color:#64748b;font-size:14px}
.stat h2{font-size:28px;margin:10px 0 0;color:#173b65}
.panel{margin-bottom:22px}
.panel h2{font-size:19px;margin:0 0 18px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.field{display:flex;flex-direction:column;gap:7px}
.field.full{grid-column:1/-1}
label{font-size:14px;font-weight:bold;color:#334155}
input,textarea{width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;background:white}
textarea{resize:vertical;min-height:80px}
input:focus,textarea:focus{outline:2px solid #bfdbfe;border-color:#2563eb}
.actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:17px}
.btn{display:inline-block;border:0;border-radius:8px;padding:10px 14px;text-decoration:none;font-weight:bold;font-size:13px;cursor:pointer}
.primary{background:#1d4ed8;color:white}
.secondary{background:#e2e8f0;color:#334155}
.danger{background:#dc2626;color:white}
.edit{background:#dbeafe;color:#1d4ed8}
.alert{padding:13px 15px;border-radius:8px;margin-bottom:17px}
.alert.error{background:#fee2e2;color:#991b1b}
.alert.success{background:#dcfce7;color:#166534}
.search{display:flex;gap:10px;margin-bottom:17px}
.search input{flex:1}
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:720px}
th{background:#173b65;color:white;text-align:left;padding:14px 12px;font-size:13px}
td{padding:14px 12px;border-bottom:1px solid #e2e8f0;font-size:14px;vertical-align:top}
tbody tr:hover{background:#f8fafc}
.badge{display:inline-block;padding:5px 9px;border-radius:20px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:bold}
.empty{text-align:center;padding:28px;color:#64748b}
@media(max-width:650px){.container{margin:18px auto;padding:0 12px}.header{padding:22px}.stats{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.search{flex-direction:column}}
</style>
</head>
<body>
<div class="container">

    <header class="header">
        <h1>Manajemen Supplier</h1>
        <p>Kelola informasi pemasok yang menyediakan produk untuk inventaris.</p>
        <nav class="nav">
            <a href="/inventory-app/public/dashboard.php">Dashboard</a>
            <a href="/inventory-app/public/suppliers/index.php">Data Supplier</a>
            <a href="/inventory-app/public/product-suppliers/index.php">Relasi Produk-Supplier</a>
        </nav>
    </header>

    <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat">
            <span>Total Supplier Terdaftar</span>
            <h2><?= $totalSuppliers ?></h2>
        </div>
        <div class="stat">
            <span>Total Relasi Produk-Supplier</span>
            <h2><?= $totalRelations ?></h2>
        </div>
    </section>

    <section class="panel">
        <h2><?= $editSupplier ? 'Edit Data Supplier' : 'Tambah Supplier Baru' ?></h2>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= e($editSupplier['id'] ?? 0) ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="name">Nama Supplier *</label>
                    <input id="name" name="name" required
                        value="<?= e($editSupplier['name'] ?? '') ?>"
                        placeholder="Contoh: PT Sumber Elektronik">
                </div>

                <div class="field">
                    <label for="phone">Nomor Telepon</label>
                    <input id="phone" name="phone"
                        value="<?= e($editSupplier['phone'] ?? '') ?>"
                        placeholder="Contoh: 081234567890">
                </div>

                <div class="field full">
                    <label for="address">Alamat Supplier</label>
                    <textarea id="address" name="address"
                        placeholder="Masukkan alamat supplier"><?= e($editSupplier['address'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="actions">
                <button class="btn primary" type="submit">
                    <?= $editSupplier ? 'Simpan Perubahan' : 'Tambah Supplier' ?>
                </button>
                <?php if ($editSupplier): ?>
                    <a class="btn secondary" href="index.php">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Daftar Supplier</h2>

        <form class="search" method="GET">
            <input type="text" name="search" value="<?= e($search) ?>"
                placeholder="Cari nama, nomor telepon, atau alamat supplier...">
            <button class="btn primary" type="submit">Cari</button>
            <a class="btn secondary" href="index.php">Reset</a>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Supplier</th>
                        <th>Telepon</th>
                        <th>Alamat</th>
                        <th>Relasi Produk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$suppliers): ?>
                    <tr><td colspan="6" class="empty">Belum ada data supplier.</td></tr>
                <?php else: ?>
                    <?php foreach ($suppliers as $i => $supplier): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= e($supplier['name']) ?></strong></td>
                        <td><?= e($supplier['phone'] ?: '-') ?></td>
                        <td><?= e($supplier['address'] ?: '-') ?></td>
                        <td><span class="badge"><?= (int) $supplier['total_products'] ?> produk</span></td>
                        <td>
                            <div class="actions" style="margin-top:0">
                                <a class="btn edit" href="?edit=<?= (int) $supplier['id'] ?>">Edit</a>
                                <form method="POST" onsubmit="return confirm('Yakin ingin menghapus supplier ini?')">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                                    <button class="btn danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</body>
</html>
