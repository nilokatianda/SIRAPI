<?php
require_once "../includes/auth_check.php";
require_admin();
require_once "../includes/koneksi.php";
$result = mysqli_query($conn, "SELECT id_pengguna, nama_pengguna, username, nim, jabatan, role FROM pengguna");
if (!$result) {
    die("Gagal mengambil data: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna - SIRAPI</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>Data Pengguna</h1>
    <p><a href="<?= $dashboard_url ?>" class="btn-kembali">&larr; <?= $dashboard_label ?></a></p>

    <div class="table-toolbar-bar">
        <div class="table-toolbar-left">
            <a class="tombol" href="tambah.php">+ Tambah Data Pengguna</a>
        </div>
        <div class="table-search-box">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="penggunaSearch" placeholder="Cari nama pengguna, username, NIM, jabatan..." onkeyup="liveSearchTable('penggunaSearch', 'penggunaTable', 'penggunaCount')">
        </div>
    </div>

    <table id="penggunaTable">
        <thead><tr><th>ID</th><th>Nama Pengguna</th><th>Username</th><th>NIM</th><th>Jabatan</th><th>Role</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars((string)($row['id_pengguna'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['nama_pengguna'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['nim'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string)($row['jabatan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <?php if (($row['role'] ?? '') === 'admin'): ?>
                        <span style="display:inline-block; padding: 3px 9px; border-radius: 12px; font-weight: 700; font-size: 11px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a;">Admin</span>
                    <?php else: ?>
                        <span style="display:inline-block; padding: 3px 9px; border-radius: 12px; font-weight: 700; font-size: 11px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;">Pengguna</span>
                    <?php endif; ?>
                </td>
                <td class="aksi">
                    <a href="edit.php?id=<?php echo (int)$row['id_pengguna']; ?>">Edit</a> 
                    <a class="hapus" href="hapus.php?id=<?php echo (int)$row['id_pengguna']; ?>" onclick="return confirm('Yakin ingin menghapus data ini?')">Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">Belum ada data.</td></tr>
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
                const colCount = table.querySelectorAll('thead th').length || 7;
                noResultEl.innerHTML = `<td colspan="${colCount}" style="text-align: center; padding: 24px; color: #94a3b8; font-weight: 600;">🔍 Tidak ditemukan data pengguna yang sesuai dengan pencarian "${input.value}"</td>`;
                tbody.appendChild(noResultEl);
            } else {
                noResultEl.style.display = '';
                noResultEl.querySelector('td').innerHTML = `🔍 Tidak ditemukan data pengguna yang sesuai dengan pencarian "${input.value}"`;
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