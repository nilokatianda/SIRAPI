<?php
/**
 * SIRAPI - Helper Pencatatan Denda & Pembayaran QRIS/E-Wallet
 * Mengatur perhitungan denda keterlambatan pengembalian arsip dan integrasi pembayaran.
 *
 * Aturan Denda:
 * - Tepat Waktu (<= 0 hari): Rp 0 (Bebas Denda)
 * - Terlambat 1 hari: Rp 20.000
 * - Terlambat 2 hari: Rp 22.500
 * - Terlambat 3 hari: Rp 25.000
 * - Terlambat 4 hari: Rp 30.000
 * - Terlambat > 4 hari: Rp 30.000 + ((hari - 4) * Rp 5.000)
 */

if (!function_exists('ensure_denda_schema')) {
    /**
     * Memastikan kolom-kolom terkait denda tersedia di tabel peminjaman
     */
    function ensure_denda_schema($conn) {
        static $checked = false;
        if ($checked) return;

        // Cek kolom denda
        $check = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman LIKE 'denda'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN denda INT NOT NULL DEFAULT 0 AFTER status_peminjaman");
        }

        // Cek kolom status_denda
        $check_status = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman LIKE 'status_denda'");
        if ($check_status && mysqli_num_rows($check_status) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN status_denda VARCHAR(30) NOT NULL DEFAULT 'Bebas Denda' AFTER denda");
        }

        // Cek kolom tanggal_dikembalikan
        $check_tgl = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman LIKE 'tanggal_dikembalikan'");
        if ($check_tgl && mysqli_num_rows($check_tgl) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN tanggal_dikembalikan DATE NULL AFTER status_denda");
        }

        // Cek kolom metode_pembayaran
        $check_metode = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman LIKE 'metode_pembayaran'");
        if ($check_metode && mysqli_num_rows($check_metode) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN metode_pembayaran VARCHAR(50) NULL AFTER tanggal_dikembalikan");
        }

        // Cek kolom tanggal_bayar_denda
        $check_tgl_bayar = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman LIKE 'tanggal_bayar_denda'");
        if ($check_tgl_bayar && mysqli_num_rows($check_tgl_bayar) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN tanggal_bayar_denda DATETIME NULL AFTER metode_pembayaran");
        }

        $checked = true;
    }
}

if (!function_exists('hitung_hari_terlambat')) {
    /**
     * Menghitung jumlah hari keterlambatan berdasarkan batas tanggal kembali
     */
    function hitung_hari_terlambat($batas_kembali, $tanggal_aktual = null) {
        if (empty($batas_kembali)) return 0;

        $target = $tanggal_aktual ? $tanggal_aktual : date('Y-m-d');
        $ts_batas = strtotime($batas_kembali);
        $ts_target = strtotime($target);

        if ($ts_target > $ts_batas) {
            $diff_seconds = $ts_target - $ts_batas;
            return (int)floor($diff_seconds / 86400);
        }
        return 0;
    }
}

if (!function_exists('hitung_nominal_denda')) {
    /**
     * Menghitung nominal denda berdasarkan aturan spesifik:
     * 1 hari = 20.000 | 2 hari = 22.500 | 3 hari = 25.000 | 4 hari = 30.000 | > 4 hari = +5.000/hari
     */
    function hitung_nominal_denda($hari_terlambat) {
        if ($hari_terlambat <= 0) {
            return 0;
        }
        if ($hari_terlambat === 1) {
            return 20000;
        }
        if ($hari_terlambat === 2) {
            return 22500;
        }
        if ($hari_terlambat === 3) {
            return 25000;
        }
        // 4 hari ke atas: 30.000 + 5.000 per hari tambahan
        return 30000 + (($hari_terlambat - 4) * 5000);
    }
}

