<?php
session_start();

// --- Pengaturan Koneksi Database ---
$dbHost = '192.168.196.37';
$dbName = 'sik_master';
$dbUser = 'client';
$dbPass = 'epotoransu';

// --- Placeholder fungsi validasi dari file referensi ---
if (!function_exists('validTeks4')) {
    function validTeks4($text, $length) {
        // Fungsi ini mensanitasi dan membatasi panjang teks input
        return substr(htmlspecialchars(strip_tags($text)), 0, $length);
    }
}

// --- Proses Logout ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: " . basename(__FILE__)); // Kembali ke halaman ini sendiri untuk menampilkan form login
    exit;
}

// --- Variabel Global ---
$namaInstansi = 'Pencarian Rujukan Operasi';
$favicon = '';
$logoSrc = '';
$loginError = '';

// --- Cek Status Login ---
$isLoggedIn = isset($_SESSION["ses_admin_login"]) && $_SESSION["ses_admin_login"] === true;

// --- Proses Login Jika Form Disubmit dan User Belum Login ---
if (!$isLoggedIn && isset($_POST['BtnLogin'])) {
    $connLogin = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if (!$connLogin->connect_error) {
        $usere = $connLogin->real_escape_string(validTeks4($_POST['usere'], 30));
        $passworde = $connLogin->real_escape_string(validTeks4($_POST['passworde'], 30));

        // Cek kredensial di tabel admin
        $sqlAdmin = "SELECT COUNT(*) as total FROM admin WHERE usere=aes_encrypt(?,'nur') AND passworde=aes_encrypt(?,'windi')";
        $stmtAdmin = $connLogin->prepare($sqlAdmin);
        $stmtAdmin->bind_param('ss', $usere, $passworde);
        $stmtAdmin->execute();
        $resultAdmin = $stmtAdmin->get_result()->fetch_assoc();
        $stmtAdmin->close();

        // Cek kredensial di tabel user
        $sqlUser = "SELECT COUNT(*) as total FROM user WHERE id_user=aes_encrypt(?,'nur') AND password=aes_encrypt(?,'windi')";
        $stmtUser = $connLogin->prepare($sqlUser);
        $stmtUser->bind_param('ss', $usere, $passworde);
        $stmtUser->execute();
        $resultUser = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();

        if ($resultAdmin['total'] > 0 || $resultUser['total'] > 0) {
            $_SESSION["ses_admin_login"] = true;
            header("Location: " . basename(__FILE__)); // Redirect untuk me-refresh halaman setelah login berhasil
            exit;
        } else {
            $loginError = "Username atau Password salah!";
        }
        $connLogin->close();
    } else {
        $loginError = "Koneksi ke database login gagal.";
    }
}

// --- Inisialisasi variabel untuk halaman data ---
$opData = [];
$error = '';

