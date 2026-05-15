<?php
/*
 * File: laporan_audit_erm.php
 * Deskripsi: Integrasi Audit ERM V2 (Fixed UI & Color Coding)
 * Author: Kamerad (Gemini) for Alicia
 * Date: 2025-11-27
 */

// 1. Integrasi Header & Keamanan
$page_title = "Audit Kepatuhan ERM";
require_once('includes/header.php');

// ==========================================
// LOGIKA & CONFIG
// ==========================================

// Ambil Data Instansi
$q_instansi = $koneksi->query("SELECT nama_instansi FROM setting LIMIT 1");
$data_instansi = $q_instansi->fetch_assoc();
$nama_rs_audit = $data_instansi['nama_instansi'] ?? 'Rumah Sakit';
// Use cached logo link instead of Base64 embedding
$logo_src_audit = "core/logo.php";
?>

<!-- Warning for high data usage on heavy report -->
<div class="alert alert-warning alert-dismissible fade show m-3 shadow-sm border-start border-4 border-warning" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Perhatian:</strong> Membuka laporan audit lengkap melalui internet akan menyedot kuota yang lumayan besar karena volume data yang sangat tinggi.
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<?php

// Definisi Peta Data ERM
$erm_map = [
    'Triase IGD' => ['tabel' => 'data_triase_igd', 'grup' => 'IGD', 'tipe' => 'All'],
    'Asesmen Awal IGD (Medis)' => ['tabel' => 'penilaian_medis_igd', 'grup' => 'IGD', 'tipe' => 'All'],
    'Asesmen Awal IGD (Kep)' => ['tabel' => 'penilaian_awal_keperawatan_igd', 'grup' => 'IGD', 'tipe' => 'All'],
    'Catatan Observasi IGD' => ['tabel' => 'catatan_observasi_igd', 'grup' => 'IGD', 'tipe' => 'All'],
    'Asesmen Medis Ralan (Umum)' => ['tabel' => 'penilaian_medis_ralan', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Anak' => ['tabel' => 'penilaian_medis_ralan_anak', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Kandungan' => ['tabel' => 'penilaian_medis_ralan_kandungan', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Penyakit Dalam' => ['tabel' => 'penilaian_medis_ralan_penyakit_dalam', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Mata' => ['tabel' => 'penilaian_medis_ralan_mata', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis THT' => ['tabel' => 'penilaian_medis_ralan_tht', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Bedah' => ['tabel' => 'penilaian_medis_ralan_bedah', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Orthopedi' => ['tabel' => 'penilaian_medis_ralan_orthopedi', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Saraf' => ['tabel' => 'penilaian_medis_ralan_neurologi', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Jiwa' => ['tabel' => 'penilaian_medis_ralan_psikiatrik', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Kulit' => ['tabel' => 'penilaian_medis_ralan_kulitdankelamin', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Geriatri' => ['tabel' => 'penilaian_medis_ralan_geriatri', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Medis Rehab Medik' => ['tabel' => 'penilaian_medis_ralan_rehab_medik', 'grup' => 'Asesmen Awal Medis', 'tipe' => 'Ralan'],
    'Asesmen Kep Ralan (Umum)' => ['tabel' => 'penilaian_awal_keperawatan_ralan', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Bayi/Anak' => ['tabel' => 'penilaian_awal_keperawatan_ralan_bayi', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Gigi' => ['tabel' => 'penilaian_awal_keperawatan_gigi', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Kebidanan' => ['tabel' => 'penilaian_awal_keperawatan_kebidanan', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Mata' => ['tabel' => 'penilaian_awal_keperawatan_mata', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Psikiatri' => ['tabel' => 'penilaian_awal_keperawatan_ralan_psikiatri', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Asesmen Kep Geriatri' => ['tabel' => 'penilaian_awal_keperawatan_ralan_geriatri', 'grup' => 'Asesmen Awal Keperawatan', 'tipe' => 'Ralan'],
    'Transfer Antar Ruang' => ['tabel' => 'transfer_pasien_antar_ruang', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Medis Ranap' => ['tabel' => 'penilaian_medis_ranap', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Medis Kandungan' => ['tabel' => 'penilaian_medis_ranap_kandungan', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Medis Neonatus' => ['tabel' => 'penilaian_medis_ranap_neonatus', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Kep Ranap' => ['tabel' => 'penilaian_awal_keperawatan_ranap', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Kebidanan Ranap' => ['tabel' => 'penilaian_awal_keperawatan_kebidanan_ranap', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'Asesmen Awal Neonatus Ranap' => ['tabel' => 'penilaian_awal_keperawatan_ranap_neonatus', 'grup' => 'Asesmen Ranap', 'tipe' => 'Ranap'],
    'CPPT Ralan' => ['tabel' => 'pemeriksaan_ralan', 'grup' => 'CPPT & SOAP', 'tipe' => 'Ralan'],
    'CPPT Ranap' => ['tabel' => 'pemeriksaan_ranap', 'grup' => 'CPPT & SOAP', 'tipe' => 'Ranap'],
    'Catatan Keperawatan Ranap' => ['tabel' => 'catatan_keperawatan_ranap', 'grup' => 'CPPT & SOAP', 'tipe' => 'Ranap'],
    'Grafik Harian / Observasi' => ['tabel' => 'catatan_observasi_ranap', 'grup' => 'CPPT & SOAP', 'tipe' => 'Ranap'],
    'Resep Dokter' => ['tabel' => 'resep_obat', 'grup' => 'Penunjang', 'tipe' => 'All'],
    'Permintaan Lab' => ['tabel' => 'permintaan_lab', 'grup' => 'Penunjang', 'tipe' => 'All'],
    'Permintaan Radiologi' => ['tabel' => 'permintaan_radiologi', 'grup' => 'Penunjang', 'tipe' => 'All'],
    'Diagnosa (ICD10)' => ['tabel' => 'diagnosa_pasien', 'grup' => 'Penunjang', 'tipe' => 'All'],
    'Prosedur (ICD9)' => ['tabel' => 'prosedur_pasien', 'grup' => 'Penunjang', 'tipe' => 'All'],
    'Penilaian Pre-Operasi' => ['tabel' => 'penilaian_pre_operasi', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Penilaian Pre-Anestesi' => ['tabel' => 'penilaian_pre_anestesi', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Sign In (Sebelum Anestesi)' => ['tabel' => 'signin_sebelum_anestesi', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Time Out (Sebelum Insisi)' => ['tabel' => 'timeout_sebelum_insisi', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Sign Out (Menutup Luka)' => ['tabel' => 'signout_sebelum_menutup_luka', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Laporan Operasi' => ['tabel' => 'laporan_operasi', 'grup' => 'Operasi', 'tipe' => 'All'],
    'Penilaian Ulang Nyeri' => ['tabel' => 'penilaian_ulang_nyeri', 'grup' => 'Monitoring & Risiko', 'tipe' => 'All'],
    'Risiko Dekubitus' => ['tabel' => 'penilaian_risiko_dekubitus', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'Risiko Jatuh Dewasa' => ['tabel' => 'penilaian_lanjutan_resiko_jatuh_dewasa', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'Risiko Jatuh Anak' => ['tabel' => 'penilaian_lanjutan_resiko_jatuh_anak', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'Risiko Jatuh Lansia' => ['tabel' => 'penilaian_lanjutan_resiko_jatuh_lansia', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'EWS Neonatus' => ['tabel' => 'pemantauan_ews_neonatus', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'MEOWS Obstetri' => ['tabel' => 'pemantauan_meows_obstetri', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'PEWS Anak' => ['tabel' => 'pemantauan_pews_anak', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'NEWS Dewasa' => ['tabel' => 'pemantauan_pews_dewasa', 'grup' => 'Monitoring & Risiko', 'tipe' => 'Ranap'],
    'Skrining Gizi' => ['tabel' => 'skrining_gizi', 'grup' => 'Gizi', 'tipe' => 'All'],
    'Asuhan Gizi (ADIME)' => ['tabel' => 'catatan_adime_gizi', 'grup' => 'Gizi', 'tipe' => 'Ranap'],
    'Perencanaan Pemulangan' => ['tabel' => 'perencanaan_pemulangan', 'grup' => 'Resume & Pulang', 'tipe' => 'Ranap'],
    'Resume Pasien (Ralan)' => ['tabel' => 'resume_pasien', 'grup' => 'Resume & Pulang', 'tipe' => 'Ralan'],
    'Resume Pasien (Ranap)' => ['tabel' => 'resume_pasien_ranap', 'grup' => 'Resume & Pulang', 'tipe' => 'Ranap'],
];

// Handler Form
$tgl_awal = date('Y-m-d');
$tgl_akhir = date('Y-m-d');
$status_lanjut = 'Semua';
$selected_cols = array_keys($erm_map);

if (isset($_POST['cari'])) {
    $tgl_awal = $_POST['tanggal_awal'];
    $tgl_akhir = $_POST['tanggal_akhir'];
    $status_lanjut = $_POST['status_lanjut'];
    if (isset($_POST['cols']) && is_array($_POST['cols'])) {
        $selected_cols = $_POST['cols'];
    }
}

// Fungsi Helper Format Audit
if (!function_exists('format_audit')) {
    function format_audit($val) {
        if ($val === 'Tidak Ada') {
            return "<span class='badge bg-danger' style='font-size:0.65rem;'>KOSONG</span>";
        } else {
            return "<i class='fas fa-check-circle text-success' style='font-size:1.1rem;'></i>";
        }
    }
}
?>

<style>
    /* General Styling */
    .header-rs { background: #fff; padding: 15px 20px; border-bottom: 3px solid #dc3545; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 5px; }
    .rs-logo { height: 50px; margin-right: 15px; }
    .filter-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; }
    
    /* Custom CSS for Color Coding */
    .bg-pink { background-color: #e83e8c !important; color: white; }
    .bg-grey { background-color: #6c757d !important; color: white; }
    
    

    /* Modal Styling */
    .group-header { background-color: #e9ecef; padding: 8px 10px; font-weight: bold; border-radius: 4px; margin-top: 10px; margin-bottom: 5px; }
    .check-item { margin-bottom: 5px; }
    .check-item label { font-size: 0.85rem; cursor: pointer; }
</style>

<div class="container-fluid px-4">
    <div class="header-rs shadow-sm">
        <div class="d-flex align-items-center">
            <img src="<?php echo $logo_src_audit; ?>" alt="Logo" class="rs-logo">
            <div>
                <h4 class="m-0 fw-bold text-dark"><?php echo $nama_rs_audit; ?></h4>
                <small class="text-muted">Audit Kepatuhan & Kelengkapan Rekam Medis Elektronik</small>
            </div>
        </div>
        <div class="text-end">
            <h5 class="m-0 text-danger fw-bold">AUDIT LOG</h5>
            <small class="text-muted"><?php echo date('d F Y'); ?></small>
        </div>
    </div>

    <form method="POST" action="">
        <div class="filter-box">
            <div class="row align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" class="form-control form-control-sm" value="<?php echo $tgl_awal; ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" class="form-control form-control-sm" value="<?php echo $tgl_akhir; ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Status Pelayanan</label>
                    <select name="status_lanjut" class="form-select form-select-sm">
                        <option value="Semua" <?php echo ($status_lanjut == 'Semua') ? 'selected' : ''; ?>>Semua (Ralan & Ranap)</option>
                        <option value="Ralan" <?php echo ($status_lanjut == 'Ralan') ? 'selected' : ''; ?>>Rawat Jalan Saja</option>
                        <option value="Ranap" <?php echo ($status_lanjut == 'Ranap') ? 'selected' : ''; ?>>Rawat Inap Saja</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Kolom Audit</label>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#columnModal">
                        <i class="fas fa-list-check me-1"></i> Pilih Kolom
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="cari" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-search me-1"></i> Tampilkan Data
                    </button>
                </div>
            </div>
        </div>

        <div class="modal fade" id="columnModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title"><i class="fas fa-tasks me-2"></i>Konfigurasi Kolom Audit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3 sticky-top bg-white py-2 border-bottom">
                            <div class="col-md-6">
                                <input type="text" id="searchCol" class="form-control form-control-sm" placeholder="Cari nama formulir/asesmen...">
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="button" class="btn btn-sm btn-success" onclick="checkAll(true)">Pilih Semua</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="checkAll(false)">Hapus Semua</button>
                            </div>
                        </div>
                        <div class="row" id="checkboxList">
                            <?php
                            $grouped_map = [];
                            foreach ($erm_map as $key => $val) {
                                $grouped_map[$val['grup']][$key] = $val;
                            }
                            foreach ($grouped_map as $grup => $items) {
                                echo "<div class='col-12 group-header'>$grup</div>";
                                foreach ($items as $label => $val) {
                                    $checked = in_array($label, $selected_cols) ? 'checked' : '';
                                    echo "
                                    <div class='col-md-3 col-sm-6 check-item'>
                                        <div class='form-check'>
                                            <input class='form-check-input col-checkbox' type='checkbox' name='cols[]' value='$label' id='chk_$label' $checked>
                                            <label class='form-check-label' for='chk_$label'>$label</label>
                                        </div>
                                    </div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Simpan Pilihan</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm mb-5">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table id="tableAudit" class="table table-striped table-bordered table-hover w-100">
                    <thead>
                        <tr>
                            <th class="fixed-col" style="min-width: 50px;">No.</th>
                            <th class="fixed-col" style="min-width: 120px;">No. Rawat</th>
                            <th style="min-width: 90px;">Tgl Reg</th>
                            <th style="min-width: 80px;">No. RM</th>
                            <th style="min-width: 200px;">Pasien</th>
                            <th style="min-width: 120px;">Penjamin</th>
                            <th style="min-width: 200px;">Dokter</th>
                            <th style="min-width: 150px;">Poli/Bangsal</th>
                            <th style="min-width: 80px;">Status</th>
                            
                            <?php foreach ($selected_cols as $col): ?>
                                <th class="text-center" style="min-width: 100px;"><?php echo $col; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if (isset($_POST['cari'])) {
                        
                        // MODIFIKASI QUERY: Tambah JOIN penjab untuk penjamin
                        $sql = "SELECT 
                                    rp.no_rawat, rp.tgl_registrasi, rp.no_rkm_medis, rp.status_lanjut,
                                    p.nm_pasien, d.nm_dokter, pj.png_jawab,
                                    IF(rp.status_lanjut='Ralan', poli.nm_poli, b.nm_bangsal) as unit
                                FROM reg_periksa rp
                                JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                                JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                                LEFT JOIN poliklinik poli ON rp.kd_poli = poli.kd_poli
                                LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
                                LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                                LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                                WHERE rp.tgl_registrasi BETWEEN ? AND ?
                                AND rp.stts <> 'Batal' ";
                        
                        if ($status_lanjut != 'Semua') {
                            $sql .= " AND rp.status_lanjut = '$status_lanjut' ";
                        }

                        $sql .= " GROUP BY rp.no_rawat ORDER BY rp.tgl_registrasi ASC, rp.jam_reg ASC";

                        $stmt = $koneksi->prepare($sql);
                        $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
                        $stmt->execute();
                        $result_main = $stmt->get_result();
                        
                        $no_urut = 1;
                        while ($row = $result_main->fetch_assoc()) {
                            $no_rawat = $row['no_rawat'];
                            
                            // LOGIKA WARNA STATUS
                            $status_badge = ($row['status_lanjut'] == 'Ralan') 
                                ? 'bg-info'  // Biru Muda
                                : 'bg-warning text-dark'; // Kuning
                                
                            // LOGIKA WARNA PENJAMIN
                            $pj = $row['png_jawab'];
                            $pj_badge = 'bg-secondary'; // Default Abu-abu (Perusahaan/Lainnya)
                            
                            if (stripos($pj, 'BPJS') !== false) {
                                $pj_badge = 'bg-success'; // Hijau
                            } elseif (stripos($pj, 'Umum') !== false || stripos($pj, 'Tunai') !== false) {
                                $pj_badge = 'bg-primary'; // Biru
                            } elseif (stripos($pj, 'Asuransi') !== false) {
                                $pj_badge = 'bg-pink'; // Pink
                            } 
                            
                            echo "<tr>";
                            echo "<td class='fixed-col text-center'>$no_urut</td>";
                            echo "<td class='fixed-col fw-bold'>$no_rawat</td>";
                            echo "<td>{$row['tgl_registrasi']}</td>";
                            echo "<td>{$row['no_rkm_medis']}</td>";
                            echo "<td>{$row['nm_pasien']}</td>";
                            echo "<td><span class='badge $pj_badge'>{$row['png_jawab']}</span></td>";
                            echo "<td>{$row['nm_dokter']}</td>";
                            echo "<td>{$row['unit']}</td>";
                            echo "<td><span class='badge $status_badge'>{$row['status_lanjut']}</span></td>";
                            
                            // Loop Dinamis Kolom Terpilih
                            foreach ($selected_cols as $col_label) {
                                $config = $erm_map[$col_label];
                                $table_name = $config['tabel'];
                                
                                $check_sql = "SELECT 1 FROM $table_name WHERE no_rawat = '$no_rawat' LIMIT 1";
                                $check_res = $koneksi->query($check_sql);
                                $status_isi = ($check_res && $check_res->num_rows > 0) ? 'Ada' : 'Tidak Ada';
                                
                                echo "<td class='text-center'>" . format_audit($status_isi) . "</td>";
                            }
                            echo "</tr>";
                            $no_urut++;
                        }
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<script>
$(document).ready(function() {
    $('#tableAudit').DataTable({
        "scrollX": true,
        "scrollY": "60vh",
        "scrollCollapse": true,
        "paging": false, 
        "fixedColumns": {
            left: 2 // Fix No dan No Rawat
        },
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excelHtml5', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Excel' },
            { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Print' }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" }
    });

    $("#searchCol").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".check-item").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
        $(".group-header").each(function() {
            var groupVisible = $(this).nextUntil(".group-header", ".check-item:visible").length > 0;
            $(this).toggle(groupVisible);
        });
    });
});

function checkAll(status) {
    $('.col-checkbox').prop('checked', status);
}
</script>
<?php $page_js = ob_get_clean(); ?>

<?php require_once('includes/footer.php'); ?>