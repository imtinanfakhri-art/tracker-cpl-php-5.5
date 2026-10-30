<?php
/**
 * Laporan Capaian Pembelajaran Lulusan (CPL) - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_login();

$user = get_logged_user();
$page_title = 'Laporan Capaian CPL';
$db = get_db();

// Tab Laporan (tahun / matakuliah / kriteria)
$tab = isset($_GET['tab']) ? trim($_GET['tab']) : 'tahun';

// Filter
$f_tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$f_mk = isset($_GET['mk']) ? intval($_GET['mk']) : 0;
$f_crit = isset($_GET['kriteria']) ? trim($_GET['kriteria']) : '';

// Dropdowns
$years_list = $db->query("SELECT DISTINCT tahun_akademik FROM grades ORDER BY tahun_akademik DESC")->fetchAll(PDO::FETCH_COLUMN);

$mk_query = "SELECT * FROM matakuliah ";
if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    $mk_query .= "WHERE prodi_id = " . intval($user['prodi_id']) . " ";
}
$mk_query .= "ORDER BY name ASC";
$matakuliah_list = $db->query($mk_query)->fetchAll();

// Bangun query grades sesuai scope & filter
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
$report_grades = $stmt->fetchAll();

// Kalkulasi statistik laporan
$all_totals = array();
$crit_accum = array();
foreach ($GLOBAL_CPL_CRITERIA as $c => $meta) {
    $crit_accum[$c] = array();
}
$cat_accum = array('sikap' => array(), 'pengetahuan' => array(), 'keterampilan_umum' => array(), 'keterampilan_khusus' => array());

foreach ($report_grades as $g) {
    if ($g['total_score'] !== null) {
        $all_totals[] = floatval($g['total_score']);
    }
    $sc = json_decode($g['scores_json'], true);
    if (is_array($sc)) {
        foreach ($sc as $code => $val) {
            if ($val !== null && isset($crit_accum[$code])) {
                $crit_accum[$code][] = floatval($val);
            }
        }
    }
    $ca = json_decode($g['cat_avg_json'], true);
    if (is_array($ca)) {
        foreach ($ca as $cat_k => $val) {
            if ($val !== null && isset($cat_accum[$cat_k])) {
                $cat_accum[$cat_k][] = floatval($val);
            }
        }
    }
}

$overall_avg = count($all_totals) > 0 ? round(array_sum($all_totals) / count($all_totals), 2) : 0;
$overall_pred = get_predicate(count($all_totals) > 0 ? $overall_avg : null);

include dirname(__FILE__) . '/header.php';
?>

<div class="space-y-6">

    <!-- Header Laporan & Tombol Aksi -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Laporan Capaian CPL</h1>
            <p class="text-xs text-slate-500 mt-1">Cetak dokumen capaian kompetensi OBE dan ekspor lembar kerja ke Excel.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="print-laporan.php?tahun=<?= urlencode($f_tahun) ?>&mk=<?= $f_mk ?>" target="_blank" class="px-3.5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Cetak / Simpan PDF
            </a>
            <a href="export-excel.php?tahun=<?= urlencode($f_tahun) ?>&mk=<?= $f_mk ?>" class="px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Unduh Excel
            </a>
        </div>
    </div>

    <!-- Navigation Tabs & Filter Bar -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-4">
        <!-- Filter Form -->
        <form method="GET" action="laporan.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tahun Akademik</label>
                <select name="tahun" onchange="this.form.submit()" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2 text-slate-800 focus:outline-none">
                    <option value="all">Semua Tahun</option>
                    <?php foreach ($years_list as $yr): ?>
                    <option value="<?= htmlspecialchars($yr) ?>" <?= $f_tahun === $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Mata Kuliah</label>
                <select name="mk" onchange="this.form.submit()" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2 text-slate-800 focus:outline-none">
                    <option value="0">Semua Mata Kuliah</option>
                    <?php foreach ($matakuliah_list as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $f_mk === intval($m['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['code'] . ' - ' . $m['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full py-2 bg-uinsa-800 hover:bg-uinsa-900 text-white font-semibold text-xs rounded-lg transition-colors">
                    Terapkan Filter
                </button>
            </div>
        </form>

        <!-- Tab Selector -->
        <div class="flex border-b border-slate-200 text-xs font-semibold">
            <a href="laporan.php?tab=tahun&tahun=<?= urlencode($f_tahun) ?>&mk=<?= $f_mk ?>" class="py-2.5 px-4 border-b-2 transition-colors <?= $tab === 'tahun' ? 'border-uinsa-800 text-uinsa-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                Ringkasan Laporan & Metrik
            </a>
            <a href="laporan.php?tab=tabel&tahun=<?= urlencode($f_tahun) ?>&mk=<?= $f_mk ?>" class="py-2.5 px-4 border-b-2 transition-colors <?= $tab === 'tabel' ? 'border-uinsa-800 text-uinsa-800 font-bold' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
                Tabel Rincian 12 Kriteria CPL
            </a>
        </div>
    </div>

    <!-- Ringkasan Banner KPI Laporan -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500 font-semibold">Rata-rata Terbobot Filtered</div>
            <div class="text-3xl font-extrabold text-slate-900 mt-1"><?= $overall_avg > 0 ? number_format($overall_avg, 2) : '0.00' ?></div>
            <div class="mt-2 text-xs font-bold text-emerald-700">Grade <?= $overall_pred['grade'] ?> &bull; <?= $overall_pred['predicate'] ?></div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500 font-semibold">Total Paket Penilaian Terdata</div>
            <div class="text-3xl font-extrabold text-slate-900 mt-1"><?= count($report_grades) ?></div>
            <div class="mt-2 text-xs text-slate-500">Kelas / Semester / Tahun</div>
        </div>
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
            <div class="text-xs text-slate-500 font-semibold">Capaian Kategori Tertinggi</div>
            <?php 
            $best_cat = '-';
            $best_val = -1;
            foreach ($cat_accum as $ck => $cvals) {
                if (count($cvals) > 0) {
                    $v = round(array_sum($cvals) / count($cvals), 2);
                    if ($v > $best_val) {
                        $best_val = $v;
                        $best_cat = $GLOBAL_CPL_CATEGORIES[$ck]['name'] . " ($v)";
                    }
                }
            }
            ?>
            <div class="text-lg font-bold text-slate-800 mt-1 truncate" title="<?= $best_cat ?>"><?= $best_cat ?></div>
            <div class="mt-2 text-xs text-slate-500">Kategori kompetensi mahasiswa</div>
        </div>
    </div>

    <!-- TABEL UTAMA LAPORAN -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide">
                Rincian Lembar Penilaian Capaian Lulusan
            </h3>
            <span class="text-xs text-slate-500"><?= count($report_grades) ?> baris data</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-3">Mata Kuliah</th>
                        <th class="py-3 px-3">Prodi</th>
                        <th class="py-3 px-2 text-center">Tahun</th>
                        <th class="py-3 px-2 text-center">Smt/Kls</th>
                        <th class="py-3 px-2 text-center text-emerald-800">Sikap (20%)</th>
                        <th class="py-3 px-2 text-center text-amber-700">Penget. (10%)</th>
                        <th class="py-3 px-2 text-center text-teal-800">KU (30%)</th>
                        <th class="py-3 px-2 text-center text-yellow-700">KK (40%)</th>
                        <th class="py-3 px-3 text-center bg-slate-200/60 font-black">Nilai CPL</th>
                        <th class="py-3 px-2 text-center">Grade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (count($report_grades) === 0): ?>
                    <tr>
                        <td colspan="10" class="text-center py-8 text-slate-400">Tidak ada data penilaian untuk kriteria filter ini.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($report_grades as $g): 
                        $ca = json_decode($g['cat_avg_json'], true);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3 px-3 font-semibold text-slate-900">
                            <div><?= htmlspecialchars($g['mk_name']) ?></div>
                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($g['mk_code']) ?></div>
                        </td>
                        <td class="py-3 px-3 text-slate-700 whitespace-nowrap">
                            <?= htmlspecialchars($g['prodi_name']) ?>
                        </td>
                        <td class="py-3 px-2 text-center whitespace-nowrap">
                            <?= htmlspecialchars($g['tahun_akademik']) ?>
                        </td>
                        <td class="py-3 px-2 text-center whitespace-nowrap font-medium">
                            <?= htmlspecialchars($g['semester_type']) ?> &bull; Kls <?= htmlspecialchars($g['kelas']) ?>
                        </td>
                        <td class="py-3 px-2 text-center font-semibold text-emerald-700">
                            <?= isset($ca['sikap']) && $ca['sikap'] !== null ? number_format($ca['sikap'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-2 text-center font-semibold text-amber-700">
                            <?= isset($ca['pengetahuan']) && $ca['pengetahuan'] !== null ? number_format($ca['pengetahuan'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-2 text-center font-semibold text-teal-700">
                            <?= isset($ca['keterampilan_umum']) && $ca['keterampilan_umum'] !== null ? number_format($ca['keterampilan_umum'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-2 text-center font-semibold text-yellow-700">
                            <?= isset($ca['keterampilan_khusus']) && $ca['keterampilan_khusus'] !== null ? number_format($ca['keterampilan_khusus'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-3 text-center font-black text-slate-900 text-sm bg-slate-100/50">
                            <?= $g['total_score'] !== null ? number_format($g['total_score'], 2) : '-' ?>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-block px-2 py-0.5 rounded text-xs font-bold bg-slate-100 text-slate-800">
                                <?= htmlspecialchars($g['grade']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include dirname(__FILE__) . '/footer.php'; ?>
