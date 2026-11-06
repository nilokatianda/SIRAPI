<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_periode = trim($_POST['nama_periode'] ?? '');
    $nama_divisi = trim($_POST['nama_divisi'] ?? '');
    
    $sql = "INSERT INTO periode (nama_periode, nama_divisi) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $nama_periode, $nama_divisi);
        if (mysqli_stmt_execute($stmt)) {
            header("Location: index.php");
            exit;
        } else {
            $error = "Gagal menambahkan data. Pastikan ID relasi tersedia dan data sudah benar. " . mysqli_stmt_error($stmt);
        }
    } else {
        $error = "Gagal menyiapkan query: " . mysqli_error($conn);
    }
}
$data = [];
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Tambah Periode</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
    <h1>Tambah Data Periode</h1>
    <p><a href="index.php">Kembali</a></p>
    <?php if ($error !== "") echo "<p class='hapus'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
    <form method="post">
        <label>Nama Periode
    <input type="text" name="nama_periode" value="<?php echo htmlspecialchars((string)($data['nama_periode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
</label>
<label>Nama Divisi
    <input type="text" name="nama_divisi" value="<?php echo htmlspecialchars((string)($data['nama_divisi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
</label>
        <button type="submit">Simpan</button>
    </form>
</body>
</html>