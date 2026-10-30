<?php
/**
 * Log Audit Sistem - SI-CPL UINSA (PHP 5.5 Compatible)
 * Khusus Administrator
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin'));

$page_title = 'Audit Log Aktivitas';
$db = get_db();

$f_entity = isset($_GET['entity']) ? trim($_GET['entity']) : 'all';
$f_action = isset($_GET['action']) ? trim($_GET['action']) : 'all';

$where = array();
$params = array();

if (!empty($f_entity) && $f_entity !== 'all') {
    $where[] = "entity = ?";
    $params[] = $f_entity;
}
if (!empty($f_action) && $f_action !== 'all') {
    $where[] = "action = ?";
    $params[] = $f_action;
}

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";
$sql = "SELECT * FROM audit_logs $where_sql ORDER BY created_at DESC LIMIT 300";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

include dirname(__FILE__) . '/header.php';
?>

<div class="space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Audit Log Aktivitas Sistem</h1>
            <p class="text-xs text-slate-500 mt-1">Catatan jejak digital setiap perubahan data nilai, kurikulum, dan akun.</p>
        </div>

        <!-- Filter Entity -->
        <form method="GET" action="audit-log.php" class="flex items-center gap-2">
            <select name="entity" onchange="this.form.submit()" class="text-xs bg-white border border-slate-300 rounded-lg p-2 text-slate-700 focus:outline-none">
                <option value="all">Semua Entitas</option>
                <option value="nilai" <?= $f_entity === 'nilai' ? 'selected' : '' ?>>Nilai CPL</option>
                <option value="matakuliah" <?= $f_entity === 'matakuliah' ? 'selected' : '' ?>>Mata Kuliah</option>
                <option value="fakultas" <?= $f_entity === 'fakultas' ? 'selected' : '' ?>>Fakultas</option>
                <option value="prodi" <?= $f_entity === 'prodi' ? 'selected' : '' ?>>Program Studi</option>
                <option value="pengguna" <?= $f_entity === 'pengguna' ? 'selected' : '' ?>>Pengguna</option>
            </select>

            <select name="action" onchange="this.form.submit()" class="text-xs bg-white border border-slate-300 rounded-lg p-2 text-slate-700 focus:outline-none">
                <option value="all">Semua Aksi</option>
                <option value="buat" <?= $f_action === 'buat' ? 'selected' : '' ?>>Buat (Create)</option>
                <option value="ubah" <?= $f_action === 'ubah' ? 'selected' : '' ?>>Ubah (Update)</option>
                <option value="hapus" <?= $f_action === 'hapus' ? 'selected' : '' ?>>Hapus (Delete)</option>
                <option value="impor" <?= $f_action === 'impor' ? 'selected' : '' ?>>Impor CSV</option>
                <option value="login" <?= $f_action === 'login' ? 'selected' : '' ?>>Login</option>
            </select>
        </form>
    </div>

    <!-- Tabel Audit Log -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 uppercase tracking-wide">300 Aktivitas Terkini</span>
            <span class="text-xs text-slate-400">Diurutkan dari yang terbaru</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Waktu (WIB)</th>
                        <th class="py-3 px-4">Pelaku (Actor)</th>
                        <th class="py-3 px-4">Aksi</th>
                        <th class="py-3 px-4">Entitas</th>
                        <th class="py-3 px-4">Ringkasan Perubahan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 font-mono text-[11px]">
                    <?php if (count($logs) === 0): ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-400 font-sans text-xs">Belum ada catatan log aktivitas.</td>
                    </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $l): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                            <?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?>
                        </td>
                        <td class="py-3 px-4 font-sans whitespace-nowrap">
                            <span class="font-bold text-slate-800"><?= htmlspecialchars($l['actor_email']) ?></span>
                            <span class="text-[10px] text-slate-400 block">(<?= htmlspecialchars($l['actor_role']) ?>)</span>
                        </td>
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded font-bold uppercase text-[9px] font-sans <?= $l['action'] === 'buat' ? 'bg-emerald-100 text-emerald-800' : ($l['action'] === 'ubah' ? 'bg-amber-100 text-amber-800' : ($l['action'] === 'hapus' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700')) ?>">
                                <?= htmlspecialchars($l['action']) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 font-sans font-semibold text-slate-700 capitalize whitespace-nowrap">
                            <?= htmlspecialchars($l['entity']) ?>
                        </td>
                        <td class="py-3 px-4 font-sans text-slate-800 max-w-md truncate" title="<?= htmlspecialchars($l['summary']) ?>">
                            <?= htmlspecialchars($l['summary']) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include dirname(__FILE__) . '/footer.php'; ?>
