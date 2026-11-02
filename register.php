<?php
session_start();
require_once "koneksi.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $nim = trim($_POST['nim'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $role = 'pengguna'; // Otomatis sebagai pengguna biasa

    if ($nama_pengguna === "" || $username === "" || $password === "") {
        $error = "Nama, Username, dan Password wajib diisi.";
    } else {
        // Cek apakah username sudah ada
        $cek = mysqli_prepare($conn, "SELECT id_pengguna FROM pengguna WHERE username = ?");
        mysqli_stmt_bind_param($cek, "s", $username);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $error = "Username '$username' sudah terdaftar. Silakan gunakan username lain.";
        } else {
            $sql = "INSERT INTO pengguna (nama_pengguna, username, password, nim, jabatan, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssss", $nama_pengguna, $username, $password, $nim, $jabatan, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $success = "Pendaftaran berhasil! Silakan <a href='login.php' style='color:#15803d; font-weight:bold; text-decoration:underline;'>Klik di sini untuk Login</a>.";
                } else {
                    $error = "Gagal mendaftar: " . mysqli_stmt_error($stmt);
                }
            } else {
                $error = "Gagal menyiapkan query: " . mysqli_error($conn);
            }
        }
    }
}
$data = $_POST ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengguna Baru - SIRAPI</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card-register {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            padding: 38px 32px;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(21, 128, 61, 0.15);
            border: 1px solid #bbf7d0;
            border-top: 6px solid #15803d;
        }
        .header-register {
            text-align: center;
            margin-bottom: 22px;
        }
        .header-register h1 {
            font-size: 22px;
            color: #14532d;
            margin-bottom: 6px;
            justify-content: center;
        }
        .header-register p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
        .card-register form {
            background: transparent;
            padding: 0;
            border: none;
            box-shadow: none;
            margin: 0;
            max-width: 100%;
        }
        .card-register form label {
            text-transform: none;
            font-size: 13px;
            color: #334155;
            margin-bottom: 12px;
        }
        .card-register form input {
            padding: 10px 12px;
            font-size: 13px;
            border-radius: 6px;
            margin-top: 5px;
            border-color: #cbd5e1;
        }
        .card-register form input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }
        .btn-submit-reg {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 10px rgba(21, 128, 61, 0.2);
            margin-top: 10px;
        }
        .btn-submit-reg:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(21, 128, 61, 0.3);
        }
        .footer-links {
            margin-top: 22px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 16px;
        }
    </style>
</head>
<body>
    <div class="card-register">
        <div class="header-register">
            <h1>Registrasi Akun Baru</h1>
            <p>Daftarkan akun anggota/staff UKM Kependudukan</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="hapus"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Nama Lengkap
                <input type="text" name="nama_pengguna" value="<?php echo htmlspecialchars((string)($data['nama_pengguna'] ?? '')); ?>" required placeholder="Contoh: Muhammad Budi">
            </label>
            <label>NIM
                <input type="text" name="nim" value="<?php echo htmlspecialchars((string)($data['nim'] ?? '')); ?>" placeholder="Contoh: 44324105">
            </label>
            <label>Jabatan di UKM
                <input type="text" name="jabatan" value="<?php echo htmlspecialchars((string)($data['jabatan'] ?? 'Staff')); ?>" placeholder="Contoh: Staff / Anggota">
            </label>
            <label>Username Baru
                <input type="text" name="username" value="<?php echo htmlspecialchars((string)($data['username'] ?? '')); ?>" required placeholder="Contoh: budi123">
            </label>
            <label>Password
                <input type="password" name="password" required placeholder="Buat password login">
            </label>

            <button type="submit" class="btn-submit-reg">Daftar Sekarang</button>
        </form>

        <div class="footer-links">
            Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
            <div style="margin-top: 8px;">
                <a href="index.php" style="color: #64748b; font-size: 12px;">&larr; Kembali ke Beranda Publik</a>
            </div>
        </div>
    </div>
</body>
</html>