if (!function_exists('get_info_denda')) {
    /**
     * Mengambil detail keterlambatan dan denda dari satu baris data peminjaman
     */
    function get_info_denda($row) {
        $status_pinjam = $row['status_peminjaman'] ?? 'Dipinjam';
        $batas_kembali = $row['tanggal_kembali'] ?? null;
        $status_denda_db = $row['status_denda'] ?? '';
        $denda_db = (int)($row['denda'] ?? 0);
        $tgl_kembali_aktual = $row['tanggal_dikembalikan'] ?? null;

        if ($status_pinjam === 'Dipinjam') {
            $hari_terlambat = hitung_hari_terlambat($batas_kembali);
            $nominal = hitung_nominal_denda($hari_terlambat);

            if ($hari_terlambat > 0) {
                $status_denda = ($status_denda_db === 'Lunas') ? 'Lunas' : 'Belum Dibayar';
            } else {
                $status_denda = 'Bebas Denda';
            }
        } else {
            // Sudah dikembalikan
            if ($denda_db > 0) {
                $nominal = $denda_db;
                $hari_terlambat = hitung_hari_terlambat($batas_kembali, $tgl_kembali_aktual);
                $status_denda = ($status_denda_db === 'Belum Dibayar') ? 'Belum Dibayar' : 'Lunas';
            } else {
                // Cek apakah sebenarnya terlambat saat dikembalikan
                $hari_terlambat = hitung_hari_terlambat($batas_kembali, $tgl_kembali_aktual);
                if ($hari_terlambat > 0) {
                    $nominal = hitung_nominal_denda($hari_terlambat);
                    $status_denda = ($status_denda_db === 'Belum Dibayar') ? 'Belum Dibayar' : 'Lunas';
                } else {
                    $nominal = 0;
                    $status_denda = 'Bebas Denda';
                }
            }
        }

        return [
            'hari_terlambat' => $hari_terlambat,
            'nominal' => $nominal,
            'nominal_format' => 'Rp ' . number_format($nominal, 0, ',', '.'),
            'status' => $status_denda,
            'is_terlambat' => ($hari_terlambat > 0),
            'harus_bayar' => ($hari_terlambat > 0 && $status_denda === 'Belum Dibayar')
        ];
    }
}

