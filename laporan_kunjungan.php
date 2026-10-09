<?php
/*
 * File laporan_kunjungan.php (BERSIH)
 * Laporan jumlah kunjungan pasien (Non-Batal).
 */

// 1. Setup
$page_title = "Laporan Kunjungan Pasien";
require_once('includes/header.php');
require_once('includes/functions.php');
require_once('config/bed_sk_mapping.php');

// Hanya tampilkan kunjungan pada kamar yang terdaftar pada mapping bed SK.
$sk_bed_in = getSkBedInSql($koneksi);
$has_sk_bed_filter = ($sk_bed_in !== '');

// 2. Parameter Filter
$tgl_awal = isset($_GET['tgl_awal']) ? htmlspecialchars($_GET['tgl_awal']) : date('Y-m-d');
$jam_awal = isset($_GET['jam_awal']) ? htmlspecialchars($_GET['jam_awal']) : '00:00:00';
$tgl_akhir = isset($_GET['tgl_akhir']) ? htmlspecialchars($_GET['tgl_akhir']) : date('Y-m-d');
$jam_akhir = isset($_GET['jam_akhir']) ? htmlspecialchars($_GET['jam_akhir']) : '23:59:59';
$kd_pj = isset($_GET['kd_pj']) ? htmlspecialchars($_GET['kd_pj']) : '';
$kd_kamar_filter = isset($_GET['kd_kamar']) ? htmlspecialchars($_GET['kd_kamar']) : '';
$stts_pulang_filter = isset($_GET['stts_pulang']) ? htmlspecialchars($_GET['stts_pulang']) : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

$datetime_awal = $tgl_awal . ' ' . $jam_awal;
$datetime_akhir = $tgl_akhir . ' ' . $jam_akhir;

// 3. Data Penjamin (Dropdown)
$penjabs = [];
$sql_penjab = "SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab ASC";
$result_penjab = $koneksi->query($sql_penjab);
if ($result_penjab) {
    while ($row = $result_penjab->fetch_assoc()) {
        $penjabs[] = $row;
    }
}

// 3b. Data Kamar SK (Dropdown Filter Ranap)
$kamar_options = [];
if ($has_sk_bed_filter) {
    $sql_kamar = "
        SELECT k.kd_kamar, b.kd_bangsal, b.nm_bangsal, k.kelas
        FROM kamar k
        INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        WHERE k.statusdata = '1'
          AND k.kd_kamar IN ($sk_bed_in)
        ORDER BY b.nm_bangsal ASC, k.kd_kamar ASC
    ";
    $result_kamar = $koneksi->query($sql_kamar);
    if ($result_kamar) {
        while ($row = $result_kamar->fetch_assoc()) {
            $kamar_options[] = $row;
        }
    }
}

// 4. Logika Pengambilan Data Tabel (Hanya jika ada action cari)
$data_ralan = [];
$data_ranap = [];
$is_search = ($action == 'cari');

