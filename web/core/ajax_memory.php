<?php
// File này nằm trong core/ nên gọi db.php cùng cấp
require_once __DIR__ . '/db.php';

if (isset($_POST['ajax_memory'])) {
    header('Content-Type: application/json');
    try {
        $action = $_POST['ajax_memory'];
        
        if ($action === 'get') {
            $stmt = $pdo->query("SELECT * FROM auto_push_configs ORDER BY created_at DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } 
        elseif ($action === 'save') {
            $stmt = $pdo->prepare("INSERT INTO auto_push_configs (note, is_pinned, config_data) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['note'], $_POST['is_pinned'], $_POST['config_data']]);
            echo json_encode(['success' => true]);
        } 
        elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM auto_push_configs WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
        } 
        elseif ($action === 'toggle_pin') {
            $stmt = $pdo->prepare("UPDATE auto_push_configs SET is_pinned = 1 - is_pinned WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}