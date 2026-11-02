<?php
require_once "../koneksi.php";
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$stmt = mysqli_prepare($conn, "DELETE FROM pengguna WHERE id_pengguna = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
if (mysqli_stmt_execute($stmt)) {
    header("Location: index.php");
    exit;
}
echo "Data gagal dihapus. Data mungkin masih digunakan oleh tabel lain. ";
echo "<a href='index.php'>Kembali</a>";
?>