<?php
require_once "../koneksi.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$cek = mysqli_prepare($conn, "SELECT id_kegiatan, nama_kegiatan, deskripsi, tanggal_kegiatan, id_periode FROM kegiatan WHERE id_kegiatan = ?");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);
if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}

// Ambil daftar periode untuk pilihan dropdown
$periode_list = [];
$res_periode = mysqli_query($conn, "SELECT id_periode, nama_periode, nama_divisi FROM periode ORDER BY nama_periode DESC, nama_divisi ASC");
if ($res_periode) {
    while ($row = mysqli_fetch_assoc($res_periode)) {
        $periode_list[] = $row;
    }
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_kegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $tanggal_kegiatan = trim($_POST['tanggal_kegiatan'] ?? '');
    $id_periode_raw = trim($_POST['id_periode'] ?? '');
    $id_periode = ($id_periode_raw !== '' && is_numeric($id_periode_raw)) ? (int)$id_periode_raw : null;

    if ($tanggal_kegiatan === '') $tanggal_kegiatan = null;

    if ($nama_kegiatan === '') {
        $error = "Nama kegiatan tidak boleh kosong.";
    } else {
        try {
            if ($id_periode === null) {
                $sql = "UPDATE kegiatan SET nama_kegiatan = ?, deskripsi = ?, tanggal_kegiatan = ?, id_periode = NULL WHERE id_kegiatan = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssi", $nama_kegiatan, $deskripsi, $tanggal_kegiatan, $id);
                }
            } else {
                $sql = "UPDATE kegiatan SET nama_kegiatan = ?, deskripsi = ?, tanggal_kegiatan = ?, id_periode = ? WHERE id_kegiatan = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssii", $nama_kegiatan, $deskripsi, $tanggal_kegiatan, $id_periode, $id);
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
            $error = "Gagal mengubah data (Relasi database gagal): Periode yang dipilih tidak valid di database.";
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
    <title>Edit Kegiatan - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Edit Data Kegiatan</h1>
    <p><a href="index.php">Kembali</a></p>
    <?php if ($error !== "") echo "<p class='hapus'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
    <form method="post">
        <label>Nama Kegiatan
            <input type="text" name="nama_kegiatan" value="<?php echo htmlspecialchars((string)($data['nama_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        
        <label>Deskripsi
            <textarea name="deskripsi" rows="4"><?php echo htmlspecialchars((string)($data['deskripsi'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label>
        
        <label>Tanggal Kegiatan
            <input type="date" name="tanggal_kegiatan" value="<?php echo htmlspecialchars((string)($data['tanggal_kegiatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Pilih Periode
            <select name="id_periode">
                <option value="">-- Pilih Periode (Boleh Kosong) --</option>
                <?php foreach ($periode_list as $p): ?>
                    <option value="<?php echo (int)$p['id_periode']; ?>" <?php echo ((string)($data['id_periode'] ?? '') === (string)$p['id_periode']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nama_periode'] . ' - ' . $p['nama_divisi'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo (int)$p['id_periode']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <button type="submit">Simpan Perubahan</button>
    </form>
</body>
</html>