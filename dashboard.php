<?php
/**
 * Dashboard SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_login();

$user = get_logged_user();
$page_title = 'Dashboard Capaian CPL';
$db = get_db();

// Filter Tahun Akademik
$selected_tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';

// Bangun query grades dengan scoping peran
$where_clauses = array();
$params = array();

if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    if (!empty($user['prodi_id'])) {
        $where_clauses[] = "g.prodi_id = ?";
        $params[] = $user['prodi_id'];
    } elseif (!empty($user['fakultas_id'])) {
        $where_clauses[] = "g.fakultas_id = ?";
        $params[] = $user['fakultas_id'];
    }
}

if (!empty($selected_tahun) && $selected_tahun !== 'all') {
    $where_clauses[] = "g.tahun_akademik = ?";
    $params[] = $selected_tahun;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Ambil list tahun akademik yang tersedia untuk filter
$stmt_years = $db->query("SELECT DISTINCT tahun_akademik FROM grades ORDER BY tahun_akademik DESC");
$available_years = $stmt_years->fetchAll(PDO::FETCH_COLUMN);

// Hitung counts
$count_fakultas = $db->query("SELECT COUNT(*) FROM fakultas")->fetchColumn();
$count_prodi = $db->query("SELECT COUNT(*) FROM prodi")->fetchColumn();

// MK count scoped
if ($user['role'] === 'kaprodi' || $user['role'] === 'dosen') {
    $stmt_mk = $db->prepare("SELECT COUNT(*) FROM matakuliah WHERE prodi_id = ?");
    $stmt_mk->execute(array($user['prodi_id']));
    $count_mk = $stmt_mk->fetchColumn();
} else {
    $count_mk = $db->query("SELECT COUNT(*) FROM matakuliah")->fetchColumn();
}

// Ambil data nilai
$sql_grades = "SELECT g.*, m.name as mk_name, m.code as mk_code, p.name as prodi_name, f.name as fakultas_name 
               FROM grades g 
               LEFT JOIN matakuliah m ON g.mata_kuliah_id = m.id 
               LEFT JOIN prodi p ON g.prodi_id = p.id 
               LEFT JOIN fakultas f ON g.fakultas_id = f.id 
               $where_sql ORDER BY g.created_at DESC";
$stmt_grades = $db->prepare($sql_grades);
$stmt_grades->execute($params);
$grades_list = $stmt_grades->fetchAll();
$total_grades_count = count($grades_list);

// Kalkulasi Statistik CPL
$cat_sums = array('sikap' => array(), 'pengetahuan' => array(), 'keterampilan_umum' => array(), 'keterampilan_khusus' => array());
$crit_sums = array();
foreach ($GLOBAL_CPL_CRITERIA as $code => $c) {
    $crit_sums[$code] = array();
}
$grade_dist = array('A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0);
$all_totals = array();

foreach ($grades_list as $g) {
    $scores = json_decode($g['scores_json'], true);
    $cat_avg = json_decode($g['cat_avg_json'], true);

    if (is_array($cat_avg)) {
        foreach ($cat_avg as $k => $v) {
            if ($v !== null && isset($cat_sums[$k])) {
                $cat_sums[$k][] = floatval($v);
            }
        }
    }

    if (is_array($scores)) {
        foreach ($scores as $code => $val) {
            if ($val !== null && isset($crit_sums[$code])) {
                $crit_sums[$code][] = floatval($val);
            }
        }
    }

    if (isset($g['grade']) && isset($grade_dist[$g['grade']])) {
        $grade_dist[$g['grade']]++;
    }

    if ($g['total_score'] !== null) {
        $all_totals[] = floatval($g['total_score']);
    }
}

// Rata-rata keseluruhan
$overall_average = count($all_totals) > 0 ? round(array_sum($all_totals) / count($all_totals), 2) : 0.0;
$overall_pred = get_predicate(count($all_totals) > 0 ? $overall_average : null);

// Info scope pengguna
$scope_info = '';
if ($user['role'] !== 'admin' && !empty($user['prodi_id'])) {
    $stmt_p = $db->prepare("SELECT p.name as prodi_name, f.name as fakultas_name FROM prodi p JOIN fakultas f ON p.fakultas_id = f.id WHERE p.id = ?");
    $stmt_p->execute(array($user['prodi_id']));
    $p_info = $stmt_p->fetch();
    if ($p_info) {
        $scope_info = htmlspecialchars($p_info['prodi_name']) . ' &bull; ' . htmlspecialchars($p_info['fakultas_name']);
    }
}

include dirname(__FILE__) . '/header.php';
?>

<div class="space-y-6">

    <!-- Header & Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
                <span>Dashboard Pencapaian CPL</span>
                <span class="text-xs bg-emerald-100 text-emerald-800 font-semibold px-2.5 py-0.5 rounded-full border border-emerald-300">
                    Kurikulum OBE
                </span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                Ringkasan pengukuran ketercapaian 12 kriteria CPL UIN Sunan Ampel Surabaya terbobot (20/10/30/40).
            </p>
            <?php if (!empty($scope_info)): ?>
            <div class="mt-2 text-xs text-uinsa-800 font-medium bg-emerald-50 px-2.5 py-1 rounded inline-flex items-center gap-1.5 border border-emerald-200">
                <svg class="w-3.5 h-3.5 text-emerald-700" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12z"/></svg>
                Cakupan Data: <?= $scope_info ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter Tahun Akademik -->
        <form method="GET" action="dashboard.php" class="flex items-center gap-2">
            <label for="tahun" class="text-xs font-medium text-slate-600 whitespace-nowrap">Tahun Akademik:</label>
            <select name="tahun" id="tahun" onchange="this.form.submit()" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-3 py-2 text-slate-700 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                <option value="all">Semua Tahun</option>
                <?php foreach ($available_years as $yr): ?>
                <option value="<?= htmlspecialchars($yr) ?>" <?= $selected_tahun === $yr ? 'selected' : '' ?>>
                    <?= htmlspecialchars($yr) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Banner Rata-Rata Terbobot Keseluruhan -->
    <div class="bg-gradient-to-r from-uinsa-800 via-emerald-800 to-teal-900 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-1">
                <span class="text-xs font-semibold text-emerald-200 uppercase tracking-widest bg-emerald-950/50 px-3 py-1 rounded-full border border-emerald-500/30 inline-block">
                    Kinerja Capaian Pembelajaran Lulusan (CPL)
                </span>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight mt-2">
                    <?= $overall_average > 0 ? number_format($overall_average, 2) : '0.00' ?> <span class="text-lg font-normal text-emerald-200">/ 100</span>
                </h2>
                <p class="text-sm text-emerald-100 max-w-xl">
                    Nilai rata-rata terbobot dihitung berdasarkan renormalisasi 4 kategori: Sikap (20%), Pengetahuan (10%), Keterampilan Umum (30%), dan Keterampilan Khusus (40%).
                </p>
            </div>

            <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-xl p-4 sm:px-6 sm:py-5 text-center flex-shrink-0">
                <div class="text-xs text-emerald-200 uppercase font-medium">Predikat Kelulusan</div>
                <div class="text-3xl font-black text-amber-400 mt-1"><?= htmlspecialchars($overall_pred['grade']) ?></div>
                <div class="text-sm font-semibold text-white mt-0.5"><?= htmlspecialchars($overall_pred['predicate']) ?></div>
                <div class="text-[11px] text-emerald-200 mt-2 pt-2 border-t border-white/10">
                    Berdasarkan <?= $total_grades_count ?> paket nilai terdata
                </div>
            </div>
        </div>
    </div>

    <!-- 4 Metrik Kartu Utama (KPI) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-xl flex-shrink-0">
                🏛️
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Fakultas</div>
                <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $count_fakultas ?></div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center font-bold text-xl flex-shrink-0">
                🎓
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Program Studi</div>
                <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $count_prodi ?></div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-xl flex-shrink-0">
                📖
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Mata Kuliah</div>
                <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $count_mk ?></div>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center space-x-4">
            <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-xl flex-shrink-0">
                📝
            </div>
            <div>
                <div class="text-xs text-slate-500 font-medium">Nilai Terinput</div>
                <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $total_grades_count ?></div>
            </div>
        </div>
    </div>

    <!-- 4 Kategori CPL & Bobotnya -->
    <div>
        <h3 class="text-base font-bold text-slate-800 mb-3">Capaian per Kategori CPL</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($GLOBAL_CPL_CATEGORIES as $key => $meta): 
                $vals = $cat_sums[$key];
                $avg = count($vals) > 0 ? round(array_sum($vals) / count($vals), 2) : 0;
                $pct = min(100, $avg);
                $cpred = get_predicate(count($vals) > 0 ? $avg : null);
            ?>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold text-slate-700"><?= htmlspecialchars($meta['name']) ?></span>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                            Bobot: <?= $meta['weight'] ?>%
                        </span>
                    </div>
                    <div class="text-2xl font-extrabold text-slate-900 mt-2">
                        <?= count($vals) > 0 ? number_format($avg, 2) : '-' ?>
                    </div>
                    <!-- Progress Bar -->
                    <div class="w-full bg-slate-100 rounded-full h-2.5 mt-3 overflow-hidden">
                        <div class="h-2.5 rounded-full transition-all duration-500" style="width: <?= $pct ?>%; background-color: <?= $meta['chart_color'] ?>;"></div>
                    </div>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500"><?= count($vals) ?> sampel</span>
                    <span class="font-semibold <?= strpos($cpred['color'], 'emerald') !== false ? 'text-emerald-700' : 'text-slate-700' ?>">
                        Grade <?= $cpred['grade'] ?> (<?= $cpred['predicate'] ?>)
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Dua Kolom: 12 Kriteria CPL & Distribusi Nilai -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- 12 Kriteria CPL -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-800">Capaian 12 Kriteria CPL</h3>
                <span class="text-xs text-slate-400">Skala 0 - 100</span>
            </div>

            <div class="space-y-4">
                <?php foreach ($GLOBAL_CPL_CRITERIA as $code => $crit): 
                    $cvals = $crit_sums[$code];
                    $cavg = count($cvals) > 0 ? round(array_sum($cvals) / count($cvals), 2) : 0;
                    $cat_color = $GLOBAL_CPL_CATEGORIES[$crit['category']]['chart_color'];
                    $cpred = get_predicate(count($cvals) > 0 ? $cavg : null);
                ?>
                <div>
                    <div class="flex items-center justify-between text-xs mb-1">
                        <div class="flex items-center space-x-2">
                            <span class="font-bold px-1.5 py-0.5 rounded text-white text-[10px]" style="background-color: <?= $cat_color ?>;">
                                <?= $code ?>
                            </span>
                            <span class="text-slate-700 font-medium truncate max-w-xs sm:max-w-md" title="<?= htmlspecialchars($crit['label']) ?>">
                                <?= htmlspecialchars($crit['label']) ?>
                            </span>
                        </div>
                        <div class="text-right font-bold text-slate-800 whitespace-nowrap pl-2">
                            <?= count($cvals) > 0 ? number_format($cavg, 2) : '-' ?>
                            <span class="text-[10px] font-normal text-slate-400 ml-1">(<?= $cpred['grade'] ?>)</span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="h-2 rounded-full" style="width: <?= min(100, $cavg) ?>%; background-color: <?= $cat_color ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Distribusi Predikat & Akses Cepat -->
        <div class="space-y-6">
            <!-- Distribusi Grade -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h3 class="text-base font-bold text-slate-800 mb-4">Distribusi Predikat Kelulusan</h3>
                <div class="space-y-3">
                    <?php 
                    $pred_labels = array(
                        'A' => array('label' => 'Sangat Memuaskan (85 - 100)', 'color' => 'bg-emerald-500', 'text' => 'text-emerald-800', 'bg' => 'bg-emerald-50'),
                        'B' => array('label' => 'Memuaskan (75 - 84.9)', 'color' => 'bg-teal-500', 'text' => 'text-teal-800', 'bg' => 'bg-teal-50'),
                        'C' => array('label' => 'Cukup (65 - 74.9)', 'color' => 'bg-amber-500', 'text' => 'text-amber-800', 'bg' => 'bg-amber-50'),
                        'D' => array('label' => 'Kurang (50 - 64.9)', 'color' => 'bg-orange-500', 'text' => 'text-orange-800', 'bg' => 'bg-orange-50'),
                        'E' => array('label' => 'Gagal (< 50)', 'color' => 'bg-rose-500', 'text' => 'text-rose-800', 'bg' => 'bg-rose-50'),
                    );
                    foreach ($pred_labels as $gr => $meta): 
                        $cnt = $grade_dist[$gr];
                        $gr_pct = $total_grades_count > 0 ? round(($cnt / $total_grades_count) * 100, 1) : 0;
                    ?>
                    <div class="p-3 rounded-lg <?= $meta['bg'] ?> border border-slate-200/60">
                        <div class="flex items-center justify-between text-xs font-semibold mb-1">
                            <span class="<?= $meta['text'] ?>">Grade <?= $gr ?> &bull; <?= $meta['label'] ?></span>
                            <span class="font-bold text-slate-800"><?= $cnt ?> (<?= $gr_pct ?>%)</span>
                        </div>
                        <div class="w-full bg-white/80 rounded-full h-1.5 overflow-hidden">
                            <div class="h-1.5 rounded-full <?= $meta['color'] ?>" style="width: <?= $gr_pct ?>%;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Kartu Aksi Cepat -->
            <div class="bg-gradient-to-br from-slate-900 to-uinsa-900 text-white rounded-xl p-6 shadow-md">
                <h4 class="text-sm font-bold text-emerald-300 uppercase tracking-wider mb-2">Aksi Cepat</h4>
                <p class="text-xs text-slate-300 mb-4">Mulai input nilai CPL kelas atau cetak laporan hasil capaian:</p>
                <div class="space-y-2">
                    <a href="input-nilai.php" class="block w-full py-2.5 px-3 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg text-center transition-colors shadow">
                        + Input Nilai CPL Baru
                    </a>
                    <a href="laporan.php" class="block w-full py-2.5 px-3 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-lg text-center transition-colors border border-white/20">
                        Lihat & Cetak Laporan
                    </a>
                    <a href="import-nilai.php" class="block w-full py-2.5 px-3 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-lg text-center transition-colors border border-white/20">
                        Impor Nilai Massal (CSV)
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include dirname(__FILE__) . '/footer.php'; ?>
