<?php
/**
 * Header & Layout Navigasi SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';
require_login();
$current_user = get_logged_user();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?><?= APP_NAME ?> <?= INSTITUSI_NAME ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        uinsa: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            500: '#10b981',
                            700: '#047857',
                            800: '#064E3B', // Warna Hijau Resmi UINSA
                            900: '#022c22',
                        },
                        gold: {
                            500: '#D97706',
                            600: '#B45309',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-uinsa-800 text-white shadow-md sticky top-0 z-40 border-b border-emerald-900 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand / Logo -->
                <div class="flex items-center space-x-3">
                    <a href="dashboard.php" class="flex items-center space-x-3 group">
                        <div class="w-10 h-10 rounded-lg bg-emerald-700/60 flex items-center justify-center border border-emerald-500/30 text-gold-500 font-bold text-lg shadow-inner">
                            CPL
                        </div>
                        <div>
                            <div class="font-bold text-lg tracking-tight group-hover:text-emerald-200 transition-colors">
                                SI-CPL <span class="text-amber-400">UINSA</span>
                            </div>
                            <div class="text-[10px] text-emerald-200 uppercase tracking-widest hidden sm:block">
                                UIN Sunan Ampel Surabaya
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'dashboard.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Dashboard
                    </a>
                    
                    <a href="input-nilai.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'input-nilai.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Input Nilai CPL
                    </a>

                    <a href="laporan.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'laporan.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Laporan
                    </a>

                    <a href="mata-kuliah.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'mata-kuliah.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Mata Kuliah
                    </a>

                    <?php if ($current_user['role'] === 'admin'): ?>
                    <a href="fakultas-prodi.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'fakultas-prodi.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Fakultas & Prodi
                    </a>

                    <a href="users.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'users.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Pengguna
                    </a>

                    <a href="audit-log.php" class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?= $current_page == 'audit-log.php' ? 'bg-uinsa-900 text-white font-semibold' : 'text-emerald-100 hover:bg-uinsa-700 hover:text-white' ?>">
                        Audit Log
                    </a>
                    <?php endif; ?>
                </nav>

                <!-- User Profile & Logout -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-semibold text-white leading-tight">
                            <?= htmlspecialchars($current_user['name']) ?>
                        </div>
                        <div class="text-[10px] text-amber-300 capitalize font-medium">
                            Peran: <?= htmlspecialchars($current_user['role']) ?>
                        </div>
                    </div>
                    <a href="logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?');" class="bg-emerald-900/80 hover:bg-red-700 text-white text-xs font-medium px-3 py-1.5 rounded-md border border-emerald-700 transition-colors shadow-sm" title="Keluar">
                        Keluar
                    </a>
                    <!-- Mobile Hamburger -->
                    <button id="mobile-menu-btn" class="md:hidden text-white hover:text-emerald-200 focus:outline-none p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile-menu" class="hidden md:hidden bg-uinsa-900 border-t border-emerald-800 px-4 pt-2 pb-3 space-y-1">
            <a href="dashboard.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'dashboard.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Dashboard</a>
            <a href="input-nilai.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'input-nilai.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Input Nilai CPL</a>
            <a href="laporan.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'laporan.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Laporan</a>
            <a href="mata-kuliah.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'mata-kuliah.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Mata Kuliah</a>
            <?php if ($current_user['role'] === 'admin'): ?>
            <a href="fakultas-prodi.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'fakultas-prodi.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Fakultas & Prodi</a>
            <a href="users.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'users.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Pengguna</a>
            <a href="audit-log.php" class="block px-3 py-2 rounded-md text-sm font-medium <?= $current_page == 'audit-log.php' ? 'bg-emerald-800 text-white' : 'text-emerald-100 hover:bg-emerald-800' ?>">Audit Log</a>
            <?php endif; ?>
        </div>
    </header>

    <script>
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            var menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });
    </script>

    <!-- Main Content Area -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