if (!function_exists('render_modal_pembayaran_denda')) {
    /**
     * Merender Modal Pembayaran QRIS & E-Wallet Modern
     */
    function render_modal_pembayaran_denda() {
        ?>
        <!-- MODAL PEMBAYARAN DENDA (QRIS & E-WALLET) -->
        <div id="modalDenda" class="modal-denda-backdrop" style="display:none;">
            <div class="modal-denda-card">
                <div class="modal-denda-header">
                    <div>
                        <span class="badge-qris-top">&#128179; Pembayaran Denda Kearsipan</span>
                        <h3 id="mdTitleArsip">Pembayaran Denda Arsip</h3>
                        <p id="mdSubInfo">Transaksi #<span id="mdIdPinjam"></span> - <span id="mdHariTelat"></span></p>
                    </div>
                    <button type="button" class="btn-close-modal" onclick="tutupModalDenda()">&times;</button>
                </div>

                <div class="modal-denda-body">
                    <!-- Ringkasan Tagihan -->
                    <div class="tagihan-box">
                        <span class="tagihan-label">Total Denda yang Harus Dibayar:</span>
                        <span class="tagihan-nominal" id="mdNominalDenda">Rp 0</span>
                    </div>

                    <!-- Navigasi Metode Bayar -->
                    <div class="tabs-metode">
                        <button type="button" class="tab-btn active" onclick="switchMetode('qris')">&#128241; QRIS</button>
                        <button type="button" class="tab-btn" onclick="switchMetode('gopay')">GoPay</button>
                        <button type="button" class="tab-btn" onclick="switchMetode('ovo')">OVO</button>
                        <button type="button" class="tab-btn" onclick="switchMetode('dana')">DANA</button>
                        <button type="button" class="tab-btn" onclick="switchMetode('shopee')">ShopeePay</button>
                    </div>

                    <!-- KONTEN QRIS -->
                    <div id="tabKontenQris" class="tab-content active">
                        <div class="qris-frame">
                            <div class="qris-brand">
                                <span class="qris-logo-text">QRIS</span>
                                <span class="qris-desc">Pembayaran Nasional Terverifikasi</span>
                            </div>
                            <!-- Mockup QRIS SVG Interaktif Berstandar -->
                            <div class="qris-qr-wrapper">
                                <svg width="180" height="180" viewBox="0 0 200 200" style="background:#fff; border-radius:12px; padding:8px;">
                                    <!-- Border Pattern -->
                                    <rect x="15" y="15" width="50" height="50" fill="#000" rx="4"/>
                                    <rect x="25" y="25" width="30" height="30" fill="#fff" rx="2"/>
                                    <rect x="33" y="33" width="14" height="14" fill="#15803d" rx="2"/>

                                    <rect x="135" y="15" width="50" height="50" fill="#000" rx="4"/>
                                    <rect x="145" y="25" width="30" height="30" fill="#fff" rx="2"/>
                                    <rect x="153" y="33" width="14" height="14" fill="#15803d" rx="2"/>

                                    <rect x="15" y="135" width="50" height="50" fill="#000" rx="4"/>
                                    <rect x="25" y="145" width="30" height="30" fill="#fff" rx="2"/>
                                    <rect x="33" y="153" width="14" height="14" fill="#15803d" rx="2"/>

                                    <!-- Random QR Code Grid simulation -->
                                    <rect x="80" y="25" width="10" height="25" fill="#000"/>
                                    <rect x="105" y="20" width="15" height="10" fill="#000"/>
                                    <rect x="80" y="65" width="20" height="12" fill="#000"/>
                                    <rect x="110" y="55" width="12" height="25" fill="#000"/>
                                    <rect x="25" y="85" width="20" height="10" fill="#000"/>
                                    <rect x="55" y="90" width="15" height="25" fill="#000"/>
                                    <rect x="80" y="90" width="40" height="40" fill="#15803d" rx="6"/>
                                    <!-- Icon di tengah QR -->
                                    <circle cx="100" cy="110" r="12" fill="#ffffff"/>
                                    <path d="M96 110 L99 113 L105 107" stroke="#15803d" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                                    <rect x="140" y="85" width="20" height="15" fill="#000"/>
                                    <rect x="170" y="95" width="15" height="20" fill="#000"/>
                                    <rect x="80" y="145" width="25" height="15" fill="#000"/>
                                    <rect x="120" y="140" width="20" height="20" fill="#000"/>
                                    <rect x="155" y="150" width="25" height="20" fill="#000"/>
                                </svg>
                            </div>
                            <div class="qris-footer-info">
                                <strong>NMID: ID1020304050607</strong><br>
                                <span>A.N. SIRAPI KEARSIPAN TERPADU</span><br>
                                <small>Scan menggunakan BCA, Mandiri, BRI, BNI, GoPay, OVO, Dana</small>
                            </div>
                        </div>
                    </div>

                    <!-- KONTEN E-WALLET LAINNYA -->
                    <div id="tabKontenEwallet" class="tab-content" style="display:none;">
                        <div class="ewallet-info-card">
                            <div class="ewallet-header-row">
                                <span class="ewallet-badge-nama" id="ewalletNama">GoPay</span>
                                <span class="ewallet-verified">&#10004; Akun Terverifikasi</span>
                            </div>
                            <div class="ewallet-nomor-box">
                                <span class="label-nomor">Nomor Rekening / E-Wallet:</span>
                                <div class="copy-row">
                                    <strong class="nomor-ewallet" id="ewalletNomor">0812-3456-7890</strong>
                                    <button type="button" class="btn-copy-num" onclick="salinNomor()">Salin</button>
                                </div>
                                <span class="nama-pemilik">a.n. Bendahara SIRAPI Kearsipan</span>
                            </div>
                            <div class="ewallet-petunjuk">
                                <ol>
                                    <li>Buka aplikasi e-wallet Anda.</li>
                                    <li>Pilih menu <strong>Transfer / Kirim Uang</strong>.</li>
                                    <li>Kirim sesuai nominal <strong id="ewalletNominalText">Rp 0</strong>.</li>
                                    <li>Klik tombol <strong>Konfirmasi Pembayaran</strong> di bawah ini.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <!-- FORM KONFIRMASI BAYAR -->
                    <form method="POST" action="" class="form-konfirmasi-denda">
                        <input type="hidden" name="action_denda" value="bayar_denda">
                        <input type="hidden" name="id_peminjaman_denda" id="formIdPinjam" value="">
                        <input type="hidden" name="metode_pembayaran_pilihan" id="formMetode" value="QRIS">

                        <button type="submit" class="btn-konfirmasi-bayar" onclick="return confirm('Apakah Anda sudah selesai melakukan transfer pembayaran denda?')">
                            &#10004; Konfirmasi Pembayaran Denda Telah Dilakukan
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            let currentNomor = "0812-3456-7890";
            let currentNominal = "Rp 0";

            function bukaModalDenda(idPinjam, namaArsip, hariTelat, nominalStr, nominalAngka) {
                document.getElementById('mdIdPinjam').innerText = idPinjam;
                document.getElementById('formIdPinjam').value = idPinjam;
                document.getElementById('mdTitleArsip').innerText = namaArsip;
                document.getElementById('mdHariTelat').innerText = "Terlambat " + hariTelat + " Hari";
                document.getElementById('mdNominalDenda').innerText = nominalStr;
                document.getElementById('ewalletNominalText').innerText = nominalStr;
                currentNominal = nominalStr;

                switchMetode('qris');
                document.getElementById('modalDenda').style.display = 'flex';
            }

            function tutupModalDenda() {
                document.getElementById('modalDenda').style.display = 'none';
            }

            function switchMetode(metode) {
                // Update tab buttons
                const buttons = document.querySelectorAll('.tabs-metode .tab-btn');
                buttons.forEach(btn => btn.classList.remove('active'));

                const tabQris = document.getElementById('tabKontenQris');
                const tabEwallet = document.getElementById('tabKontenEwallet');
                const formMetode = document.getElementById('formMetode');

                if (metode === 'qris') {
                    tabQris.style.display = 'block';
                    tabEwallet.style.display = 'none';
                    formMetode.value = 'QRIS';
                    if (event && event.target) event.target.classList.add('active');
                } else {
                    tabQris.style.display = 'none';
                    tabEwallet.style.display = 'block';

                    let namaApp = 'GoPay';
                    let noHp = '0812-3456-7890';
                    if (metode === 'ovo') { namaApp = 'OVO'; noHp = '0812-8877-6655'; }
                    else if (metode === 'dana') { namaApp = 'DANA'; noHp = '0813-9988-7766'; }
                    else if (metode === 'shopee') { namaApp = 'ShopeePay'; noHp = '0812-3456-7890'; }

                    document.getElementById('ewalletNama').innerText = namaApp;
                    document.getElementById('ewalletNomor').innerText = noHp;
                    currentNomor = noHp.replace(/-/g, '');
                    formMetode.value = namaApp;
                    if (event && event.target) event.target.classList.add('active');
                }
            }

            function salinNomor() {
                navigator.clipboard.writeText(currentNomor).then(() => {
                    alert('Nomor e-wallet berhasil disalin: ' + currentNomor);
                }).catch(() => {
                    alert('Silakan salin nomor: ' + currentNomor);
                });
            }

            // Tutup jika klik luar card
            window.onclick = function(e) {
                const modal = document.getElementById('modalDenda');
                if (e.target === modal) {
                    tutupModalDenda();
                }
            }
        </script>
        <?php
    }
}
?>

