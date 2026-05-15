<?php
session_start();
require_once 'config/koneksi.php';

// Proteksi Khusus Super Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Super Admin') {
    $_SESSION['error'] = "Anda tidak memiliki akses ke halaman ini.";
    header('Location: index.php');
    exit;
}

$page_title = "Pengaturan Sidebar";
$sidebar_json_file = 'config/sidebar_menu.json';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sidebar'])) {
    
    // Baca current JSON untuk mempertahankan struktur
    $current_json = [];
    if (file_exists($sidebar_json_file)) {
        $current_json = json_decode(file_get_contents($sidebar_json_file), true);
    }

    if (!empty($current_json)) {
        // Cek data POST toggle group
        $posted_groups = isset($_POST['group_active']) ? $_POST['group_active'] : [];
        // Cek data POST toggle sub-item
        $posted_items = isset($_POST['item_active']) ? $_POST['item_active'] : [];

        // Looping untuk apply changes ke array
        foreach ($current_json as $index => $group) {
            $group_id = $group['id'];
            
            // Set group active status
            $current_json[$index]['is_active'] = isset($posted_groups[$group_id]) ? true : false;
            
            // Set items active status
            if (isset($group['items']) && is_array($group['items'])) {
                foreach ($group['items'] as $item_index => $item) {
                    // Karena URL unik per item, kita pakai sebagai key (md5 hash untuk amannya jika url ada spacenya dsb)
                    $item_key = md5($item['url']);
                    $current_json[$index]['items'][$item_index]['is_active'] = isset($posted_items[$item_key]) ? true : false;
                }
            }
        }

        // Tulis kembali ke file JSON
        $new_json_data = json_encode($current_json, JSON_PRETTY_PRINT);
        if (file_put_contents($sidebar_json_file, $new_json_data)) {
            $_SESSION['success_msg'] = "Pengaturan sidebar berhasil diperbarui.";
        } else {
            $_SESSION['error_msg'] = "Gagal menyimpan file pengaturan JSON. Pastikan file permissions / chmod mendukung proses write.";
        }
        
        // Redirect agar refresh
        header('Location: setting_sidebar.php');
        exit;
    }
}

