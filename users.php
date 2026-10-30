<?php
/**
 * Manajemen Pengguna (RBAC) - SI-CPL UINSA (PHP 5.5 Compatible)
 * Khusus Administrator
 */
require_once dirname(__FILE__) . '/config.php';
require_role(array('admin'));

$current_user = get_logged_user();
$page_title = 'Manajemen Pengguna';
$db = get_db();
$msg_success = '';
$msg_error = '';

$fakultas_list = $db->query("SELECT * FROM fakultas ORDER BY name ASC")->fetchAll();
$prodi_list = $db->query("SELECT * FROM prodi ORDER BY name ASC")->fetchAll();

// Hapus User
if (isset($_GET['delete'])) {
    $del_uid = intval($_GET['delete']);
    try {
        if ($del_uid === intval($current_user['id'])) {
            $msg_error = 'Anda tidak dapat menghapus akun Anda sendiri.';
        } else {
            $stmt_t = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_t->execute(array($del_uid));
            $target = $stmt_t->fetch();

            if ($target) {
                // Cek jika ini admin terakhir
                if ($target['role'] === 'admin') {
                    $active_admins = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
                    if ($active_admins <= 1) {
                        $msg_error = 'Tidak dapat menghapus administrator aktif terakhir.';
                    }
                }

                if (empty($msg_error)) {
                    $stmt_d = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt_d->execute(array($del_uid));
                    log_audit('hapus', 'pengguna', $del_uid, 'Hapus akun: ' . $target['email'] . ' (' . $target['role'] . ')');
                    $msg_success = 'Pengguna berhasil dihapus.';
                    header("Location: users.php");
                    exit;
                }
            }
        }
    } catch (Exception $e) {
        $msg_error = 'Gagal menghapus pengguna: ' . $e->getMessage();
    }
}

// Toggle Aktif / Nonaktif
if (isset($_GET['toggle_status'])) {
    $t_uid = intval($_GET['toggle_status']);
    if ($t_uid === intval($current_user['id'])) {
        $msg_error = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
    } else {
        $stmt_cur = $db->prepare("SELECT is_active, email FROM users WHERE id = ?");
        $stmt_cur->execute(array($t_uid));
        $u_info = $stmt_cur->fetch();
        if ($u_info) {
            $new_st = $u_info['is_active'] ? 0 : 1;
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute(array($new_st, $t_uid));
            log_audit('ubah', 'pengguna', $t_uid, ($new_st ? 'Mengaktifkan' : 'Menonaktifkan') . ' akun ' . $u_info['email']);
            $msg_success = 'Status pengguna berhasil diperbarui.';
            header("Location: users.php");
            exit;
        }
    }
}

