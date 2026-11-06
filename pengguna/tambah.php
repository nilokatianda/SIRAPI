
<?php
require_once "../includes/auth_check.php";
require_admin();
require_once "../includes/koneksi.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password_asli = trim($_POST['password'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $role = trim($_POST['role'] ?? 'pengguna');

    // Validasi data
    if ($nama_pengguna === '' || $username === '' || $password_asli === '') {
        $error = "Nama pengguna, username, dan password wajib diisi.";

    } elseif (!in_array($role, ['admin', 'pengguna'], true)) {
        $error = "Role tidak valid.";

    } else {

        // Cek username sudah digunakan atau belum
        $cek = mysqli_prepare(
            $conn,
            "SELECT id_pengguna FROM pengguna WHERE username = ?"
        );

        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {

            $error = "Username '$username' sudah terdaftar, silakan gunakan username lain.";

        } else {

            // Mengubah password menjadi MD5
            $password = md5($password_asli);

            // Menyimpan data pengguna
            $sql = "INSERT INTO pengguna
                    (nama_pengguna, username, password, nim, jabatan, role)
                    VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssss",
                    $nama_pengguna,
                    $username,
                    $password,
                    $nim,
                    $jabatan,
                    $role
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Gagal menambahkan data: " . mysqli_stmt_error($stmt);
                }

                mysqli_stmt_close($stmt);

            } else {
                $error = "Gagal menyiapkan query: " . mysqli_error($conn);
            }
        }

        mysqli_stmt_close($cek);
    }
}

$data = $_POST ?? [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pengguna - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <h1>Tambah Data Pengguna</h1>

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

        <label>Password
            <input
                type="password"
                name="password"
                placeholder="Masukkan password"
                required
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
                    <?= (($data['role'] ?? 'pengguna') === 'pengguna') ? 'selected' : '' ?>>
                    Pengguna (Staff/Anggota)
                </option>

                <option value="admin"
                    <?= (($data['role'] ?? '') === 'admin') ? 'selected' : '' ?>>
                    Admin (Pengelola)
                </option>
            </select>
        </label>

        <button type="submit">Simpan</button>

    </form>

</body>
</html>
