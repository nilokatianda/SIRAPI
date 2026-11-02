
<?php
require_once "../koneksi.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Mengambil data pengguna berdasarkan ID
$cek = mysqli_prepare(
    $conn,
    "SELECT id_pengguna, nama_pengguna, username, nim, jabatan, role
     FROM pengguna WHERE id_pengguna = ?"
);

mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);

$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);

mysqli_stmt_close($cek);

if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password_baru = trim($_POST['password'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $role = trim($_POST['role'] ?? 'pengguna');

    // Validasi data
    if ($nama_pengguna === '' || $username === '') {

        $error = "Nama pengguna dan username wajib diisi.";

    } elseif (!in_array($role, ['admin', 'pengguna'], true)) {

        $error = "Role tidak valid.";

    } else {

        // Cek username digunakan akun lain atau tidak
        $cek_user = mysqli_prepare(
            $conn,
            "SELECT id_pengguna FROM pengguna
             WHERE username = ? AND id_pengguna != ?"
        );

        mysqli_stmt_bind_param($cek_user, "si", $username, $id);
        mysqli_stmt_execute($cek_user);
        mysqli_stmt_store_result($cek_user);

        if (mysqli_stmt_num_rows($cek_user) > 0) {

            $error = "Username '$username' sudah dipakai oleh pengguna lain.";

        } else {

            if ($password_baru !== "") {

                // Password baru diubah menjadi MD5
                $password = md5($password_baru);

                $sql = "UPDATE pengguna
                        SET nama_pengguna = ?,
                            username = ?,
                            password = ?,
                            nim = ?,
                            jabatan = ?,
                            role = ?
                        WHERE id_pengguna = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssi",
                    $nama_pengguna,
                    $username,
                    $password,
                    $nim,
                    $jabatan,
                    $role,
                    $id
                );

            } else {

                // Password tidak berubah jika dikosongkan
                $sql = "UPDATE pengguna
                        SET nama_pengguna = ?,
                            username = ?,
                            nim = ?,
                            jabatan = ?,
                            role = ?
                        WHERE id_pengguna = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssssi",
                    $nama_pengguna,
                    $username,
                    $nim,
                    $jabatan,
                    $role,
                    $id
                );
            }

            if (mysqli_stmt_execute($stmt)) {

                header("Location: index.php");
                exit;

            } else {

                $error = "Gagal mengubah data: " . mysqli_stmt_error($stmt);
            }

            mysqli_stmt_close($stmt);
        }

        mysqli_stmt_close($cek_user);
    }

    // Mempertahankan data formulir ketika terjadi error
    $data['nama_pengguna'] = $nama_pengguna;
    $data['username'] = $username;
    $data['nim'] = $nim;
    $data['jabatan'] = $jabatan;
    $data['role'] = $role;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna - SIRAPI</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>

    <h1>Edit Data Pengguna</h1>

    <p><a href="index.php">Kembali</a></p>

    <?php if ($error !== ""): ?>
        <p class="hapus">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post">

        <label>Nama Pengguna
            <input
                type="text"
                name="nama_pengguna"
                value="<?= htmlspecialchars($data['nama_pengguna'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </label>

        <label>Username
            <input
                type="text"
                name="username"
                value="<?= htmlspecialchars($data['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </label>

        <label>Password Baru
            <small>(Kosongkan jika tidak ingin mengubah password)</small>

            <input
                type="password"
                name="password"
                placeholder="Masukkan password baru jika ingin diganti"
            >
        </label>

        <label>NIM
            <input
                type="text"
                name="nim"
                value="<?= htmlspecialchars($data['nim'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
        </label>

        <label>Jabatan
            <input
                type="text"
                name="jabatan"
                value="<?= htmlspecialchars($data['jabatan'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
        </label>

        <label>Role / Hak Akses

            <select name="role">

                <option value="pengguna"
                    <?= (($data['role'] ?? '') === 'pengguna') ? 'selected' : '' ?>>
                    Pengguna (Staff/Anggota)
                </option>

                <option value="admin"
                    <?= (($data['role'] ?? '') === 'admin') ? 'selected' : '' ?>>
                    Admin (Pengelola)
                </option>

            </select>

        </label>

        <button type="submit">Simpan Perubahan</button>

    </form>

</body>
</html>
