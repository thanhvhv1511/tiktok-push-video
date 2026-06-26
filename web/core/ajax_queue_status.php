<?php
// core/ajax_queue_status.php
require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT product_id, status FROM render_queue WHERE status IN ('pending', 'running')");
    $data = [];
    while ($row = $stmt->fetch()) {
        $data[$row['product_id']] = $row['status'];
    }
    echo json_encode(["status" => "success", "data" => $data]);
} catch (Exception $e) {
    echo json_encode(["status" => "error"]);
}
?>