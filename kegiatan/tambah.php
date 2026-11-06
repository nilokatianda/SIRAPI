<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";

$error = "";

// Ambil daftar periode untuk pilihan dropdown
$periode_list = [];
$res_periode = mysqli_query($conn, "SELECT id_periode, nama_periode, nama_divisi FROM periode ORDER BY nama_periode DESC, nama_divisi ASC");
if ($res_periode) {
    while ($row = mysqli_fetch_assoc($res_periode)) {
        $periode_list[] = $row;
    }
}

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
                $sql = "INSERT INTO kegiatan (nama_kegiatan, deskripsi, tanggal_kegiatan, id_periode) VALUES (?, ?, ?, NULL)";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sss", $nama_kegiatan, $deskripsi, $tanggal_kegiatan);
                }
            } else {
                $sql = "INSERT INTO kegiatan (nama_kegiatan, deskripsi, tanggal_kegiatan, id_periode) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssi", $nama_kegiatan, $deskripsi, $tanggal_kegiatan, $id_periode);
                }
            }

            if ($stmt) {
                if (mysqli_stmt_execute($stmt)) {
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Gagal menambahkan data: " . mysqli_stmt_error($stmt);
                }
            } else {
                $error = "Gagal menyiapkan query: " . mysqli_error($conn);
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal menambahkan data (Relasi database gagal): Periode yang dipilih tidak valid di database.";
        }
    }
}
$data = $_POST;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kegiatan - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>Tambah Data Kegiatan</h1>
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
            <input type="date" name="tanggal_kegiatan" value="<?php echo htmlspecialchars((string)($data['tanggal_kegiatan'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Pilih Periode
            <select name="id_periode">
                <option value="">-- Pilih Periode (Boleh Kosong) --</option>
                <?php foreach ($periode_list as $p): ?>
                    <option value="<?php echo (int)$p['id_periode']; ?>" <?php echo (isset($data['id_periode']) && (string)$data['id_periode'] === (string)$p['id_periode']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nama_periode'] . ' - ' . $p['nama_divisi'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <button type="submit">Simpan</button>
    </form>
</body>
</html>