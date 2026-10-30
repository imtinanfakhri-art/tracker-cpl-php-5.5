<?php
/**
 * Sistem Informasi Capaian Pembelajaran Lulusan (SI-CPL) UIN Sunan Ampel Surabaya
 * File Konfigurasi Utama & Helper Database (PHP 5.5+ Compatible)
 * 
 * Kompatibel dengan: PHP 5.5, PHP 5.6, PHP 7.x, PHP 8.x
 * Web Server: Apache / Nginx di cPanel
 * Database: MySQL 5.5+ / MariaDB 10.x
 */

// Mulai session jika belum aktif
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------------------------------
// KONFIGURASI DATABASE CPANEL
// Silakan sesuaikan dengan kredensial database MySQL di cPanel Anda
// -----------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'u1234567_cpl_uinsa'); // Ganti dengan nama database cPanel Anda
define('DB_USER', 'u1234567_cpl_user');  // Ganti dengan username database cPanel Anda
define('DB_PASS', 'PasswordDatabaseAnda123!'); // Ganti dengan password database cPanel Anda
define('DB_CHARSET', 'utf8mb4');

// -----------------------------------------------------------------------------
// KONFIGURASI APLIKASI
// -----------------------------------------------------------------------------
define('APP_NAME', 'SI-CPL UINSA');
define('APP_FULLNAME', 'Sistem Informasi Capaian Pembelajaran Lulusan (CPL)');
define('INSTITUSI_NAME', 'UIN Sunan Ampel Surabaya');
define('APP_VERSION', '1.0.0-cpanel-php55');

// Base URL otomatis (mendeteksi subfolder atau root domain di public_html)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$base_url = rtrim($protocol . $host . $script_dir, '/');
define('BASE_URL', $base_url);

