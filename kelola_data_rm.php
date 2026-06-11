<?php
/*
 * File: kelola_data_rm.php
 * Modul: Kelola Data RM — Laporan RL untuk dikirim ke Dinkes/Kemenkes
 * Deskripsi: Memudahkan bagian Rekam Medis mengambil data pelaporan RL.
 */
$page_title = "Kelola Data RM";
require_once('includes/header.php');
?>

<style>
    .rl-card {
        transition: transform .2s, box-shadow .2s;
        cursor: pointer;
        border-left: 4px solid transparent;
    }
    .rl-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .rl-card.rv-normatif { border-left-color: #4e73df; }
    .rl-card.rv-khusus   { border-left-color: #e74a3b; }
    .rl-card.rv-kegiatan  { border-left-color: #1cc88a; }
    .rl-card.rv-pelayanan { border-left-color: #f6c23e; }

    .rl-icon {
        width: 3rem; height: 3rem; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem; color: #fff;
    }
    .rl-icon.bg-normatif  { background: linear-gradient(135deg, #4e73df, #224abe); }
    .rl-icon.bg-khusus    { background: linear-gradient(135deg, #e74a3b, #be2617); }
    .rl-icon.bg-kegiatan  { background: linear-gradient(135deg, #1cc88a, #13855c); }
    .rl-icon.bg-pelayanan { background: linear-gradient(135deg, #f6c23e, #dda20a); }

    .badge-coming {
        font-size: 0.65rem;
        padding: 3px 8px;
        border-radius: 12px;
        background: rgba(108,117,125,0.15);
        color: #6c757d;
        font-weight: 600;
    }
</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="fas fa-folder-open me-2 text-primary"></i>Kelola Data Rekam Medis</h4>
            <span class="text-muted small">Laporan RL untuk pengiriman data ke Dinkes / Kemenkes</span>
        </div>
        <div>
            <span class="badge bg-info"><i class="fas fa-info-circle me-1"></i>Modul dalam pengembangan</span>
        </div>
    </div>

    <div class="row">

        <!-- RL 1.1 - Data Dasar Rumah Sakit -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-normatif">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-normatif me-3"><i class="fas fa-hospital"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 1.1</h6>
                            <small class="text-muted">Data Dasar RS</small>
                        </div>
                    </div>
                    <p class="small mb-2">Identitas dan profil rumah sakit meliputi nama, jenis, kelas, kepemilikan, dan akreditasi.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 1.2 - Ketenagaan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-normatif">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-normatif me-3"><i class="fas fa-users"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 1.2</h6>
                            <small class="text-muted">Ketenagaan</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data kepegawaian tenaga kesehatan berdasarkan jenis, jumlah, dan kualifikasi.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 2 - Kegiatan Pelayanan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-pelayanan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-pelayanan me-3"><i class="fas fa-notes-medical"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 2</h6>
                            <small class="text-muted">Kegiatan Pelayanan</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data kegiatan pelayanan rawat jalan, rawat inap, IGD, dan ruang khusus lainnya.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.1 - Data Morbiditas -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-virus"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.1</h6>
                            <small class="text-muted">Morbiditas</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data 10 penyakit terbanyak rawat jalan & rawat inap berdasarkan ICD-10.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.2 - Penyakit Menular -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-biohazard"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.2</h6>
                            <small class="text-muted">Penyakit Menular</small>
                        </div>
                    </div>
                    <p class="small mb-2">Laporan penyakit menular yang ditemukan di rumah sakit (HIV, TB, DBD, dll).</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.3 - Penyakit Tidak Menular -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-heartbeat"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.3</h6>
                            <small class="text-muted">Penyakit Tidak Menular</small>
                        </div>
                    </div>
                    <p class="small mb-2">Laporan penyakit tidak menular (diabetes, hipertensi, stroke, kanker, dll).</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.4 - Kesehatan Jiwa -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-brain"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.4</h6>
                            <small class="text-muted">Kesehatan Jiwa</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan kesehatan jiwa: gangguan jiwa, pasien rawat, dan rujukan.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.5 - Lanjut Usia -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-user-clock"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.5</h6>
                            <small class="text-muted">Lanjut Usia</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan kesehatan lanjut usia (lansia) di fasilitas kesehatan.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.6 - Kecelakaan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-car-crash"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.6</h6>
                            <small class="text-muted">Kecelakaan</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data kecelakaan: lalu lintas, kerja, dan kecelakaan lainnya yang ditangani RS.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.7 - Kematian -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-file-medical"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.7</h6>
                            <small class="text-muted">Kematian</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data kematian pasien di rumah sakit berdasarkan penyebab, usia, dan jenis kelamin.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.8 - Kesehatan Gigi -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-tooth"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.8</h6>
                            <small class="text-muted">Kesehatan Gigi</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan kesehatan gigi dan mulut yang dilakukan di RS.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.9 - Kesehatan Kerja -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-hard-hat"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.9</h6>
                            <small class="text-muted">Kesehatan Kerja</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan kesehatan kerja dan penyakit akibat kerja di RS.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.10 - Kesehatan Mata -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-eye"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.10</h6>
                            <small class="text-muted">Kesehatan Mata</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan kesehatan mata: diagnosis, tindakan, dan jumlah kunjungan.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.11 - IMunisasi -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-khusus">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-khusus me-3"><i class="fas fa-syringe"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.11</h6>
                            <small class="text-muted">Imunisasi</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelaksanaan imunisasi di rumah sakit pada bayi, anak, dan dewasa.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.12 - Farmasi -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-kegiatan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-kegiatan me-3"><i class="fas fa-pills"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.12</h6>
                            <small class="text-muted">Farmasi</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan farmasi: resep, obat, distribusi, dan informasi obat.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.13 - Kesehatan Rujukan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-kegiatan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-kegiatan me-3"><i class="fas fa-exchange-alt"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.13</h6>
                            <small class="text-muted">Rujukan</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data rujukan masuk dan keluar antar fasilitas kesehatan.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.14 - Laboratorium -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-kegiatan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-kegiatan me-3"><i class="fas fa-flask"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.14</h6>
                            <small class="text-muted">Laboratorium</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pemeriksaan laboratorium: jenis pemeriksaan dan jumlah pelayanan.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 3.15 - Rehabilitasi Medik -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-kegiatan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-kegiatan me-3"><i class="fas fa-wheelchair"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 3.15</h6>
                            <small class="text-muted">Rehabilitasi Medik</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan rehabilitasi medik: fisioterapi, okupasi, wicara, dll.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 4 - Data Pelayanan Khusus -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-pelayanan">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-pelayanan me-3"><i class="fas fa-procedures"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 4</h6>
                            <small class="text-muted">Pelayanan Khusus</small>
                        </div>
                    </div>
                    <p class="small mb-2">Data pelayanan khusus: ICU, NICU, HCU, IGD, kamar bersalin, dan operasi.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

        <!-- RL 5 - Data Keuangan -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card shadow rl-card rv-normatif">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="rl-icon bg-normatif me-3"><i class="fas fa-chart-bar"></i></div>
                        <div>
                            <h6 class="mb-0 fw-bold">RL 5</h6>
                            <small class="text-muted">Keuangan</small>
                        </div>
                    </div>
                    <p class="small mb-2">Laporan keuangan rumah sakit meliputi pendapatan, belanja, dan aset.</p>
                    <span class="badge-coming"><i class="fas fa-clock me-1"></i>Segera hadir</span>
                </div>
            </div>
        </div>

    </div><!-- end row -->

    <div class="card shadow mt-2">
        <div class="card-body text-center py-4">
            <i class="fas fa-tools fa-2x text-muted mb-3"></i>
            <h6 class="text-muted">Modul ini sedang dalam tahap pengembangan</h6>
            <p class="small text-muted mb-0">Laporan RL akan ditambahkan secara bertahap. Hubungi IT jika ada kebutuhan mendesak untuk laporan tertentu.</p>
        </div>
    </div>

</div>

<?php require_once('includes/footer.php'); ?>
