<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' || !isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$clientId = $_GET['id'];

try {
    // First check if client has any invoices
    $stmt = $pdo->prepare("SELECT COUNT(*) as invoice_count FROM invoices WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['invoice_count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete client with existing invoices']);
        exit;
    }
    
    // Delete the client
    $stmt = $pdo->prepare("DELETE FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Client not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>