<?php
/**
 * REST API JSON Endpoints - SI-CPL UINSA (PHP 5.5 Compatible)
 */
require_once dirname(__FILE__) . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$db = get_db();

if ($action === 'get_prodi') {
    $fid = isset($_GET['fakultas_id']) ? intval($_GET['fakultas_id']) : 0;
    if ($fid > 0) {
        $stmt = $db->prepare("SELECT * FROM prodi WHERE fakultas_id = ? ORDER BY name ASC");
        $stmt->execute(array($fid));
    } else {
        $stmt = $db->query("SELECT * FROM prodi ORDER BY name ASC");
    }
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($action === 'get_matakuliah') {
    $pid = isset($_GET['prodi_id']) ? intval($_GET['prodi_id']) : 0;
    if ($pid > 0) {
        $stmt = $db->prepare("SELECT * FROM matakuliah WHERE prodi_id = ? ORDER BY name ASC");
        $stmt->execute(array($pid));
    } else {
        $stmt = $db->query("SELECT * FROM matakuliah ORDER BY name ASC");
    }
    $res = $stmt->fetchAll();
    foreach ($res as &$item) {
        $item['cpl_codes'] = json_decode($item['cpl_codes'], true);
    }
    echo json_encode($res);
    exit;
}

if ($action === 'calculate') {
    $scores = isset($_POST['scores']) && is_array($_POST['scores']) ? $_POST['scores'] : array();
    $calc = calculate_cpl_scores($scores);
    echo json_encode($calc);
    exit;
}

if ($action === 'meta') {
    echo json_encode(array(
        'categories' => $GLOBAL_CPL_CATEGORIES,
        'criteria' => $GLOBAL_CPL_CRITERIA,
    ));
    exit;
}

echo json_encode(array('status' => 'error', 'message' => 'Aksi API tidak valid.'));
exit;