// --- Jika sudah login, lanjutkan dengan logika aplikasi pencarian ---
if ($isLoggedIn) {
    // Tentukan Filter Pencarian dari GET request atau gunakan nilai default
    $tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
    $tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-t');
    $nama_perawatan = $_GET['nama_perawatan'] ?? 'phaco';

    // Ambil Data Instansi (Logo, Nama, dll)
    try {
        $connSik = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if (!$connSik->connect_error) {
            $sqlSik = "SELECT nama_instansi FROM setting LIMIT 1";
            $resultSik = $connSik->query($sqlSik);
            if ($resultSik && $resultSik->num_rows > 0) {
                $row = $resultSik->fetch_assoc();
                $namaInstansiHeader = htmlspecialchars($row['nama_instansi']);
            }
            // Use cached logo link instead of Base64 embedding
            $logoSrc = "core/logo.php";
            $favicon = $logoSrc;
            $connSik->close();
        }
    } catch (Exception $e) {
        error_log("Error saat mengambil data instansi: " . $e->getMessage());
    }

    // Fungsi untuk mengambil data operasi dengan filter
    function getOperasiData($conn, $tgl_awal, $tgl_akhir, $perawatan) {
        $data = [];
        $sql = "SELECT reg_periksa.tgl_registrasi, reg_periksa.no_rawat, pasien.nm_pasien, 
                       rujuk_masuk.perujuk, rujuk_masuk.dokter_perujuk, paket_operasi.nm_perawatan
                FROM reg_periksa
                JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                JOIN rujuk_masuk ON reg_periksa.no_rawat = rujuk_masuk.no_rawat
                JOIN operasi ON reg_periksa.no_rawat = operasi.no_rawat
                JOIN paket_operasi ON operasi.kode_paket = paket_operasi.kode_paket
                WHERE reg_periksa.tgl_registrasi BETWEEN ? AND ?
                AND paket_operasi.nm_perawatan LIKE ?
                ORDER BY reg_periksa.tgl_registrasi ASC";
        
        $stmt = $conn->prepare($sql);
        $searchTerm = '%' . $perawatan . '%';
        $stmt->bind_param('sss', $tgl_awal, $tgl_akhir, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        return $data;
    }

    // Proses Pengambilan Data & Ekspor
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        $error = "Koneksi database gagal untuk mencari data operasi.";
    } else {
        // Proses ekspor jika diminta
        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            $opDataExport = getOperasiData($conn, $tanggal_awal, $tanggal_akhir, $nama_perawatan);
            
            header("Content-Type: application/vnd.ms-excel");
            $filename = "laporan_rujukan_operasi_" . $nama_perawatan . "_" . $tanggal_awal . "_sd_" . $tanggal_akhir . ".xls";
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            echo "Tgl Registrasi\tNo. Rawat\tNama Pasien\tPerujuk\tDokter Perujuk\tNama Perawatan\n";
            if (!empty($opDataExport)) {
                foreach ($opDataExport as $data) {
                    echo htmlspecialchars($data['tgl_registrasi']) . "\t" . 
                         htmlspecialchars($data['no_rawat']) . "\t" .
                         htmlspecialchars($data['nm_pasien']) . "\t" . 
                         htmlspecialchars($data['perujuk']) . "\t" .
                         htmlspecialchars($data['dokter_perujuk']) . "\t" .
                         htmlspecialchars($data['nm_perawatan']) . "\n";
                }
            }
            $conn->close();
            exit;
        }
        
        // Ambil data untuk ditampilkan di tabel HTML
        $opData = getOperasiData($conn, $tanggal_awal, $tanggal_akhir, $nama_perawatan);
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $namaInstansi; ?></title>
    <?php if ($favicon): ?>
        <link rel="icon" href="<?php echo $favicon; ?>" type="image/x-icon">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .table-container { max-height: 60vh; overflow-y: auto; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen py-12">
    <?php if ($isLoggedIn): ?>
    <!-- ====================================================== -->
    <!-- TAMPILAN UTAMA JIKA SUDAH LOGIN (HALAMAN PENCARIAN DATA) -->
    <!-- ====================================================== -->
    <div class="w-full max-w-7xl mx-auto bg-white p-8 rounded-xl shadow-lg relative">
        <div class="flex justify-between items-start mb-6">
            <div class="flex items-center space-x-4">
                <?php if ($logoSrc): ?>
                    <img src="<?php echo $logoSrc; ?>" alt="Logo Instansi" class="h-12 w-12 object-contain">
                <?php endif; ?>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo isset($namaInstansiHeader) ? $namaInstansiHeader : $namaInstansi; ?></h1>
                    <p class="text-gray-500"><?php echo $namaInstansi; ?></p>
                </div>
            </div>
            <a href="?action=logout" class="text-sm text-gray-500 hover:text-indigo-600 font-medium">Logout</a>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div>
            <!-- Form Pencarian -->
            <form action="" method="GET" class="bg-gray-50 p-4 rounded-lg mb-6 flex items-end space-x-4 flex-wrap">
                <div>
                    <label for="tanggal_awal" class="block text-sm font-medium text-gray-700">Tanggal Awal</label>
                    <input type="date" id="tanggal_awal" name="tanggal_awal" value="<?php echo htmlspecialchars($tanggal_awal); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="tanggal_akhir" class="block text-sm font-medium text-gray-700">Tanggal Akhir</label>
                    <input type="date" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo htmlspecialchars($tanggal_akhir); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label for="nama_perawatan" class="block text-sm font-medium text-gray-700">Nama Paket Operasi</label>
                    <input type="text" id="nama_perawatan" name="nama_perawatan" value="<?php echo htmlspecialchars($nama_perawatan); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Contoh: phaco">
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Cari Data
                </button>
            </form>

            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">
                    Hasil Pencarian: <?php echo date('d M Y', strtotime($tanggal_awal)) . ' - ' . date('d M Y', strtotime($tanggal_akhir)); ?>
                </h2>
                <a href="?action=export&tanggal_awal=<?php echo $tanggal_awal; ?>&tanggal_akhir=<?php echo $tanggal_akhir; ?>&nama_perawatan=<?php echo urlencode($nama_perawatan); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                    <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Export ke Excel
                </a>
            </div>
            <div class="border rounded-lg shadow table-container">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl Registrasi</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Rawat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Pasien</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Perujuk</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dokter Perujuk</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Perawatan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($opData)): ?>
                            <?php foreach ($opData as $data): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($data['tgl_registrasi']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($data['no_rawat']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($data['nm_pasien']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($data['perujuk']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($data['dokter_perujuk']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($data['nm_perawatan']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Tidak ada data operasi yang ditemukan untuk filter yang dipilih.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- ================================== -->
    <!-- TAMPILAN FORM LOGIN JIKA BELUM LOGIN -->
    <!-- ================================== -->
    <div class="w-full max-w-md mx-auto bg-white p-8 rounded-xl shadow-lg">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-800"><?php echo $namaInstansi; ?></h1>
            <p class="text-gray-500">Silakan login menggunakan akun Khanza Anda.</p>
        </div>

        <?php if ($loginError): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6 text-center" role="alert">
                <span><?php echo htmlspecialchars($loginError); ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="post" class="space-y-6">
            <div>
                <label for="usere" class="block text-sm font-medium text-gray-700">Username</label>
                <div class="mt-1">
                    <input id="usere" name="usere" type="text" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" autofocus>
                </div>
            </div>
            <div>
                <label for="passworde" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="mt-1">
                    <input id="passworde" name="passworde" type="password" required class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
            </div>
            <div>
                <button type="submit" name="BtnLogin" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Log In
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
</body>
</html>
