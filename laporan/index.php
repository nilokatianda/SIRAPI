
<?php
require_once "../koneksi.php";

$sql = "SELECT * FROM laporan ORDER BY id_peminjaman ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Gagal mengambil laporan: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan peminjaman - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

    <h1>Laporan Peminjaman Arsip</h1>

    <p><a href="../index.php">Kembali ke Beranda</a></p>

    <table>
        <thead>
            <tr>
                <th>ID Peminjaman</th>
                <th>Nama Pengguna</th>
                <th>Nama Arsip</th>
                <th>Tanggal Pinjam</th>
                <th>Tanggal Kembali</th>
                <th>Status Peminjaman</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row["id_peminjaman"]) ?></td>
                        <td><?= htmlspecialchars((string)$row["nama_pengguna"]) ?></td>
                        <td><?= htmlspecialchars((string)$row["nama_arsip"]) ?></td>
                        <td><?= htmlspecialchars((string)$row["tanggal_pinjam"]) ?></td>
                        <td>
                            <?= $row["tanggal_kembali"] !== null
                                ? htmlspecialchars((string)$row["tanggal_kembali"])
                                : "Belum dikembalikan" ?>
                        </td>
                        <td><?= htmlspecialchars((string)$row["status_peminjaman"]) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Belum ada data peminjaman.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
