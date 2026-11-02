<?php
require_once "../koneksi.php";
$result = mysqli_query($conn, "SELECT id_peminjaman, id_arsip, id_pengguna, tanggal_pinjam, tanggal_kembali, status_peminjaman FROM peminjaman");
if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Peminjaman - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Data Peminjaman</h1>
    <p><a href="../index.php">Kembali ke Beranda</a></p>
    <a class="tombol" href="tambah.php">Tambah Data Peminjaman</a>
    <table>
        <thead><tr><th>ID</th><th>ID Arsip</th><th>ID Pengguna</th><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Status Peminjaman</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($row['id_peminjaman'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['id_arsip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['id_pengguna'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['tanggal_pinjam'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['tanggal_kembali'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($row['status_peminjaman'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="aksi">
                    <a href="edit.php?id=<?php echo (int)$row['id_peminjaman']; ?>">Edit</a> 
                    <a class="hapus" href="hapus.php?id=<?php echo (int)$row['id_peminjaman']; ?>" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">Belum ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>