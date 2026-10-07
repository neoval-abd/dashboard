<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/includes/kepegawaian.php';
// Simulate permission rows without a live server; exercise the actual access helper.
$db = new class {
    public $permission = null;
    public $queries = 0;
    public function prepare($sql) {
        if (strpos($sql, 'SELECT pegawai_admin FROM user') !== 0) throw new RuntimeException('Unexpected query.');
        $this->queries++;
        return new class($this) {
            private $db;
            public function __construct($db) { $this->db = $db; }
            public function bind_param($types, ...$values) { }
            public function execute() { return true; }
            public function close() { }
            public function get_result() {
                return new class($this->db->permission) {
                    private $permission;
                    public function __construct($permission) { $this->permission = $permission; }
                    public function fetch_all($mode) { return $this->permission === null ? [] : [['pegawai_admin' => $this->permission]]; }
                };
            }
        };
    }
};
$_SESSION = ['user_id' => 'test-user', 'role' => 'Manajemen'];
foreach (['true' => true, 'false' => false, '' => false] as $permission => $expected) {
    $db->permission = $permission;
    if (kp_can_access($db) !== $expected || kp_can_write($db) !== $expected) throw new RuntimeException('Permission mismatch.');
}
$db->permission = null;
if (kp_can_access($db)) throw new RuntimeException('Missing user allowed.');
$db->permission = 'true';
if (!kp_can_access($db)) throw new RuntimeException('Granted user denied.');
$db->permission = 'false';
if (kp_can_access($db)) throw new RuntimeException('Revoked permission still allowed.');
$_SESSION['role'] = 'Super Admin';
$queries = $db->queries;
if (!kp_can_access($db) || $db->queries !== $queries) throw new RuntimeException('Super Admin access failed.');
unset($_SESSION['user_id']);
if (kp_can_access($db)) throw new RuntimeException('Anonymous user allowed.');
$menus = json_decode(file_get_contents(dirname(__DIR__) . '/config/sidebar_menu.json'), true);
$visible = kp_filter_sidebar($menus, false);
if (strpos(json_encode($visible), 'kepegawaian.php') !== false) throw new RuntimeException('Restricted menu visible.');
if (strpos(json_encode($visible), 'laporan_absensi.php') === false) throw new RuntimeException('Absensi hidden.');
if (kp_filter_sidebar($menus, true) !== $menus) throw new RuntimeException('Authorized menus changed.');
if (kp_filter_sidebar([['url' => '/dashboardassalam/kepegawaian.php?tab=index']], false)) throw new RuntimeException('Direct menu visible.');
if (kp_filter_sidebar([['is_group' => true, 'items' => [['url' => 'kepegawaian.php']]]], false)) throw new RuntimeException('Empty group visible.');
echo "PASS: permission grant/revoke, missing user, Super Admin, anonymous, sidebar filtering\n";
