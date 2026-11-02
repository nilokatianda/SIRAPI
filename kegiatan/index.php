<?php
require_once "../koneksi.php";
$result = mysqli_query($conn, "SELECT id_kegiatan, nama_kegiatan, deskripsi, tanggal_kegiatan, id_periode FROM kegiatan");
if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kegiatan - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Data Kegiatan</h1>
    <p><a href="../index.php">Kembali ke Beranda</a></p>
    <a class="tombol" href="tambah.php">Tambah Data Kegiatan</a>
    <table>
        <thead><tr><th>ID</th><th>Nama Kegiatan</th><th>Deskripsi</th><th>Tanggal Kegiatan</th><th>ID Periode</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($row['id_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['nama_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['tanggal_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['id_periode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="aksi">
                    <a href="edit.php?id=<?php echo (int)$row['id_kegiatan']; ?>">Edit</a> 
                    <a class="hapus" href="hapus.php?id=<?php echo (int)$row['id_kegiatan']; ?>" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>