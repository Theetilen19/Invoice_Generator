<?php
require_once 'config.php';

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=invoice_management', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

// Generate invoice number
function generateInvoiceNumber($businessType) {
    global $pdo;
    
    $prefix = '';
    switch ($businessType) {
        case 1: $prefix = 'FL-'; break; // Freelancing
        case 2: $prefix = 'CS-'; break; // Computer Sales
        case 3: $prefix = 'ISP-'; break; // ISP
    }
    
    $year = date('Y');
    $month = date('m');
    
    // Getting the last invoice number
    $stmt = $pdo->prepare("SELECT invoice_number FROM invoices 
                          WHERE business_type_id = ? 
                          AND YEAR(date_issued) = ? 
                          AND MONTH(date_issued) = ?
                          ORDER BY invoice_id DESC LIMIT 1");
    $stmt->execute([$businessType, $year, $month]);
    $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastInvoice) {
        $lastNumber = intval(substr($lastInvoice['invoice_number'], -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . $year . $month . '-' . $newNumber;
}
?>