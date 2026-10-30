<?php
/**
 * Kelola Mata Kuliah - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin', 'kaprodi'));

$user = get_logged_user();
$page_title = 'Kelola Mata Kuliah';
$db = get_db();
$msg_success = '';
$msg_error = '';

$fakultas_list = $db->query("SELECT * FROM fakultas ORDER BY name ASC")->fetchAll();
$prodi_list = $db->query("SELECT * FROM prodi ORDER BY name ASC")->fetchAll();

// Scoped Matakuliah Query
$where_mk = array();
$params_mk = array();
if ($user['role'] === 'kaprodi' && !empty($user['prodi_id'])) {
    $where_mk[] = "m.prodi_id = ?";
    $params_mk[] = $user['prodi_id'];
}
$where_mk_sql = count($where_mk) > 0 ? "WHERE " . implode(" AND ", $where_mk) : "";

$sql_mk = "SELECT m.*, p.name as prodi_name, f.name as fakultas_name 
           FROM matakuliah m 
           LEFT JOIN prodi p ON m.prodi_id = p.id 
           LEFT JOIN fakultas f ON m.fakultas_id = f.id 
           $where_mk_sql ORDER BY f.name ASC, p.name ASC, m.name ASC";
$stmt_mk = $db->prepare($sql_mk);
$stmt_mk->execute($params_mk);
$matakuliah_items = $stmt_mk->fetchAll();

// Hapus Mata Kuliah
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    try {
        $stmt_chk = $db->prepare("SELECT * FROM matakuliah WHERE id = ?");
        $stmt_chk->execute(array($del_id));
        $target = $stmt_chk->fetch();

        if ($target) {
            if ($user['role'] === 'kaprodi' && $target['prodi_id'] != $user['prodi_id']) {
                $msg_error = 'Anda tidak memiliki hak untuk menghapus mata kuliah prodi lain.';
            } else {
                $stmt_del = $db->prepare("DELETE FROM matakuliah WHERE id = ?");
                $stmt_del->execute(array($del_id));
                log_audit('hapus', 'matakuliah', $del_id, 'Hapus MK: ' . $target['name'] . ' (' . $target['code'] . ')');
                $msg_success = 'Mata kuliah berhasil dihapus.';
                header("Location: mata-kuliah.php");
                exit;
            }
        }
    } catch (Exception $e) {
        $msg_error = 'Gagal menghapus: ' . $e->getMessage();
    }
}

// Simpan / Update Mata Kuliah
$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_item = null;
if ($edit_id > 0) {
    $stmt_e = $db->prepare("SELECT * FROM matakuliah WHERE id = ?");
    $stmt_e->execute(array($edit_id));
    $edit_item = $stmt_e->fetch();
    if ($edit_item) {
        $edit_item['cpls'] = json_decode($edit_item['cpl_codes'], true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_mk'])) {
    $mid = intval($_POST['mid']);
    $name = trim($_POST['name']);
    $code = strtoupper(trim($_POST['code']));
    $sks = intval($_POST['sks']);
    $fakultas_id = intval($_POST['fakultas_id']);
    $prodi_id = intval($_POST['prodi_id']);
    $cpls = isset($_POST['cpl_codes']) && is_array($_POST['cpl_codes']) ? $_POST['cpl_codes'] : array();

    if ($user['role'] === 'kaprodi' && $prodi_id != $user['prodi_id']) {
        $msg_error = 'Anda hanya dapat mengelola mata kuliah untuk Program Studi Anda.';
    } elseif (empty($name) || empty($code) || $fakultas_id <= 0 || $prodi_id <= 0) {
        $msg_error = 'Lengkapi Nama Mata Kuliah, Kode, SKS, Fakultas, dan Program Studi.';
    } elseif (count($cpls) === 0) {
        $msg_error = 'Pilih minimal satu kriteria CPL yang dibebankan pada mata kuliah ini.';
    } else {
        $cpl_json = json_encode(array_values($cpls));
        try {
            if ($mid > 0) {
                $stmt_u = $db->prepare("UPDATE matakuliah SET name = ?, code = ?, sks = ?, fakultas_id = ?, prodi_id = ?, cpl_codes = ? WHERE id = ?");
                $stmt_u->execute(array($name, $code, $sks, $fakultas_id, $prodi_id, $cpl_json, $mid));
                log_audit('ubah', 'matakuliah', $mid, "Update MK: $name ($code)");
                $msg_success = 'Mata kuliah berhasil diperbarui!';
            } else {
                $stmt_i = $db->prepare("INSERT INTO matakuliah (name, code, sks, fakultas_id, prodi_id, cpl_codes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt_i->execute(array($name, $code, $sks, $fakultas_id, $prodi_id, $cpl_json));
                $new_id = $db->lastInsertId();
                log_audit('buat', 'matakuliah', $new_id, "Tambah MK baru: $name ($code)");
                $msg_success = 'Mata kuliah berhasil ditambahkan!';
            }
            $edit_id = 0;
            $edit_item = null;
            // Muat ulang daftar
            $stmt_mk->execute($params_mk);
            $matakuliah_items = $stmt_mk->fetchAll();
        } catch (Exception $e) {
            $msg_error = 'Gagal menyimpan mata kuliah: ' . $e->getMessage();
        }
    }
}

// Kelompokkan data berdasarkan Fakultas & Prodi
$grouped = array();
foreach ($matakuliah_items as $m) {
    $key = $m['fakultas_name'] . ' &bull; ' . $m['prodi_name'];
    if (!isset($grouped[$key])) {
        $grouped[$key] = array();
    }
    $grouped[$key][] = $m;
}

include dirname(__FILE__) . '/header.php';
?>

<div class="space-y-6">

    <?php if (!empty($msg_success)): ?>
    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-lg text-sm text-emerald-800 shadow-sm">
        <?= htmlspecialchars($msg_success) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($msg_error)): ?>
    <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-lg text-sm text-rose-800 shadow-sm">
        <?= htmlspecialchars($msg_error) ?>
    </div>
    <?php endif; ?>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Kelola Mata Kuliah</h1>
            <p class="text-xs text-slate-500 mt-1">Daftar mata kuliah dan pemetaan kriteria Capaian Pembelajaran Lulusan (CPL).</p>
        </div>
        <a href="#form-modal" class="px-4 py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow transition-colors flex items-center gap-1.5 self-start">
            + Tambah Mata Kuliah
        </a>
    </div>

    <!-- FORM TAMBAH / EDIT MATA KULIAH -->
    <div id="form-modal" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 flex items-center justify-between border-b pb-3">
            <span><?= $edit_item ? 'Edit Mata Kuliah: ' . htmlspecialchars($edit_item['name']) : 'Tambah Mata Kuliah Baru' ?></span>
            <?php if ($edit_item): ?>
            <a href="mata-kuliah.php" class="text-xs text-rose-600 font-semibold hover:underline">&times; Batalkan</a>
            <?php endif; ?>
        </h3>

        <form method="POST" action="mata-kuliah.php" class="space-y-4">
            <input type="hidden" name="action_save_mk" value="1">
            <input type="hidden" name="mid" value="<?= $edit_item ? $edit_item['id'] : 0 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Fakultas *</label>
                    <select name="fakultas_id" id="sel_fak" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach ($fakultas_list as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($edit_item && $edit_item['fakultas_id'] == $f['id']) || ($user['role'] === 'kaprodi' && $user['fakultas_id'] == $f['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Program Studi *</label>
                    <select name="prodi_id" id="sel_prd" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Pilih Program Studi --</option>
                        <?php foreach ($prodi_list as $p): ?>
                        <option value="<?= $p['id'] ?>" data-fakultas="<?= $p['fakultas_id'] ?>" <?= ($edit_item && $edit_item['prodi_id'] == $p['id']) || ($user['role'] === 'kaprodi' && $user['prodi_id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kode MK *</label>
                    <input type="text" name="code" value="<?= $edit_item ? htmlspecialchars($edit_item['code']) : '' ?>" placeholder="Contoh: TI301" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none uppercase">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">SKS *</label>
                    <input type="number" name="sks" min="1" max="6" value="<?= $edit_item ? htmlspecialchars($edit_item['sks']) : '3' ?>" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Mata Kuliah *</label>
                <input type="text" name="name" value="<?= $edit_item ? htmlspecialchars($edit_item['name']) : '' ?>" placeholder="Contoh: Pemrograman Web Lanjut" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
            </div>

            <!-- Pemetaan 12 Kriteria CPL -->
            <div>
                <label class="block text-xs font-bold text-slate-800 mb-2">Pemetaan Kriteria CPL yang Dibebankan *</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <?php 
                    $selected_cpls = ($edit_item && isset($edit_item['cpls']) && is_array($edit_item['cpls'])) ? $edit_item['cpls'] : array();
                    foreach ($GLOBAL_CPL_CATEGORIES as $cat_key => $cat_meta): ?>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="text-xs font-bold text-slate-800 mb-2 pb-1 border-b border-slate-200">
                            <?= htmlspecialchars($cat_meta['name']) ?> (<?= $cat_meta['weight'] ?>%)
                        </div>
                        <div class="space-y-1.5">
                            <?php foreach ($cat_meta['codes'] as $code): 
                                $checked = in_array($code, $selected_cpls);
                            ?>
                            <label class="flex items-start space-x-2 text-xs text-slate-700 cursor-pointer hover:text-slate-900">
                                <input type="checkbox" name="cpl_codes[]" value="<?= $code ?>" <?= $checked ? 'checked' : '' ?> class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <span class="font-bold text-slate-900"><?= $code ?></span>
                                    <p class="text-[10px] text-slate-500 line-clamp-1"><?= htmlspecialchars($GLOBAL_CPL_CRITERIA[$code]['label']) ?></p>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-3 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow transition-colors">
                    <?= $edit_item ? 'Perbarui Mata Kuliah' : 'Simpan Mata Kuliah' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- LIST MATA KULIAH GROUPED -->
    <div class="space-y-6">
        <?php if (count($grouped) === 0): ?>
        <div class="bg-white p-8 rounded-xl border border-slate-200 text-center text-slate-400 text-sm">
            Belum ada mata kuliah yang didaftarkan.
        </div>
        <?php endif; ?>

        <?php foreach ($grouped as $group_title => $items): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-slate-100/70 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <div class="font-bold text-xs sm:text-sm text-slate-800 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                    <?= $group_title ?>
                </div>
                <span class="text-xs text-slate-500 font-medium"><?= count($items) ?> Mata Kuliah</span>
            </div>

            <div class="divide-y divide-slate-200">
                <?php foreach ($items as $m): 
                    $cpls = json_decode($m['cpl_codes'], true);
                ?>
                <div class="p-4 sm:px-6 hover:bg-slate-50 transition-colors flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 border border-emerald-300">
                                <?= htmlspecialchars($m['code']) ?>
                            </span>
                            <span class="font-bold text-sm text-slate-900"><?= htmlspecialchars($m['name']) ?></span>
                            <span class="text-xs text-slate-500">(<?= $m['sks'] ?> SKS)</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="text-[10px] text-slate-400 font-semibold uppercase">CPL:</span>
                            <?php if (is_array($cpls)): foreach ($cpls as $cp): ?>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                <?= $cp ?>
                            </span>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-center">
                        <a href="mata-kuliah.php?edit=<?= $m['id'] ?>" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors">
                            Edit
                        </a>
                        <a href="mata-kuliah.php?delete=<?= $m['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus mata kuliah <?= htmlspecialchars($m['name']) ?>?');" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded text-xs font-semibold transition-colors">
                            Hapus
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<?php include dirname(__FILE__) . '/footer.php'; ?>
