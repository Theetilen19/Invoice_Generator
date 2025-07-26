<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

// Status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Base query
$query = "SELECT i.*, c.name as client_name, b.name as business_name 
          FROM invoices i 
          JOIN clients c ON i.client_id = c.client_id 
          JOIN business_types b ON i.business_type_id = b.id";

// Add status filter if not 'all'
if ($statusFilter !== 'all') {
    $query .= " WHERE i.status = :status";
}

$query .= " ORDER BY i.date_issued DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);

if ($statusFilter !== 'all') {
    $stmt->bindParam(':status', $statusFilter);
}

$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to calculate invoice total
function calculateInvoiceTotal($invoiceId, $businessTypeId, $pdo) {
    // First get the invoice details to access tax_rate and discount
    $stmt = $pdo->prepare("SELECT tax_rate, discount FROM invoices WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $invoiceDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoiceDetails) {
        return 0;
    }
    
    $subtotal = 0;
    
    switch ($businessTypeId) {
        case 1: // Freelancing
            $stmt = $pdo->prepare("SELECT SUM((pages * price_per_page) + (classes * price_per_class) + (research_hours * price_per_hour)) as total 
                                 FROM freelancing_items WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $subtotal = $result['total'] ?? 0;
            break;
            
        case 2: // Computer Sales
            $stmt = $pdo->prepare("SELECT SUM(quantity * unit_price) as total 
                                 FROM computer_sales_items WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $subtotal = $result['total'] ?? 0;
            break;
            
        case 3: // ISP
            $stmt = $pdo->prepare("SELECT SUM(amount) as total 
                                 FROM isp_items WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $subtotal = $result['total'] ?? 0;
            break;
    }
    
    $taxAmount = $subtotal * ($invoiceDetails['tax_rate'] / 100);
    $total = $subtotal + $taxAmount - $invoiceDetails['discount'];
    
    return $total;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Invoices</h4>
    <div>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Invoice
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <span class="me-2">Filter by status:</span>
            <div class="btn-group" role="group">
                <a href="list.php?status=all" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                    All
                </a>
                <a href="list.php?status=draft" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter === 'draft' ? 'active' : ''; ?>">
                    Draft
                </a>
                <a href="list.php?status=sent" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter === 'sent' ? 'active' : ''; ?>">
                    Sent
                </a>
                <a href="list.php?status=paid" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">
                    Paid
                </a>
                <a href="list.php?status=overdue" class="btn btn-sm btn-outline-secondary <?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>">
                    Overdue
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (count($invoices) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Business Type</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): 
                            $total = calculateInvoiceTotal($invoice['invoice_id'], $invoice['business_type_id'], $pdo);
                            $isOverdue = strtotime($invoice['due_date']) < time() && $invoice['status'] !== 'paid';
                        ?>
                            <tr>
                                <td><?php echo $invoice['invoice_number']; ?></td>
                                <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                                <td><?php echo $invoice['business_name']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($invoice['date_issued'])); ?></td>
                                <td class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>
                                </td>
                                <td>Ksh<?php echo number_format($total, 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $invoice['status'] == 'paid' ? 'success' : 
                                             ($isOverdue ? 'danger' : 
                                             ($invoice['status'] == 'sent' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo $isOverdue ? 'Overdue' : ucfirst($invoice['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="view.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-secondary" title="Print" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($invoice['status'] !== 'paid'): ?>
                                            <button class="btn btn-sm btn-success mark-paid" data-id="<?php echo $invoice['invoice_id']; ?>" title="Mark as Paid">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info m-4">
                No invoices found. <a href="create.php">Create your first invoice</a>.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark invoice as paid
    document.querySelectorAll('.mark-paid').forEach(button => {
        button.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to mark this invoice as paid?')) {
                fetch('mark_paid.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + invoiceId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while marking the invoice as paid.');
                });
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>