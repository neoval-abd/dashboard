<?php
/*
 * File: api/data_penjualan_obat.php
 * Deskripsi: API Backend Penjualan Obat & Sirkulasi Barang Farmasi
 * Sesuai query dan logika DlgSirkulasiBarang3.java
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
require_once(dirname(__DIR__) . '/config/koneksi.php');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : 'table';
$tgl_awal = isset($_GET['tgl_awal']) && !empty($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-d');
$tgl_akhir = isset($_GET['tgl_akhir']) && !empty($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$kd_bangsal = isset($_GET['kd_bangsal']) ? trim($_GET['kd_bangsal']) : '';
$nmjns = isset($_GET['nmjns']) ? trim($_GET['nmjns']) : '';
$nmkategori = isset($_GET['nmkategori']) ? trim($_GET['nmkategori']) : '';
$nmgolongan = isset($_GET['nmgolongan']) ? trim($_GET['nmgolongan']) : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// 1. Cek Penentuan HPP Farmasi (dasar / h_beli)
$hppfarmasi = "dasar";
$sql_conf = "SELECT hargadasar FROM set_harga_obat LIMIT 1";
$res_conf = $koneksi->query($sql_conf);
if ($res_conf && $res_conf->num_rows > 0) {
    $row_conf = $res_conf->fetch_assoc();
    if ($row_conf['hargadasar'] == 'Harga Beli') {
        $hppfarmasi = "h_beli";
    }
}

// =========================================================================
// ACTION: GRAFIK ANALISA
// =========================================================================
if ($action === 'grafik') {
    $tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'jual_banyak';
    $labels = [];
    $data_qty = [];
    $data_rp = [];
    $title = "";

    switch ($tipe) {
        case 'jual_banyak':
            $title = "Top 10 Barang Penjualan Terbanyak";
            $sql = "SELECT databarang.nama_brng, SUM(detailjual.jumlah) as jml, SUM(detailjual.total) as total
                    FROM penjualan 
                    INNER JOIN detailjual ON penjualan.nota_jual = detailjual.nota_jual
                    INNER JOIN databarang ON detailjual.kode_brng = databarang.kode_brng
                    WHERE penjualan.status = 'Sudah Dibayar' AND penjualan.tgl_jual BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND penjualan.kd_bangsal = ? " : "") .
                    "GROUP BY detailjual.kode_brng ORDER BY jml DESC LIMIT 10";
            break;

        case 'jual_dikit':
            $title = "Top 10 Barang Penjualan Tersedikit";
            $sql = "SELECT databarang.nama_brng, SUM(detailjual.jumlah) as jml, SUM(detailjual.total) as total
                    FROM penjualan 
                    INNER JOIN detailjual ON penjualan.nota_jual = detailjual.nota_jual
                    INNER JOIN databarang ON detailjual.kode_brng = databarang.kode_brng
                    WHERE penjualan.status = 'Sudah Dibayar' AND penjualan.tgl_jual BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND penjualan.kd_bangsal = ? " : "") .
                    "GROUP BY detailjual.kode_brng HAVING jml > 0 ORDER BY jml ASC LIMIT 10";
            break;

        case 'resep_banyak':
            $title = "Top 10 Resep Ke Pasien Terbanyak";
            $sql = "SELECT databarang.nama_brng, SUM(detail_pemberian_obat.jml) as jml, SUM(detail_pemberian_obat.total) as total
                    FROM detail_pemberian_obat
                    INNER JOIN databarang ON detail_pemberian_obat.kode_brng = databarang.kode_brng
                    WHERE detail_pemberian_obat.tgl_perawatan BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND detail_pemberian_obat.kd_bangsal = ? " : "") .
                    "GROUP BY detail_pemberian_obat.kode_brng ORDER BY jml DESC LIMIT 10";
            break;

        case 'resep_dikit':
            $title = "Top 10 Resep Ke Pasien Tersedikit";
            $sql = "SELECT databarang.nama_brng, SUM(detail_pemberian_obat.jml) as jml, SUM(detail_pemberian_obat.total) as total
                    FROM detail_pemberian_obat
                    INNER JOIN databarang ON detail_pemberian_obat.kode_brng = databarang.kode_brng
                    WHERE detail_pemberian_obat.tgl_perawatan BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND detail_pemberian_obat.kd_bangsal = ? " : "") .
                    "GROUP BY detail_pemberian_obat.kode_brng HAVING jml > 0 ORDER BY jml ASC LIMIT 10";
            break;

        case 'beli_banyak':
            $title = "Top 10 Pembelian (Pengadaan) Terbanyak";
            $sql = "SELECT databarang.nama_brng, SUM(detailbeli.jumlah2) as jml, SUM(detailbeli.total) as total
                    FROM pembelian 
                    INNER JOIN detailbeli ON pembelian.no_faktur = detailbeli.no_faktur
                    INNER JOIN databarang ON detailbeli.kode_brng = databarang.kode_brng
                    WHERE pembelian.tgl_beli BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND pembelian.kd_bangsal = ? " : "") .
                    "GROUP BY detailbeli.kode_brng ORDER BY jml DESC LIMIT 10";
            break;

        case 'beli_dikit':
            $title = "Top 10 Pembelian (Pengadaan) Tersedikit";
            $sql = "SELECT databarang.nama_brng, SUM(detailbeli.jumlah2) as jml, SUM(detailbeli.total) as total
                    FROM pembelian 
                    INNER JOIN detailbeli ON pembelian.no_faktur = detailbeli.no_faktur
                    INNER JOIN databarang ON detailbeli.kode_brng = databarang.kode_brng
                    WHERE pembelian.tgl_beli BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND pembelian.kd_bangsal = ? " : "") .
                    "GROUP BY detailbeli.kode_brng HAVING jml > 0 ORDER BY jml ASC LIMIT 10";
            break;

        case 'piutang_banyak':
            $title = "Top 10 Piutang Terbanyak";
            $sql = "SELECT databarang.nama_brng, SUM(detailpiutang.jumlah) as jml, SUM(detailpiutang.total) as total
                    FROM piutang 
                    INNER JOIN detailpiutang ON piutang.nota_piutang = detailpiutang.nota_piutang
                    INNER JOIN databarang ON detailpiutang.kode_brng = databarang.kode_brng
                    WHERE piutang.tgl_piutang BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND piutang.kd_bangsal = ? " : "") .
                    "GROUP BY detailpiutang.kode_brng ORDER BY jml DESC LIMIT 10";
            break;

        case 'piutang_dikit':
            $title = "Top 10 Piutang Tersedikit";
            $sql = "SELECT databarang.nama_brng, SUM(detailpiutang.jumlah) as jml, SUM(detailpiutang.total) as total
                    FROM piutang 
                    INNER JOIN detailpiutang ON piutang.nota_piutang = detailpiutang.nota_piutang
                    INNER JOIN databarang ON detailpiutang.kode_brng = databarang.kode_brng
                    WHERE piutang.tgl_piutang BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND piutang.kd_bangsal = ? " : "") .
                    "GROUP BY detailpiutang.kode_brng HAVING jml > 0 ORDER BY jml ASC LIMIT 10";
            break;

        default:
            $title = "Top 10 Penjualan Terbanyak";
            $sql = "SELECT databarang.nama_brng, SUM(detailjual.jumlah) as jml, SUM(detailjual.total) as total
                    FROM penjualan 
                    INNER JOIN detailjual ON penjualan.nota_jual = detailjual.nota_jual
                    INNER JOIN databarang ON detailjual.kode_brng = databarang.kode_brng
                    WHERE penjualan.status = 'Sudah Dibayar' AND penjualan.tgl_jual BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND penjualan.kd_bangsal = ? " : "") .
                    "GROUP BY detailjual.kode_brng ORDER BY jml DESC LIMIT 10";
    }

    $stmt = $koneksi->prepare($sql);
    if (!empty($kd_bangsal)) {
        $stmt->bind_param("sss", $tgl_awal, $tgl_akhir, $kd_bangsal);
    } else {
        $stmt->bind_param("ss", $tgl_awal, $tgl_akhir);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $labels[] = $r['nama_brng'];
        $data_qty[] = (float)$r['jml'];
        $data_rp[] = (float)$r['total'];
    }
    $stmt->close();

    echo json_encode([
        'title'    => $title,
        'labels'   => $labels,
        'data_qty' => $data_qty,
        'data_rp'  => $data_rp
    ]);
    exit;
}

// =========================================================================
// ACTION: TABLE DATA (ALL 33 COLUMNS + SUMMARIES)
// =========================================================================

// 2. Fetch master databarang terfilter
$where_base = " WHERE databarang.status = '1' ";
$params_base = [];
$types_base = "";

if (!empty($nmjns)) {
    $where_base .= " AND (jenis.nama LIKE ? OR jenis.kdjns = ?) ";
    $params_base[] = "%$nmjns%";
    $params_base[] = $nmjns;
    $types_base .= "ss";
}
if (!empty($nmkategori)) {
    $where_base .= " AND (kategori_barang.nama LIKE ? OR kategori_barang.kode = ?) ";
    $params_base[] = "%$nmkategori%";
    $params_base[] = $nmkategori;
    $types_base .= "ss";
}
if (!empty($nmgolongan)) {
    $where_base .= " AND (golongan_barang.nama LIKE ? OR golongan_barang.kode = ?) ";
    $params_base[] = "%$nmgolongan%";
    $params_base[] = $nmgolongan;
    $types_base .= "ss";
}
if (!empty($keyword)) {
    $where_base .= " AND (databarang.kode_brng LIKE ? OR databarang.nama_brng LIKE ?) ";
    $params_base[] = "%$keyword%";
    $params_base[] = "%$keyword%";
    $types_base .= "ss";
}

$sql_items = "SELECT databarang.kode_brng, databarang.nama_brng, kodesatuan.satuan, 
                     databarang.$hppfarmasi as hpp,
                     COALESCE(jenis.nama, '') as nama_jenis, 
                     COALESCE(kategori_barang.nama, '') as nama_kategori, 
                     COALESCE(golongan_barang.nama, '') as nama_golongan
              FROM databarang
              INNER JOIN kodesatuan ON databarang.kode_sat = kodesatuan.kode_sat
              LEFT JOIN jenis ON databarang.kdjns = jenis.kdjns
              LEFT JOIN kategori_barang ON databarang.kode_kategori = kategori_barang.kode
              LEFT JOIN golongan_barang ON databarang.kode_golongan = golongan_barang.kode
              $where_base
              ORDER BY databarang.nama_brng ASC";

$stmt = $koneksi->prepare($sql_items);
if (!empty($params_base)) {
    $stmt->bind_param($types_base, ...$params_base);
}
$stmt->execute();
$res_items = $stmt->get_result();

$items = [];
while ($row = $res_items->fetch_assoc()) {
    $items[$row['kode_brng']] = [
        'kode_brng' => $row['kode_brng'],
        'nama_brng' => $row['nama_brng'],
        'satuan'    => $row['satuan'],
        'hpp'       => (float)$row['hpp']
    ];
}
$stmt->close();

if (empty($items)) {
    echo json_encode([
        'summary' => [
            'total_item' => 0, 'total_stok' => 0, 'total_aset' => 0,
            'total_beli_jml' => 0, 'total_beli_tot' => 0,
            'total_pesan_jml' => 0, 'total_pesan_tot' => 0,
            'total_jual_jml' => 0, 'total_jual_tot' => 0,
            'total_pasien_jml' => 0, 'total_pasien_tot' => 0,
            'total_piutang_jml' => 0, 'total_piutang_tot' => 0,
            'total_retbeli_jml' => 0, 'total_retbeli_tot' => 0,
            'total_retjual_jml' => 0, 'total_retjual_tot' => 0,
            'total_retpiut_jml' => 0, 'total_retpiut_tot' => 0,
            'total_utd_jml' => 0, 'total_utd_tot' => 0,
            'total_keluar_jml' => 0, 'total_keluar_tot' => 0,
            'total_respulang_jml' => 0, 'total_respulang_tot' => 0,
            'total_mutasimasuk_jml' => 0, 'total_mutasimasuk_tot' => 0,
            'total_mutasikeluar_jml' => 0, 'total_mutasikeluar_tot' => 0,
            'total_hibah_jml' => 0, 'total_hibah_tot' => 0
        ],
        'data' => []
    ]);
    exit;
}

// Helper Aggregator Query
function run_aggregation($koneksi, $sql, $types, $params) {
    $stmt = $koneksi->prepare($sql);
    if (!$stmt) return [];
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[$r['kode_brng']] = [
            'jml'   => (float)($r['jml'] ?? 0),
            'total' => (float)($r['total'] ?? 0)
        ];
    }
    $stmt->close();
    return $data;
}

// 3. Eksekusi 13 Query Agregasi
// Parameter tanggal standar
$params_date = [$tgl_awal, $tgl_akhir];
$types_date = "ss";
if (!empty($kd_bangsal)) {
    $params_date[] = $kd_bangsal;
    $types_date .= "s";
}

// Parameter tanggal + jam (untuk tabel dengan datetime seperti UTD dan Mutasi)
$params_dt = [$tgl_awal . ' 00:00:01', $tgl_akhir . ' 23:59:59'];
$types_dt = "ss";
if (!empty($kd_bangsal)) {
    $params_dt[] = $kd_bangsal;
    $types_dt .= "s";
}

// Stok dari gudangbarang
$sql_stok = "SELECT kode_brng, SUM(stok) as jml FROM gudangbarang " .
            (!empty($kd_bangsal) ? "WHERE kd_bangsal = ? " : "") .
            "GROUP BY kode_brng";
$stok_agg = run_aggregation($koneksi, $sql_stok, !empty($kd_bangsal) ? "s" : "", !empty($kd_bangsal) ? [$kd_bangsal] : []);

// Pengadaan (Pembelian)
$sql_beli = "SELECT detailbeli.kode_brng, SUM(detailbeli.jumlah2) as jml, SUM(detailbeli.total) as total
             FROM pembelian INNER JOIN detailbeli ON pembelian.no_faktur = detailbeli.no_faktur
             WHERE pembelian.tgl_beli BETWEEN ? AND ? " .
             (!empty($kd_bangsal) ? "AND pembelian.kd_bangsal = ? " : "") .
             "GROUP BY detailbeli.kode_brng";
$beli_agg = run_aggregation($koneksi, $sql_beli, $types_date, $params_date);

// Penerimaan (Pemesanan)
$sql_pesan = "SELECT detailpesan.kode_brng, SUM(detailpesan.jumlah2) as jml, SUM(detailpesan.total) as total
              FROM pemesanan INNER JOIN detailpesan ON pemesanan.no_faktur = detailpesan.no_faktur
              WHERE pemesanan.tgl_pesan BETWEEN ? AND ? " .
              (!empty($kd_bangsal) ? "AND pemesanan.kd_bangsal = ? " : "") .
              "GROUP BY detailpesan.kode_brng";
$pesan_agg = run_aggregation($koneksi, $sql_pesan, $types_date, $params_date);

// Penjualan
$sql_jual = "SELECT detailjual.kode_brng, SUM(detailjual.jumlah) as jml, SUM(detailjual.total) as total
             FROM penjualan INNER JOIN detailjual ON penjualan.nota_jual = detailjual.nota_jual
             WHERE penjualan.status = 'Sudah Dibayar' AND penjualan.tgl_jual BETWEEN ? AND ? " .
             (!empty($kd_bangsal) ? "AND penjualan.kd_bangsal = ? " : "") .
             "GROUP BY detailjual.kode_brng";
$jual_agg = run_aggregation($koneksi, $sql_jual, $types_date, $params_date);

// Ke Pasien (Pemberian Obat)
$sql_pasien = "SELECT detail_pemberian_obat.kode_brng, 
                      SUM(detail_pemberian_obat.jml) as jml, 
                      (SUM(detail_pemberian_obat.total) - SUM(detail_pemberian_obat.embalase + detail_pemberian_obat.tuslah)) as total
               FROM detail_pemberian_obat
               WHERE detail_pemberian_obat.tgl_perawatan BETWEEN ? AND ? " .
               (!empty($kd_bangsal) ? "AND detail_pemberian_obat.kd_bangsal = ? " : "") .
               "GROUP BY detail_pemberian_obat.kode_brng";
$pasien_agg = run_aggregation($koneksi, $sql_pasien, $types_date, $params_date);

// Piutang Jual
$sql_piutang = "SELECT detailpiutang.kode_brng, SUM(detailpiutang.jumlah) as jml, SUM(detailpiutang.total) as total
                FROM piutang INNER JOIN detailpiutang ON piutang.nota_piutang = detailpiutang.nota_piutang
                WHERE piutang.tgl_piutang BETWEEN ? AND ? " .
                (!empty($kd_bangsal) ? "AND piutang.kd_bangsal = ? " : "") .
                "GROUP BY detailpiutang.kode_brng";
$piutang_agg = run_aggregation($koneksi, $sql_piutang, $types_date, $params_date);

// Retur Beli
$sql_retbeli = "SELECT detreturbeli.kode_brng, SUM(detreturbeli.jml_retur2) as jml, SUM(detreturbeli.total) as total
                FROM returbeli INNER JOIN detreturbeli ON returbeli.no_retur_beli = detreturbeli.no_retur_beli
                WHERE returbeli.tgl_retur BETWEEN ? AND ? " .
                (!empty($kd_bangsal) ? "AND returbeli.kd_bangsal = ? " : "") .
                "GROUP BY detreturbeli.kode_brng";
$retbeli_agg = run_aggregation($koneksi, $sql_retbeli, $types_date, $params_date);

// Retur Jual
$sql_retjual = "SELECT detreturjual.kode_brng, SUM(detreturjual.jml_retur) as jml, SUM(detreturjual.subtotal) as total
                FROM returjual INNER JOIN detreturjual ON returjual.no_retur_jual = detreturjual.no_retur_jual
                WHERE returjual.tgl_retur BETWEEN ? AND ? " .
                (!empty($kd_bangsal) ? "AND returjual.kd_bangsal = ? " : "") .
                "GROUP BY detreturjual.kode_brng";
$retjual_agg = run_aggregation($koneksi, $sql_retjual, $types_date, $params_date);

// Retur Piutang
$sql_retpiut = "SELECT detreturpiutang.kode_brng, SUM(detreturpiutang.jml_retur) as jml, SUM(detreturpiutang.subtotal) as total
                FROM returpiutang INNER JOIN detreturpiutang ON returpiutang.no_retur_piutang = detreturpiutang.no_retur_piutang
                WHERE returpiutang.tgl_retur BETWEEN ? AND ? " .
                (!empty($kd_bangsal) ? "AND returpiutang.kd_bangsal = ? " : "") .
                "GROUP BY detreturpiutang.kode_brng";
$retpiut_agg = run_aggregation($koneksi, $sql_retpiut, $types_date, $params_date);

// Pengambilan UTD
$sql_utd = "SELECT utd_pengambilan_medis.kode_brng, SUM(utd_pengambilan_medis.jml) as jml, SUM(utd_pengambilan_medis.total) as total
            FROM utd_pengambilan_medis
            WHERE utd_pengambilan_medis.tanggal BETWEEN ? AND ? " .
            (!empty($kd_bangsal) ? "AND utd_pengambilan_medis.kd_bangsal_dr = ? " : "") .
            "GROUP BY utd_pengambilan_medis.kode_brng";
$utd_agg = run_aggregation($koneksi, $sql_utd, $types_dt, $params_dt);

// Stok Keluar Medis
$sql_keluar = "SELECT detail_pengeluaran_obat_bhp.kode_brng, SUM(detail_pengeluaran_obat_bhp.jumlah) as jml, SUM(detail_pengeluaran_obat_bhp.total) as total
               FROM pengeluaran_obat_bhp INNER JOIN detail_pengeluaran_obat_bhp ON pengeluaran_obat_bhp.no_keluar = detail_pengeluaran_obat_bhp.no_keluar
               WHERE pengeluaran_obat_bhp.tanggal BETWEEN ? AND ? " .
               (!empty($kd_bangsal) ? "AND pengeluaran_obat_bhp.kd_bangsal = ? " : "") .
               "GROUP BY detail_pengeluaran_obat_bhp.kode_brng";
$keluar_agg = run_aggregation($koneksi, $sql_keluar, $types_date, $params_date);

// Resep Pulang
$sql_respulang = "SELECT resep_pulang.kode_brng, SUM(resep_pulang.jml_barang) as jml, SUM(resep_pulang.total) as total
                  FROM resep_pulang
                  WHERE resep_pulang.tanggal BETWEEN ? AND ? " .
                  (!empty($kd_bangsal) ? "AND resep_pulang.kd_bangsal = ? " : "") .
                  "GROUP BY resep_pulang.kode_brng";
$respulang_agg = run_aggregation($koneksi, $sql_respulang, $types_date, $params_date);

// Mutasi Masuk
$sql_mutasimasuk = "SELECT kode_brng, SUM(jml) as jml, SUM(jml*harga) as total
                    FROM mutasibarang
                    WHERE tanggal BETWEEN ? AND ? " .
                    (!empty($kd_bangsal) ? "AND kd_bangsalke = ? " : "") .
                    "GROUP BY kode_brng";
$mutasimasuk_agg = run_aggregation($koneksi, $sql_mutasimasuk, $types_dt, $params_dt);

// Mutasi Keluar
$sql_mutasikeluar = "SELECT kode_brng, SUM(jml) as jml, SUM(jml*harga) as total
                     FROM mutasibarang
                     WHERE tanggal BETWEEN ? AND ? " .
                     (!empty($kd_bangsal) ? "AND kd_bangsaldari = ? " : "") .
                     "GROUP BY kode_brng";
$mutasikeluar_agg = run_aggregation($koneksi, $sql_mutasikeluar, $types_dt, $params_dt);

// Hibah
$sql_hibah = "SELECT detailhibah_obat_bhp.kode_brng, SUM(detailhibah_obat_bhp.jumlah2) as jml, SUM(detailhibah_obat_bhp.subtotaldiakui) as total
              FROM hibah_obat_bhp INNER JOIN detailhibah_obat_bhp ON hibah_obat_bhp.no_hibah = detailhibah_obat_bhp.no_hibah
              WHERE hibah_obat_bhp.tgl_hibah BETWEEN ? AND ? " .
              (!empty($kd_bangsal) ? "AND hibah_obat_bhp.kd_bangsal = ? " : "") .
              "GROUP BY detailhibah_obat_bhp.kode_brng";
$hibah_agg = run_aggregation($koneksi, $sql_hibah, $types_date, $params_date);

// 4. Susun Baris Hasil & Grand Total
$data = [];
$summary = [
    'total_item' => 0, 'total_stok' => 0, 'total_aset' => 0,
    'total_beli_jml' => 0, 'total_beli_tot' => 0,
    'total_pesan_jml' => 0, 'total_pesan_tot' => 0,
    'total_jual_jml' => 0, 'total_jual_tot' => 0,
    'total_pasien_jml' => 0, 'total_pasien_tot' => 0,
    'total_piutang_jml' => 0, 'total_piutang_tot' => 0,
    'total_retbeli_jml' => 0, 'total_retbeli_tot' => 0,
    'total_retjual_jml' => 0, 'total_retjual_tot' => 0,
    'total_retpiut_jml' => 0, 'total_retpiut_tot' => 0,
    'total_utd_jml' => 0, 'total_utd_tot' => 0,
    'total_keluar_jml' => 0, 'total_keluar_tot' => 0,
    'total_respulang_jml' => 0, 'total_respulang_tot' => 0,
    'total_mutasimasuk_jml' => 0, 'total_mutasimasuk_tot' => 0,
    'total_mutasikeluar_jml' => 0, 'total_mutasikeluar_tot' => 0,
    'total_hibah_jml' => 0, 'total_hibah_tot' => 0
];

foreach ($items as $kode => $it) {
    $stok = $stok_agg[$kode]['jml'] ?? 0;
    $aset = $stok * $it['hpp'];

    $beli_jml = $beli_agg[$kode]['jml'] ?? 0;
    $beli_tot = $beli_agg[$kode]['total'] ?? 0;

    $pesan_jml = $pesan_agg[$kode]['jml'] ?? 0;
    $pesan_tot = $pesan_agg[$kode]['total'] ?? 0;

    $jual_jml = $jual_agg[$kode]['jml'] ?? 0;
    $jual_tot = $jual_agg[$kode]['total'] ?? 0;

    $pasien_jml = $pasien_agg[$kode]['jml'] ?? 0;
    $pasien_tot = $pasien_agg[$kode]['total'] ?? 0;

    $piutang_jml = $piutang_agg[$kode]['jml'] ?? 0;
    $piutang_tot = $piutang_agg[$kode]['total'] ?? 0;

    $retbeli_jml = $retbeli_agg[$kode]['jml'] ?? 0;
    $retbeli_tot = $retbeli_agg[$kode]['total'] ?? 0;

    $retjual_jml = $retjual_agg[$kode]['jml'] ?? 0;
    $retjual_tot = $retjual_agg[$kode]['total'] ?? 0;

    $retpiut_jml = $retpiut_agg[$kode]['jml'] ?? 0;
    $retpiut_tot = $retpiut_agg[$kode]['total'] ?? 0;

    $utd_jml = $utd_agg[$kode]['jml'] ?? 0;
    $utd_tot = $utd_agg[$kode]['total'] ?? 0;

    $keluar_jml = $keluar_agg[$kode]['jml'] ?? 0;
    $keluar_tot = $keluar_agg[$kode]['total'] ?? 0;

    $respulang_jml = $respulang_agg[$kode]['jml'] ?? 0;
    $respulang_tot = $respulang_agg[$kode]['total'] ?? 0;

    $mutasimasuk_jml = $mutasimasuk_agg[$kode]['jml'] ?? 0;
    $mutasimasuk_tot = $mutasimasuk_agg[$kode]['total'] ?? 0;

    $mutasikeluar_jml = $mutasikeluar_agg[$kode]['jml'] ?? 0;
    $mutasikeluar_tot = $mutasikeluar_agg[$kode]['total'] ?? 0;

    $hibah_jml = $hibah_agg[$kode]['jml'] ?? 0;
    $hibah_tot = $hibah_agg[$kode]['total'] ?? 0;

    // Filter baris sesuai DlgSirkulasiBarang3: hanya tampilkan jika ada stok, aset, atau mutasi/transaksi
    if ($aset > 0 || $stok != 0 || $beli_jml > 0 || $pesan_jml > 0 || $jual_jml > 0 ||
        $pasien_jml > 0 || $piutang_jml > 0 || $retbeli_jml > 0 || $retjual_jml > 0 ||
        $retpiut_jml > 0 || $utd_jml > 0 || $keluar_jml > 0 || $respulang_jml > 0 ||
        $mutasimasuk_jml > 0 || $mutasikeluar_jml > 0 || $hibah_jml > 0) {
        
        $data[] = [
            'kode_brng'        => $kode,
            'nama_brng'        => $it['nama_brng'],
            'satuan'           => $it['satuan'],
            'stok'             => $stok,
            'aset'             => $aset,
            'pengadaan_jml'    => $beli_jml,
            'pengadaan_tot'    => $beli_tot,
            'penerimaan_jml'   => $pesan_jml,
            'penerimaan_tot'   => $pesan_tot,
            'penjualan_jml'    => $jual_jml,
            'penjualan_tot'    => $jual_tot,
            'ke_pasien_jml'    => $pasien_jml,
            'ke_pasien_tot'    => $pasien_tot,
            'piutang_jml'      => $piutang_jml,
            'piutang_tot'      => $piutang_tot,
            'retur_beli_jml'   => $retbeli_jml,
            'retur_beli_tot'   => $retbeli_tot,
            'retur_jual_jml'   => $retjual_jml,
            'retur_jual_tot'   => $retjual_tot,
            'retur_piutang_jml'=> $retpiut_jml,
            'retur_piutang_tot'=> $retpiut_tot,
            'utd_jml'          => $utd_jml,
            'utd_tot'          => $utd_tot,
            'keluar_medis_jml' => $keluar_jml,
            'keluar_medis_tot' => $keluar_tot,
            'resep_pulang_jml' => $respulang_jml,
            'resep_pulang_tot' => $respulang_tot,
            'mutasi_masuk_jml' => $mutasimasuk_jml,
            'mutasi_masuk_tot' => $mutasimasuk_tot,
            'mutasi_keluar_jml'=> $mutasikeluar_jml,
            'mutasi_keluar_tot'=> $mutasikeluar_tot,
            'hibah_jml'        => $hibah_jml,
            'hibah_tot'        => $hibah_tot
        ];

        $summary['total_item']++;
        $summary['total_stok'] += $stok;
        $summary['total_aset'] += $aset;
        $summary['total_beli_jml'] += $beli_jml;
        $summary['total_beli_tot'] += $beli_tot;
        $summary['total_pesan_jml'] += $pesan_jml;
        $summary['total_pesan_tot'] += $pesan_tot;
        $summary['total_jual_jml'] += $jual_jml;
        $summary['total_jual_tot'] += $jual_tot;
        $summary['total_pasien_jml'] += $pasien_jml;
        $summary['total_pasien_tot'] += $pasien_tot;
        $summary['total_piutang_jml'] += $piutang_jml;
        $summary['total_piutang_tot'] += $piutang_tot;
        $summary['total_retbeli_jml'] += $retbeli_jml;
        $summary['total_retbeli_tot'] += $retbeli_tot;
        $summary['total_retjual_jml'] += $retjual_jml;
        $summary['total_retjual_tot'] += $retjual_tot;
        $summary['total_retpiut_jml'] += $retpiut_jml;
        $summary['total_retpiut_tot'] += $retpiut_tot;
        $summary['total_utd_jml'] += $utd_jml;
        $summary['total_utd_tot'] += $utd_tot;
        $summary['total_keluar_jml'] += $keluar_jml;
        $summary['total_keluar_tot'] += $keluar_tot;
        $summary['total_respulang_jml'] += $respulang_jml;
        $summary['total_respulang_tot'] += $respulang_tot;
        $summary['total_mutasimasuk_jml'] += $mutasimasuk_jml;
        $summary['total_mutasimasuk_tot'] += $mutasimasuk_tot;
        $summary['total_mutasikeluar_jml'] += $mutasikeluar_jml;
        $summary['total_mutasikeluar_tot'] += $mutasikeluar_tot;
        $summary['total_hibah_jml'] += $hibah_jml;
        $summary['total_hibah_tot'] += $hibah_tot;
    }
}

echo json_encode([
    'summary' => $summary,
    'data'    => $data
]);
