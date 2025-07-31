<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: list.php");
    exit;
}

$invoiceId = $_GET['id'];

// Fetch invoice with client and business type
$stmt = $pdo->prepare("SELECT i.*, c.*, b.name AS business_name 
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
    case 1: // Freelancing
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

$taxAmount = ($invoice['tax_rate'] / 100) * $subtotal;
$total = $subtotal + $taxAmount - $invoice['discount'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Invoice #<?php echo $invoice['invoice_number']; ?></h4>
    <div>
        <a href="edit.php?id=<?php echo $invoiceId; ?>" class="btn btn-warning me-2"><i class="fas fa-edit"></i> Edit</a>
        <a href="print.php?id=<?php echo $invoiceId; ?>" class="btn btn-primary me-2" target="_blank"><i class="fas fa-print"></i> Print</a>
        <a href="list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>
</div>

<!-- Bill From and To -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>From</h5></div>
            <div class="card-body">
                <img src="../assets/images/LOGO-TEE.png" alt="Master Tee Logo" height="60" class="mb-3">
                <p><strong><?php echo COMPANY_NAME; ?></strong></p>
                <p>Freelancing | Computer Sales | ISP Services</p>
                <p>info@mastertee.com</p>
                <p>+254 729 596 966</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5>Bill To</h5></div>
            <div class="card-body">
                <p><strong><?php echo htmlspecialchars($invoice['name']); ?></strong></p>
                <?php if (!empty($invoice['company'])): ?><p><?php echo htmlspecialchars($invoice['company']); ?></p><?php endif; ?>
                <?php if (!empty($invoice['email'])): ?><p><?php echo htmlspecialchars($invoice['email']); ?></p><?php endif; ?>
                <?php if (!empty($invoice['phone'])): ?><p><?php echo htmlspecialchars($invoice['phone']); ?></p><?php endif; ?>
                <?php if (!empty($invoice['address'])): ?><p><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Invoice Meta Info -->
<div class="card mb-4">
    <div class="card-header"><h5>Invoice Details</h5></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong>Invoice Number:</strong><br><?php echo $invoice['invoice_number']; ?></div>
            <div class="col-md-3"><strong>Business Type:</strong><br><?php echo $invoice['business_name']; ?></div>
            <div class="col-md-3"><strong>Date Issued:</strong><br><?php echo date('M d, Y', strtotime($invoice['date_issued'])); ?></div>
            <div class="col-md-3"><strong>Due Date:</strong><br><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></div>
        </div>
    </div>
</div>

<!-- Items Table -->
<div class="card mb-4">
    <div class="card-header"><h5>Items</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Description</th>
                        <?php if ($invoice['business_type_id'] == 1): ?>
                            <th>Type</th><th>Details</th>
                        <?php elseif ($invoice['business_type_id'] == 2): ?>
                            <th>Quantity</th><th>Unit Price</th>
                        <?php elseif ($invoice['business_type_id'] == 3): ?>
                            <th>Subscription</th>
                        <?php endif; ?>
                        <th class="text-end">Amount</th>
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
                                        if ($item['item_type'] === 'article') {
                                            echo "{$item['pages']} pages × Ksh" . number_format($item['price_per_page'], 2);
                                        } elseif ($item['item_type'] === 'class') {
                                            echo "{$item['classes']} classes × Ksh" . number_format($item['price_per_class'], 2);
                                        } elseif ($item['item_type'] === 'research') {
                                            echo "{$item['research_hours']} hrs × Ksh" . number_format($item['price_per_hour'], 2);
                                        } elseif ($item['item_type'] === 'powerpoint') {
                                            echo "{$item['slides']} slides × Ksh" . number_format($item['price_per_slide'], 2);
                                        }
                                    ?>
                                </td>
                                <td class="text-end">
                                    Ksh<?php echo number_format(
                                        ($item['pages'] * $item['price_per_page']) +
                                        ($item['classes'] * $item['price_per_class']) +
                                        ($item['research_hours'] * $item['price_per_hour']) +
                                        ($item['slides'] * $item['price_per_slide']), 2); ?>
                                </td>
                            <?php elseif ($invoice['business_type_id'] == 2): ?>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>Ksh<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end">Ksh<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                            <?php elseif ($invoice['business_type_id'] == 3): ?>
                                <td><?php echo htmlspecialchars($item['subscription_period']); ?></td>
                                <td class="text-end">Ksh<?php echo number_format($item['amount'], 2); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Summary Card -->
<div class="row justify-content-end">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <tr><th>Subtotal:</th><td class="text-end">Ksh<?php echo number_format($subtotal, 2); ?></td></tr>
                    <?php if ($invoice['tax_rate'] > 0): ?>
                        <tr><th>Tax (<?php echo $invoice['tax_rate']; ?>%):</th><td class="text-end">Ksh<?php echo number_format($taxAmount, 2); ?></td></tr>
                    <?php endif; ?>
                    <?php if ($invoice['discount'] > 0): ?>
                        <tr><th>Discount:</th><td class="text-end">-Ksh<?php echo number_format($invoice['discount'], 2); ?></td></tr>
                    <?php endif; ?>
                    <tr class="table-active">
                        <th>Total:</th><td class="text-end fw-bold">Ksh<?php echo number_format($total, 2); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Notes -->
<?php if (!empty($invoice['notes'])): ?>
    <div class="card mt-4">
        <div class="card-header"><h5>Notes</h5></div>
        <div class="card-body">
            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>