// Simpan / Edit User
$edit_uid = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_user = null;
if ($edit_uid > 0) {
    $stmt_eu = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_eu->execute(array($edit_uid));
    $edit_user = $stmt_eu->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_user'])) {
    $uid = intval($_POST['uid']);
    $email = strtolower(trim($_POST['email']));
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $fakultas_id = !empty($_POST['fakultas_id']) ? intval($_POST['fakultas_id']) : null;
    $prodi_id = !empty($_POST['prodi_id']) ? intval($_POST['prodi_id']) : null;
    $password = trim($_POST['password']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($role === 'admin') {
        $fakultas_id = null;
        $prodi_id = null;
    }

    if (empty($email) || empty($name) || !in_array($role, array('admin', 'kaprodi', 'dosen'))) {
        $msg_error = 'Lengkapi Nama, Email, dan Peran Pengguna yang valid.';
    } elseif ($uid === 0 && empty($password)) {
        $msg_error = 'Kata sandi wajib diisi untuk pengguna baru.';
    } else {
        try {
            // Cek duplikasi email
            $stmt_check = $db->prepare("SELECT id FROM users WHERE LOWER(email) = ? AND id != ?");
            $stmt_check->execute(array($email, $uid));
            if ($stmt_check->fetch()) {
                $msg_error = "Email '$email' sudah digunakan oleh akun lain.";
            } else {
                if ($uid > 0) {
                    // Update User
                    if (!empty($password)) {
                        $p_hash = function_exists('password_hash') ? password_hash($password, PASSWORD_DEFAULT) : sha1($password);
                        $stmt_up = $db->prepare("UPDATE users SET email = ?, name = ?, role = ?, fakultas_id = ?, prodi_id = ?, password_hash = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt_up->execute(array($email, $name, $role, $fakultas_id, $prodi_id, $p_hash, $is_active, $uid));
                    } else {
                        $stmt_up = $db->prepare("UPDATE users SET email = ?, name = ?, role = ?, fakultas_id = ?, prodi_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt_up->execute(array($email, $name, $role, $fakultas_id, $prodi_id, $is_active, $uid));
                    }
                    log_audit('ubah', 'pengguna', $uid, "Perbarui profil pengguna $email ($role)");
                    $msg_success = 'Data pengguna berhasil diperbarui.';
                } else {
                    // Buat User Baru
                    $p_hash = function_exists('password_hash') ? password_hash($password, PASSWORD_DEFAULT) : sha1($password);
                    $stmt_in = $db->prepare("INSERT INTO users (email, name, role, fakultas_id, prodi_id, password_hash, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt_in->execute(array($email, $name, $role, $fakultas_id, $prodi_id, $p_hash, $is_active));
                    $new_uid = $db->lastInsertId();
                    log_audit('buat', 'pengguna', $new_uid, "Tambah pengguna baru $email ($role)");
                    $msg_success = 'Pengguna baru berhasil ditambahkan.';
                }
                header("Location: users.php");
                exit;
            }
        } catch (Exception $e) {
            $msg_error = 'Gagal menyimpan pengguna: ' . $e->getMessage();
        }
    }
}

// Ambil semua pengguna
$users_list = $db->query("SELECT u.*, f.name as fakultas_name, p.name as prodi_name 
                          FROM users u 
                          LEFT JOIN fakultas f ON u.fakultas_id = f.id 
                          LEFT JOIN prodi p ON u.prodi_id = p.id 
                          ORDER BY u.role ASC, u.name ASC")->fetchAll();

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
            <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">Manajemen Pengguna</h1>
            <p class="text-xs text-slate-500 mt-1">Kontrol akses berbasis peran (RBAC) untuk Admin, Kaprodi, dan Dosen.</p>
        </div>
        <a href="#form-user" class="px-4 py-2 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow transition-colors self-start">
            + Tambah Pengguna Baru
        </a>
    </div>

    <!-- FORM TAMBAH / EDIT USER -->
    <div id="form-user" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4 flex items-center justify-between border-b pb-3">
            <span><?= $edit_user ? 'Edit Pengguna: ' . htmlspecialchars($edit_user['name']) : 'Form Pendaftaran Pengguna Baru' ?></span>
            <?php if ($edit_user): ?>
            <a href="users.php" class="text-xs text-rose-600 font-semibold hover:underline">&times; Batalkan Edit</a>
            <?php endif; ?>
        </h3>

        <form method="POST" action="users.php" class="space-y-4">
            <input type="hidden" name="action_save_user" value="1">
            <input type="hidden" name="uid" value="<?= $edit_user ? $edit_user['id'] : 0 ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                    <input type="text" name="name" value="<?= $edit_user ? htmlspecialchars($edit_user['name']) : '' ?>" placeholder="Dr. Fulan, M.Kom" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email *</label>
                    <input type="email" name="email" value="<?= $edit_user ? htmlspecialchars($edit_user['email']) : '' ?>" placeholder="dosen@uinsa.ac.id" required class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Peran Akses (Role) *</label>
                    <select name="role" id="user_role" required onchange="toggleScopeInputs(this.value)" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="dosen" <?= $edit_user && $edit_user['role'] === 'dosen' ? 'selected' : '' ?>>Dosen (Scoped Prodi)</option>
                        <option value="kaprodi" <?= $edit_user && $edit_user['role'] === 'kaprodi' ? 'selected' : '' ?>>Ketua Program Studi (Kaprodi)</option>
                        <option value="admin" <?= $edit_user && $edit_user['role'] === 'admin' ? 'selected' : '' ?>>Administrator (Akses Penuh)</option>
                    </select>
                </div>

                <div id="div_fakultas">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Fakultas Penugasan</label>
                    <select name="fakultas_id" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Tanpa / Global --</option>
                        <?php foreach ($fakultas_list as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= $edit_user && $edit_user['fakultas_id'] == $f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="div_prodi">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Program Studi Penugasan</label>
                    <select name="prodi_id" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                        <option value="">-- Tanpa / Global --</option>
                        <?php foreach ($prodi_list as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $edit_user && $edit_user['prodi_id'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        <?= $edit_user ? 'Ubah Kata Sandi (Kosongkan jika tidak diubah)' : 'Kata Sandi Awal *' ?>
                    </label>
                    <input type="password" name="password" placeholder="Minimal 6 karakter" <?= $edit_user ? '' : 'required' ?> class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>
            </div>

            <div class="flex items-center space-x-2 pt-2">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?= (!$edit_user || $edit_user['is_active']) ? 'checked' : '' ?> class="rounded text-emerald-600 focus:ring-emerald-500">
                <label for="is_active" class="text-xs font-medium text-slate-700">Akun Aktif (Dapat masuk ke sistem)</label>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white text-xs font-bold rounded-lg shadow transition-colors">
                    <?= $edit_user ? 'Perbarui Pengguna' : 'Simpan Pengguna Baru' ?>
                </button>
            </div>
        </form>
    </div>

    <!-- TABEL LIST PENGGUNA -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wide">Daftar Akun Pengguna</h3>
            <span class="text-xs text-slate-500"><?= count($users_list) ?> akun terdaftar</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700">
                <thead class="bg-slate-100 text-slate-600 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Nama & Email</th>
                        <th class="py-3 px-4">Peran</th>
                        <th class="py-3 px-4">Cakupan (Prodi / Fak)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($users_list as $u): 
                        $is_self = ($u['id'] == $current_user['id']);
                    ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-slate-900"><?= htmlspecialchars($u['name']) ?></div>
                            <div class="text-[11px] text-slate-500"><?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-block px-2.5 py-0.5 rounded-full font-bold text-[10px] uppercase tracking-wide <?= $u['role'] === 'admin' ? 'bg-purple-100 text-purple-800 border border-purple-200' : ($u['role'] === 'kaprodi' ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-blue-100 text-blue-800 border border-blue-200') ?>">
                                <?= htmlspecialchars($u['role']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600">
                            <?php if ($u['role'] === 'admin'): ?>
                                <span class="text-slate-400 italic">Seluruh Universitas</span>
                            <?php else: ?>
                                <?= htmlspecialchars($u['prodi_name'] ? $u['prodi_name'] : ($u['fakultas_name'] ? $u['fakultas_name'] : '-')) ?>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <?php if ($u['is_active']): ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">Aktif</span>
                            <?php else: ?>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-300">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3.5 px-4 text-right space-x-1 whitespace-nowrap">
                            <a href="users.php?edit=<?= $u['id'] ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors inline-block">
                                Edit
                            </a>
                            <?php if (!$is_self): ?>
                            <a href="users.php?toggle_status=<?= $u['id'] ?>" class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-xs font-semibold transition-colors inline-block">
                                <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </a>
                            <a href="users.php?delete=<?= $u['id'] ?>" onclick="return confirm('Hapus akun <?= htmlspecialchars($u['email']) ?>?');" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded text-xs font-semibold transition-colors inline-block">
                                Hapus
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    function toggleScopeInputs(role) {
        var df = document.getElementById('div_fakultas');
        var dp = document.getElementById('div_prodi');
        if (role === 'admin') {
            df.style.opacity = '0.4';
            dp.style.opacity = '0.4';
        } else {
            df.style.opacity = '1';
            dp.style.opacity = '1';
        }
    }
    toggleScopeInputs(document.getElementById('user_role').value);
</script>

<?php include dirname(__FILE__) . '/footer.php'; ?>
