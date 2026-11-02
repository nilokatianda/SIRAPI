<?php
session_start();
require_once "koneksi.php";

// Jika sudah login, langsung arahkan ke halamannya masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === "" || $password === "") {
        $error = "Silakan masukkan username dan password.";
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_pengguna, nama_pengguna, username, password, nim, jabatan, role
             FROM pengguna
             WHERE username = ?"
        );

        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // Verifikasi password menggunakan MD5
        if ($user && md5($password) === $user['password']) {
            $_SESSION['login'] = true;
            $_SESSION['id_pengguna'] = $user['id_pengguna'];
            $_SESSION['nama_pengguna'] = $user['nama_pengguna'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nim'] = $user['nim'];
            $_SESSION['jabatan'] = $user['jabatan'];
            $_SESSION['role'] = $user['role'];

            // Deteksi otomatis role
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit;
        } else {
            $error = "Username atau password yang Anda masukkan salah.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem - SIRAPI</title>
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

        .card-login {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            padding: 40px 34px;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(21, 128, 61, 0.15);
            border: 1px solid #bbf7d0;
            border-top: 6px solid #15803d;
        }

        .header-login {
            text-align: center;
            margin-bottom: 26px;
        }

        .header-login .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #dcfce7;
            color: #15803d;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
            border: 1px solid #bbf7d0;
        }

        .header-login h1 {
            font-size: 24px;
            color: #14532d;
            margin-bottom: 6px;
            justify-content: center;
        }

        .header-login p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .card-login form {
            background: transparent;
            padding: 0;
            border: none;
            box-shadow: none;
            margin: 0;
            max-width: 100%;
        }

        .card-login form label {
            text-transform: none;
            font-size: 13px;
            color: #334155;
            margin-bottom: 18px;
        }

        .card-login form input {
            padding: 12px 14px;
            font-size: 14px;
            border-radius: 8px;
            margin-top: 6px;
            border-color: #cbd5e1;
        }

        .card-login form input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }

        .btn-submit-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
            margin-top: 10px;
        }

        .btn-submit-login:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(21, 128, 61, 0.35);
        }

        .role-hint {
            background: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 20px;
            font-size: 12px;
            color: #166534;
            text-align: center;
        }

        .footer-links {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
            padding-top: 18px;
        }
    </style>
</head>

<body>
    <div class="card-login">
        <div class="header-login">
            <span class="logo-badge">&#128272; Satu Pintu Masuk</span>
            <h1>SIRAPI Login</h1>
            <p>Sistem Informasi Arsip UKM Kependudukan</p>
        </div>

        <?php if ($error !== ""): ?>
            <div class="hapus">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>
                Username
                <input
                    type="text"
                    name="username"
                    placeholder="Masukkan username Anda"
                    required
                    autofocus
                >
            </label>

            <label>
                Password
                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan password Anda"
                    required
                >
            </label>

            <button type="submit" class="btn-submit-login">
                Masuk ke Sistem &rarr;
            </button>
        </form>

        <div class="role-hint">
            &#9432; Sistem otomatis mendeteksi apakah Anda
            <strong>Admin</strong> atau <strong>Pengguna</strong>
            dan mengarahkan ke halaman terkait.
        </div>

        <div class="footer-links">
            <p>
                Belum memiliki akun?
                <a href="register.php">Daftar Pengguna Baru</a>
            </p>

            <div style="margin-top: 10px;">
                <a
                    href="index.php"
                    style="color: #64748b; font-size: 12px; font-weight: normal;"
                >
                    &larr; Kembali ke Beranda Publik
                </a>
            </div>
        </div>
    </div>
</body>
</html>
