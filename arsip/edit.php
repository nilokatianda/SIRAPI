<?php
require_once "../koneksi.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$cek = mysqli_prepare($conn, "SELECT id_arsip, nama_arsip, nama_file, tanggal_upload, id_kegiatan FROM arsip WHERE id_arsip = ?");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);
if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}

// Ambil daftar kegiatan untuk pilihan dropdown
$kegiatan_list = [];
$res_kegiatan = mysqli_query($conn, "SELECT id_kegiatan, nama_kegiatan FROM kegiatan ORDER BY nama_kegiatan ASC");
if ($res_kegiatan) {
    while ($row = mysqli_fetch_assoc($res_kegiatan)) {
        $kegiatan_list[] = $row;
    }
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_arsip = trim($_POST['nama_arsip'] ?? '');
    $nama_file = trim($_POST['nama_file'] ?? '');
    $tanggal_upload = trim($_POST['tanggal_upload'] ?? '');
    $id_kegiatan_raw = trim($_POST['id_kegiatan'] ?? '');
    $id_kegiatan = ($id_kegiatan_raw !== '' && is_numeric($id_kegiatan_raw)) ? (int)$id_kegiatan_raw : null;

    if ($tanggal_upload === '') $tanggal_upload = null;

    if ($nama_arsip === '') {
        $error = "Nama arsip tidak boleh kosong.";
    } else {
        try {
            if ($id_kegiatan === null) {
                $sql = "UPDATE arsip SET nama_arsip = ?, nama_file = ?, tanggal_upload = ?, id_kegiatan = NULL WHERE id_arsip = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssi", $nama_arsip, $nama_file, $tanggal_upload, $id);
                }
            } else {
                $sql = "UPDATE arsip SET nama_arsip = ?, nama_file = ?, tanggal_upload = ?, id_kegiatan = ? WHERE id_arsip = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssii", $nama_arsip, $nama_file, $tanggal_upload, $id_kegiatan, $id);
                }
            }

            if ($stmt) {
                if (mysqli_stmt_execute($stmt)) {
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Gagal mengubah data: " . mysqli_stmt_error($stmt);
                }
            } else {
                $error = "Gagal menyiapkan query: " . mysqli_error($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal mengubah data (Relasi database gagal): Kegiatan yang dipilih tidak valid di database.";
        }
    }
    foreach ($_POST as $key => $value) {
        if (is_string($value)) $data[$key] = $value;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Arsip - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Edit Data Arsip</h1>
    <p><a href="index.php">Kembali</a></p>
    <?php if ($error !== "") echo "<p class='hapus'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
    <form method="post">
        <label>Nama Arsip
            <input type="text" name="nama_arsip" value="<?php echo htmlspecialchars((string)($data['nama_arsip'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        
        <label>Nama File
            <input type="text" name="nama_file" value="<?php echo htmlspecialchars((string)($data['nama_file'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Tanggal Upload
            <input type="date" name="tanggal_upload" value="<?php echo htmlspecialchars((string)($data['tanggal_upload'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Pilih Kegiatan
            <select name="id_kegiatan">
                <option value="">-- Pilih Kegiatan (Boleh Kosong) --</option>
                <?php foreach ($kegiatan_list as $k): ?>
                    <option value="<?php echo (int)$k['id_kegiatan']; ?>" <?php echo ((string)($data['id_kegiatan'] ?? '') === (string)$k['id_kegiatan']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($k['nama_kegiatan'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo (int)$k['id_kegiatan']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <button type="submit">Simpan Perubahan</button>
    </form>
</body>
</html>