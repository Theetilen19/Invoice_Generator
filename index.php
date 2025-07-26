<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/header.php';
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Total Invoices</h5>
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM invoices");
                $total = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <h2 class="card-text"><?php echo $total['total']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Paid Invoices</h5>
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as paid FROM invoices WHERE status = 'paid'");
                $paid = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <h2 class="card-text"><?php echo $paid['paid']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h5 class="card-title">Pending Invoices</h5>
                <?php
                $stmt = $pdo->query("SELECT COUNT(*) as pending FROM invoices WHERE status IN ('draft', 'sent')");
                $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                <h2 class="card-text"><?php echo $pending['pending']; ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h4>Recent Invoices</h4>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Business Type</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT i.*, c.name as client_name, b.name as business_name 
                                        FROM invoices i 
                                        JOIN clients c ON i.client_id = c.client_id 
                                        JOIN business_types b ON i.business_type_id = b.id 
                                        ORDER BY i.date_issued DESC LIMIT 5");
                    while ($invoice = $stmt->fetch(PDO::FETCH_ASSOC)):
                        // Calculate total amount (simplified for dashboard)
                        $amount = 0;
                        switch ($invoice['business_type_id']) {
                            case 1: // Freelancing
                                $stmt2 = $pdo->prepare("SELECT SUM((pages * price_per_page) + (classes * price_per_class) + (research_hours * price_per_hour)) as total 
                                                       FROM freelancing_items WHERE invoice_id = ?");
                                $stmt2->execute([$invoice['invoice_id']]);
                                $total = $stmt2->fetch(PDO::FETCH_ASSOC);
                                $amount = $total['total'] ?? 0;
                                break;
                            case 2: // Computer Sales
                                $stmt2 = $pdo->prepare("SELECT SUM(quantity * unit_price) as total 
                                                       FROM computer_sales_items WHERE invoice_id = ?");
                                $stmt2->execute([$invoice['invoice_id']]);
                                $total = $stmt2->fetch(PDO::FETCH_ASSOC);
                                $amount = $total['total'] ?? 0;
                                break;
                            case 3: // ISP
                                $stmt2 = $pdo->prepare("SELECT SUM(amount) as total 
                                                       FROM isp_items WHERE invoice_id = ?");
                                $stmt2->execute([$invoice['invoice_id']]);
                                $total = $stmt2->fetch(PDO::FETCH_ASSOC);
                                $amount = $total['total'] ?? 0;
                                break;
                        }
                    ?>
                    <tr>
                        <td><?php echo $invoice['invoice_number']; ?></td>
                        <td><?php echo $invoice['client_name']; ?></td>
                        <td><?php echo $invoice['business_name']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($invoice['date_issued'])); ?></td>
                        <td>Ksh.<?php echo number_format($amount, 2); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $invoice['status'] == 'paid' ? 'success' : 
                                     ($invoice['status'] == 'overdue' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="invoices/view.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="invoices/print.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>