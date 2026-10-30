<?php
/**
 * Ekspor Data Nilai CPL ke Excel (.csv kompatibel MS Excel / LibreOffice)
 * SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_login();

$user = get_logged_user();
$db = get_db();

$f_tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$f_mk = isset($_GET['mk']) ? intval($_GET['mk']) : 0;

$where_clauses = array();
$params = array();

if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    $where_clauses[] = "g.prodi_id = ?";
    $params[] = $user['prodi_id'];
}
if (!empty($f_tahun) && $f_tahun !== 'all') {
    $where_clauses[] = "g.tahun_akademik = ?";
    $params[] = $f_tahun;
}
if ($f_mk > 0) {
    $where_clauses[] = "g.mata_kuliah_id = ?";
    $params[] = $f_mk;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
$sql = "SELECT g.*, m.name as mk_name, m.code as mk_code, p.name as prodi_name, f.name as fakultas_name 
        FROM grades g 
        LEFT JOIN matakuliah m ON g.mata_kuliah_id = m.id 
        LEFT JOIN prodi p ON g.prodi_id = p.id 
        LEFT JOIN fakultas f ON g.fakultas_id = f.id 
        $where_sql ORDER BY g.tahun_akademik DESC, m.name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$filename = "Laporan_CPL_UINSA_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM agar terbaca sempurna di Microsoft Excel
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// Header Kolom
fputcsv($out, array(
    'No',
    'Fakultas',
    'Program Studi',
    'Kode MK',
    'Nama Mata Kuliah',
    'Tahun Akademik',
    'Semester',
    'Kelas',
    'Semester Tingkat',
    'Sikap (20%)',
    'Pengetahuan (10%)',
    'Keterampilan Umum (30%)',
    'Keterampilan Khusus (40%)',
    'Nilai Akhir CPL',
    'Grade',
    'Predikat',
    'Tanggal Input'
));

$no = 1;
foreach ($rows as $r) {
    $ca = json_decode($r['cat_avg_json'], true);
    fputcsv($out, array(
        $no++,
        $r['fakultas_name'],
        $r['prodi_name'],
        $r['mk_code'],
        $r['mk_name'],
        $r['tahun_akademik'],
        $r['semester_type'],
        $r['kelas'],
        $r['semester_number'],
        isset($ca['sikap']) && $ca['sikap'] !== null ? number_format($ca['sikap'], 2) : '',
        isset($ca['pengetahuan']) && $ca['pengetahuan'] !== null ? number_format($ca['pengetahuan'], 2) : '',
        isset($ca['keterampilan_umum']) && $ca['keterampilan_umum'] !== null ? number_format($ca['keterampilan_umum'], 2) : '',
        isset($ca['keterampilan_khusus']) && $ca['keterampilan_khusus'] !== null ? number_format($ca['keterampilan_khusus'], 2) : '',
        $r['total_score'] !== null ? number_format($r['total_score'], 2) : '',
        $r['grade'],
        $r['predicate'],
        $r['created_at']
    ));
}

fclose($out);
exit;
