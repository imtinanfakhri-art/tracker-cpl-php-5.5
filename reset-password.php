<?php
/**
 * Atur Ulang Kata Sandi - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';

$page_title = 'Atur Ulang Kata Sandi';
$db = get_db();
$msg_success = '';
$msg_error = '';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$token_valid = false;
$reset_record = null;

if (!empty($token)) {
    $stmt = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 LIMIT 1");
    $stmt->execute(array($token));
    $reset_record = $stmt->fetch();

    if ($reset_record) {
        $expires = strtotime($reset_record['expires_at']);
        if (time() <= $expires) {
            $token_valid = true;
        } else {
            $msg_error = 'Tautan pengaturan ulang kata sandi sudah kedaluwarsa (masa berlaku 2 jam).';
        }
    } else {
        $msg_error = 'Tautan tidak valid atau sudah pernah digunakan sebelumnya.';
    }
}

// Proses Reset Password Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_do_reset'])) {
    $post_token = trim($_POST['token']);
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);

    if (strlen($new_pass) < 6) {
        $msg_error = 'Kata sandi minimal harus 6 karakter.';
    } elseif ($new_pass !== $confirm_pass) {
        $msg_error = 'Konfirmasi kata sandi tidak cocok.';
    } else {
        $stmt_c = $db->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 LIMIT 1");
        $stmt_c->execute(array($post_token));
        $rec = $stmt_c->fetch();

        if ($rec && strtotime($rec['expires_at']) >= time()) {
            $p_hash = function_exists('password_hash') ? password_hash($new_pass, PASSWORD_DEFAULT) : sha1($new_pass);
            $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute(array($p_hash, $rec['user_id']));
            $db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?")->execute(array($rec['id']));
            log_audit('reset_sandi', 'pengguna', $rec['user_id'], 'Kata sandi berhasil direset via tautan token');
            $msg_success = 'Kata sandi Anda berhasil diperbarui! Silakan masuk dengan kata sandi baru.';
            $token_valid = false;
        } else {
            $msg_error = 'Tautan kedaluwarsa atau tidak valid.';
        }
    }
}

// Proses Permintaan Tautan Reset (Request Link)
$generated_link = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_request_reset'])) {
    $email = strtolower(trim($_POST['email']));
    if (empty($email)) {
        $msg_error = 'Silakan masukkan alamat email akun Anda.';
    } else {
        $stmt_u = $db->prepare("SELECT * FROM users WHERE LOWER(email) = ? AND is_active = 1 LIMIT 1");
        $stmt_u->execute(array($email));
        $u = $stmt_u->fetch();

        if ($u) {
            $new_token = md5(uniqid(mt_rand(), true)) . sha1(time() . $email);
            $expires = date('Y-m-d H:i:s', time() + 7200); // 2 Jam
            $db->prepare("INSERT INTO password_resets (token, user_id, email, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())")
               ->execute(array($new_token, $u['id'], $email, $expires));

            $link = BASE_URL . "/reset-password.php?token=" . $new_token;
            $generated_link = $link;
            $msg_success = "Tautan reset telah dibuat. Silakan gunakan tautan di bawah ini:";
            log_audit('minta_reset', 'pengguna', $u['id'], "Membuat token reset untuk $email");
        } else {
            // Demi keamanan jangan beritahu jika email tidak ada
            $msg_success = "Jika email terdaftar di sistem, instruksi reset kata sandi telah disiapkan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Ulang Kata Sandi - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        uinsa: { 800: '#064E3B', 900: '#022c22' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
        <div class="bg-uinsa-800 text-white p-6 text-center">
            <h1 class="text-xl font-bold">Atur Ulang Kata Sandi</h1>
            <p class="text-xs text-emerald-200 mt-1">SI-CPL &bull; UIN Sunan Ampel Surabaya</p>
        </div>

        <div class="p-6 space-y-4">
            <?php if (!empty($msg_success)): ?>
            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-3.5 rounded text-xs text-emerald-800">
                <?= htmlspecialchars($msg_success) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($msg_error)): ?>
            <div class="bg-rose-50 border-l-4 border-rose-500 p-3.5 rounded text-xs text-rose-800">
                <?= htmlspecialchars($msg_error) ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($generated_link)): ?>
            <div class="bg-slate-50 border border-slate-200 p-3 rounded-lg text-xs space-y-1">
                <span class="font-bold text-slate-700">Tautan Pengaturan Ulang Kata Sandi:</span>
                <input type="text" readonly value="<?= htmlspecialchars($generated_link) ?>" class="w-full bg-white border p-2 rounded text-[11px] font-mono select-all">
                <a href="<?= htmlspecialchars($generated_link) ?>" class="inline-block mt-2 font-bold text-emerald-700 hover:underline">
                    &rarr; Langsung Buka Tautan Reset
                </a>
            </div>
            <?php endif; ?>

            <?php if ($token_valid && $reset_record): ?>
            <!-- FORM INPUT KATA SANDI BARU -->
            <form method="POST" action="reset-password.php" class="space-y-3">
                <input type="hidden" name="action_do_reset" value="1">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div>
                    <span class="text-xs text-slate-500 block mb-1">Akun: <strong><?= htmlspecialchars($reset_record['email']) ?></strong></span>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Kata Sandi Baru *</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="Minimal 6 karakter" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru *</label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="Ulangi kata sandi baru" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white font-bold text-xs rounded-lg shadow transition-colors">
                    Simpan Kata Sandi Baru
                </button>
            </form>
            <?php elseif (empty($generated_link)): ?>
            <!-- FORM MINTA TAUTAN RESET -->
            <form method="POST" action="reset-password.php" class="space-y-3">
                <input type="hidden" name="action_request_reset" value="1">
                <p class="text-xs text-slate-600">
                    Masukkan email akun Anda untuk mendapatkan tautan atur ulang kata sandi.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Email Terdaftar</label>
                    <input type="email" name="email" required placeholder="nama@uinsa.ac.id" class="w-full text-xs bg-slate-50 border border-slate-300 rounded-lg p-2.5 focus:ring-2 focus:ring-uinsa-800 focus:outline-none">
                </div>

                <button type="submit" class="w-full py-2.5 bg-uinsa-800 hover:bg-uinsa-900 text-white font-bold text-xs rounded-lg shadow transition-colors">
                    Buat Tautan Atur Ulang
                </button>
            </form>
            <?php endif; ?>

            <div class="pt-3 border-t border-slate-200 text-center">
                <a href="login.php" class="text-xs font-semibold text-uinsa-800 hover:underline">
                    &larr; Kembali ke Halaman Masuk
                </a>
            </div>
        </div>
    </div>

</body>
</html>