// -----------------------------------------------------------------------------
// KONEKSI DATABASE (PDO MySQL)
// -----------------------------------------------------------------------------
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Tampilkan pesan panduan instalasi jika database belum dibuat atau salah koneksi
            die('
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="utf-8">
                <title>Koneksi Database Gagal - SI-CPL UINSA</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding: 40px 20px; }
                    .card { max-width: 650px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
                    h2 { color: #dc2626; margin-top: 0; }
                    pre { background: #f1f5f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
                    .tip { background: #ecfdf5; border-left: 4px solid #059669; padding: 12px 16px; margin: 16px 0; border-radius: 4px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h2>Koneksi ke Database MySQL Belum Berhasil</h2>
                    <p>File <code>config.php</code> belum terhubung ke database cPanel Anda.</p>
                    <pre>Pesan Error: ' . htmlspecialchars($e->getMessage()) . '</pre>
                    <div class="tip">
                        <strong>Langkah Penyelesaian di cPanel:</strong>
                        <ol style="margin-top:8px; padding-left:20px;">
                            <li>Buka <strong>cPanel &rarr; MySQL Databases</strong>.</li>
                            <li>Buat Database dan User baru, lalu berikan <em>All Privileges</em>.</li>
                            <li>Buka <strong>phpMyAdmin</strong> dan impor file <code>database.sql</code>.</li>
                            <li>Buka file <code>config.php</code> di File Manager dan isi <code>DB_NAME</code>, <code>DB_USER</code>, dan <code>DB_PASS</code>.</li>
                        </ol>
                    </div>
                </div>
            </body>
            </html>');
        }
    }
    return $pdo;
}

// -----------------------------------------------------------------------------
// DEFINISI KRITERIA DAN BOBOT CPL UINSA
// -----------------------------------------------------------------------------
$GLOBAL_CPL_CATEGORIES = array(
    'sikap' => array(
        'name' => 'Sikap',
        'weight' => 20,
        'chart_color' => '#059669',
        'codes' => array('CPL-S1', 'CPL-S2', 'CPL-S3')
    ),
    'pengetahuan' => array(
        'name' => 'Pengetahuan',
        'weight' => 10,
        'chart_color' => '#D97706',
        'codes' => array('CPL-P1', 'CPL-P2', 'CPL-P3', 'CPL-P4')
    ),
    'keterampilan_umum' => array(
        'name' => 'Keterampilan Umum',
        'weight' => 30,
        'chart_color' => '#0D9488',
        'codes' => array('CPL-KU1', 'CPL-KU2')
    ),
    'keterampilan_khusus' => array(
        'name' => 'Keterampilan Khusus',
        'weight' => 40,
        'chart_color' => '#EAB308',
        'codes' => array('CPL-KK1', 'CPL-KK2', 'CPL-KK3')
    )
);

$GLOBAL_CPL_CRITERIA = array(
    'CPL-S1'  => array('code' => 'CPL-S1',  'category' => 'sikap', 'label' => 'Bertaqwa kepada Tuhan YME dan menunjukkan sikap religius'),
    'CPL-S2'  => array('code' => 'CPL-S2',  'category' => 'sikap', 'label' => 'Menjunjung tinggi nilai kemanusiaan, etika, dan integritas akademik'),
    'CPL-S3'  => array('code' => 'CPL-S3',  'category' => 'sikap', 'label' => 'Berkontribusi dalam peningkatan mutu kehidupan bermasyarakat'),
    'CPL-P1'  => array('code' => 'CPL-P1',  'category' => 'pengetahuan', 'label' => 'Menguasai konsep teoritis bidang keilmuan secara mendalam'),
    'CPL-P2'  => array('code' => 'CPL-P2',  'category' => 'pengetahuan', 'label' => 'Menguasai metodologi penelitian dan pemikiran logis, kritis'),
    'CPL-P3'  => array('code' => 'CPL-P3',  'category' => 'pengetahuan', 'label' => 'Menguasai standar nasional & internasional pengembangan program'),
    'CPL-P4'  => array('code' => 'CPL-P4',  'category' => 'pengetahuan', 'label' => 'Menguasai prinsip integrasi keilmuan Islam dan sains modern'),
    'CPL-KU1' => array('code' => 'CPL-KU1', 'category' => 'keterampilan_umum', 'label' => 'Mampu menerapkan pemikiran logis, kritis, sistematis, inovatif'),
    'CPL-KU2' => array('code' => 'CPL-KU2', 'category' => 'keterampilan_umum', 'label' => 'Mampu mengambil keputusan tepat dalam penyelesaian masalah'),
    'CPL-KK1' => array('code' => 'CPL-KK1', 'category' => 'keterampilan_khusus', 'label' => 'Mampu merancang & mengimplementasikan karya keahlian prodi'),
    'CPL-KK2' => array('code' => 'CPL-KK2', 'category' => 'keterampilan_khusus', 'label' => 'Mampu evaluasi & supervisi kualitas hasil kerja bidang studi'),
    'CPL-KK3' => array('code' => 'CPL-KK3', 'category' => 'keterampilan_khusus', 'label' => 'Mampu beradaptasi dengan inovasi teknologi & industri global'),
);

// -----------------------------------------------------------------------------
// FUNGSI PERHITUNGAN CPL & PREDIKAT (PHP 5.5 Compatible)
// -----------------------------------------------------------------------------
function get_predicate($score) {
    if ($score === null || $score === '' || !is_numeric($score)) {
        return array('grade' => '-', 'predicate' => '-', 'color' => 'bg-gray-100 text-gray-700 border-gray-300');
    }
    $s = floatval($score);
    if ($s >= 85.0) {
        return array('grade' => 'A', 'predicate' => 'Sangat Memuaskan', 'color' => 'bg-emerald-100 text-emerald-800 border-emerald-300');
    } elseif ($s >= 75.0) {
        return array('grade' => 'B', 'predicate' => 'Memuaskan', 'color' => 'bg-teal-100 text-teal-800 border-teal-300');
    } elseif ($s >= 65.0) {
        return array('grade' => 'C', 'predicate' => 'Cukup', 'color' => 'bg-amber-100 text-amber-800 border-amber-300');
    } elseif ($s >= 50.0) {
        return array('grade' => 'D', 'predicate' => 'Kurang', 'color' => 'bg-orange-100 text-orange-800 border-orange-300');
    } else {
        return array('grade' => 'E', 'predicate' => 'Gagal', 'color' => 'bg-rose-100 text-rose-800 border-rose-300');
    }
}

function calculate_cpl_scores($scores_array) {
    global $GLOBAL_CPL_CATEGORIES;
    $category_averages = array();
    $present_weight = 0.0;
    $weighted_sum = 0.0;

    foreach ($GLOBAL_CPL_CATEGORIES as $cat_key => $cat_meta) {
        $vals = array();
        foreach ($cat_meta['codes'] as $code) {
            if (isset($scores_array[$code]) && $scores_array[$code] !== '' && $scores_array[$code] !== null) {
                $val = floatval($scores_array[$code]);
                if ($val >= 0 && $val <= 100) {
                    $vals[] = $val;
                }
            }
        }

        if (count($vals) > 0) {
            $avg = round(array_sum($vals) / count($vals), 2);
            $category_averages[$cat_key] = $avg;
            $present_weight += $cat_meta['weight'];
            $weighted_sum += ($avg * $cat_meta['weight']);
        } else {
            $category_averages[$cat_key] = null;
        }
    }

    $total = ($present_weight > 0) ? round($weighted_sum / $present_weight, 2) : null;
    $pred = get_predicate($total);

    return array(
        'category_averages' => $category_averages,
        'total' => $total,
        'grade' => $pred['grade'],
        'predicate' => $pred['predicate']
    );
}

// -----------------------------------------------------------------------------
// HELPER OTENTIKASI & PERAN (RBAC)
// -----------------------------------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_user() {
    if (!is_logged_in()) {
        return null;
    }
    return array(
        'id'          => $_SESSION['user_id'],
        'email'       => $_SESSION['user_email'],
        'name'        => $_SESSION['user_name'],
        'role'        => $_SESSION['user_role'],
        'fakultas_id' => isset($_SESSION['user_fakultas_id']) ? $_SESSION['user_fakultas_id'] : null,
        'prodi_id'    => isset($_SESSION['user_prodi_id']) ? $_SESSION['user_prodi_id'] : null,
    );
}

