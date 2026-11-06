
<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";

$error = "";

// Ambil daftar kegiatan
$kegiatan_list = [];
$res_kegiatan = mysqli_query(
    $conn,
    "SELECT id_kegiatan, nama_kegiatan
     FROM kegiatan
     ORDER BY nama_kegiatan ASC"
);

if ($res_kegiatan) {
    while ($row = mysqli_fetch_assoc($res_kegiatan)) {
        $kegiatan_list[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama_arsip = trim($_POST['nama_arsip'] ?? '');
    $nama_file = trim($_POST['nama_file'] ?? '');
    $tanggal_upload = trim($_POST['tanggal_upload'] ?? '');
    $id_kegiatan_raw = trim($_POST['id_kegiatan'] ?? '');

    $id_kegiatan = (
        $id_kegiatan_raw !== '' &&
        is_numeric($id_kegiatan_raw)
    ) ? (int)$id_kegiatan_raw : null;

    if ($tanggal_upload === '') {
        $tanggal_upload = null;
    }

    $file_arsip = null;
    $lokasi_file = null;

    // Validasi nama arsip
    if ($nama_arsip === '') {
        $error = "Nama arsip tidak boleh kosong.";
    }

    // Proses upload file jika pengguna memilih file
    if ($error === '' && isset($_FILES['file_arsip'])) {

        $file = $_FILES['file_arsip'];

        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $error = "Terjadi kesalahan saat mengunggah file.";

            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $error = "Ukuran file maksimal 10 MB.";

            } else {

                $ekstensi = strtolower(
                    pathinfo($file['name'], PATHINFO_EXTENSION)
                );

                $ekstensi_diizinkan = [
                    'pdf', 'doc', 'docx',
                    'xls', 'xlsx', 'ppt', 'pptx',
                    'odt', 'ods', 'odp',
                    'txt', 'csv',
                    'jpg', 'jpeg', 'png', 'gif', 'webp',
                    'zip', 'rar', '7z'
                ];

                if (!in_array($ekstensi, $ekstensi_diizinkan, true)) {
                    $error = "Format file tidak diizinkan.";

                } else {

                    // Membuat folder upload jika belum tersedia
                    $folder_upload = __DIR__ . "/../uploads/arsip/";

                    if (!is_dir($folder_upload)) {
                        mkdir($folder_upload, 0755, true);
                    }

                    if (!is_dir($folder_upload) || !is_writable($folder_upload)) {
                        $error = "Folder upload tidak dapat digunakan.";

                    } else {

                        // Membuat nama file baru agar tidak bentrok
                        $nama_baru = date('Ymd_His') . '_' .
                            bin2hex(random_bytes(5)) . '.' . $ekstensi;

                        $lokasi_file = $folder_upload . $nama_baru;

                        if (move_uploaded_file(
                            $file['tmp_name'],
                            $lokasi_file
                        )) {
                            $file_arsip = $nama_baru;
                        } else {
                            $error = "File gagal disimpan ke folder upload.";
                        }
                    }
                }
            }
        }
    }

    // Simpan data ke database
    if ($error === '') {

        try {

            if ($id_kegiatan === null) {

                $sql = "INSERT INTO arsip
                        (nama_arsip, nama_file, file_arsip,
                         tanggal_upload, id_kegiatan)
                        VALUES (?, ?, ?, ?, NULL)";

                $stmt = mysqli_prepare($conn, $sql);

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssss",
                        $nama_arsip,
                        $nama_file,
                        $file_arsip,
                        $tanggal_upload
                    );
                }

            } else {

                $sql = "INSERT INTO arsip
                        (nama_arsip, nama_file, file_arsip,
                         tanggal_upload, id_kegiatan)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nama_arsip,
                        $nama_file,
                        $file_arsip,
                        $tanggal_upload,
                        $id_kegiatan
                    );
                }
            }

            if ($stmt) {

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header("Location: index.php");
                    exit;

                } else {
                    $error = "Gagal menyimpan data: " .
                        mysqli_stmt_error($stmt);

                    mysqli_stmt_close($stmt);
                }

            } else {
                $error = "Gagal menyiapkan query: " .
                    mysqli_error($conn);
            }

        } catch (mysqli_sql_exception $e) {
            $error = "Gagal menyimpan data. Periksa kembali data kegiatan dan database.";
        }

        // Hapus file jika penyimpanan database gagal
        if ($error !== '' && $lokasi_file !== null &&
            is_file($lokasi_file)) {
            unlink($lokasi_file);
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
    <title>Tambah Arsip - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <h1>Tambah Data Arsip</h1>

    <p><a href="index.php">Kembali</a></p>

    <?php if ($error !== ""): ?>
        <p class="hapus">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <label>Nama Arsip
            <input
                type="text"
                name="nama_arsip"
                value="<?= htmlspecialchars((string)($data['nama_arsip'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                required
            >
        </label>

        <label>Nama File
            <input
                type="text"
                name="nama_file"
                value="<?= htmlspecialchars((string)($data['nama_file'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </label>

        <label>Upload File Arsip
            <input
                type="file"
                name="file_arsip"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.7z"
            >
            <small>Maksimal ukuran file 10 MB.</small>
        </label>

        <label>Tanggal Upload
            <input
                type="date"
                name="tanggal_upload"
                value="<?= htmlspecialchars((string)($data['tanggal_upload'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8') ?>"
            >
        </label>

        <label>Pilih Kegiatan
            <select name="id_kegiatan">
                <option value="">-- Pilih Kegiatan (Boleh Kosong) --</option>

                <?php foreach ($kegiatan_list as $k): ?>
                    <option
                        value="<?= (int)$k['id_kegiatan'] ?>"
                        <?= (isset($data['id_kegiatan']) &&
                            (string)$data['id_kegiatan'] ===
                            (string)$k['id_kegiatan']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($k['nama_kegiatan'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </label>

        <button type="submit">Simpan</button>

    </form>

</body>
</html>
