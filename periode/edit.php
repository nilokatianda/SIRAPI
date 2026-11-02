<?php
require_once "../koneksi.php";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$cek = mysqli_prepare($conn, "SELECT id_periode, nama_periode, nama_divisi FROM periode WHERE id_periode = ?");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);
if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_periode = trim($_POST['nama_periode'] ?? '');
    $nama_divisi = trim($_POST['nama_divisi'] ?? '');
    
    $sql = "UPDATE periode SET nama_periode = ?, nama_divisi = ? WHERE id_periode = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssi", $nama_periode, $nama_divisi, $id);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: index.php");
        exit;
    } else {
        $error = "Gagal mengubah data. Pastikan ID relasi tersedia dan data sudah benar. " . mysqli_stmt_error($stmt);
    }
    foreach ($_POST as $key => $value) {
        if (is_string($value)) $data[$key] = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Edit Periode</title><link rel="stylesheet" href="../style.css"></head>
<body>
    <h1>Edit Data Periode</h1>
    <p><a href="index.php">Kembali</a></p>
    <?php if ($error !== "") echo "<p class='hapus'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
    <form method="post">
        <label>Nama Periode
    <input type="text" name="nama_periode" value="<?php echo htmlspecialchars((string)($data['nama_periode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
</label>
<label>Nama Divisi
    <input type="text" name="nama_divisi" value="<?php echo htmlspecialchars((string)($data['nama_divisi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
</label>
        <button type="submit">Simpan Perubahan</button>
    </form>
</body>
</html>