// Baca Data untuk View
$sidebar_menus = [];
if (file_exists($sidebar_json_file)) {
    $sidebar_menus = json_decode(file_get_contents($sidebar_json_file), true);
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-sliders-h me-2"></i> Pengaturan Menu Sidebar</h1>
</div>

<?php if(isset($_SESSION['success_msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if(isset($_SESSION['error_msg'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Manajemen Tampilan Sidebar</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="checkAll(true)">Pilih Semua</button>
            </div>
            <div class="card-body">
                <p class="text-muted small">Catatan: Menu ini hanya terlihat dan dapat diubah oleh <strong>Super Admin</strong>. Jika sebuah grup dimatikan, semua sub-menu di dalamnya juga akan tersembunyi berapapun status sub-menu tersebut.</p>
                
                <form action="setting_sidebar.php" method="POST">
                    <input type="hidden" name="update_sidebar" value="1">
                    
                    <div class="accordion" id="accordionSidebarSettings">
                        <?php 
                        if (!empty($sidebar_menus)) {
                            foreach ($sidebar_menus as $menu) {
                                $group_id = $menu['id'];
                                $group_title = $menu['title'];
                                $group_active = isset($menu['is_active']) ? $menu['is_active'] : true;
                                ?>
                                <div class="accordion-item mb-2 border">
                                    <h2 class="accordion-header" id="heading_<?php echo $group_id; ?>">
                                        <div class="d-flex align-items-center justify-content-between w-100 pe-3 bg-light">
                                            <button class="accordion-button collapsed fw-bold shadow-none bg-transparent" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_<?php echo $group_id; ?>" style="flex: 1;">
                                                <?php echo htmlspecialchars($group_title); ?>
                                            </button>
                                            <div class="form-check form-switch fs-5 mb-0 ms-2" title="Toggle Grup">
                                                <input class="form-check-input group-toggle" type="checkbox" name="group_active[<?php echo $group_id; ?>]" value="1" <?php echo $group_active ? 'checked' : ''; ?> id="switch_grp_<?php echo $group_id; ?>">
                                            </div>
                                        </div>
                                    </h2>
                                    <div id="collapse_<?php echo $group_id; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionSidebarSettings">
                                        <div class="accordion-body p-0 border-top">
                                            <ul class="list-group list-group-flush">
                                                <?php 
                                                if (isset($menu['items']) && is_array($menu['items'])) {
                                                    foreach ($menu['items'] as $item) {
                                                        $item_url = $item['url'];
                                                        $item_title = $item['title'];
                                                        $item_icon = $item['icon'];
                                                        $item_active = isset($item['is_active']) ? $item['is_active'] : true;
                                                        $item_key = md5($item_url);
                                                        ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center ps-4">
                                                            <div>
                                                                <i class="<?php echo $item_icon; ?> text-muted me-2" style="width: 20px;"></i>
                                                                <?php echo htmlspecialchars($item_title); ?>
                                                                <small class="text-muted ms-2">(<?php echo htmlspecialchars($item_url); ?>)</small>
                                                            </div>
                                                            <div class="form-check form-switch mb-0">
                                                                <input class="form-check-input item-toggle" type="checkbox" name="item_active[<?php echo $item_key; ?>]" value="1" <?php echo $item_active ? 'checked' : ''; ?> data-group="<?php echo $group_id; ?>" id="switch_item_<?php echo $item_key; ?>">
                                                            </div>
                                                        </li>
                                                        <?php
                                                    }
                                                } else {
                                                    ?>
                                                    <li class="list-group-item text-muted text-center py-3">Tidak ada sub-menu</li>
                                                    <?php
                                                }
                                                ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="alert alert-warning">Data menu tidak ditemukan. (File JSON kosong/tidak valid)</div>';
                        }
                        ?>
                    </div>
                
                    <div class="mt-4 form-group">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Simpan Pengaturan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // JS Logic untuk Checkbox behavior
    document.addEventListener('DOMContentLoaded', function() {
        // Jika parent di-uncheck, uncheck juga semua childnya.
        // Jika parent di check, tidak memaksa semua childnya check.
        // Jika child dicheck, pastikan parentnya juga ikut dicheck otomatis.

        const groupToggles = document.querySelectorAll('.group-toggle');
        const itemToggles = document.querySelectorAll('.item-toggle');

        groupToggles.forEach(toggle => {
            toggle.addEventListener('change', function(e) {
                // Prevent event bubbling ke accordion click
                e.stopPropagation();

                const isChecked = this.checked;
                const groupId = this.id.replace('switch_grp_', '');
                
                if (!isChecked) {
                    // Jika group dimatikan, matikan semua sub-item
                    const childItems = document.querySelectorAll(`.item-toggle[data-group="${groupId}"]`);
                    childItems.forEach(child => {
                        child.checked = false;
                    });
                }
            });
            // Stop propagation on click to avoid accordion expansion/collapse on switch click
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        itemToggles.forEach(toggle => {
            toggle.addEventListener('change', function() {
                const isChecked = this.checked;
                const groupId = this.getAttribute('data-group');
                const parentGroupToggle = document.getElementById(`switch_grp_${groupId}`);
                
                if (isChecked && parentGroupToggle && !parentGroupToggle.checked) {
                    // Jika sub-item dihidupkan, hidupkan juga parent-nya otomatis
                    parentGroupToggle.checked = true;
                }
            });
        });
    });

    function checkAll(status) {
        document.querySelectorAll('.form-check-input').forEach(el => el.checked = status);
    }
</script>

<?php include 'includes/footer.php'; ?>
