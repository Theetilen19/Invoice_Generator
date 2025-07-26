<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$invoiceId = $_GET['id'];

// Get invoice details
$stmt = $pdo->prepare("SELECT i.*, c.*, b.name as business_name 
                      FROM invoices i 
                      JOIN clients c ON i.client_id = c.client_id 
                      JOIN business_types b ON i.business_type_id = b.id 
                      WHERE i.invoice_id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    header("Location: list.php");
    exit;
}

// Get items based on business type
$items = [];
$subtotal = 0;

switch ($invoice['business_type_id']) {
    case 1: // Freelancing
        $stmt = $pdo->prepare("SELECT * FROM freelancing_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $itemTotal = ($item['pages'] * $item['price_per_page']) + 
                         ($item['classes'] * $item['price_per_class']) + 
                         ($item['research_hours'] * $item['price_per_hour']);
            $subtotal += $itemTotal;
        }
        break;
        
    case 2: // Computer Sales
        $stmt = $pdo->prepare("SELECT * FROM computer_sales_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $itemTotal = $item['quantity'] * $item['unit_price'];
            $subtotal += $itemTotal;
        }
        break;
        
    case 3: // ISP
        $stmt = $pdo->prepare("SELECT * FROM isp_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $subtotal += $item['amount'];
        }
        break;
}

$taxAmount = $subtotal * ($invoice['tax_rate'] / 100);
$total = $subtotal + $taxAmount - $invoice['discount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Invoice #<?php echo $invoice['invoice_number']; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .logo {
            max-height: 80px;
        }
        .invoice-info {
            text-align: right;
        }
        .from-to {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .from, .to {
            width: 48%;
        }
        .details {
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
        .totals {
            width: 300px;
            margin-left: auto;
        }
        .text-right {
            text-align: right;
        }
        .notes {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            background-color: #28a745;
            color: white;
            border-radius: 3px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <div>
                <img src="<?php echo BASE_URL; ?>assets/images/LOGO-TEE.png" alt="Master Tee Logo" class="logo">
                <h1>INVOICE</h1>
                <p><strong>Business Type:</strong> <?php echo $invoice['business_name']; ?></p>
            </div>
            <div class="invoice-info">
                <p><strong>Invoice #:</strong> <?php echo $invoice['invoice_number']; ?></p>
                <p><strong>Date Issued:</strong> <?php echo date('M d, Y', strtotime($invoice['date_issued'])); ?></p>
                <p><strong>Due Date:</strong> <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></p>
                <p><strong>Status:</strong> <span class="status"><?php echo ucfirst($invoice['status']); ?></span></p>
            </div>
        </div>
        
        <div class="from-to">
            <div class="from">
                <h3>From:</h3>
                <p><strong><?php echo COMPANY_NAME; ?></strong></p>
                <p>Freelancing | Computer Sales | ISP Services</p>
                <p>info@mastertee.com</p>
                <p>+254 729 596 966</p>
            </div>
            <div class="to">
                <h3>To:</h3>
                <p><strong><?php echo htmlspecialchars($invoice['name']); ?></strong></p>
                <?php if (!empty($invoice['company'])): ?>
                    <p><?php echo htmlspecialchars($invoice['company']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['email'])): ?>
                    <p><?php echo htmlspecialchars($invoice['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['phone'])): ?>
                    <p><?php echo htmlspecialchars($invoice['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($invoice['address'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <?php if ($invoice['business_type_id'] == 1): ?>
                        <th>Type</th>
                        <th>Details</th>
                    <?php elseif ($invoice['business_type_id'] == 2): ?>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                    <?php elseif ($invoice['business_type_id'] == 3): ?>
                        <th>Subscription</th>
                    <?php endif; ?>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        
                        <?php if ($invoice['business_type_id'] == 1): ?>
                            <td><?php echo ucfirst($item['item_type']); ?></td>
                            <td>
                                <?php if ($item['item_type'] == 'article'): ?>
                                    <?php echo $item['pages']; ?> pages × Ksh<?php echo number_format($item['price_per_page'], 2); ?>
                                <?php elseif ($item['item_type'] == 'class'): ?>
                                    <?php echo $item['classes']; ?> classes × Ksh<?php echo number_format($item['price_per_class'], 2); ?>
                                <?php elseif ($item['item_type'] == 'research'): ?>
                                    <?php echo $item['research_hours']; ?> hours × Ksh<?php echo number_format($item['price_per_hour'], 2); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                Ksh<?php echo number_format(
                                    ($item['pages'] * $item['price_per_page']) + 
                                    ($item['classes'] * $item['price_per_class']) + 
                                    ($item['research_hours'] * $item['price_per_hour']), 
                                    2
                                ); ?>
                            </td>
                            
                        <?php elseif ($invoice['business_type_id'] == 2): ?>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>Ksh<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right">
                                Ksh<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                            </td>
                            
                        <?php elseif ($invoice['business_type_id'] == 3): ?>
                            <td><?php echo htmlspecialchars($item['subscription_period']); ?></td>
                            <td class="text-right">
                                Ksh<?php echo number_format($item['amount'], 2); ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="totals">
            <table>
                <tr>
                    <th>Subtotal:</th>
                    <td class="text-right">Ksh<?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <?php if ($invoice['tax_rate'] > 0): ?>
                    <tr>
                        <th>Tax (<?php echo $invoice['tax_rate']; ?>%):</th>
                        <td class="text-right">Ksh<?php echo number_format($taxAmount, 2); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($invoice['discount'] > 0): ?>
                    <tr>
                        <th>Discount:</th>
                        <td class="text-right">-Ksh<?php echo number_format($invoice['discount'], 2); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <th>Total:</th>
                    <td class="text-right"><strong>Ksh<?php echo number_format($total, 2); ?></strong></td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($invoice['notes'])): ?>
        <div class="notes">
            <h3>Notes</h3>
            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="footer" style="margin-top: 50px; padding-top: 20px; border-top: 1px solid #eee;">
            <p>Thank you for your business!</p>
            <p><strong><?php echo COMPANY_NAME; ?></strong></p>
        </div>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>