function require_login() {
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

function require_role($allowed_roles) {
    require_login();
    $user = get_logged_user();
    if (!in_array($user['role'], $allowed_roles)) {
        http_response_code(403);
        die('
        <div style="font-family:sans-serif; text-align:center; padding:50px;">
            <h1 style="color:#b91c1c;">403 - Akses Ditolak</h1>
            <p>Anda tidak memiliki hak akses untuk membuka halaman ini.</p>
            <p><a href="dashboard.php" style="color:#059669; font-weight:bold;">&larr; Kembali ke Dashboard</a></p>
        </div>');
    }
}

// -----------------------------------------------------------------------------
// AUDIT LOG HELPER
// -----------------------------------------------------------------------------
function log_audit($action, $entity, $entity_id = '', $summary = '') {
    try {
        $db = get_db();
        $user = get_logged_user();
        $actor_id = $user ? $user['id'] : null;
        $actor_email = $user ? $user['email'] : 'sistem';
        $actor_role = $user ? $user['role'] : '-';

        $stmt = $db->prepare("INSERT INTO audit_logs (actor_id, actor_email, actor_role, action, entity, entity_id, summary, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute(array($actor_id, $actor_email, $actor_role, $action, $entity, strval($entity_id), $summary));
    } catch (Exception $e) {
        // Abaikan error audit log agar proses utama tidak terhenti
    }
}

// -----------------------------------------------------------------------------
// HELPER SANITASI INPUT
// -----------------------------------------------------------------------------
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
