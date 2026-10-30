<?php
/**
 * Impor Nilai Massal via CSV - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin', 'kaprodi', 'dosen'));

$user = get_logged_user();
$page_title = 'Impor Nilai Massal (CSV)';
$db = get_db();

$msg_success = '';
$errors = array();
$imported_count = 0;

// Download Template CSV
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=template_nilai_cpl_uinsa.csv');
    $out = fopen('php://output', 'w');
    // Header CSV
    fputcsv($out, array(
        'Kode Mata Kuliah', 'Tahun Akademik', 'Semester', 'Kelas', 'Semester Mahasiswa',
        'CPL-S1', 'CPL-S2', 'CPL-S3',
        'CPL-P1', 'CPL-P2', 'CPL-P3', 'CPL-P4',
        'CPL-KU1', 'CPL-KU2',
        'CPL-KK1', 'CPL-KK2', 'CPL-KK3'
    ));
    // Contoh Baris 1
    fputcsv($out, array('TI301', '2024-2025', 'Ganjil', 'A', 'V', '90', '', '', '88', '', '', '', '92', '', '95', '90', ''));
    // Contoh Baris 2
    fputcsv($out, array('TI202', '2024-2025', 'Ganjil', 'B', 'III', '', '', '', '85', '82', '', '', '88', '', '84', '', ''));
    fclose($out);
    exit;
}

// Proses Upload CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Gagal mengunggah file. Silakan coba lagi.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv' && $ext !== 'txt') {
            $errors[] = 'Format file harus berupa CSV (.csv).';
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                $errors[] = 'Tidak dapat membaca file CSV.';
            } else {
                // Baca header
                $header = fgetcsv($handle, 2000, ',');
                $row_idx = 1;

                // Siapkan lookup matakuliah
                $stmt_mk_all = $db->query("SELECT * FROM matakuliah");
                $mk_by_code = array();
                while ($r = $stmt_mk_all->fetch()) {
                    $mk_by_code[strtoupper(trim($r['code']))] = $r;
                }

                $criteria_columns = array(
                    5 => 'CPL-S1', 6 => 'CPL-S2', 7 => 'CPL-S3',
                    8 => 'CPL-P1', 9 => 'CPL-P2', 10 => 'CPL-P3', 11 => 'CPL-P4',
                    12 => 'CPL-KU1', 13 => 'CPL-KU2',
                    14 => 'CPL-KK1', 15 => 'CPL-KK2', 16 => 'CPL-KK3'
                );

                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    $row_idx++;
                    if (count($data) < 5 || empty($data[0])) {
                        continue; // Lewati baris kosong
                    }

                    $mk_code = strtoupper(trim($data[0]));
                    $tahun = trim($data[1]);
                    $semester = trim($data[2]);
                    $kelas = trim($data[3]);
                    $smt_num = trim($data[4]);

                    if (!isset($mk_by_code[$mk_code])) {
                        $errors[] = "Baris $row_idx: Kode mata kuliah '$mk_code' tidak ditemukan di database.";
                        continue;
                    }

                    $mk = $mk_by_code[$mk_code];

                    // Cek cakupan peran prodi
                    if (($user['role'] === 'kaprodi' || $user['role'] === 'dosen') && $mk['prodi_id'] != $user['prodi_id']) {
                        $errors[] = "Baris $row_idx: Mata kuliah '$mk_code' berada di luar cakupan program studi Anda.";
                        continue;
                    }

                    // Ambil nilai kriteria
                    $scores = array();
                    foreach ($criteria_columns as $col_idx => $crit_code) {
                        if (isset($data[$col_idx]) && $data[$col_idx] !== '' && is_numeric(trim($data[$col_idx]))) {
                            $sc = floatval(trim($data[$col_idx]));
                            if ($sc >= 0 && $sc <= 100) {
                                $scores[$crit_code] = $sc;
                            }
                        }
                    }

                    if (count($scores) === 0) {
                        $errors[] = "Baris $row_idx: Tidak ada nilai kriteria CPL yang valid (harus angka 0 - 100).";
                        continue;
                    }

                    // Kalkulasi nilai terbobot
                    $calc = calculate_cpl_scores($scores);
                    $scores_json = json_encode($scores);
                    $cat_avg_json = json_encode($calc['category_averages']);

                    try {
                        $stmt_ins = $db->prepare("INSERT INTO grades (fakultas_id, prodi_id, mata_kuliah_id, tahun_akademik, semester_type, kelas, semester_number, scores_json, cat_avg_json, total_score, grade, predicate, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt_ins->execute(array($mk['fakultas_id'], $mk['prodi_id'], $mk['id'], $tahun, $semester, $kelas, $smt_num, $scores_json, $cat_avg_json, $calc['total'], $calc['grade'], $calc['predicate']));
                        $imported_count++;
                    } catch (Exception $e) {
                        $errors[] = "Baris $row_idx: Gagal menyimpan data: " . $e->getMessage();
                    }
                }
                fclose($handle);

                if ($imported_count > 0) {
                    log_audit('impor', 'nilai', '', "Impor $imported_count data nilai via CSV");
                    $msg_success = "Berhasil mengimpor $imported_count data nilai CPL!";
                }
            }
        }
    }
}

include dirname(__FILE__) . '/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Impor Nilai Massal (CSV)</h1>
            <p class="text-xs text-slate-500 mt-1">Unggah berkas spreadsheet CSV untuk menginput puluhan nilai CPL sekaligus.</p>
        </div>
        <a href="input-nilai.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
            &larr; Kembali ke Input Nilai
        </a>
    </div>

    <?php if (!empty($msg_success)): ?>
    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800 shadow-sm">
        <?= htmlspecialchars($msg_success) ?>
    </div>
    <?php endif; ?>

    <?php if (count($errors) > 0): ?>
    <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-lg text-xs text-rose-800 shadow-sm space-y-1">
        <div class="font-bold text-sm mb-1">Catatan Kesalahan Saat Impor:</div>
        <?php foreach ($errors as $err): ?>
        <div>&bull; <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Kartu Panduan & Unduh Template -->
    <div class="bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 rounded-xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-sm font-bold text-emerald-950">Langkah 1: Unduh Berkas Template CSV</h3>
                <p class="text-xs text-emerald-800 mt-1 max-w-lg">
                    Gunakan format template resmi yang telah memuat kolom kode mata kuliah, semester, kelas, dan 12 kode kriteria CPL.
                </p>
            </div>
            <a href="import-nilai.php?download_template=1" class="px-4 py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow transition-colors flex items-center gap-2 whitespace-nowrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Unduh Template CSV
            </a>
        </div>
    </div>

    <!-- Form Upload -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Langkah 2: Unggah Berkas CSV Anda</h3>
        
        <form method="POST" action="import-nilai.php" enctype="multipart/form-data" class="space-y-4">
            <div class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:border-emerald-600 transition-colors bg-slate-50">
                <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <label for="csv_file" class="cursor-pointer">
                    <span class="text-sm font-semibold text-uinsa-800 hover:text-uinsa-900 underline">Klik untuk memilih file CSV</span>
                    <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="hidden" onchange="document.getElementById('file-chosen').textContent = this.files[0].name">
                </label>
                <div id="file-chosen" class="text-xs text-slate-500 mt-2 font-medium">Belum ada file dipilih</div>
                <div class="text-[11px] text-slate-400 mt-1">Hanya menerima format berkas .csv (Comma Separated Values)</div>
            </div>

            <button type="submit" class="w-full py-3 px-4 bg-uinsa-800 hover:bg-uinsa-900 text-white font-bold text-xs rounded-lg shadow transition-all">
                Mulai Proses Impor Nilai
            </button>
        </form>
    </div>

</div>

<?php include dirname(__FILE__) . '/footer.php'; ?>
