<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$invoiceId = $_POST['id'];

try {
    // Update invoice status to paid
    $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>