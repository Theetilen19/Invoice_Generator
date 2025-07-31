<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

// Get business types
$businessTypes = $pdo->query("SELECT * FROM business_types")->fetchAll(PDO::FETCH_ASSOC);

// Get clients
$clients = $pdo->query("SELECT * FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = $_POST['client_id'];
    $businessTypeId = $_POST['business_type_id'];
    $dateIssued = $_POST['date_issued'];
    $dueDate = $_POST['due_date'];
    $notes = $_POST['notes'];
    $taxRate = $_POST['tax_rate'];
    $discount = $_POST['discount'];
    
    // Generate invoice number
    $invoiceNumber = generateInvoiceNumber($businessTypeId);
    
    // Insert invoice
    $stmt = $pdo->prepare("INSERT INTO invoices (client_id, business_type_id, invoice_number, date_issued, due_date, notes, tax_rate, discount) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$clientId, $businessTypeId, $invoiceNumber, $dateIssued, $dueDate, $notes, $taxRate, $discount]);
    $invoiceId = $pdo->lastInsertId();
    
    // Handle items based on business type
    switch ($businessTypeId) {
        case 1: // Freelancing
            foreach ($_POST['items'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO freelancing_items 
                                     (invoice_id, item_type, pages, price_per_page, classes, price_per_class, research_hours, price_per_hour, slides, price_per_slide, description) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                //Adding Float to pages
                $pages = isset($item['pages']) ? (float)$item['pages'] : 0.0;

                if($pages < 0){
                    $pages = 0.0; //Avoiding neagtive pages
                }

                //Executing the statement with all required fields
                $stmt->execute([
                    $invoiceId,
            $item['item_type'],
            $pages,  // Now accepts decimal values
            isset($item['price_per_page']) ? (float)$item['price_per_page'] : 0.0,
            isset($item['classes']) ? (int)$item['classes'] : 0,  // Keep as integer if appropriate
            isset($item['price_per_class']) ? (float)$item['price_per_class'] : 0.0,
            isset($item['research_hours']) ? (float)$item['research_hours'] : 0.0,
            isset($item['price_per_hour']) ? (float)$item['price_per_hour'] : 0.0,
            isset($item['slides']) ? (int)$item['slides'] : 0,  // Keep as integer if appropriate
            isset($item['price_per_slide']) ? (float)$item['price_per_slide'] : 0.0,
            $item['description']
        ]);
    }
            break;
            
        case 2: // Computer Sales
            foreach ($_POST['items'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO computer_sales_items 
                                     (invoice_id, description, quantity, unit_price) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $invoiceId,
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price']
                ]);
            }
            break;
            
        case 3: // ISP
            foreach ($_POST['items'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO isp_items 
                                     (invoice_id, description, subscription_period, amount) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $invoiceId,
                    $item['description'],
                    $item['subscription_period'],
                    $item['amount']
                ]);
            }
            break;
    }
    
    // Redirect to view invoice
    header("Location: view.php?id=$invoiceId");
    exit;
}
?>

<div class="card">
    <div class="card-header">
        <h4>Create New Invoice</h4>
    </div>
    <div class="card-body">
        <form id="invoiceForm" method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="client_id" class="form-label">Client</label>
                    <select class="form-select" id="client_id" name="client_id" required>
                        <option value="">Select Client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['client_id']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?>
                                <?php if (!empty($client['company'])) echo ' (' . htmlspecialchars($client['company']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="business_type_id" class="form-label">Business Type</label>
                    <select class="form-select" id="business_type_id" name="business_type_id" required>
                        <option value="">Select Business Type</option>
                        <?php foreach ($businessTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="date_issued" class="form-label">Date Issued</label>
                    <input type="date" class="form-control" id="date_issued" name="date_issued" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="due_date" class="form-label">Due Date</label>
                    <input type="date" class="form-control" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="0" min="0" max="100" step="0.01">
                </div>
                <div class="col-md-3">
                    <label for="discount" class="form-label">Discount (Ksh)</label>
                    <input type="number" class="form-control" id="discount" name="discount" value="0" min="0" step="0.01">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="notes" class="form-label">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
            </div>
            
            <hr>
            
            <h5>Invoice Items</h5>
            <div id="itemsContainer">
                <!-- Items will be added here dynamically -->
            </div>
            
            <div class="mb-3">
                <button type="button" id="addItemBtn" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            
            <hr>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- Template for freelancing items -->
<template id="freelancingItemTemplate">
    <div class="item-card card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label class="form-label">Item Type</label>
                    <select class="form-select item-type" name="items[{{index}}][item_type]" required>
                        <option value="article">Article</option>
                        <option value="class">Class</option>
                        <option value="research">Research</option>
                        <option value="powerpoint">Powerpoint</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="items[{{index}}][description]" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Actions</label>
                    <button type="button" class="btn btn-sm btn-danger remove-item w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <div class="row mt-2 article-fields">
                <div class="col-md-6">
                    <label class="form-label">Number of Pages</label>
                    <input type="number" class="form-control" name="items[{{index}}][pages]" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per Page (Ksh)</label>
                    <input type="number" class="form-control" name="items[{{index}}][price_per_page]" min="0" step="0.01" value="0">
                </div>
            </div>
            
            <div class="row mt-2 class-fields d-none">
                <div class="col-md-6">
                    <label class="form-label">Number of Classes</label>
                    <input type="number" class="form-control" name="items[{{index}}][classes]" min="0" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per Class (Ksh)</label>
                    <input type="number" class="form-control" name="items[{{index}}][price_per_class]" min="0" step="0.01" value="0">
                </div>
            </div>
            
            <div class="row mt-2 research-fields d-none">
                <div class="col-md-6">
                    <label class="form-label">Research Hours</label>
                    <input type="number" class="form-control" name="items[{{index}}][research_hours]" min="0" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per Hour (Ksh)</label>
                    <input type="number" class="form-control" name="items[{{index}}][price_per_hour]" min="0" step="0.01" value="0">
                </div>
            </div>

            <div class="row mt-2 powerpoint-fields d-none">
                <div class="col-md-6">
                    <label class="form-label">Number of Slides</label>
                    <input type="number" class="form-control" name="items[{{index}}][slides]" min="0" step="0.01" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Price per slide (Ksh)</label>
                    <input type="number" class="form-control" name="items[{{index}}][price_per_slide]" min="0" step="0.01" value="0">
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Template for computer sales items -->
<template id="computerSalesItemTemplate">
    <div class="item-card card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="items[{{index}}][description]" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="items[{{index}}][quantity]" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unit Price (Ksh)</label>
                    <input type="number" class="form-control" name="items[{{index}}][unit_price]" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Actions</label>
                    <button type="button" class="btn btn-sm btn-danger remove-item w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Template for ISP items -->
<template id="ispItemTemplate">
    <div class="item-card card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="items[{{index}}][description]" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subscription Period</label>
                    <input type="text" class="form-control" name="items[{{index}}][subscription_period]" placeholder="e.g., 1 Month">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Amount (Ksh.)</label>
                    <input type="number" class="form-control" name="items[{{index}}][amount]" min="0" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Actions</label>
                    <button type="button" class="btn btn-sm btn-danger remove-item w-100">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script src="../assets/js/invoice-create.js"></script>

<?php require_once '../includes/footer.php'; ?>