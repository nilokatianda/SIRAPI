<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";
$result = mysqli_query($conn, "SELECT id_periode, nama_periode, nama_divisi FROM periode");
if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Periode - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>Data Periode</h1>
    <p><a href="<?= $dashboard_url ?>" class="btn-kembali">&larr; <?= $dashboard_label ?></a></p>
    <a class="tombol" href="tambah.php">Tambah Data Periode</a>
    <table>
        <thead><tr><th>ID</th><th>Nama Periode</th><th>Nama Divisi</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($row['id_periode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['nama_periode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['nama_divisi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="aksi">
                    <a href="edit.php?id=<?php echo (int)$row['id_periode']; ?>">Edit</a>
                    <a class="hapus" href="hapus.php?id=<?php echo (int)$row['id_periode']; ?>" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>