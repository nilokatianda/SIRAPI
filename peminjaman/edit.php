<?php
require_once "../koneksi.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$cek = mysqli_prepare($conn, "SELECT id_peminjaman, id_arsip, id_pengguna, tanggal_pinjam, tanggal_kembali, status_peminjaman FROM peminjaman WHERE id_peminjaman = ?");
mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);
if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}

// Ambil daftar arsip untuk pilihan dropdown
$arsip_list = [];
$res_arsip = mysqli_query($conn, "SELECT id_arsip, nama_arsip FROM arsip ORDER BY nama_arsip ASC");
if ($res_arsip) {
    while ($row = mysqli_fetch_assoc($res_arsip)) {
        $arsip_list[] = $row;
    }
}

// Ambil daftar pengguna untuk pilihan dropdown
$pengguna_list = [];
$res_pengguna = mysqli_query($conn, "SELECT id_pengguna, nama_pengguna, jabatan FROM pengguna ORDER BY nama_pengguna ASC");
if ($res_pengguna) {
    while ($row = mysqli_fetch_assoc($res_pengguna)) {
        $pengguna_list[] = $row;
    }
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_arsip = !empty($_POST['id_arsip']) ? (int)$_POST['id_arsip'] : 0;
    $id_pengguna = !empty($_POST['id_pengguna']) ? (int)$_POST['id_pengguna'] : 0;
    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $status_peminjaman = trim($_POST['status_peminjaman'] ?? '');

    if ($tanggal_pinjam === '') $tanggal_pinjam = null;
    if ($tanggal_kembali === '') $tanggal_kembali = null;

    if ($id_arsip <= 0 || $id_pengguna <= 0) {
        $error = "Harap pilih Arsip dan Pengguna yang valid dari daftar.";
    } else {
        try {
            $sql = "UPDATE peminjaman SET id_arsip = ?, id_pengguna = ?, tanggal_pinjam = ?, tanggal_kembali = ?, status_peminjaman = ? WHERE id_peminjaman = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iisssi", $id_arsip, $id_pengguna, $tanggal_pinjam, $tanggal_kembali, $status_peminjaman, $id);
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
            $error = "Gagal mengubah data (Relasi database gagal): Pastikan ID Arsip dan Pengguna tersedia di database.";
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
    <title>Edit Peminjaman - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <h1>Edit Data Peminjaman</h1>
    <p><a href="index.php">Kembali</a></p>
    <?php if ($error !== "") echo "<p class='hapus'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
    <form method="post">
        <label>Pilih Arsip
            <select name="id_arsip" required>
                <option value="">-- Pilih Arsip --</option>
                <?php foreach ($arsip_list as $a): ?>
                    <option value="<?php echo (int)$a['id_arsip']; ?>" <?php echo ((int)$data['id_arsip'] === (int)$a['id_arsip']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['nama_arsip'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo (int)$a['id_arsip']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <label>Pilih Pengguna
            <select name="id_pengguna" required>
                <option value="">-- Pilih Pengguna --</option>
                <?php foreach ($pengguna_list as $p): ?>
                    <option value="<?php echo (int)$p['id_pengguna']; ?>" <?php echo ((int)$data['id_pengguna'] === (int)$p['id_pengguna']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['nama_pengguna'] . ' - ' . $p['jabatan'], ENT_QUOTES, 'UTF-8'); ?> (ID: <?php echo (int)$p['id_pengguna']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <label>Tanggal Pinjam
            <input type="date" name="tanggal_pinjam" value="<?php echo htmlspecialchars((string)($data['tanggal_pinjam'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Tanggal Kembali (kosongkan jika belum kembali)
            <input type="date" name="tanggal_kembali" value="<?php echo htmlspecialchars((string)($data['tanggal_kembali'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        
        <label>Status Peminjaman
            <select name="status_peminjaman" required>
                <option value="Dipinjam" <?php echo (($data['status_peminjaman'] ?? '') === 'Dipinjam') ? 'selected' : ''; ?>>Dipinjam</option>
                <option value="Dikembalikan" <?php echo (($data['status_peminjaman'] ?? '') === 'Dikembalikan') ? 'selected' : ''; ?>>Dikembalikan</option>
            </select>
        </label>
        
        <button type="submit">Simpan Perubahan</button>
    </form>
</body>
</html>