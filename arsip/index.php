
<?php
require_once "../koneksi.php";

$result = mysqli_query(
    $conn,
    "SELECT id_arsip, nama_arsip, nama_file, file_arsip,
            tanggal_upload, id_kegiatan
     FROM arsip"
);

if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Arsip - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Data Arsip</h1>

    <p>
        <a href="../index.php">Kembali ke Beranda</a>
    </p>

    <a class="tombol" href="tambah.php">+ Tambah Data Arsip</a>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Arsip</th>
                <th>Nama File</th>
                <th>File Arsip</th>
                <th>Tanggal Upload</th>
                <th>ID Kegiatan</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars((string)$row["id_arsip"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["nama_arsip"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["nama_file"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?php
                        $file = basename($row["file_arsip"] ?? "");
                        $lokasi = __DIR__ . "/../uploads/arsip/" . $file;
                        ?>

                        <?php if ($file !== "" && is_file($lokasi)): ?>
                            <a
                                href="../uploads/arsip/<?= rawurlencode($file) ?>"
                                target="_blank"
                                rel="noopener"
                            >Lihat File</a>
                        <?php else: ?>
                            Belum ada file
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["tanggal_upload"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["id_kegiatan"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td class="aksi">
                        <a href="edit.php?id=<?= (int)$row["id_arsip"] ?>">Edit</a>
                        <span class="pemisah"></span>
                        <a
                            class="hapus"
                            href="hapus.php?id=<?= (int)$row["id_arsip"] ?>"
                            onclick="return confirm('Yakin ingin menghapus data ini?')"
                        >Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="7">Belum ada data.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
