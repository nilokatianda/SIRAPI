<?php
session_start();
require_once __DIR__ . "/../includes/koneksi.php";

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
            $password_hashed = md5($password);
            $sql = "INSERT INTO pengguna (nama_pengguna, username, password, nim, jabatan, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssss", $nama_pengguna, $username, $password_hashed, $nim, $jabatan, $role);
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
    <title>Pendaftaran Akun Baru - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 50%, #bbf7d0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .card-reg {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            padding: 34px 28px;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(21, 128, 61, 0.15);
            border: 1px solid #bbf7d0;
        }
        .reg-header {
            text-align: center;
            margin-bottom: 22px;
        }
        .reg-header h1 {
            font-size: 21px;
            color: #0f172a;
            margin-bottom: 4px;
            justify-content: center;
        }
        .reg-header p {
            font-size: 12.5px;
            color: #64748b;
            margin: 0;
        }
        form label {
            display: block;
            margin-bottom: 12px;
            font-size: 11.5px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        form input {
            display: block;
            width: 100%;
            margin-top: 5px;
            padding: 9px 12px;
            font-family: inherit;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s;
        }
        form input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }
        .btn-submit-reg {
            width: 100%;
            margin-top: 10px;
            padding: 11px;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 8px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
            transition: all 0.25s ease;
        }
        .btn-submit-reg:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 6px 16px rgba(21, 128, 61, 0.35);
            transform: translateY(-2px);
        }
        .footer-links {
            margin-top: 18px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .footer-links a {
            color: #15803d;
            font-weight: 700;
            text-decoration: none;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card-reg">
        <div class="reg-header">
            <h1>Pendaftaran Akun Baru</h1>
            <p>Registrasi untuk mengakses sistem kearsipan SIRAPI</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
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
                <a href="../index.php" style="color: #64748b; font-size: 12px;">&larr; Kembali ke Beranda Publik</a>
            </div>
        </div>
    </div>
</body>
</html>

