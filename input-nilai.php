<?php
/**
 * Input & Kelola Nilai CPL - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin', 'kaprodi', 'dosen'));

$user = get_logged_user();
$page_title = 'Input Nilai Kriteria CPL';
$db = get_db();
$msg_success = '';
$msg_error = '';

// Data dropdown referensi
$TAHUN_LIST = array('2020-2021', '2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026', '2026-2027');
$SEMESTER_TYPES = array('Ganjil', 'Genap');
$KELAS_LIST = array('A', 'B', 'C', 'D');
$SEMESTER_NUMBERS = array('I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII');

// Ambil list Fakultas & Prodi
$fakultas_list = $db->query("SELECT * FROM fakultas ORDER BY name ASC")->fetchAll();
$prodi_list = $db->query("SELECT * FROM prodi ORDER BY name ASC")->fetchAll();

// Scoped Matakuliah
if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    $stmt_mk = $db->prepare("SELECT * FROM matakuliah WHERE prodi_id = ? ORDER BY name ASC");
    $stmt_mk->execute(array($user['prodi_id']));
} else {
    $stmt_mk = $db->query("SELECT * FROM matakuliah ORDER BY name ASC");
}
$matakuliah_list = $stmt_mk->fetchAll();

// Index data matakuliah untuk lookup JavaScript
$mk_indexed = array();
foreach ($matakuliah_list as $m) {
    $cpls = json_decode($m['cpl_codes'], true);
    $mk_indexed[$m['id']] = array(
        'id' => $m['id'],
        'name' => $m['name'],
        'code' => $m['code'],
        'fakultas_id' => $m['fakultas_id'],
        'prodi_id' => $m['prodi_id'],
        'cpl_codes' => is_array($cpls) ? $cpls : array()
    );
}

// -----------------------------------------------------------------------------
// PROSES SIMPAN / UPDATE NILAI
// -----------------------------------------------------------------------------
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_data = null;

if ($edit_id > 0) {
    $stmt_edit = $db->prepare("SELECT * FROM grades WHERE id = ?");
    $stmt_edit->execute(array($edit_id));
    $edit_data = $stmt_edit->fetch();
    if ($edit_data) {
        $edit_data['scores'] = json_decode($edit_data['scores_json'], true);
    }
}

// Hapus Nilai
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    try {
        $stmt_del_check = $db->prepare("SELECT g.*, m.name as mk_name FROM grades g LEFT JOIN matakuliah m ON g.mata_kuliah_id = m.id WHERE g.id = ?");
        $stmt_del_check->execute(array($del_id));
        $target = $stmt_del_check->fetch();

        if ($target) {
            // Cek batasan peran prodi
            if (($user['role'] === 'kaprodi' || $user['role'] === 'dosen') && $target['prodi_id'] != $user['prodi_id']) {
                $msg_error = 'Anda tidak memiliki hak untuk menghapus data nilai prodi lain.';
            } else {
                $stmt_del = $db->prepare("DELETE FROM grades WHERE id = ?");
                $stmt_del->execute(array($del_id));
                log_audit('hapus', 'nilai', $del_id, 'Hapus nilai ' . $target['mk_name'] . ' Kelas ' . $target['kelas']);
                $msg_success = 'Data nilai berhasil dihapus.';
            }
        }
    } catch (Exception $e) {
        $msg_error = 'Gagal menghapus data: ' . $e->getMessage();
    }
}

// Simpan Form Nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save'])) {
    $post_edit_id = isset($_POST['grade_id']) ? intval($_POST['grade_id']) : 0;
    $fakultas_id = intval($_POST['fakultas_id']);
    $prodi_id = intval($_POST['prodi_id']);
    $mk_id = intval($_POST['mata_kuliah_id']);
    $tahun_akademik = trim($_POST['tahun_akademik']);
    $semester_type = trim($_POST['semester_type']);
    $kelas = trim($_POST['kelas']);
    $semester_number = trim($_POST['semester_number']);
    
    // Cek otorisasi scope prodi
    if (($user['role'] === 'kaprodi' || $user['role'] === 'dosen') && $prodi_id != $user['prodi_id']) {
        $msg_error = 'Pilihan Program Studi di luar cakupan wewenang akun Anda.';
    } elseif ($mk_id <= 0 || empty($tahun_akademik)) {
        $msg_error = 'Lengkapi pilihan Fakultas, Program Studi, Mata Kuliah, dan Tahun Akademik.';
    } else {
        // Ambil nilai kriteria yang diinput
        $raw_scores = isset($_POST['scores']) && is_array($_POST['scores']) ? $_POST['scores'] : array();
        $clean_scores = array();

        foreach ($raw_scores as $code => $val) {
            if ($val !== '' && $val !== null && is_numeric($val)) {
                $num = floatval($val);
                if ($num >= 0 && $num <= 100) {
                    $clean_scores[$code] = $num;
                }
            }
        }

        if (count($clean_scores) === 0) {
            $msg_error = 'Silakan masukkan minimal satu nilai kriteria CPL (skala 0 - 100).';
        } else {
            // Hitung nilai terbobot & predikat menggunakan helper PHP 5.5
            $calc = calculate_cpl_scores($clean_scores);
            $scores_json = json_encode($clean_scores);
            $cat_avg_json = json_encode($calc['category_averages']);

            try {
                if ($post_edit_id > 0) {
                    $stmt_u = $db->prepare("UPDATE grades SET fakultas_id = ?, prodi_id = ?, mata_kuliah_id = ?, tahun_akademik = ?, semester_type = ?, kelas = ?, semester_number = ?, scores_json = ?, cat_avg_json = ?, total_score = ?, grade = ?, predicate = ?, updated_at = NOW() WHERE id = ?");
                    $stmt_u->execute(array($fakultas_id, $prodi_id, $mk_id, $tahun_akademik, $semester_type, $kelas, $semester_number, $scores_json, $cat_avg_json, $calc['total'], $calc['grade'], $calc['predicate'], $post_edit_id));
                    log_audit('ubah', 'nilai', $post_edit_id, "Update nilai MK ID $mk_id total: " . $calc['total']);
                    $msg_success = 'Data nilai CPL berhasil diperbarui!';
                    $edit_id = 0;
                    $edit_data = null;
                } else {
                    $stmt_i = $db->prepare("INSERT INTO grades (fakultas_id, prodi_id, mata_kuliah_id, tahun_akademik, semester_type, kelas, semester_number, scores_json, cat_avg_json, total_score, grade, predicate, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_i->execute(array($fakultas_id, $prodi_id, $mk_id, $tahun_akademik, $semester_type, $kelas, $semester_number, $scores_json, $cat_avg_json, $calc['total'], $calc['grade'], $calc['predicate']));
                    $new_id = $db->lastInsertId();
                    log_audit('buat', 'nilai', $new_id, "Input nilai baru MK ID $mk_id total: " . $calc['total']);
                    $msg_success = 'Data nilai CPL berhasil disimpan!';
                }
            } catch (Exception $e) {
                $msg_error = 'Gagal menyimpan nilai: ' . $e->getMessage();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// DAFTAR DATA NILAI TERSIMPAN (FILTER & PENCARIAN)
// -----------------------------------------------------------------------------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$f_tahun = isset($_GET['f_tahun']) ? trim($_GET['f_tahun']) : 'all';
$f_kelas = isset($_GET['f_kelas']) ? trim($_GET['f_kelas']) : 'all';

$tbl_where = array();
$tbl_params = array();

if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    $tbl_where[] = "g.prodi_id = ?";
    $tbl_params[] = $user['prodi_id'];
}
if (!empty($f_tahun) && $f_tahun !== 'all') {
    $tbl_where[] = "g.tahun_akademik = ?";
    $tbl_params[] = $f_tahun;
}
if (!empty($f_kelas) && $f_kelas !== 'all') {
    $tbl_where[] = "g.kelas = ?";
    $tbl_params[] = $f_kelas;
}
if (!empty($search)) {
    $tbl_where[] = "(m.name LIKE ? OR m.code LIKE ? OR p.name LIKE ?)";
    $like_term = "%$search%";
    $tbl_params[] = $like_term;
    $tbl_params[] = $like_term;
    $tbl_params[] = $like_term;
}

$tbl_where_sql = count($tbl_where) > 0 ? "WHERE " . implode(" AND ", $tbl_where) : "";
$sql_list = "SELECT g.*, m.name as mk_name, m.code as mk_code, p.name as prodi_name, f.name as fakultas_name 
             FROM grades g 
             LEFT JOIN matakuliah m ON g.mata_kuliah_id = m.id 
             LEFT JOIN prodi p ON g.prodi_id = p.id 
             LEFT JOIN fakultas f ON g.fakultas_id = f.id 
             $tbl_where_sql ORDER BY g.created_at DESC";
$stmt_list = $db->prepare($sql_list);
$stmt_list->execute($tbl_params);
$stored_grades = $stmt_list->fetchAll();

include dirname(__FILE__) . '/header.php';
?>

<div class="space-y-6">

    <!-- Notifikasi Sukses / Error -->
    <?php if (!empty($msg_success)): ?>
    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800 shadow-sm flex items-center justify-between">
        <span><?= htmlspecialchars($msg_success) ?></span>
        <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-900">&times;</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
    <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-lg text-sm text-rose-800 shadow-sm flex items-center justify-between">
        <span><?= htmlspecialchars($msg_error) ?></span>
        <button onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-900">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Input Nilai Capaian CPL</h1>
            <p class="text-xs text-slate-500 mt-1">Formulir penilaian ketercapaian kriteria CPL dengan kalkulator terbobot otomatis.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="import-nilai.php" class="px-3 py-2 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 shadow-sm flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Impor Nilai CSV
            </a>
            <a href="export-excel.php" class="px-3 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Ekspor Data Nilai
            </a>
        </div>
    </div>

    <!-- FORM INPUT NILAI & LIVE CALCULATOR -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                <?= $edit_data ? 'Edit Data Nilai CPL' : 'Form Input Nilai Baru' ?>
            </h2>
            <?php if ($edit_data): ?>
            <a href="input-nilai.php" class="text-xs text-rose-600 hover:text-rose-800 font-semibold">&times; Batalkan Edit</a>
            <?php endif; ?>
        </div>

        <form method="POST" action="input-nilai.php" class="p-6 space-y-6" id="form-cpl">
            <input type="hidden" name="action_save" value="1">
            <input type="hidden" name="grade_id" value="<?= $edit_data ? $edit_data['id'] : 0 ?>">

            <!-- Dropdown Akademik & Kelas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Fakultas -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Fakultas *</label>
                    <select name="fakultas_id" id="sel_fakultas" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach ($fakultas_list as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($edit_data && $edit_data['fakultas_id'] == $f['id']) || ($user['role'] !== 'admin' && $user['fakultas_id'] == $f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Program Studi -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Program Studi *</label>
                    <select name="prodi_id" id="sel_prodi" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Pilih Program Studi --</option>
                        <?php foreach ($prodi_list as $p): ?>
                        <option value="<?= $p['id'] ?>" data-fakultas="<?= $p['fakultas_id'] ?>" <?= ($edit_data && $edit_data['prodi_id'] == $p['id']) || ($user['role'] !== 'admin' && $user['prodi_id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Mata Kuliah -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mata Kuliah *</label>
                    <select name="mata_kuliah_id" id="sel_matakuliah" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Pilih Mata Kuliah --</option>
                        <?php foreach ($matakuliah_list as $m): ?>
                        <option value="<?= $m['id'] ?>" data-prodi="<?= $m['prodi_id'] ?>" <?= ($edit_data && $edit_data['mata_kuliah_id'] == $m['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['code'] . ' - ' . $m['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tahun Akademik -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tahun Akademik *</label>
                    <select name="tahun_akademik" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <?php foreach ($TAHUN_LIST as $yr): ?>
                        <option value="<?= $yr ?>" <?= ($edit_data && $edit_data['tahun_akademik'] == $yr) || (!$edit_data && $yr === '2024-2025') ? 'selected' : '' ?>>
                            <?= $yr ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semester Ganjil / Genap -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tipe Semester *</label>
                    <select name="semester_type" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <?php foreach ($SEMESTER_TYPES as $st): ?>
                        <option value="<?= $st ?>" <?= ($edit_data && $edit_data['semester_type'] == $st) ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kelas A - D -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kelas *</label>
                    <select name="kelas" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <?php foreach ($KELAS_LIST as $kl): ?>
                        <option value="<?= $kl ?>" <?= ($edit_data && $edit_data['kelas'] == $kl) ? 'selected' : '' ?>>Kelas <?= $kl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Semester Mahasiswa I - VIII -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Semester Tingkat *</label>
                    <select name="semester_number" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <?php foreach ($SEMESTER_NUMBERS as $sn): ?>
                        <option value="<?= $sn ?>" <?= ($edit_data && $edit_data['semester_number'] == $sn) ? 'selected' : '' ?>>Semester <?= $sn ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- LIVE CALCULATOR BANNER (Auto-update saat mengetik nilai) -->
            <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-slate-100 p-5 rounded-xl border border-emerald-200">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold text-emerald-900 uppercase tracking-wider">Pratinjau Ketercapaian Terbobot (Live Calculation)</div>
                        <div class="flex items-baseline space-x-3 mt-1">
                            <span id="preview-total" class="text-3xl font-extrabold text-emerald-950">
                                <?= $edit_data && $edit_data['total_score'] !== null ? number_format($edit_data['total_score'], 2) : '0.00' ?>
                            </span>
                            <span class="text-sm text-emerald-700">/ 100</span>
                            <span id="preview-badge" class="px-3 py-1 rounded-full text-xs font-bold border bg-emerald-100 text-emerald-800 border-emerald-300">
                                <?= $edit_data ? 'Grade ' . $edit_data['grade'] . ' &bull; ' . $edit_data['predicate'] : 'Grade - &bull; -' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Category Breakdown Chips -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center text-xs">
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200 shadow-sm">
                            <div class="text-[10px] text-slate-500 font-semibold">Sikap (20%)</div>
                            <div id="preview-sikap" class="text-sm font-bold text-emerald-700 mt-0.5">-</div>
                        </div>
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200 shadow-sm">
                            <div class="text-[10px] text-slate-500 font-semibold">Pengetahuan (10%)</div>
                            <div id="preview-pengetahuan" class="text-sm font-bold text-amber-600 mt-0.5">-</div>
                        </div>
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200 shadow-sm">
                            <div class="text-[10px] text-slate-500 font-semibold">KU (30%)</div>
                            <div id="preview-ku" class="text-sm font-bold text-teal-700 mt-0.5">-</div>
                        </div>
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200 shadow-sm">
                            <div class="text-[10px] text-slate-500 font-semibold">KK (40%)</div>
                            <div id="preview-kk" class="text-sm font-bold text-yellow-600 mt-0.5">-</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INPUT FIELD 12 KRITERIA CPL -->
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-slate-800">Nilai Per Kriteria CPL (Skala 0 - 100)</h3>
                    <span class="text-xs text-slate-400">Kosongkan jika kriteria tidak dibebankan pada mata kuliah ini</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php foreach ($GLOBAL_CPL_CATEGORIES as $cat_key => $cat_meta): ?>
                    <div class="bg-slate-50/70 p-4 rounded-xl border border-slate-200 space-y-3">
                        <div class="pb-2 border-b border-slate-200">
                            <span class="text-xs font-bold text-slate-800 block"><?= htmlspecialchars($cat_meta['name']) ?></span>
                            <span class="text-[10px] text-slate-500 font-medium">Bobot Kategori: <?= $cat_meta['weight'] ?>%</span>
                        </div>

                        <?php foreach ($cat_meta['codes'] as $code): 
                            $crit = $GLOBAL_CPL_CRITERIA[$code];
                            $val = ($edit_data && isset($edit_data['scores'][$code])) ? $edit_data['scores'][$code] : '';
                        ?>
                        <div class="cpl-input-row" data-code="<?= $code ?>" data-cat="<?= $cat_key ?>">
                            <div class="flex items-center justify-between mb-1">
                                <label for="inp_<?= $code ?>" class="text-[11px] font-bold text-slate-700 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full" style="background-color: <?= $cat_meta['chart_color'] ?>"></span>
                                    <?= $code ?>
                                </label>
                                <span class="text-[10px] text-slate-400 truncate max-w-[140px]" title="<?= htmlspecialchars($crit['label']) ?>">
                                    <?= htmlspecialchars($crit['label']) ?>
                                </span>
                            </div>
                            <input type="number" step="0.01" min="0" max="100" id="inp_<?= $code ?>" name="scores[<?= $code ?>]" value="<?= htmlspecialchars($val) ?>"
                                placeholder="0 - 100"
                                class="cpl-score-input w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-medium text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="pt-4 border-t border-slate-200 flex items-center justify-end space-x-3">
                <button type="reset" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition-colors">
                    Reset Form
                </button>
                <button type="submit" class="px-6 py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    <?= $edit_data ? 'Perbarui Data Nilai' : 'Simpan Data Nilai' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- TABEL DATA NILAI TERSIMPAN -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <!-- Filter Bar -->
        <div class="p-4 sm:p-5 bg-slate-50/70 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Data Nilai CPL Tersimpan</h3>
                <p class="text-xs text-slate-500">Ditemukan <?= count($stored_grades) ?> data</p>
            </div>

            <!-- Search & Filters -->
            <form method="GET" action="input-nilai.php" class="flex flex-wrap items-center gap-2">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari mata kuliah..." class="text-xs bg-white border border-slate-300 rounded-lg px-3 py-2 w-44 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                
                <select name="f_tahun" onchange="this.form.submit()" class="text-xs bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-slate-700 focus:outline-none">
                    <option value="all">Semua Tahun</option>
                    <?php foreach ($TAHUN_LIST as $yr): ?>
                    <option value="<?= $yr ?>" <?= $f_tahun === $yr ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="f_kelas" onchange="this.form.submit()" class="text-xs bg-white border border-slate-300 rounded-lg px-2.5 py-2 text-slate-700 focus:outline-none">
                    <option value="all">Semua Kelas</option>
                    <?php foreach ($KELAS_LIST as $kl): ?>
                    <option value="<?= $kl ?>" <?= $f_kelas === $kl ? 'selected' : '' ?>>Kelas <?= $kl ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="px-3 py-2 bg-uinsa-800 text-white rounded-lg text-xs font-semibold hover:bg-uinsa-900">
                    Filter
                </button>
            </form>
        </div>

        <!-- Tabel Responsive -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Mata Kuliah</th>
                        <th class="py-3 px-4">Program Studi</th>
                        <th class="py-3 px-4">Tahun / Smt</th>
                        <th class="py-3 px-4">Kelas</th>
                        <th class="py-3 px-4 text-center">Nilai CPL</th>
                        <th class="py-3 px-4 text-center">Grade</th>
                        <th class="py-3 px-4 text-center">Predikat</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (count($stored_grades) === 0): ?>
                    <tr>
                        <td colspan="8" class="text-center py-8 text-slate-400">Belum ada data nilai tersimpan sesuai filter.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($stored_grades as $g): 
                        $pred = get_predicate($g['total_score']);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3.5 px-4 font-semibold text-slate-900">
                            <div><?= htmlspecialchars($g['mk_name']) ?></div>
                            <div class="text-[10px] text-slate-400"><?= htmlspecialchars($g['mk_code']) ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-block px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-medium text-[11px] border border-emerald-200">
                                <?= htmlspecialchars($g['prodi_name']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap">
                            <?= htmlspecialchars($g['tahun_akademik']) ?> &bull; <?= htmlspecialchars($g['semester_type']) ?>
                        </td>
                        <td class="py-3.5 px-4 font-semibold text-slate-800">
                            Kelas <?= htmlspecialchars($g['kelas']) ?> (Smt <?= htmlspecialchars($g['semester_number']) ?>)
                        </td>
                        <td class="py-3.5 px-4 text-center font-bold text-slate-900 text-sm">
                            <?= $g['total_score'] !== null ? number_format($g['total_score'], 2) : '-' ?>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <span class="inline-block px-2.5 py-0.5 rounded-full font-bold text-xs border <?= $pred['color'] ?>">
                                <?= htmlspecialchars($g['grade']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-center text-xs font-medium text-slate-600">
                            <?= htmlspecialchars($g['predicate']) ?>
                        </td>
                        <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                            <a href="input-nilai.php?edit=<?= $g['id'] ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors inline-block">
                                Edit
                            </a>
                            <a href="input-nilai.php?delete=<?= $g['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data nilai ini?');" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded text-xs font-semibold transition-colors inline-block">
                                Hapus
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- JAVASCRIPT: DEPENDENT DROPDOWNS & LIVE CALCULATOR -->
<script>
    // Data Kategori & Bobot CPL
    var CPL_WEIGHTS = {
        'sikap': 20,
        'pengetahuan': 10,
        'keterampilan_umum': 30,
        'keterampilan_khusus': 40
    };

    var CODES_BY_CAT = {
        'sikap': ['CPL-S1', 'CPL-S2', 'CPL-S3'],
        'pengetahuan': ['CPL-P1', 'CPL-P2', 'CPL-P3', 'CPL-P4'],
        'keterampilan_umum': ['CPL-KU1', 'CPL-KU2'],
        'keterampilan_khusus': ['CPL-KK1', 'CPL-KK2', 'CPL-KK3']
    };

    var MK_DATA = <?= json_encode($mk_indexed) ?>;

    // Filter Prodi saat Fakultas diubah
    document.getElementById('sel_fakultas').addEventListener('change', function() {
        var fid = this.value;
        var prodiSelect = document.getElementById('sel_prodi');
        var prodiOptions = prodiSelect.querySelectorAll('option');

        prodiOptions.forEach(function(opt) {
            if (!opt.value) return;
            var f = opt.getAttribute('data-fakultas');
            if (!fid || f === fid) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
    });

    // Highlight CPL Criteria yang relevan dengan MK terpilih
    document.getElementById('sel_matakuliah').addEventListener('change', function() {
        var mkId = this.value;
        var mk = MK_DATA[mkId];
        var rows = document.querySelectorAll('.cpl-input-row');

        if (!mk || !mk.cpl_codes || mk.cpl_codes.length === 0) {
            rows.forEach(function(r) { r.classList.remove('opacity-40'); });
            return;
        }

        rows.forEach(function(r) {
            var code = r.getAttribute('data-code');
            if (mk.cpl_codes.indexOf(code) !== -1) {
                r.classList.remove('opacity-40');
                r.querySelector('input').setAttribute('placeholder', 'Wajib diisi');
            } else {
                r.classList.add('opacity-40');
                r.querySelector('input').setAttribute('placeholder', 'Opsional');
            }
        });
    });

    // Live Calculation
    function calculateLive() {
        var catAverages = {};
        var presentWeight = 0;
        var weightedSum = 0;

        for (var cat in CODES_BY_CAT) {
            var codes = CODES_BY_CAT[cat];
            var vals = [];

            for (var i = 0; i < codes.length; i++) {
                var inp = document.getElementById('inp_' + codes[i]);
                if (inp && inp.value !== '' && !isNaN(inp.value)) {
                    var n = parseFloat(inp.value);
                    if (n >= 0 && n <= 100) {
                        vals.push(n);
                    }
                }
            }

            if (vals.length > 0) {
                var sum = vals.reduce(function(a, b) { return a + b; }, 0);
                var avg = Math.round((sum / vals.length) * 100) / 100;
                catAverages[cat] = avg;
                var w = CPL_WEIGHTS[cat];
                presentWeight += w;
                weightedSum += (avg * w);
            } else {
                catAverages[cat] = null;
            }
        }

        var total = presentWeight > 0 ? Math.round((weightedSum / presentWeight) * 100) / 100 : null;

        // Update UI
        document.getElementById('preview-total').textContent = total !== null ? total.toFixed(2) : '0.00';
        document.getElementById('preview-sikap').textContent = catAverages.sikap !== null ? catAverages.sikap.toFixed(2) : '-';
        document.getElementById('preview-pengetahuan').textContent = catAverages.pengetahuan !== null ? catAverages.pengetahuan.toFixed(2) : '-';
        document.getElementById('preview-ku').textContent = catAverages.keterampilan_umum !== null ? catAverages.keterampilan_umum.toFixed(2) : '-';
        document.getElementById('preview-kk').textContent = catAverages.keterampilan_khusus !== null ? catAverages.keterampilan_khusus.toFixed(2) : '-';

        // Predicate
        var grade = '-';
        var pred = '-';
        var badgeColor = 'bg-gray-100 text-gray-800 border-gray-300';

        if (total !== null) {
            if (total >= 85) { grade = 'A'; pred = 'Sangat Memuaskan'; badgeColor = 'bg-emerald-100 text-emerald-800 border-emerald-300'; }
            else if (total >= 75) { grade = 'B'; pred = 'Memuaskan'; badgeColor = 'bg-teal-100 text-teal-800 border-teal-300'; }
            else if (total >= 65) { grade = 'C'; pred = 'Cukup'; badgeColor = 'bg-amber-100 text-amber-800 border-amber-300'; }
            else if (total >= 50) { grade = 'D'; pred = 'Kurang'; badgeColor = 'bg-orange-100 text-orange-800 border-orange-300'; }
            else { grade = 'E'; pred = 'Gagal'; badgeColor = 'bg-rose-100 text-rose-800 border-rose-300'; }
        }

        var badge = document.getElementById('preview-badge');
        badge.textContent = 'Grade ' + grade + ' \u2022 ' + pred;
        badge.className = 'px-3 py-1 rounded-full text-xs font-bold border ' + badgeColor;
    }

    // Pasang listener pada semua input CPL
    document.querySelectorAll('.cpl-score-input').forEach(function(inp) {
        inp.addEventListener('input', calculateLive);
    });

    // Jalankan kalkulasi awal jika form edit aktif
    calculateLive();
</script>

<?php include dirname(__FILE__) . '/footer.php'; ?>
