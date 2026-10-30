<?php
/**
 * Master Fakultas & Program Studi - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin'));

$page_title = 'Fakultas & Program Studi';
$db = get_db();
$msg_success = '';
$msg_error = '';

// Aksi Simpan / Edit Fakultas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_fakultas'])) {
    $fid = intval($_POST['fid']);
    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);

    if (empty($code) || empty($name)) {
        $msg_error = 'Kode dan nama fakultas wajib diisi.';
    } else {
        try {
            if ($fid > 0) {
                $stmt = $db->prepare("UPDATE fakultas SET code = ?, name = ? WHERE id = ?");
                $stmt->execute(array($code, $name, $fid));
                log_audit('ubah', 'fakultas', $fid, "Update Fakultas: $name ($code)");
                $msg_success = 'Fakultas berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("INSERT INTO fakultas (code, name, created_at) VALUES (?, ?, NOW())");
                $stmt->execute(array($code, $name));
                $new_id = $db->lastInsertId();
                log_audit('buat', 'fakultas', $new_id, "Tambah Fakultas: $name ($code)");
                $msg_success = 'Fakultas berhasil ditambahkan.';
            }
        } catch (Exception $e) {
            $msg_error = 'Gagal menyimpan fakultas: ' . $e->getMessage();
        }
    }
}

// Aksi Hapus Fakultas
if (isset($_GET['delete_fakultas'])) {
    $del_fid = intval($_GET['delete_fakultas']);
    try {
        $stmt_info = $db->prepare("SELECT name FROM fakultas WHERE id = ?");
        $stmt_info->execute(array($del_fid));
        $fn = $stmt_info->fetchColumn();

        $stmt = $db->prepare("DELETE FROM fakultas WHERE id = ?");
        $stmt->execute(array($del_fid));
        // Hapus prodi & matakuliah di bawahnya
        $db->prepare("DELETE FROM prodi WHERE fakultas_id = ?")->execute(array($del_fid));
        $db->prepare("DELETE FROM matakuliah WHERE fakultas_id = ?")->execute(array($del_fid));

        log_audit('hapus', 'fakultas', $del_fid, "Hapus Fakultas: $fn beserta prodi & mata kuliah terkait");
        $msg_success = 'Fakultas dan seluruh prodi/mata kuliah terkait berhasil dihapus.';
        header("Location: fakultas-prodi.php");
        exit;
    } catch (Exception $e) {
        $msg_error = 'Gagal menghapus fakultas: ' . $e->getMessage();
    }
}

// Aksi Simpan / Edit Prodi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_prodi'])) {
    $pid = intval($_POST['pid']);
    $fakultas_id = intval($_POST['fakultas_id']);
    $code = strtoupper(trim($_POST['code']));
    $name = trim($_POST['name']);

    if (empty($code) || empty($name) || $fakultas_id <= 0) {
        $msg_error = 'Lengkapi Fakultas, Kode, dan Nama Program Studi.';
    } else {
        try {
            if ($pid > 0) {
                $stmt = $db->prepare("UPDATE prodi SET fakultas_id = ?, code = ?, name = ? WHERE id = ?");
                $stmt->execute(array($fakultas_id, $code, $name, $pid));
                log_audit('ubah', 'prodi', $pid, "Update Prodi: $name ($code)");
                $msg_success = 'Program Studi berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("INSERT INTO prodi (fakultas_id, code, name, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute(array($fakultas_id, $code, $name));
                $new_id = $db->lastInsertId();
                log_audit('buat', 'prodi', $new_id, "Tambah Prodi: $name ($code)");
                $msg_success = 'Program Studi berhasil ditambahkan.';
            }
        } catch (Exception $e) {
            $msg_error = 'Gagal menyimpan prodi: ' . $e->getMessage();
        }
    }
}

// Aksi Hapus Prodi
if (isset($_GET['delete_prodi'])) {
    $del_pid = intval($_GET['delete_prodi']);
    try {
        $stmt_info = $db->prepare("SELECT name FROM prodi WHERE id = ?");
        $stmt_info->execute(array($del_pid));
        $pn = $stmt_info->fetchColumn();

        $stmt = $db->prepare("DELETE FROM prodi WHERE id = ?");
        $stmt->execute(array($del_pid));
        $db->prepare("DELETE FROM matakuliah WHERE prodi_id = ?")->execute(array($del_pid));

        log_audit('hapus', 'prodi', $del_pid, "Hapus Prodi: $pn beserta mata kuliah terkait");
        $msg_success = 'Program Studi dan mata kuliah terkait berhasil dihapus.';
        header("Location: fakultas-prodi.php");
        exit;
    } catch (Exception $e) {
        $msg_error = 'Gagal menghapus prodi: ' . $e->getMessage();
    }
}

// Ambil data untuk tampilan
$fakultas_list = $db->query("SELECT * FROM fakultas ORDER BY name ASC")->fetchAll();

// Fakultas terpilih untuk tampilan detail prodi
$selected_fid = isset($_GET['fid']) ? intval($_GET['fid']) : (count($fakultas_list) > 0 ? $fakultas_list[0]['id'] : 0);

$stmt_p = $db->prepare("SELECT p.*, f.name as fakultas_name FROM prodi p JOIN fakultas f ON p.fakultas_id = f.id WHERE p.fakultas_id = ? ORDER BY p.name ASC");
$stmt_p->execute(array($selected_fid));
$prodi_in_selected = $stmt_p->fetchAll();

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

    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Fakultas & Program Studi</h1>
        <p class="text-xs text-slate-500 mt-1">Struktur hierarki akademik UIN Sunan Ampel Surabaya (Master-Detail).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- PANEL KIRI: FAKULTAS -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                        <span>🏛️</span> Daftar Fakultas
                    </h3>
                    <button onclick="document.getElementById('form-fakultas').classList.toggle('hidden')" class="px-2.5 py-1 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-semibold rounded shadow transition-colors">
                        + Tambah
                    </button>
                </div>

                <!-- Form Tambah Fakultas (Toggle) -->
                <div id="form-fakultas" class="p-4 bg-emerald-50/50 border-b border-emerald-100 hidden">
                    <form method="POST" action="fakultas-prodi.php" class="space-y-3">
                        <input type="hidden" name="action_save_fakultas" value="1">
                        <input type="hidden" name="fid" id="f_id" value="0">
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">Kode *</label>
                                <input type="text" name="code" id="f_code" placeholder="FST" required class="w-full text-xs bg-white border border-slate-300 rounded p-2 focus:ring-1 focus:ring-uinsa-800 focus:outline-none uppercase">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">Nama Fakultas *</label>
                                <input type="text" name="name" id="f_name" placeholder="Fakultas Sains dan Teknologi" required class="w-full text-xs bg-white border border-slate-300 rounded p-2 focus:ring-1 focus:ring-uinsa-800 focus:outline-none">
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="document.getElementById('form-fakultas').classList.add('hidden')" class="px-3 py-1 bg-slate-200 text-slate-700 text-xs rounded">Batal</button>
                            <button type="submit" class="px-4 py-1 bg-uinsa-800 text-white text-xs font-semibold rounded">Simpan</button>
                        </div>
                    </form>
                </div>

                <!-- List Fakultas -->
                <div class="divide-y divide-slate-200">
                    <?php foreach ($fakultas_list as $f): 
                        $is_sel = ($f['id'] == $selected_fid);
                    ?>
                    <div class="p-4 flex items-center justify-between cursor-pointer transition-colors <?= $is_sel ? 'bg-emerald-50/80 border-l-4 border-emerald-600' : 'hover:bg-slate-50' ?>" onclick="location.href='fakultas-prodi.php?fid=<?= $f['id'] ?>'">
                        <div class="space-y-0.5">
                            <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($f['name']) ?></div>
                            <div class="text-xs text-slate-500 font-mono">Kode: <?= htmlspecialchars($f['code']) ?></div>
                        </div>

                        <div class="flex items-center space-x-2" onclick="event.stopPropagation();">
                            <button type="button" onclick="editFakultas(<?= $f['id'] ?>, '<?= htmlspecialchars($f['code']) ?>', '<?= htmlspecialchars($f['name']) ?>')" class="text-xs text-slate-600 hover:text-slate-900 p-1">Edit</button>
                            <a href="fakultas-prodi.php?delete_fakultas=<?= $f['id'] ?>" onclick="return confirm('Hapus <?= htmlspecialchars($f['name']) ?> beserta semua prodi dan mata kuliahnya?');" class="text-xs text-rose-600 hover:text-rose-900 p-1">Hapus</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PANEL KANAN: PROGRAM STUDI DARI FAKULTAS TERPILIH -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
                        <span>🎓</span> Program Studi
                    </h3>
                    <button onclick="document.getElementById('form-prodi').classList.toggle('hidden')" class="px-2.5 py-1 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-semibold rounded shadow transition-colors">
                        + Tambah Prodi
                    </button>
                </div>

                <!-- Form Tambah Prodi (Toggle) -->
                <div id="form-prodi" class="p-4 bg-emerald-50/50 border-b border-emerald-100 hidden">
                    <form method="POST" action="fakultas-prodi.php?fid=<?= $selected_fid ?>" class="space-y-3">
                        <input type="hidden" name="action_save_prodi" value="1">
                        <input type="hidden" name="pid" id="p_id" value="0">
                        <input type="hidden" name="fakultas_id" value="<?= $selected_fid ?>">
                        
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-1">
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">Kode *</label>
                                <input type="text" name="code" id="p_code" placeholder="TI" required class="w-full text-xs bg-white border border-slate-300 rounded p-2 focus:ring-1 focus:ring-uinsa-800 focus:outline-none uppercase">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-[10px] font-bold text-slate-600 mb-1">Nama Prodi *</label>
                                <input type="text" name="name" id="p_name" placeholder="Teknik Informatika" required class="w-full text-xs bg-white border border-slate-300 rounded p-2 focus:ring-1 focus:ring-uinsa-800 focus:outline-none">
                            </div>
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="document.getElementById('form-prodi').classList.add('hidden')" class="px-3 py-1 bg-slate-200 text-slate-700 text-xs rounded">Batal</button>
                            <button type="submit" class="px-4 py-1 bg-uinsa-800 text-white text-xs font-semibold rounded">Simpan</button>
                        </div>
                    </form>
                </div>

                <!-- List Prodi -->
                <div class="divide-y divide-slate-200">
                    <?php if (count($prodi_in_selected) === 0): ?>
                    <div class="p-8 text-center text-slate-400 text-xs">
                        Belum ada program studi pada fakultas ini.
                    </div>
                    <?php endif; ?>

                    <?php foreach ($prodi_in_selected as $p): ?>
                    <div class="p-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                        <div class="space-y-0.5">
                            <div class="font-bold text-sm text-slate-900"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="text-xs text-slate-500 font-mono">Kode: <?= htmlspecialchars($p['code']) ?></div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button type="button" onclick="editProdi(<?= $p['id'] ?>, '<?= htmlspecialchars($p['code']) ?>', '<?= htmlspecialchars($p['name']) ?>')" class="text-xs text-slate-600 hover:text-slate-900 p-1">Edit</button>
                            <a href="fakultas-prodi.php?delete_prodi=<?= $p['id'] ?>&fid=<?= $selected_fid ?>" onclick="return confirm('Hapus program studi <?= htmlspecialchars($p['name']) ?>?');" class="text-xs text-rose-600 hover:text-rose-900 p-1">Hapus</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
    function editFakultas(id, code, name) {
        document.getElementById('form-fakultas').classList.remove('hidden');
        document.getElementById('f_id').value = id;
        document.getElementById('f_code').value = code;
        document.getElementById('f_name').value = name;
    }

    function editProdi(id, code, name) {
        document.getElementById('form-prodi').classList.remove('hidden');
        document.getElementById('p_id').value = id;
        document.getElementById('p_code').value = code;
        document.getElementById('p_name').value = name;
    }
</script>

<?php include dirname(__FILE__) . '/footer.php'; ?>
