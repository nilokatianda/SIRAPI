
<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";
require_once "../includes/denda_helper.php";

ensure_denda_schema($conn);

$error = "";

// Ambil ID pengguna dari akun yang sedang login
$id_pengguna = (int)($_SESSION['id_pengguna'] ?? 0);

// Tangkap ID arsip dari parameter URL jika diarahkan dari halaman divisi detail
$id_arsip_req = isset($_GET['id_arsip']) ? (int)$_GET['id_arsip'] : 0;

// Ambil daftar arsip beserta ketersediaan stok
$arsip_list = [];
$res_arsip = mysqli_query($conn, "
    SELECT 
        a.id_arsip, a.nama_arsip, a.jumlah_arsip,
        COUNT(CASE WHEN p.status_peminjaman = 'Dipinjam' THEN 1 END) as sedang_dipinjam
    FROM arsip a
    LEFT JOIN peminjaman p ON a.id_arsip = p.id_arsip
    GROUP BY a.id_arsip, a.nama_arsip, a.jumlah_arsip
    ORDER BY a.nama_arsip ASC
");

if ($res_arsip) {
    while ($row = mysqli_fetch_assoc($res_arsip)) {
        $stok_tot = (int)($row['jumlah_arsip'] ?? 1);
        $stok_pinjam = (int)($row['sedang_dipinjam'] ?? 0);
        $row['stok_tersedia'] = max(0, $stok_tot - $stok_pinjam);
        $arsip_list[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_arsip = !empty($_POST['id_arsip']) ? (int)$_POST['id_arsip'] : 0;
    $tanggal_pinjam = trim($_POST['tanggal_pinjam'] ?? '');
    $status_peminjaman = "Dipinjam";

    if ($id_pengguna <= 0) {
        $error = "ID pengguna tidak ditemukan. Silakan login kembali.";
    } elseif ($id_arsip <= 0) {
        $error = "Silakan pilih arsip.";
    } elseif ($tanggal_pinjam === '') {
        $error = "Tanggal pinjam wajib diisi.";
    } else {
        // Cek ketersediaan stok arsip
        $stok_cukup = true;
        foreach ($arsip_list as $a_item) {
            if ((int)$a_item['id_arsip'] === $id_arsip) {
                if ((int)$a_item['stok_tersedia'] <= 0) {
                    $stok_cukup = false;
                    $error = "Mohon maaf, semua berkas arsip ini saat ini sedang dipinjam oleh anggota lain.";
                }
                break;
            }
        }

        if ($stok_cukup) {
            // Validasi tanggal pinjam
            $tanggal = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam);

            if (!$tanggal || $tanggal->format('Y-m-d') !== $tanggal_pinjam) {
                $error = "Tanggal pinjam tidak valid.";
            } else {
                // Hitung tanggal kembali otomatis 5 hari setelah pinjam
                $tanggal->modify('+5 days');
                $tanggal_kembali = $tanggal->format('Y-m-d');

                try {
                    $sql = "INSERT INTO peminjaman
                            (id_arsip, id_pengguna, tanggal_pinjam, tanggal_kembali, status_peminjaman)
                            VALUES (?, ?, ?, ?, ?)";

                    $stmt = mysqli_prepare($conn, $sql);

                    if ($stmt) {
                        mysqli_stmt_bind_param(
                            $stmt,
                            "iisss",
                            $id_arsip,
                            $id_pengguna,
                            $tanggal_pinjam,
                            $tanggal_kembali,
                            $status_peminjaman
                        );

                        if (mysqli_stmt_execute($stmt)) {
                            $new_id = mysqli_insert_id($conn);
                            header("Location: index.php?sukses_pinjam=" . $new_id);
                            exit;
                        } else {
                            $error = "Gagal menambahkan data: " . mysqli_stmt_error($stmt);
                        }
                    } else {
                        $error = "Gagal menyiapkan query: " . mysqli_error($conn);
                    }
                } catch (mysqli_sql_exception $e) {
                    $error = "Gagal menyimpan peminjaman. Periksa kembali data arsip dan pengguna.";
                }
            }
        }
    }
}

// Mengatur tanggal pada formulir
$data = $_POST;
$tanggal_pinjam_form = $data['tanggal_pinjam'] ?? date('Y-m-d');

$tanggal_kembali_form = '';
$tanggal_form = DateTime::createFromFormat('!Y-m-d', $tanggal_pinjam_form);

if ($tanggal_form && $tanggal_form->format('Y-m-d') === $tanggal_pinjam_form) {
    $tanggal_form->modify('+5 days');
    $tanggal_kembali_form = $tanggal_form->format('Y-m-d');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Peminjaman - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

    <h1>Tambah Data Peminjaman</h1>
    <p><a href="index.php">Kembali</a></p>

    <?php if ($error !== ""): ?>
        <p class="hapus">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="post">

        <label>Pilih Arsip
            <select name="id_arsip" required>
                <option value="">-- Pilih Arsip --</option>

                <?php foreach ($arsip_list as $a): 
                    $isSelected = (isset($data['id_arsip']) && (int)$data['id_arsip'] === (int)$a['id_arsip']) || (!isset($data['id_arsip']) && $id_arsip_req === (int)$a['id_arsip']);
                    $stok_tot = (int)($a['jumlah_arsip'] ?? 1);
                    $stok_ter = (int)($a['stok_tersedia'] ?? 0);
                ?>
                    <?php if ($stok_ter > 0): ?>
                        <option value="<?php echo (int)$a['id_arsip']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a['nama_arsip'], ENT_QUOTES, 'UTF-8'); ?> (Tersedia: <?php echo $stok_ter; ?> / <?php echo $stok_tot; ?>)
                        </option>
                    <?php else: ?>
                        <option value="<?php echo (int)$a['id_arsip']; ?>" disabled style="color: #94a3b8;">
                            <?php echo htmlspecialchars($a['nama_arsip'], ENT_QUOTES, 'UTF-8'); ?> (Habis Dipinjam - 0/<?php echo $stok_tot; ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>

            </select>
        </label>

        <label>Tanggal Pinjam
            <input
                type="date"
                name="tanggal_pinjam"
                id="tanggal_pinjam"
                value="<?php echo htmlspecialchars($tanggal_pinjam_form, ENT_QUOTES, 'UTF-8'); ?>"
                required
            >
        </label>

        <label>Batas Tanggal Kembali
            <input
                type="date"
                name="tanggal_kembali"
                id="tanggal_kembali"
                value="<?php echo htmlspecialchars($tanggal_kembali_form, ENT_QUOTES, 'UTF-8'); ?>"
                readonly
            >
        </label>

        <label>Status Peminjaman
            <input type="text" value="Dipinjam" readonly>
        </label>

        <button type="submit">Simpan</button>

    </form>

    <script>
        const tanggalPinjam = document.getElementById('tanggal_pinjam');
        const tanggalKembali = document.getElementById('tanggal_kembali');

        function hitungTanggalKembali() {
            if (!tanggalPinjam.value) {
                tanggalKembali.value = '';
                return;
            }

            const tanggal = new Date(tanggalPinjam.value + 'T00:00:00');
            tanggal.setDate(tanggal.getDate() + 5);

            const tahun = tanggal.getFullYear();
            const bulan = String(tanggal.getMonth() + 1).padStart(2, '0');
            const hari = String(tanggal.getDate()).padStart(2, '0');

            tanggalKembali.value = `${tahun}-${bulan}-${hari}`;
        }

        tanggalPinjam.addEventListener('change', hitungTanggalKembali);
        hitungTanggalKembali();
    </script>

</body>
</html>