if ($is_search) {
    // Base WHERE: Rentang tanggal & Tidak Batal
    $where_base = " WHERE CONCAT(reg_periksa.tgl_registrasi, ' ', reg_periksa.jam_reg) BETWEEN ? AND ? AND reg_periksa.stts != 'Batal' ";
    $params = [$datetime_awal, $datetime_akhir];
    $types = "ss";

    if (!empty($kd_pj)) {
        $where_base .= " AND reg_periksa.kd_pj = ? ";
        $params[] = $kd_pj;
        $types .= "s";
    }

    // --- Query Ralan ---
    $sql_ralan = "
        SELECT 
            reg_periksa.no_rawat, reg_periksa.tgl_registrasi, reg_periksa.jam_reg, 
            reg_periksa.no_rkm_medis, pasien.nm_pasien, 
            dokter.nm_dokter, poliklinik.nm_poli, penjab.png_jawab, reg_periksa.stts_daftar
        FROM reg_periksa
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
        INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        $where_base AND reg_periksa.status_lanjut = 'Ralan'
        ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    ";

    $stmt = $koneksi->prepare($sql_ralan);
    if ($stmt) {
        $bind_names = [];
        $bind_names[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_names[] = &$params[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data_ralan[] = $row;
        }
        $stmt->close();
    }

    // --- Query Ranap ---
    $ranap_kamar_filter = $has_sk_bed_filter
        ? " AND kamar_fo.kd_kamar IN ($sk_bed_in)"
        : " AND 1 = 0";

    $params_ranap = $params;
    $types_ranap  = $types;

    // Filter Kamar / Bangsal Ranap Tambahan
    if (!empty($kd_kamar_filter)) {
        if (strpos($kd_kamar_filter, 'BANGSAL:') === 0) {
            $kd_b = substr($kd_kamar_filter, 8);
            $ranap_kamar_filter .= " AND kamar_fo.kd_bangsal = ? ";
            $params_ranap[] = $kd_b;
            $types_ranap .= "s";
        } else {
            $ranap_kamar_filter .= " AND kamar_fo.kd_kamar = ? ";
            $params_ranap[] = $kd_kamar_filter;
            $types_ranap .= "s";
        }
    }

    // Filter Status Pulang Ranap Tambahan (Lengkap Sesuai Master SIMRS Khanza)
    $ranap_status_filter = "";
    if (!empty($stts_pulang_filter)) {
        $sub_stts = "COALESCE(NULLIF((SELECT kamar_inap_status.stts_pulang FROM kamar_inap AS kamar_inap_status WHERE kamar_inap_status.no_rawat = reg_periksa.no_rawat ORDER BY kamar_inap_status.tgl_masuk DESC, kamar_inap_status.jam_masuk DESC LIMIT 1), ''), '-')";
        if ($stts_pulang_filter === 'dirawat' || $stts_pulang_filter === '-') {
            $ranap_status_filter = " AND $sub_stts = '-' ";
        } elseif ($stts_pulang_filter === 'pulang') {
            $ranap_status_filter = " AND $sub_stts != '-' ";
        } elseif ($stts_pulang_filter === 'Meninggal' || $stts_pulang_filter === '+') {
            $ranap_status_filter = " AND ($sub_stts = 'Meninggal' OR $sub_stts = '+') ";
        } elseif ($stts_pulang_filter === 'APS') {
            $ranap_status_filter = " AND ($sub_stts = 'APS' OR $sub_stts = 'Atas Permintaan Sendiri') ";
        } else {
            $ranap_status_filter = " AND $sub_stts = ? ";
            $params_ranap[] = $stts_pulang_filter;
            $types_ranap .= "s";
        }
    }

    $sql_ranap = "
        SELECT 
            reg_periksa.no_rawat, reg_periksa.tgl_registrasi, reg_periksa.jam_reg, 
            reg_periksa.no_rkm_medis, pasien.nm_pasien, 
            COALESCE(
                (
                    SELECT dokter_dpjp.nm_dokter
                    FROM dpjp_ranap
                    INNER JOIN dokter AS dokter_dpjp ON dokter_dpjp.kd_dokter = dpjp_ranap.kd_dokter
                    WHERE dpjp_ranap.no_rawat = reg_periksa.no_rawat
                    ORDER BY dpjp_ranap.kd_dokter
                    LIMIT 1
                ),
                dokter.nm_dokter
            ) AS nm_dpjp,
            poliklinik.nm_poli,
            CONCAT(kamar_fo.kd_kamar, ' - ', bangsal_fo.nm_bangsal) AS kamar_fo,
            penjab.png_jawab,
            COALESCE(
                NULLIF(
                    (
                        SELECT kamar_inap_status.stts_pulang
                        FROM kamar_inap AS kamar_inap_status
                        WHERE kamar_inap_status.no_rawat = reg_periksa.no_rawat
                        ORDER BY kamar_inap_status.tgl_masuk DESC, kamar_inap_status.jam_masuk DESC
                        LIMIT 1
                    ),
                    ''
                ),
                '-'
            ) AS stts_pulang
        FROM reg_periksa
        INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
        INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
        INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
        INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
        INNER JOIN kamar kamar_fo ON kamar_fo.kd_kamar = (
            SELECT kamar_inap.kd_kamar
            FROM kamar_inap
            WHERE kamar_inap.no_rawat = reg_periksa.no_rawat
            ORDER BY kamar_inap.tgl_masuk ASC, kamar_inap.jam_masuk ASC
            LIMIT 1
        )
        LEFT JOIN bangsal bangsal_fo ON bangsal_fo.kd_bangsal = kamar_fo.kd_bangsal
        $where_base AND reg_periksa.status_lanjut = 'Ranap'
        $ranap_kamar_filter
        $ranap_status_filter
        ORDER BY reg_periksa.tgl_registrasi DESC, reg_periksa.jam_reg DESC
    ";

    $stmt = $koneksi->prepare($sql_ranap);
    if ($stmt) {
        $bind_names = [];
        $bind_names[] = $types_ranap;
        for ($i = 0; $i < count($params_ranap); $i++) {
            $bind_names[] = &$params_ranap[$i];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);

        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data_ranap[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="container-fluid">
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title text-primary">Filter Laporan Kunjungan</h5>
            <form action="laporan_kunjungan.php" method="GET">
                <input type="hidden" name="action" value="cari">
                <div class="row g-3">
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold">Dari Tanggal</label>
                        <input type="date" class="form-control" name="tgl_awal" value="<?php echo $tgl_awal; ?>">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold">Jam</label>
                        <input type="time" class="form-control" name="jam_awal" value="<?php echo $jam_awal; ?>">
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label class="form-label small fw-bold">Sampai Tanggal</label>
                        <input type="date" class="form-control" name="tgl_akhir" value="<?php echo $tgl_akhir; ?>">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label small fw-bold">Jam</label>
                        <input type="time" class="form-control" name="jam_akhir" value="<?php echo $jam_akhir; ?>">
                    </div>
                    <div class="col-md-2 col-sm-12">
                        <label class="form-label small fw-bold">Penjamin</label>
                        <select name="kd_pj" class="form-select">
                            <option value="">-- Semua Penjamin --</option>
                            <?php foreach ($penjabs as $p): ?>
                                <option value="<?php echo $p['kd_pj']; ?>" <?php echo ($kd_pj == $p['kd_pj']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['png_jawab']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Khusus Ranap -->
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label small fw-bold text-info"><i class="fas fa-bed me-1"></i>Filter Kamar (Ranap)</label>
                        <select name="kd_kamar" class="form-select">
                            <option value="">-- Semua Kamar / Bangsal --</option>
                            <?php
                            $grouped_kamar = [];
                            foreach ($kamar_options as $km) {
                                $grouped_kamar[$km['nm_bangsal']][] = $km;
                            }
                            foreach ($grouped_kamar as $nm_bangsal => $beds):
                                $first_bed = $beds[0];
                                $val_bangsal = 'BANGSAL:' . $first_bed['kd_bangsal'];
                            ?>
                                <optgroup label="<?php echo htmlspecialchars($nm_bangsal); ?>">
                                    <option value="<?php echo htmlspecialchars($val_bangsal); ?>" <?php echo ($kd_kamar_filter === $val_bangsal) ? 'selected' : ''; ?>>
                                        [Semua di <?php echo htmlspecialchars($nm_bangsal); ?> - <?php echo count($beds); ?> Bed]
                                    </option>
                                    <?php foreach ($beds as $bed): ?>
                                        <option value="<?php echo htmlspecialchars($bed['kd_kamar']); ?>" <?php echo ($kd_kamar_filter === $bed['kd_kamar']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($bed['kd_kamar']); ?> (<?php echo htmlspecialchars($bed['kelas']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-6">
                        <label class="form-label small fw-bold text-info"><i class="fas fa-user-check me-1"></i>Status Pulang (Ranap)</label>
                        <select name="stts_pulang" class="form-select">
                            <option value="">-- Semua Status --</option>
                            <option value="dirawat" <?php echo ($stts_pulang_filter === 'dirawat' || $stts_pulang_filter === '-') ? 'selected' : ''; ?>>Masih Dirawat</option>
                            <option value="Atas Persetujuan Dokter" <?php echo ($stts_pulang_filter === 'Atas Persetujuan Dokter') ? 'selected' : ''; ?>>Atas Persetujuan Dokter</option>
                            <option value="Sehat" <?php echo ($stts_pulang_filter === 'Sehat') ? 'selected' : ''; ?>>Sehat</option>
                            <option value="Sembuh" <?php echo ($stts_pulang_filter === 'Sembuh') ? 'selected' : ''; ?>>Sembuh</option>
                            <option value="Membaik" <?php echo ($stts_pulang_filter === 'Membaik') ? 'selected' : ''; ?>>Membaik</option>
                            <option value="Rujuk" <?php echo ($stts_pulang_filter === 'Rujuk') ? 'selected' : ''; ?>>Rujuk</option>
                            <option value="APS" <?php echo ($stts_pulang_filter === 'APS') ? 'selected' : ''; ?>>APS</option>
                            <option value="Atas Permintaan Sendiri" <?php echo ($stts_pulang_filter === 'Atas Permintaan Sendiri') ? 'selected' : ''; ?>>Atas Permintaan Sendiri</option>
                            <option value="Pulang Paksa" <?php echo ($stts_pulang_filter === 'Pulang Paksa') ? 'selected' : ''; ?>>Pulang Paksa</option>
                            <option value="Meninggal" <?php echo ($stts_pulang_filter === 'Meninggal') ? 'selected' : ''; ?>>Meninggal</option>
                            <option value="+" <?php echo ($stts_pulang_filter === '+') ? 'selected' : ''; ?>>+ (Meninggal)</option>
                            <option value="Pindah Kamar" <?php echo ($stts_pulang_filter === 'Pindah Kamar') ? 'selected' : ''; ?>>Pindah Kamar</option>
                            <option value="Status Belum Lengkap" <?php echo ($stts_pulang_filter === 'Status Belum Lengkap') ? 'selected' : ''; ?>>Status Belum Lengkap</option>
                            <option value="Isoman" <?php echo ($stts_pulang_filter === 'Isoman') ? 'selected' : ''; ?>>Isoman</option>
                            <option value="Lain-lain" <?php echo ($stts_pulang_filter === 'Lain-lain') ? 'selected' : ''; ?>>Lain-lain</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-sm-12 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-1"></i> Tampilkan
                        </button>
                        <a href="laporan_kunjungan.php" class="btn btn-outline-secondary" title="Reset Filter">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_search): ?>

        <div class="alert alert-info shadow-sm mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">Total Kunjungan: <strong><?php echo count($data_ralan) + count($data_ranap); ?></strong></h5>
                    <small>Rawat Jalan: <?php echo count($data_ralan); ?> | Rawat Inap: <?php echo count($data_ranap); ?></small>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Proporsi Kunjungan per Penjamin</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 300px;">
                            <canvas id="chartPieKunjungan"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8 col-md-12 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Tren Kunjungan Harian</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area" style="height: 300px;">
                            <canvas id="chartLineKunjungan"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        $active_tab = (!empty($stts_pulang_filter) || !empty($kd_kamar_filter)) ? 'ranap' : 'ralan';
        ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($active_tab === 'ralan') ? 'active' : ''; ?>" id="ralan-tab" data-bs-toggle="tab" data-bs-target="#ralan" type="button" role="tab" aria-controls="ralan" aria-selected="<?php echo ($active_tab === 'ralan') ? 'true' : 'false'; ?>">
                            Rawat Jalan (<?php echo count($data_ralan); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo ($active_tab === 'ranap') ? 'active' : ''; ?>" id="ranap-tab" data-bs-toggle="tab" data-bs-target="#ranap" type="button" role="tab" aria-controls="ranap" aria-selected="<?php echo ($active_tab === 'ranap') ? 'true' : 'false'; ?>">
                            Rawat Inap (<?php echo count($data_ranap); ?>)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="myTabContent">

                    <div class="tab-pane fade <?php echo ($active_tab === 'ralan') ? 'show active' : ''; ?>" id="ralan" role="tabpanel" aria-labelledby="ralan-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm dt-table" width="100%">
                                <thead>
                                    <tr>
                                        <th>No. Rawat</th>
                                        <th>Tgl Reg</th>
                                        <th>Jam</th>
                                        <th>No. RM</th>
                                        <th>Pasien</th>
                                        <th>Poliklinik</th>
                                        <th>Dokter</th>
                                        <th>Penjamin</th>
                                        <th>Jns Kunjungan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_ralan as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                            <td><?php echo htmlspecialchars($row['tgl_registrasi']); ?></td>
                                            <td><?php echo htmlspecialchars($row['jam_reg']); ?></td>
                                            <td><?php echo htmlspecialchars($row['no_rkm_medis']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_poli']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_dokter']); ?></td>
                                            <td><?php echo htmlspecialchars($row['png_jawab']); ?></td>
                                            <td><?php echo htmlspecialchars($row['stts_daftar']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade <?php echo ($active_tab === 'ranap') ? 'show active' : ''; ?>" id="ranap" role="tabpanel" aria-labelledby="ranap-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm dt-table" width="100%">
                                <thead>
                                    <tr>
                                        <th>No. Rawat</th>
                                        <th>Tgl Masuk</th>
                                        <th>Jam</th>
                                        <th>No. RM</th>
                                        <th>Pasien</th>
                                        <th>Asal Poli/IGD</th>
                                        <th>Kamar</th>
                                        <th>Dokter DPJP</th>
                                        <th>Penjamin</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data_ranap as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['no_rawat']); ?></td>
                                            <td><?php echo htmlspecialchars($row['tgl_registrasi']); ?></td>
                                            <td><?php echo htmlspecialchars($row['jam_reg']); ?></td>
                                            <td><?php echo htmlspecialchars($row['no_rkm_medis']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_pasien']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_poli']); ?></td>
                                            <td><?php echo htmlspecialchars($row['kamar_fo']); ?></td>
                                            <td><?php echo htmlspecialchars($row['nm_dpjp']); ?></td>
                                            <td><?php echo htmlspecialchars($row['png_jawab']); ?></td>
                                            <td>
                                                <?php if ($row['stts_pulang'] === '-'): ?>
                                                    <span class="badge bg-info text-dark">Dirawat</span>
                                                <?php elseif ($row['stts_pulang'] === 'Meninggal' || $row['stts_pulang'] === '+'): ?>
                                                    <span class="badge bg-danger text-white"><?php echo htmlspecialchars($row['stts_pulang']); ?></span>
                                                <?php elseif (in_array($row['stts_pulang'], ['Sehat', 'Sembuh', 'Membaik', 'Atas Persetujuan Dokter'])): ?>
                                                    <span class="badge bg-success text-white"><?php echo htmlspecialchars($row['stts_pulang']); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($row['stts_pulang']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-secondary text-center p-5">
            <h4>Silakan pilih filter tanggal dan klik "Tampilkan"</h4>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<?php ob_start(); ?>
<script>
    $(document).ready(function() {
        // Init DataTables
        $('.dt-table').DataTable({
            "responsive": true,
            "order": [
                [1, "desc"],
                [2, "desc"]
            ],
            "pageLength": 10,
            "lengthChange": true,
            // --- TAMBAHAN UNTUK EXPORT ---
            "dom": 'Bfrtip', // B = Buttons, f = filtering, r = processing, t = table, i = info, p = pagination
            "buttons": [{
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Laporan Kunjungan Pasien - ' + $('input[name="tgl_awal"]').val() + ' sd ' + $('input[name="tgl_akhir"]').val()
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    title: 'Laporan Kunjungan Pasien'
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-secondary btn-sm'
                }
            ],
            "language": {
                "search": "Cari:",
                "lengthMenu": "Tampilkan _MENU_ baris",
                "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
                "paginate": {
                    "first": "Awal",
                    "last": "Akhir",
                    "next": "Lanjut",
                    "previous": "Kembali"
                }
            }
        });

        // Load Charts
        <?php if ($is_search): ?>
            loadCharts();
        <?php endif; ?>
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
        $.fn.dataTable.tables({
                visible: true,
                api: true
            })
            .columns.adjust()
            .responsive.recalc();
    });

    function loadCharts() {
        var params = {
            tgl_awal: $('input[name="tgl_awal"]').val(),
            jam_awal: $('input[name="jam_awal"]').val(),
            tgl_akhir: $('input[name="tgl_akhir"]').val(),
            jam_akhir: $('input[name="jam_akhir"]').val(),
            kd_pj: $('select[name="kd_pj"]').val()
        };

        $.ajax({
            url: 'api/data_kunjungan_chart.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                renderPieChart(data.pie);
                renderLineChart(data.line);
            },
            error: function(xhr, status, error) {
                console.error("Gagal memuat data chart:", error);
                console.log("Response:", xhr.responseText);
            }
        });
    }

    function renderPieChart(pieData) {
        var ctx = document.getElementById("chartPieKunjungan");
        if (!ctx) return;

        var backgroundColors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'];

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: pieData.labels,
                datasets: [{
                    data: pieData.data,
                    backgroundColor: backgroundColors,
                    hoverBackgroundColor: backgroundColors,
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' Pasien';
                            }
                        }
                    }
                },
                cutout: '70%',
            },
        });
    }

    function renderLineChart(lineData) {
        var ctx = document.getElementById("chartLineKunjungan");
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: lineData.labels,
                datasets: lineData.datasets
            },
            options: {
                maintainAspectRatio: false,
                layout: {
                    padding: {
                        left: 10,
                        right: 25,
                        top: 25,
                        bottom: 0
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    },
                    y: {
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            callback: function(value) {
                                return value;
                            }
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                }
            }
        });
    }
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>
