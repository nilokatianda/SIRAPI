
<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

// Ambil data arsip termasuk file_arsip
$cek = mysqli_prepare(
    $conn,
    "SELECT id_arsip, nama_arsip, nama_file,
            file_arsip, tanggal_upload, id_kegiatan
     FROM arsip
     WHERE id_arsip = ?"
);

mysqli_stmt_bind_param($cek, "i", $id);
mysqli_stmt_execute($cek);
$hasil = mysqli_stmt_get_result($cek);
$data = mysqli_fetch_assoc($hasil);
mysqli_stmt_close($cek);

if (!$data) {
    die("Data tidak ditemukan. <a href='index.php'>Kembali</a>");
}

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

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama_arsip = trim($_POST['nama_arsip'] ?? '');
    $nama_file = trim($_POST['nama_file'] ?? '');
    $tanggal_upload = trim($_POST['tanggal_upload'] ?? '');
    $id_kegiatan_raw = trim($_POST['id_kegiatan'] ?? '');

    $id_kegiatan = (
        $id_kegiatan_raw !== '' &&
        ctype_digit($id_kegiatan_raw)
    ) ? (int)$id_kegiatan_raw : null;

    if ($tanggal_upload === '') {
        $tanggal_upload = null;
    }

    // Pertahankan file lama jika tidak ada upload baru
    $file_arsip = $data['file_arsip'];
    $lokasi_file_baru = null;

    if ($nama_arsip === '') {
        $error = "Nama arsip tidak boleh kosong.";
    }

    // Proses upload file baru
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

                    $folder_upload = __DIR__ . "/../uploads/arsip/";

                    if (!is_dir($folder_upload)) {
                        mkdir($folder_upload, 0755, true);
                    }

                    if (!is_dir($folder_upload) ||
                        !is_writable($folder_upload)) {

                        $error = "Folder upload tidak dapat digunakan.";

                    } else {

                        $nama_baru = date('Ymd_His') . '_' .
                            bin2hex(random_bytes(5)) . '.' . $ekstensi;

                        $lokasi_file_baru = $folder_upload . $nama_baru;

                        if (move_uploaded_file(
                            $file['tmp_name'],
                            $lokasi_file_baru
                        )) {
                            $file_arsip = $nama_baru;
                        } else {
                            $error = "File gagal disimpan ke folder upload.";
                            $lokasi_file_baru = null;
                        }
                    }
                }
            }
        }
    }

    // Update data ke database
    if ($error === '') {

        try {

            if ($id_kegiatan === null) {

                $sql = "UPDATE arsip SET
                            nama_arsip = ?,
                            nama_file = ?,
                            file_arsip = ?,
                            tanggal_upload = ?,
                            id_kegiatan = NULL
                        WHERE id_arsip = ?";

                $stmt = mysqli_prepare($conn, $sql);

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nama_arsip,
                        $nama_file,
                        $file_arsip,
                        $tanggal_upload,
                        $id
                    );
                }

            } else {

                $sql = "UPDATE arsip SET
                            nama_arsip = ?,
                            nama_file = ?,
                            file_arsip = ?,
                            tanggal_upload = ?,
                            id_kegiatan = ?
                        WHERE id_arsip = ?";

                $stmt = mysqli_prepare($conn, $sql);

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssii",
                        $nama_arsip,
                        $nama_file,
                        $file_arsip,
                        $tanggal_upload,
                        $id_kegiatan,
                        $id
                    );
                }
            }

            if ($stmt) {

                if (mysqli_stmt_execute($stmt)) {

                    mysqli_stmt_close($stmt);

                    header("Location: index.php");
                    exit;

                } else {

                    $error = "Gagal mengubah data: " .
                        mysqli_stmt_error($stmt);

                    mysqli_stmt_close($stmt);
                }

            } else {
                $error = "Gagal menyiapkan query: " .
                    mysqli_error($conn);
            }

        } catch (mysqli_sql_exception $e) {
            $error = "Gagal mengubah data. Periksa kembali data kegiatan dan database.";
        }

        // Hapus file baru jika update database gagal
        if ($error !== '' &&
            $lokasi_file_baru !== null &&
            is_file($lokasi_file_baru)) {

            unlink($lokasi_file_baru);
        }
    }

    // Pertahankan isian form jika terjadi error
    foreach ($_POST as $key => $value) {
        if (is_string($value)) {
            $data[$key] = $value;
        }
    }

    $data['file_arsip'] = $file_arsip;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Arsip - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

    <h1>Edit Data Arsip</h1>

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

        <?php if (!empty($data['file_arsip'])): ?>
            <p>
                File saat ini:
                <a
                    href="../uploads/arsip/<?= rawurlencode(basename($data['file_arsip'])) ?>"
                    target="_blank"
                    rel="noopener"
                >
                    Lihat File
                </a>
            </p>
        <?php else: ?>
            <p>Belum ada file yang diunggah.</p>
        <?php endif; ?>

        <label>Tanggal Upload
            <input
                type="date"
                name="tanggal_upload"
                value="<?= htmlspecialchars((string)($data['tanggal_upload'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            >
        </label>

        <label>Pilih Kegiatan
            <select name="id_kegiatan">
                <option value="">-- Pilih Kegiatan (Boleh Kosong) --</option>

                <?php foreach ($kegiatan_list as $k): ?>
                    <option
                        value="<?= (int)$k['id_kegiatan'] ?>"
                        <?= ((string)($data['id_kegiatan'] ?? '') ===
                            (string)$k['id_kegiatan']) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($k['nama_kegiatan'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>

            </select>
        </label>

        <button type="submit">Simpan Perubahan</button>

    </form>

</body>
</html>
