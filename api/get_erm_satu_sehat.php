<?php
session_start();
require_once '../config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Akses ditolak.</div>";
    exit;
}

$no_rawat = isset($_GET['no_rawat']) ? $koneksi->real_escape_string($_GET['no_rawat']) : '';
$modul = isset($_GET['modul']) ? $koneksi->real_escape_string($_GET['modul']) : '';

if (empty($no_rawat) || empty($modul)) {
    echo "<div class='alert alert-warning'>Parameter tidak lengkap.</div>";
    exit;
}

echo "<h6>Detail Inspeksi ERM (Khanza) &middot; <strong class='text-primary'>$no_rawat</strong></h6><hr>";

if ($modul == "Encounter") {
    $q = $koneksi->query("SELECT p.no_rkm_medis, p.nm_pasien, rp.tgl_registrasi, rp.jam_reg, poli.nm_poli, pg.nama, rp.status_bayar, rp.status_lanjut FROM reg_periksa rp INNER JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis INNER JOIN poliklinik poli ON rp.kd_poli=poli.kd_poli INNER JOIN pegawai pg ON rp.kd_dokter=pg.nik WHERE rp.no_rawat='$no_rawat'");
    if($q && $q->num_rows > 0) {
        $r = $q->fetch_assoc();
        echo "<table class='table table-sm table-bordered'>
            <tr><th width='30%'>No RM</th><td>{$r['no_rkm_medis']}</td></tr>
            <tr><th>Nama Pasien</th><td>{$r['nm_pasien']}</td></tr>
            <tr><th>Tgl Registrasi</th><td>{$r['tgl_registrasi']} {$r['jam_reg']}</td></tr>
            <tr><th>Poliklinik/Unit</th><td>{$r['nm_poli']}</td></tr>
            <tr><th>Dokter PPA</th><td>{$r['nama']}</td></tr>
            <tr><th>Status Bayar</th><td><span class='badge bg-info'>{$r['status_bayar']}</span> <small class='text-muted'>(Trigger Satu Sehat memerlukan status 'Sudah Bayar')</small></td></tr>
            <tr><th>Status Lanjut</th><td>{$r['status_lanjut']}</td></tr>
        </table>";
    } else {
         echo "<div class='alert alert-info'>Tidak ada rekam jejak registrasi/kunjungan di tabel reg_periksa.</div>";
    }
} 
else if ($modul == "Diagnosa") {
    $q = $koneksi->query("SELECT dp.kd_penyakit, p.nm_penyakit, dp.status FROM diagnosa_pasien dp INNER JOIN penyakit p ON dp.kd_penyakit=p.kd_penyakit WHERE dp.no_rawat='$no_rawat'");
    if($q && $q->num_rows > 0) {
        echo "<table class='table table-sm table-bordered'>
            <thead class='table-light'><tr><th>ICD X / Snomed</th><th>Diagnosis/Penyakit</th><th>Tipe Perawatan</th><th>Status Bridging</th></tr></thead><tbody>";
        while($r = $q->fetch_assoc()) {
            echo "<tr><td><span class='fw-bold'>{$r['kd_penyakit']}</span></td><td>{$r['nm_penyakit']}</td><td>{$r['status']}</td><td><span class='badge bg-secondary'>Cek KTP / Status SNOMED</span></td></tr>";
        }
        echo "</tbody></table>";
        echo "<div class='alert alert-secondary py-2 small'><i class='fas fa-info-circle'></i> Pastikan kode ICD 10 termaping sempurna dalam referensi ICD/Snomed Kementerian Kesehatan. Diagnosa tidak akan pernah terkirim jika KTP Dokter atau Pasien kosong.</div>";
    } else {
        echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Tidak ditemukan data entry diagnosa untuk nomor rawat ini di RM (tabel <code>diagnosa_pasien</code>). Tentu saja Satu Sehat akan gagal mengirim diagnosa. Dokter mungkin lupa mengisi diagnosis akhir.</div>";
    }
}
else if ($modul == "TTV") {
    $q = $koneksi->query("SELECT tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, kesadaran, gcs FROM pemeriksaan_ralan WHERE no_rawat='$no_rawat'");
    $jenis = "Rawat Jalan";
    
    if(!$q || $q->num_rows == 0) {
        $q = $koneksi->query("SELECT tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi, kesadaran, gcs FROM pemeriksaan_ranap WHERE no_rawat='$no_rawat'");
        $jenis = "Rawat Inap";
    }
    
    if($q && $q->num_rows > 0) {
        echo "<div class='mb-2 text-primary fw-bold'><i class='fas fa-stethoscope'></i> Parameter Klinis $jenis</div>";
        echo "<table class='table table-sm table-bordered'>
            <thead class='table-light'><tr><th>Tgl/Jam</th><th>Suhu</th><th>Tensi</th><th>Nadi</th><th>RR</th><th>GCS</th><th>Kesadaran</th></tr></thead><tbody>";
        while($r = $q->fetch_assoc()) {
            echo "<tr>
                <td>{$r['tgl_perawatan']}<br>{$r['jam_rawat']}</td>
                <td class='text-center'>{$r['suhu_tubuh']}</td>
                <td class='text-center'>{$r['tensi']}</td>
                <td class='text-center'>{$r['nadi']}</td>
                <td class='text-center'>{$r['respirasi']}</td>
                <td class='text-center'>{$r['gcs']}</td>
                <td class='text-center'>{$r['kesadaran']}</td>
            </tr>";
        }
        echo "</tbody></table>";
        echo "<div class='alert alert-secondary py-2 small'><i class='fas fa-info-circle'></i> Agar berhasil terkirim, nilai TTV numerik tidak boleh berupa <i>String/Text Bebas</i> (misal: 'hangat', 'normal'). Nilai desimal wajib menggunakan titik standar (contoh: 36.5).</div>";
    } else {
        echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Data pemeriksaan rekam medis awal (CPPT / TTV) tidak ditemukan baik di Ralan maupun Ranap. TTV Gagal terkirim.</div>";
    }
}
else if ($modul == "Resep") {
    // Mengecek mapping obat dengan LEFT JOIN ke satu_sehat_mapping_obat
    $q = $koneksi->query("SELECT ro.no_resep, ro.tgl_perawatan, ro.jam, d.kode_brng, d.jml, d.aturan_pakai, ob.nama_brng, smo.obat_code, smo.obat_system 
                          FROM resep_obat ro 
                          INNER JOIN resep_dokter d ON ro.no_resep=d.no_resep 
                          INNER JOIN databarang ob ON d.kode_brng=ob.kode_brng 
                          LEFT JOIN satu_sehat_mapping_obat smo ON d.kode_brng=smo.kode_brng 
                          WHERE ro.no_rawat='$no_rawat'");
    
    // Resep racikan
    $racikan = "";
    $qr = $koneksi->query("SELECT ro.no_resep, d.nama_racik, d.jml_dr, d.aturan_pakai FROM resep_obat ro INNER JOIN resep_dokter_racikan d ON ro.no_resep=d.no_resep WHERE ro.no_rawat='$no_rawat'");
    if($qr && $qr->num_rows > 0) {
        $racikan .= "<div class='mt-3'><strong>Resep Racikan:</strong></div><table class='table table-sm table-bordered'><thead class='table-light'><tr><th>No Resep</th><th>Nama Racikan</th><th>Jml</th><th>Aturan</th><th>Status Mapping</th></tr></thead><tbody>";
        while($rx = $qr->fetch_assoc()) {
            $racikan .= "<tr><td>{$rx['no_resep']}</td><td>{$rx['nama_racik']}</td><td>{$rx['jml_dr']}</td><td>{$rx['aturan_pakai']}</td><td><span class='badge bg-warning text-dark'>Perlu Pengecekan KFA Item Resolusi</span></td></tr>";
        }
        $racikan .= "</tbody></table>";
    }
    
    if($q && $q->num_rows > 0) {
        echo "<table class='table table-sm table-bordered'>
            <thead class='table-light'><tr><th>No Resep</th><th>Nama Obat</th><th>Jml</th><th>Aturan Pakai</th><th>Status KFA (Satu Sehat)</th></tr></thead><tbody>";
        while($r = $q->fetch_assoc()) {
            $sts_map = !empty($r['obat_code']) ? "<span class='badge bg-success'><i class='fas fa-check'></i> Tapping KFA Ok</span>" : "<span class='badge bg-danger'><i class='fas fa-times'></i> Gagal: Belum Mapping KFA</span>";
            echo "<tr><td>{$r['no_resep']}<br><small>{$r['tgl_perawatan']} {$r['jam']}</small></td><td><span class='fw-bold'>{$r['kode_brng']}</span> - {$r['nama_brng']}</td><td>{$r['jml']}</td><td>{$r['aturan_pakai']}</td><td>$sts_map</td></tr>";
        }
        echo "</tbody></table>";
        
        echo $racikan;
        
        echo "<div class='alert alert-secondary py-2 small mt-2'><i class='fas fa-info-circle'></i> Pastikan obat yang GAGAL MAPPING segera ditambahkan ID KFA-nya di master DTO Farmasi -> Mapping Obat Satu Sehat.</div>";
    } else if($qr && $qr->num_rows > 0) {
        echo $racikan;
    } else {
        echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Pasien ini tidak menerima input E-Resep. Otomatis Modul Obat diskip (Bukan Gagal).</div>";
    }
}
else if ($modul == "Lab") {
    // Mengecek apakah sudah ada nilai hasil 
    // Bugfix: detail_periksa_lab tidak punya kolom noorder, jd INNER JOIN via no_rawat, tgl_periksa=tgl_hasil, jam=jam_hasil
    $sql_lab = "SELECT pl.noorder, pl.tgl_hasil, pl.jam_hasil, dp.kd_jenis_prw, j.nm_perawatan, dp.id_template, dp.nilai, dp.nilai_rujukan, sml.code AS lab_code 
                FROM permintaan_lab pl 
                INNER JOIN detail_periksa_lab dp ON pl.no_rawat=dp.no_rawat AND pl.tgl_hasil=dp.tgl_periksa AND pl.jam_hasil=dp.jam 
                INNER JOIN jns_perawatan_lab j ON dp.kd_jenis_prw=j.kd_jenis_prw 
                LEFT JOIN satu_sehat_mapping_lab sml ON dp.id_template=sml.id_template 
                WHERE pl.no_rawat='$no_rawat' LIMIT 50";
                
    $q = $koneksi->query($sql_lab);
    
    if($q && $q->num_rows > 0) {
         echo "<table class='table table-sm table-bordered'>
            <thead class='table-light'><tr><th>No Order/TPL</th><th>Jenis Perawatan PK</th><th>Nilai Uji</th><th>Ref</th><th>Status LOINC (Satu Sehat)</th></tr></thead><tbody>";
        while($r = $q->fetch_assoc()) {
            $sts_map = !empty($r['lab_code']) ? "<span class='badge bg-success'><i class='fas fa-check'></i> Mapped {$r['lab_code']}</span>" : "<span class='badge bg-danger'><i class='fas fa-times'></i> Gagal: Belum Mapping LOINC</span>";
            echo "<tr><td>{$r['noorder']}<br><small>{$r['tgl_hasil']} {$r['jam_hasil']}</small></td><td>{$r['kd_jenis_prw']} - {$r['nm_perawatan']}</td><td class='text-center fw-bold'>{$r['nilai']}</td><td class='text-center'>{$r['nilai_rujukan']}</td><td>$sts_map</td></tr>";
        }
        echo "</tbody></table>";
         echo "<div class='alert alert-secondary py-2 small'><i class='fas fa-info-circle'></i> Jika Label merah (Gagal), maka petugas / Direksi harus melengkapi Mapping Template Lab ke Master LOINC Kemkes.</div>";
    } else {
        // Fallback jika hanya ada Order (Permintaan lab) namun detail_periksa_lab-nya kosong/belum keluar hasilnya
        $sql_req = "SELECT pl.noorder, pl.tgl_permintaan, pl.jam_permintaan, ppl.kd_jenis_prw, j.nm_perawatan 
                    FROM permintaan_lab pl 
                    LEFT JOIN permintaan_pemeriksaan_lab ppl ON pl.noorder=ppl.noorder 
                    LEFT JOIN jns_perawatan_lab j ON ppl.kd_jenis_prw=j.kd_jenis_prw 
                    WHERE pl.no_rawat='$no_rawat' LIMIT 50";
        $q_req = $koneksi->query($sql_req);
        
        if($q_req && $q_req->num_rows > 0) {
            echo "<table class='table table-sm table-bordered'>
                <thead class='table-warning'><tr><th>Data Order/Permintaan Lab</th><th>Status Rilis Hasil Medis</th></tr></thead><tbody>";
            while($r_req = $q_req->fetch_assoc()) {
                $p_nama = $r_req['nm_perawatan'] != null ? $r_req['nm_perawatan'] : "Pemeriksaan Grup";
                echo "<tr><td>{$r_req['noorder']}<br><small><strong>Minta:</strong> {$r_req['tgl_permintaan']} {$r_req['jam_permintaan']}</small></td><td><span class='badge bg-warning text-dark'><i class='fas fa-hourglass-half me-1'></i> Belum Diinput/Pending</span><br><small>Order: {$r_req['kd_jenis_prw']} - {$p_nama}</small></td></tr>";
            }
            echo "</tbody></table>";
            echo "<div class='alert alert-danger py-2 small'><i class='fas fa-ban me-1'></i> <strong>Kenapa Data Ini Gagal Dikirim ke Satu Sehat?</strong><br>Direktur, ini disebabkan Dokter sudah menekan Permintaan Lab tapi analis Lab belum meng-<i>input</i> hasil pemeriksaannya. Alhasil <i>DiagnosticReport</i> gagal ter-<i>bridging</i>! Suruh Unit Lab memvalidasi hasil uji spesimen!</div>";
        } else {
            echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Tidak ada bukti permintaan maupun hasil Laboratorium untuk nomor rawat ini (Tabel mutlak kosong).</div>";
        }
    }
}
else if ($modul == "Radiologi") {
    // Bugfix: periksa_radiologi joined via no_rawat, tgl_periksa=tgl_hasil, jam=jam_hasil
    $sql_rad = "SELECT pl.noorder, pl.tgl_hasil, pl.jam_hasil, dp.kd_jenis_prw, j.nm_perawatan, smr.code AS rad_code 
                FROM permintaan_radiologi pl 
                INNER JOIN periksa_radiologi dp ON pl.no_rawat=dp.no_rawat AND pl.tgl_hasil=dp.tgl_periksa AND pl.jam_hasil=dp.jam 
                INNER JOIN jns_perawatan_radiologi j ON dp.kd_jenis_prw=j.kd_jenis_prw 
                LEFT JOIN satu_sehat_mapping_radiologi smr ON dp.kd_jenis_prw=smr.kd_jenis_prw 
                WHERE pl.no_rawat='$no_rawat' GROUP BY dp.kd_jenis_prw LIMIT 50";
                
    $q = $koneksi->query($sql_rad);
    if($q && $q->num_rows > 0) {
         echo "<table class='table table-sm table-bordered'>
            <thead class='table-light'><tr><th>No Order / TGL</th><th>Perawatan Radiologi / Imaging</th><th>Status LOINC (Satu Sehat)</th></tr></thead><tbody>";
        while($r = $q->fetch_assoc()) {
            $sts_map = !empty($r['rad_code']) ? "<span class='badge bg-success'><i class='fas fa-check'></i> Mapped {$r['rad_code']}</span>" : "<span class='badge bg-danger'><i class='fas fa-times'></i> Gagal: Belum Mapping LOINC</span>";
            echo "<tr><td>{$r['noorder']}<br><small>{$r['tgl_hasil']} {$r['jam_hasil']}</small></td><td><span class='fw-bold'>{$r['kd_jenis_prw']}</span> - {$r['nm_perawatan']}</td><td>$sts_map</td></tr>";
        }
        echo "</tbody></table>";
        echo "<div class='alert alert-secondary py-2 small'><i class='fas fa-info-circle'></i> Sama halnya dengan Lab, Bridging Radiologi juga diwajibkan melakukan Maping Tindakan -> LOINC.</div>";
    } else {
        $sql_req = "SELECT pl.noorder, pl.tgl_permintaan, pl.jam_permintaan, ppl.kd_jenis_prw, j.nm_perawatan 
                    FROM permintaan_radiologi pl 
                    LEFT JOIN permintaan_pemeriksaan_radiologi ppl ON pl.noorder=ppl.noorder 
                    LEFT JOIN jns_perawatan_radiologi j ON ppl.kd_jenis_prw=j.kd_jenis_prw 
                    WHERE pl.no_rawat='$no_rawat' LIMIT 50";
        $q_req = $koneksi->query($sql_req);
        
        if($q_req && $q_req->num_rows > 0) {
            echo "<table class='table table-sm table-bordered'>
                <thead class='table-warning'><tr><th>Data Order/Permintaan Radiologi</th><th>Status Rilis Ahli Radiologi</th></tr></thead><tbody>";
            while($r_req = $q_req->fetch_assoc()) {
                $p_nama = $r_req['nm_perawatan'] != null ? $r_req['nm_perawatan'] : "Pemeriksaan Grup";
                echo "<tr><td>{$r_req['noorder']}<br><small><strong>Minta:</strong> {$r_req['tgl_permintaan']} {$r_req['jam_permintaan']}</small></td><td><span class='badge bg-warning text-dark'><i class='fas fa-hourglass-half me-1'></i> Pemeriksaan Tertunda/Belum Beralasan Medis</span><br><small>Order: {$r_req['kd_jenis_prw']} - {$p_nama}</small></td></tr>";
            }
            echo "</tbody></table>";
            echo "<div class='alert alert-danger py-2 small'><i class='fas fa-ban me-1'></i> <strong>Kenapa Data Radiologi Gagal Ter-Bridging?</strong><br>Dokter telah memesan order radiologi, namun sampai detik ini pihak Unit Radiologi belum memasukkan hasil ekspertasi/imaging medisnya. Satu Sehat harus menerima status 'Final'!</div>";
        } else {
             echo "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Tidak ada bukti permintaan radiologi untuk nomor rawat ini (Tabel mutlak kosong).</div>";
        }
    }
}
?>
