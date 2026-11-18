
<?php
require_once "../includes/auth_check.php";
require_once "../includes/koneksi.php";

$result = mysqli_query(
    $conn,
    "SELECT a.id_arsip, a.nama_arsip, a.nama_file, a.file_arsip,
            a.tanggal_upload, a.id_kegiatan, a.jumlah_arsip,
            COUNT(CASE WHEN p.status_peminjaman = 'Dipinjam' THEN 1 END) AS sedang_dipinjam
     FROM arsip a
     LEFT JOIN peminjaman p ON a.id_arsip = p.id_arsip
     GROUP BY a.id_arsip, a.nama_arsip, a.nama_file, a.file_arsip, a.tanggal_upload, a.id_kegiatan, a.jumlah_arsip
     ORDER BY a.id_arsip ASC"
);

if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Arsip - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>Data Arsip</h1>

    <p><a href="<?= $dashboard_url ?>" class="btn-kembali">&larr; <?= $dashboard_label ?></a></p>

    <div class="table-toolbar-bar">
        <div class="table-toolbar-left">
            <a class="tombol" href="tambah.php">+ Tambah Data Arsip</a>
        </div>
        <div class="table-search-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="arsipSearch" placeholder="Cari nama arsip, file, atau tanggal..." onkeyup="liveSearchTable('arsipSearch', 'arsipTable', 'arsipCount')">
        </div>
    </div>

    <table id="arsipTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Arsip</th>
                <th>Jumlah Arsip</th>
                <th>Nama File</th>
                <th>File Arsip</th>
                <th>Tanggal Upload</th>
                <th>ID Kegiatan</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars((string)$row["id_arsip"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["nama_arsip"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?php 
                        $stok_tot = (int)($row["jumlah_arsip"] ?? 1);
                        $stok_pinjam = (int)($row["sedang_dipinjam"] ?? 0);
                        $stok_tersedia = max(0, $stok_tot - $stok_pinjam);
                        ?>
                        <?php if ($stok_pinjam > 0): ?>
                            <span style="display: inline-block; padding: 3px 10px; background: #fffbeb; color: #b45309; font-weight: 700; border-radius: 9999px; font-size: 11px; border: 1px solid #fde68a;" title="<?= $stok_pinjam ?> berkas sedang dipinjam">
                                <strong><?= $stok_tersedia ?></strong> / <?= $stok_tot ?> Tersedia
                            </span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 3px 10px; background: #ecfdf5; color: #047857; font-weight: 700; border-radius: 9999px; font-size: 11px; border: 1px solid #a7f3d0;" title="Semua berkas tersedia">
                                <strong><?= $stok_tot ?></strong> Tersedia
                            </span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["nama_file"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?php
                        $file = basename($row["file_arsip"] ?? "");
                        $lokasi = __DIR__ . "/../uploads/arsip/" . $file;
                        ?>

                        <?php if ($file !== "" && is_file($lokasi)): ?>
                            <a
                                href="../uploads/arsip/<?= rawurlencode($file) ?>"
                                target="_blank"
                                rel="noopener"
                            >Lihat File</a>
                        <?php else: ?>
                            Belum ada file
                        <?php endif; ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["tanggal_upload"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td>
                        <?= htmlspecialchars((string)$row["id_kegiatan"], ENT_QUOTES, "UTF-8") ?>
                    </td>

                    <td class="aksi">
                        <a href="edit.php?id=<?= (int)$row["id_arsip"] ?>">Edit</a>
                        <span class="pemisah"></span>
                        <a
                            class="hapus"
                            href="hapus.php?id=<?= (int)$row["id_arsip"] ?>"
                            onclick="return confirm('Yakin ingin menghapus data ini?')"
                        >Hapus</a>
                    </td>
                </tr>
            <?php endwhile; ?>

        <?php else: ?>
            <tr>
                <td colspan="8">Belum ada data.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <script>
    function liveSearchTable(inputId, tableId, countId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toLowerCase().trim();
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;
        let totalCount = 0;

        rows.forEach(row => {
            if (row.classList.contains('no-result-row')) return;
            totalCount++;

            const text = row.innerText.toLowerCase();
            if (filter === '' || text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        let noResultEl = tbody.querySelector('.no-result-row');
        if (visibleCount === 0 && totalCount > 0) {
            if (!noResultEl) {
                noResultEl = document.createElement('tr');
                noResultEl.className = 'no-result-row';
                const colCount = table.querySelectorAll('thead th').length || 8;
                noResultEl.innerHTML = `<td colspan="${colCount}" style="text-align: center; padding: 24px; color: #94a3b8; font-weight: 600;">🔍 Tidak ditemukan data arsip yang sesuai dengan pencarian "${input.value}"</td>`;
                tbody.appendChild(noResultEl);
            } else {
                noResultEl.style.display = '';
                noResultEl.querySelector('td').innerHTML = `🔍 Tidak ditemukan data arsip yang sesuai dengan pencarian "${input.value}"`;
            }
        } else if (noResultEl) {
            noResultEl.style.display = 'none';
        }

        if (countId) {
            const countBadge = document.getElementById(countId);
            if (countBadge) {
                if (filter === '') {
                    countBadge.textContent = `Total ${totalCount} Data`;
                } else {
                    countBadge.textContent = `Menampilkan ${visibleCount} dari ${totalCount} Data`;
                }
            }
        }
    }
    </script>
</body>
</html>
