<?php
session_start();
require_once __DIR__ . "/includes/koneksi.php";

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;
$user_role = $_SESSION['role'] ?? '';
$user_name = $_SESSION['nama_pengguna'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIRAPI - Sistem Informasi Kearsipan Terpadu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #15803d;
            --primary-dark: #14532d;
            --primary-light: #22c55e;
            --primary-soft: #f0fdf4;
            --primary-border: #bbf7d0;
            --text-dark: #1e293b;
            --text-gray: #64748b;
            --surface: #ffffff;
            --bg-page: #ffffff;
            --radius-pill: 9999px;
            --radius-card: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Container Pembungkus */
        .wrapper {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ==========================================================================
           HEADER / NAVBAR
           ========================================================================== */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 0 10px;
            position: relative;
            z-index: 50;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-dark);
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
        }

        .brand-text h1 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            letter-spacing: -0.5px;
        }

        .brand-text span {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-gray);
            letter-spacing: 0.2px;
        }

        /* Center Nav Capsule */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #ffffff;
            padding: 6px 10px;
            border-radius: var(--radius-pill);
            box-shadow: 0 2px 14px rgba(0, 0, 0, 0.06);
            border: 1px solid #f1f5f9;
        }

        .nav-links a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: var(--radius-pill);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-gray);
            transition: all 0.2s ease;
        }

        .nav-links a.active {
            background-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 2px 8px rgba(21, 128, 61, 0.3);
        }

        .nav-links a:hover:not(.active) {
            color: var(--primary);
            background-color: var(--primary-soft);
        }

        /* Tombol Login di Kanan */
        .btn-login-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--primary);
            color: #ffffff !important;
            padding: 9px 22px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
            transition: all 0.25s ease;
        }

        .btn-login-header:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(21, 128, 61, 0.35);
        }

        /* ==========================================================================
           HERO SECTION
           ========================================================================== */
        .hero-container {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            align-items: center;
            gap: 40px;
            padding: 50px 0 70px;
            position: relative;
        }

        /* Efek Latar Belakang Hijau Organik Bergelombang */
        .hero-bg-shapes {
            position: absolute;
            top: 0;
            right: -100px;
            width: 700px;
            height: 550px;
            background: radial-gradient(circle at 60% 40%, rgba(34, 197, 94, 0.18) 0%, rgba(240, 253, 244, 0.5) 60%, transparent 80%);
            border-radius: 50%;
            z-index: 1;
            filter: blur(40px);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 10;
        }

        .hero-title {
            font-size: 46px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.15;
            letter-spacing: -1.2px;
            margin-bottom: 20px;
        }

        .sparkle-icon {
            display: inline-block;
            vertical-align: middle;
            color: var(--primary);
            margin-left: 6px;
        }

        .hero-desc {
            font-size: 15px;
            color: #475569;
            line-height: 1.7;
            margin-bottom: 32px;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .btn-primary-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--primary);
            color: #ffffff;
            padding: 13px 26px;
            border-radius: var(--radius-pill);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(21, 128, 61, 0.3);
            transition: all 0.25s ease;
        }

        .btn-primary-hero:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 128, 61, 0.4);
        }

        .btn-secondary-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #ffffff;
            color: #334155;
            padding: 12px 24px;
            border-radius: var(--radius-pill);
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            transition: all 0.25s ease;
        }

        .btn-secondary-hero:hover {
            border-color: var(--primary);
            color: var(--primary);
            background-color: var(--primary-soft);
        }

        /* Hero Image Container */
        .hero-media {
            position: relative;
            z-index: 10;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .hero-illustration {
            width: 100%;
            max-width: 540px;
            height: auto;
            border-radius: 24px;
            filter: drop-shadow(0 15px 30px rgba(21, 128, 61, 0.15));
            transition: transform 0.4s ease;
        }

        .hero-illustration:hover {
            transform: translateY(-4px) scale(1.01);
        }

        /* ==========================================================================
           WAVE DIVIDER & FITUR SECTION
           ========================================================================== */
        .features-wrapper {
            position: relative;
            background: linear-gradient(180deg, #f0fdf4 0%, #e6f9ed 100%);
            padding: 60px 0 90px;
            border-radius: 40px 40px 0 0;
            margin-top: 20px;
        }

        .features-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .badge-pill-feature {
            display: inline-block;
            background: #dcfce7;
            color: var(--primary);
            border: 1px solid var(--primary-border);
            padding: 5px 18px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 12px;
        }

        .features-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.6px;
        }

        /* 3 Kartu Fitur Bersih */
        .cards-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }

        .feature-box {
            background: #ffffff;
            border-radius: 18px;
            padding: 28px 24px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(187, 247, 208, 0.5);
            transition: all 0.3s ease;
        }

        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(21, 128, 61, 0.12);
            border-color: var(--primary-border);
        }

        .feature-icon-circle {
            width: 52px;
            height: 52px;
            background-color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(21, 128, 61, 0.25);
        }

        .feature-box-content h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .feature-box-content p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.55;
            margin: 0;
        }

        /* ==========================================================================
           FOOTER
           ========================================================================== */
        footer {
            background-color: #0d4a22;
            color: #dcfce7;
            text-align: center;
            padding: 24px 20px;
            font-size: 13px;
        }

        footer strong {
            color: #ffffff;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 30px;
                padding: 30px 0 40px;
            }
            .hero-title {
                font-size: 36px;
            }
            .hero-desc {
                margin-left: auto;
                margin-right: auto;
            }
            .hero-buttons {
                justify-content: center;
            }
            .nav-links {
                display: none; /* Sembunyikan navigasi tengah di layar kecil agar tidak sesak */
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <header class="wrapper">
        <nav class="navbar">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                        <path d="M6 6h10"/>
                        <path d="M6 10h10"/>
                        <path d="M6 14h7"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <h1>SIRAPI</h1>
                    <span>Sistem Informasi Kearsipan Terpadu</span>
                </div>
            </a>

            <!-- Menu Kapsul Tengah -->
            <div class="nav-links">
                <a href="index.php" class="active">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    Beranda
                </a>
                <a href="#fitur">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="7" height="7" x="3" y="3" rx="1"/>
                        <rect width="7" height="7" x="14" y="3" rx="1"/>
                        <rect width="7" height="7" x="14" y="14" rx="1"/>
                        <rect width="7" height="7" x="3" y="14" rx="1"/>
                    </svg>
                    Fitur
                </a>
                <a href="#tentang">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 16v-4"/>
                        <path d="M12 8h.01"/>
                    </svg>
                    Tentang
                </a>
                <a href="#kontak">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Kontak
                </a>
            </div>

            <!-- Tombol Login / Dashboard -->
            <div>
                <?php if ($is_logged_in): ?>
                    <?php if ($user_role === 'admin'): ?>
                        <a href="dashboard/admin_dashboard.php" class="btn-login-header">&#128737; Panel Admin</a>
                    <?php else: ?>
                        <a href="dashboard/user_dashboard.php" class="btn-login-header">&#128100; Halaman Saya</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="auth/login.php" class="btn-login-header">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Login
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- HERO SECTION -->
    <section class="wrapper">
        <div class="hero-container">
            <div class="hero-bg-shapes"></div>

            <!-- Sisi Kiri (Teks & Tombol) -->
            <div class="hero-content">
                <h2 class="hero-title">
                    SIRAPI<br>
                    Sistem Informasi<br>
                    Kearsipan Terpadu
                    <span class="sparkle-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"/>
                        </svg>
                    </span>
                </h2>
                <p class="hero-desc">
                    Platform digital untuk pengarsipan dokumen kegiatan, pengelolaan berkas, dan pencatatan transaksi UKM Kependudukan secara terstruktur dan akuntabel.
                </p>
                <div class="hero-buttons">
                    <?php if (!$is_logged_in): ?>
                        <a href="auth/login.php" class="btn-primary-hero">
                            Masuk ke Sistem &rarr;
                        </a>
                    <?php else: ?>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="dashboard/admin_dashboard.php" class="btn-primary-hero">
                                Buka Panel Admin &rarr;
                            </a>
                        <?php else: ?>
                            <a href="dashboard/user_dashboard.php" class="btn-primary-hero">
                                Buka Halaman Pengguna &rarr;
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <a href="#fitur" class="btn-secondary-hero">
                        Pelajari Lebih Lanjut &or;
                    </a>
                </div>
            </div>

            <!-- Sisi Kanan (Ilustrasi 3D) -->
            <div class="hero-media">
                <img src="uploads/hero-illustration.jpg" alt="Ilustrasi Digital Kearsipan SIRAPI" class="hero-illustration">
            </div>
        </div>
    </section>

    <!-- SECTION FITUR UTAMA DENGAN GELOMBANG HIJAU LEMBUT -->
    <section class="features-wrapper" id="fitur">
        <div class="wrapper">
            <div class="features-header">
                <span class="badge-pill-feature">Fitur Utama</span>
                <h3 class="features-title">Solusi Pengelolaan Arsip yang Efisien</h3>
            </div>

            <div class="cards-row">
                <!-- Kartu 1 -->
                <div class="feature-box">
                    <div class="feature-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>
                        </svg>
                    </div>
                    <div class="feature-box-content">
                        <h3>Pengelolaan Arsip Digital</h3>
                        <p>Simpan dan kelola arsip dengan lebih mudah dan terorganisir.</p>
                    </div>
                </div>

                <!-- Kartu 2 -->
                <div class="feature-box">
                    <div class="feature-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/>
                            <path d="M18 17V9"/>
                            <path d="M13 17V5"/>
                            <path d="M8 17v-3"/>
                        </svg>
                    </div>
                    <div class="feature-box-content">
                        <h3>Akses Cepat & Akurat</h3>
                        <p>Temukan informasi arsip dengan cepat dan tepat.</p>
                    </div>
                </div>

                <!-- Kartu 3 -->
                <div class="feature-box">
                    <div class="feature-icon-circle">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <div class="feature-box-content">
                        <h3>Transparan & Akuntabel</h3>
                        <p>Dukung tata kelola arsip yang transparan dan akuntabel.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION TENTANG -->
    <section class="wrapper" id="tentang" style="padding: 70px 0;">
        <div style="max-width: 800px; margin: 0 auto; text-align: center;">
            <span class="badge-pill-feature" style="background:#f0fdf4; border-color:#bbf7d0;">Tentang Kami</span>
            <h3 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 10px 0 16px;">Mengenai SIRAPI UKM Kependudukan</h3>
            <p style="color: #64748b; font-size: 14px; line-height: 1.8;">
                SIRAPI didesain khusus sebagai media kearsipan modern untuk mendokumentasikan setiap tahapan kegiatan, surat keputusan, proposal, laporan pertanggungjawaban (LPJ), serta transaksi peminjaman berkas kepengurusan UKM Kependudukan.
            </p>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="kontak">
        <p>&copy; <?php echo date('Y'); ?> <strong>SIRAPI</strong> &bull; UKM Kependudukan. Seluruh Hak Cipta Dilindungi.</p>
    </footer>

</body>
</html>