<?php
session_start();
require_once __DIR__ . "/../includes/koneksi.php";

// Jika sudah login, langsung arahkan ke halamannya masing-masing
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../dashboard/admin_dashboard.php");
    } else {
        header("Location: ../dashboard/user_dashboard.php");
    }
    exit;
}

$error = "";
$pesan_info = "";
if (isset($_GET['pesan']) && $_GET['pesan'] === 'belum_login') {
    $pesan_info = "Sesi belum aktif atau telah berakhir. Silakan login terlebih dahulu untuk mengakses halaman tersebut.";
}

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

            // Jika ada tujuan URL redirect dari halaman yang dicoba diakses sebelumnya
            if (!empty($_SESSION['redirect_url'])) {
                $target_redirect = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: " . $target_redirect);
                exit;
            }

            // Deteksi otomatis role
            if ($user['role'] === 'admin') {
                header("Location: ../dashboard/admin_dashboard.php");
            } else {
                header("Location: ../dashboard/user_dashboard.php");
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
    <link rel="stylesheet" href="../assets/css/style.css">

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
        }

        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
        }

        .login-header h1 {
            font-size: 22px;
            color: #0f172a;
            margin-bottom: 4px;
            justify-content: center;
        }

        .login-header p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-info-guard {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 20px;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .login-form label {
            display: block;
            margin-bottom: 16px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-form input {
            display: block;
            width: 100%;
            margin-top: 6px;
            padding: 11px 14px;
            font-family: inherit;
            font-size: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s;
        }

        .login-form input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.15);
        }

        .btn-submit-login {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            font-size: 14px;
            font-weight: 700;
            border-radius: 8px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
            transition: all 0.25s ease;
        }

        .btn-submit-login:hover {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 6px 16px rgba(21, 128, 61, 0.35);
            transform: translateY(-2px);
        }

        .role-hint {
            margin-top: 18px;
            padding: 10px 12px;
            background: #f0fdf4;
            border-radius: 8px;
            border: 1px dashed #86efac;
            font-size: 11.5px;
            color: #166534;
            line-height: 1.45;
            text-align: center;
        }

        .footer-links {
            margin-top: 22px;
            text-align: center;
            font-size: 12.5px;
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
    <div class="card-login">
        <div class="login-header">
            <div class="logo-login">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                    <path d="M6 6h10"/>
                    <path d="M6 10h10"/>
                </svg>
            </div>
            <h1>Masuk ke SIRAPI</h1>
            <p>Satu pintu masuk untuk Admin & Pengguna</p>
        </div>

        <?php if ($pesan_info !== ""): ?>
            <div class="alert-info-guard">
                <span>&#128274;</span>
                <div><?php echo htmlspecialchars($pesan_info); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert-error">
                <span>&#9888;</span>
                <div><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="login-form">
            <label>
                Username
                <input
                    type="text"
                    name="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
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
                    href="../index.php"
                    style="color: #64748b; font-size: 12px; font-weight: normal;"
                >
                    &larr; Kembali ke Beranda Publik
                </a>
            </div>
        </div>
    </div>
</body>
</html>

