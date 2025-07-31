<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$invoiceId = $_GET['id'];

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

$items = [];
$subtotal = 0;

switch ($invoice['business_type_id']) {
    case 1:
        $stmt = $pdo->prepare("SELECT * FROM freelancing_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $itemTotal = 
                ($item['pages'] * $item['price_per_page']) +
                ($item['classes'] * $item['price_per_class']) +
                ($item['research_hours'] * $item['price_per_hour']) +
                ($item['slides'] * $item['price_per_slide']);
            $subtotal += $itemTotal;
        }
        break;

    case 2:
        $stmt = $pdo->prepare("SELECT * FROM computer_sales_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($items as $item) {
            $subtotal += $item['quantity'] * $item['unit_price'];
        }
        break;

    case 3:
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
    <title>Invoice #<?php echo $invoice['invoice_number']; ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 13px;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .invoice-container {
            width: 90%;
            max-width: 700px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 40%;
            left: 30%;
            opacity: 0.5;
            transform: rotate(-30deg);
            z-index: 0;
        }
        .watermark img {
            width: 600px;
        }
        .header,
        .footer {
            position: relative;
            z-index: 2;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 2px solid #ccc;
            padding-bottom: 10px;
        }
        .logo {
            height: 50px;
        }
        .invoice-info {
            text-align: right;
        }
        .from-to {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .from, .to {
            width: 45%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table th, table td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
        }
        table th {
            background-color: #f8f8f8;
        }
        .text-right {
            text-align: right;
        }
        .totals {
            margin-top: 10px;
            width: 60%;
            float: right;
        }
        .totals table {
            width: 100%;
        }
        .notes {
            clear: both;
            margin-top: 30px;
            font-style: italic;
        }
        .footer {
            margin-top: 50px;
            font-size: 12px;
            text-align: center;
        }

        /* Print-specific adjustments */
        @media print {
            body {
                margin: 0;
                zoom: 85%;
            }
            .invoice-container {
                box-shadow: none;
                border: none;
                width: 100%;
            }
            .totals {
                float: none;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Watermark -->
        <div class="watermark">
            <img src="<?php echo BASE_URL; ?>assets/images/N-LOGO.png" alt="Watermark">
        </div>

        <!-- Header -->
        <div class="header">
            <div>
                <img src="<?php echo BASE_URL; ?>assets/images/N-LOGO.png" class="logo" alt="Logo"><br>
                <strong><?php echo COMPANY_NAME; ?></strong><br>
                Freelancing | Computer Sales | ISP Services
            </div>
            <div class="invoice-info">
                <p><strong>Invoice #:</strong> <?php echo $invoice['invoice_number']; ?></p>
                <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($invoice['date_issued'])); ?></p>
                <p><strong>Due:</strong> <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($invoice['status']); ?></p>
            </div>
        </div>

        <!-- From / To -->
        <div class="from-to">
            <div class="from">
                <strong>From:</strong><br>
                <?php echo COMPANY_NAME; ?><br>
                info@mastertee.com<br>
                +254 729 596 966
            </div>
            <div class="to">
                <strong>To:</strong><br>
                <?php echo htmlspecialchars($invoice['name']); ?><br>
                <?php if ($invoice['company']) echo htmlspecialchars($invoice['company']) . "<br>"; ?>
                <?php if ($invoice['email']) echo htmlspecialchars($invoice['email']) . "<br>"; ?>
                <?php if ($invoice['phone']) echo htmlspecialchars($invoice['phone']) . "<br>"; ?>
                <?php if ($invoice['address']) echo nl2br(htmlspecialchars($invoice['address'])) . "<br>"; ?>
            </div>
        </div>

        <!-- Items -->
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
                                <?php
                                if ($item['item_type'] == 'article') {
                                    echo "{$item['pages']} pages × Ksh" . number_format($item['price_per_page'], 2);
                                } elseif ($item['item_type'] == 'class') {
                                    echo "{$item['classes']} classes × Ksh" . number_format($item['price_per_class'], 2);
                                } elseif ($item['item_type'] == 'research') {
                                    echo "{$item['research_hours']} hrs × Ksh" . number_format($item['price_per_hour'], 2);
                                } elseif ($item['item_type'] == 'powerpoint') {
                                    echo "{$item['slides']} slides × Ksh" . number_format($item['price_per_slide'], 2);
                                }
                                ?>
                            </td>
                            <td class="text-right">
                                Ksh<?php echo number_format(
                                    ($item['pages'] * $item['price_per_page']) +
                                    ($item['classes'] * $item['price_per_class']) +
                                    ($item['research_hours'] * $item['price_per_hour']) +
                                    ($item['slides'] * $item['price_per_slide']),
                                    2
                                ); ?>
                            </td>
                        <?php elseif ($invoice['business_type_id'] == 2): ?>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>Ksh<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right">Ksh<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                        <?php elseif ($invoice['business_type_id'] == 3): ?>
                            <td><?php echo htmlspecialchars($item['subscription_period']); ?></td>
                            <td class="text-right">Ksh<?php echo number_format($item['amount'], 2); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
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

        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
            <div class="notes">
                <h4>Notes:</h4>
                <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
            <p><strong><?php echo COMPANY_NAME; ?></strong></p>
        </div>
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
