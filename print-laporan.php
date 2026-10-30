<?php
/**
 * Cetak Laporan CPL Resmi UIN Sunan Ampel Surabaya (PHP 5.5 Compatible)
 * Format Kop Surat & Tanda Tangan Resmi
 */
require_once dirname(__FILE__) . '/config.php';
require_login();

$user = get_logged_user();
$db = get_db();

$f_tahun = isset($_GET['tahun']) ? trim($_GET['tahun']) : '';
$f_mk = isset($_GET['mk']) ? intval($_GET['mk']) : 0;

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
$rows = $stmt->fetchAll();

$totals = array();
foreach ($rows as $r) {
    if ($r['total_score'] !== null) $totals[] = floatval($r['total_score']);
}
$avg_total = count($totals) > 0 ? round(array_sum($totals) / count($totals), 2) : 0;
$pred = get_predicate(count($totals) > 0 ? $avg_total : null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan CPL - <?= INSTITUSI_NAME ?></title>
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; margin: 20mm; background: #fff; }
        .kop { border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 20px; text-align: center; }
        .kop h2 { margin: 0; font-size: 16pt; font-weight: bold; text-transform: uppercase; }
        .kop h3 { margin: 4px 0 0; font-size: 14pt; font-weight: bold; }
        .kop p { margin: 2px 0 0; font-size: 10pt; font-style: italic; }
        .title { text-align: center; margin-bottom: 20px; }
        .title h4 { margin: 0; font-size: 13pt; text-transform: uppercase; text-decoration: underline; }
        .meta { margin-bottom: 16px; font-size: 11pt; }
        .meta table { width: 100%; border: none; }
        .meta td { padding: 3px 6px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
        table.data th, table.data td { border: 1px solid #000; padding: 6px 8px; }
        table.data th { background-color: #f2f2f2; text-align: center; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .ttd { margin-top: 40px; width: 100%; }
        .ttd table { width: 100%; border: none; }
        .ttd td { text-align: center; font-size: 11pt; padding: 10px; }
        .btn-print { position: fixed; top: 20px; right: 20px; padding: 10px 18px; background: #064E3B; color: #fff; border: none; border-radius: 6px; font-family: sans-serif; font-size: 14px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .btn-print:hover { background: #043E2F; }
        @media print {
            .btn-print { display: none !important; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <button onclick="window.print()" class="btn-print">&#128424; Cetak / Simpan PDF</button>

    <!-- KOP SURAT UIN SUNAN AMPEL SURABAYA -->
    <div class="kop">
        <h2>KEMENTERIAN AGAMA REPUBLIK INDONESIA</h2>
        <h3>UNIVERSITAS ISLAM NEGERI SUNAN AMPEL SURABAYA</h3>
        <p>Jl. Jend. A. Yani No. 117 Surabaya, Jawa Timur 60237 &bull; Website: www.uinsa.ac.id</p>
    </div>

    <!-- JUDUL LAPORAN -->
    <div class="title">
        <h4>LAPORAN PENCAPAIAN CAPAIAN PEMBELAJARAN LULUSAN (CPL)</h4>
        <div style="font-size: 11pt; margin-top: 4px;">Sistem Evaluasi Berbasis Outcome-Based Education (OBE)</div>
    </div>

    <!-- META FILTER -->
    <div class="meta">
        <table>
            <tr>
                <td style="width: 140px;"><strong>Tahun Akademik</strong></td>
                <td>: <?= !empty($f_tahun) && $f_tahun !== 'all' ? htmlspecialchars($f_tahun) : 'Semua Tahun Terdata' ?></td>
                <td style="width: 140px;"><strong>Tanggal Cetak</strong></td>
                <td>: <?= date('d F Y, H:i') ?> WIB</td>
            </tr>
            <tr>
                <td><strong>Dicetak Oleh</strong></td>
                <td>: <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</td>
                <td><strong>Total Paket Nilai</strong></td>
                <td>: <?= count($rows) ?> berkas penilaian</td>
            </tr>
        </table>
    </div>

    <!-- TABEL DATA NILAI -->
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">No</th>
                <th>Mata Kuliah</th>
                <th>Program Studi</th>
                <th>Tahun</th>
                <th>Smt / Kls</th>
                <th>Sikap (20%)</th>
                <th>Penget. (10%)</th>
                <th>KU (30%)</th>
                <th>KK (40%)</th>
                <th>Nilai CPL</th>
                <th>Grade</th>
                <th>Predikat</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            foreach ($rows as $r): 
                $ca = json_decode($r['cat_avg_json'], true);
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['mk_name']) ?></strong><br>
                    <small><?= htmlspecialchars($r['mk_code']) ?></small>
                </td>
                <td><?= htmlspecialchars($r['prodi_name']) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['tahun_akademik']) ?></td>
                <td class="text-center"><?= htmlspecialchars($r['semester_type']) ?> &bull; <?= htmlspecialchars($r['kelas']) ?></td>
                <td class="text-center"><?= isset($ca['sikap']) && $ca['sikap'] !== null ? number_format($ca['sikap'], 2) : '-' ?></td>
                <td class="text-center"><?= isset($ca['pengetahuan']) && $ca['pengetahuan'] !== null ? number_format($ca['pengetahuan'], 2) : '-' ?></td>
                <td class="text-center"><?= isset($ca['keterampilan_umum']) && $ca['keterampilan_umum'] !== null ? number_format($ca['keterampilan_umum'], 2) : '-' ?></td>
                <td class="text-center"><?= isset($ca['keterampilan_khusus']) && $ca['keterampilan_khusus'] !== null ? number_format($ca['keterampilan_khusus'], 2) : '-' ?></td>
                <td class="text-center"><strong><?= $r['total_score'] !== null ? number_format($r['total_score'], 2) : '-' ?></strong></td>
                <td class="text-center"><strong><?= htmlspecialchars($r['grade']) ?></strong></td>
                <td><?= htmlspecialchars($r['predicate']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background: #f8f8f8; font-weight: bold;">
                <td colspan="9" class="text-right">RATA-RATA TERBOBOT KESELURUHAN:</td>
                <td class="text-center"><?= number_format($avg_total, 2) ?></td>
                <td class="text-center"><?= $pred['grade'] ?></td>
                <td><?= $pred['predicate'] ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- TANDA TANGAN RESMI -->
    <div class="ttd">
        <table>
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br>
                    Dekan Fakultas / Pimpinan Unit<br><br><br><br><br>
                    <strong><u>_____________________________</u></strong><br>
                    NIP. ....................................................
                </td>
                <td style="width: 50%;">
                    Surabaya, <?= date('d F Y') ?><br>
                    Ketua Tim Kurikulum & Penjaminan Mutu CPL<br><br><br><br><br>
                    <strong><u><?= htmlspecialchars($user['name']) ?></u></strong><br>
                    NIP. ....................................................
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
