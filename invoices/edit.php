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

// Fetch items based on business type
$items = [];
switch ($invoice['business_type_id']) {
    case 1: // Freelancing
        $stmt = $pdo->prepare("SELECT * FROM freelancing_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 2: // Computer Sales
        $stmt = $pdo->prepare("SELECT * FROM computer_sales_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
    case 3: // ISP
        $stmt = $pdo->prepare("SELECT * FROM isp_items WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update invoice basic info
        $stmt = $pdo->prepare("UPDATE invoices SET 
                              invoice_number = ?, 
                              date_issued = ?, 
                              due_date = ?, 
                              tax_rate = ?, 
                              discount = ?, 
                              notes = ? 
                              WHERE invoice_id = ?");
        $stmt->execute([
            $_POST['invoice_number'],
            $_POST['date_issued'],
            $_POST['due_date'],
            $_POST['tax_rate'],
            $_POST['discount'],
            $_POST['notes'],
            $invoiceId
        ]);

        // Delete existing items
        switch ($invoice['business_type_id']) {
            case 1:
                $pdo->prepare("DELETE FROM freelancing_items WHERE invoice_id = ?")->execute([$invoiceId]);
                break;
            case 2:
                $pdo->prepare("DELETE FROM computer_sales_items WHERE invoice_id = ?")->execute([$invoiceId]);
                break;
            case 3:
                $pdo->prepare("DELETE FROM isp_items WHERE invoice_id = ?")->execute([$invoiceId]);
                break;
        }

        // Add new items
        if ($invoice['business_type_id'] == 1) { // Freelancing
            foreach ($_POST['items'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO freelancing_items 
                                      (invoice_id, description, item_type, pages, price_per_page, classes, price_per_class, research_hours, price_per_hour, slides, price_per_slide) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $invoiceId,
                    $item['description'],
                    $item['item_type'],
                    $item['pages'] ?? 0,
                    $item['price_per_page'] ?? 0,
                    $item['classes'] ?? 0,
                    $item['price_per_class'] ?? 0,
                    $item['research_hours'] ?? 0,
                    $item['price_per_hour'] ?? 0,
                    $item['slides'] ?? 0,
                    $item['price_per_slide'] ?? 0
                ]);
            }
        } elseif ($invoice['business_type_id'] == 2) { // Computer Sales
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
        } elseif ($invoice['business_type_id'] == 3) { // ISP
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
        }

        $pdo->commit();
        $_SESSION['success_message'] = "Invoice updated successfully!";
        header("Location: view.php?id=" . $invoiceId);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating invoice: " . $e->getMessage();
    }
}

// Fetch business types for dropdown
$businessTypes = $pdo->query("SELECT * FROM business_types")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Edit Invoice #<?php echo $invoice['invoice_number']; ?></h4>
        <div>
            <a href="view.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
        </div>
    </div>

    <form method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5>Invoice Details</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" class="form-control" name="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Business Type</label>
                            <select class="form-control" disabled>
                                <option><?php echo htmlspecialchars($invoice['business_name']); ?></option>
                            </select>
                            <small class="text-muted">Business type cannot be changed after invoice creation</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date Issued</label>
                                <input type="date" class="form-control" name="date_issued" value="<?php echo htmlspecialchars($invoice['date_issued']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date" value="<?php echo htmlspecialchars($invoice['due_date']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5>Client Information</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['name']); ?>" disabled>
                        </div>
                        <?php if (!empty($invoice['company'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Company</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['company']); ?>" disabled>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($invoice['email'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['email']); ?>" disabled>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($invoice['phone'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['phone']); ?>" disabled>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5>Financial Details</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tax Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($invoice['tax_rate']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Discount Amount</label>
                            <input type="number" step="0.01" class="form-control" name="discount" value="<?php echo htmlspecialchars($invoice['discount']); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Invoice Items</h5>
                <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            <div class="card-body">
                <div id="itemsContainer">
                    <?php foreach ($items as $index => $item): ?>
                        <div class="item-row mb-3 border p-3">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="items[<?php echo $index; ?>][description]" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                </div>
                                
                                <?php if ($invoice['business_type_id'] == 1): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Item Type</label>
                                        <select class="form-control" name="items[<?php echo $index; ?>][item_type]" required>
                                            <option value="article" <?php echo ($item['item_type'] == 'article') ? 'selected' : ''; ?>>Article</option>
                                            <option value="class" <?php echo ($item['item_type'] == 'class') ? 'selected' : ''; ?>>Class</option>
                                            <option value="research" <?php echo ($item['item_type'] == 'research') ? 'selected' : ''; ?>>Research</option>
                                            <option value="powerpoint" <?php echo ($item['item_type'] == 'powerpoint') ? 'selected' : ''; ?>>PowerPoint</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3 article-fields" style="<?php echo ($item['item_type'] != 'article') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Pages</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][pages]" value="<?php echo htmlspecialchars($item['pages']); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3 article-fields" style="<?php echo ($item['item_type'] != 'article') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Price per Page</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][price_per_page]" value="<?php echo htmlspecialchars($item['price_per_page']); ?>">
                                    </div>
                                    
                                    <div class="col-md-3 mb-3 class-fields" style="<?php echo ($item['item_type'] != 'class') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Classes</label>
                                        <input type="number" class="form-control" name="items[<?php echo $index; ?>][classes]" value="<?php echo htmlspecialchars($item['classes']); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3 class-fields" style="<?php echo ($item['item_type'] != 'class') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Price per Class</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][price_per_class]" value="<?php echo htmlspecialchars($item['price_per_class']); ?>">
                                    </div>
                                    
                                    <div class="col-md-3 mb-3 research-fields" style="<?php echo ($item['item_type'] != 'research') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Research Hours</label>
                                        <input type="number" step="0.1" class="form-control" name="items[<?php echo $index; ?>][research_hours]" value="<?php echo htmlspecialchars($item['research_hours']); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3 research-fields" style="<?php echo ($item['item_type'] != 'research') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Price per Hour</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][price_per_hour]" value="<?php echo htmlspecialchars($item['price_per_hour']); ?>">
                                    </div>
                                    
                                    <div class="col-md-3 mb-3 powerpoint-fields" style="<?php echo ($item['item_type'] != 'powerpoint') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Slides</label>
                                        <input type="number" class="form-control" name="items[<?php echo $index; ?>][slides]" value="<?php echo htmlspecialchars($item['slides']); ?>">
                                    </div>
                                    <div class="col-md-3 mb-3 powerpoint-fields" style="<?php echo ($item['item_type'] != 'powerpoint') ? 'display:none;' : ''; ?>">
                                        <label class="form-label">Price per Slide</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][price_per_slide]" value="<?php echo htmlspecialchars($item['price_per_slide']); ?>">
                                    </div>
                                    
                                <?php elseif ($invoice['business_type_id'] == 2): ?>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" name="items[<?php echo $index; ?>][quantity]" value="<?php echo htmlspecialchars($item['quantity']); ?>" required>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Unit Price</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][unit_price]" value="<?php echo htmlspecialchars($item['unit_price']); ?>" required>
                                    </div>
                                    
                                <?php elseif ($invoice['business_type_id'] == 3): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Subscription Period</label>
                                        <input type="text" class="form-control" name="items[<?php echo $index; ?>][subscription_period]" value="<?php echo htmlspecialchars($item['subscription_period']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Amount</label>
                                        <input type="number" step="0.01" class="form-control" name="items[<?php echo $index; ?>][amount]" value="<?php echo htmlspecialchars($item['amount']); ?>" required>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-danger remove-item">Remove</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="text-end mb-4">
            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemBtn = document.getElementById('addItemBtn');
    const businessTypeId = <?php echo $invoice['business_type_id']; ?>;
    let itemCount = <?php echo count($items); ?>;

    // Add new item
    addItemBtn.addEventListener('click', function() {
        const newIndex = itemCount++;
        const itemRow = document.createElement('div');
        itemRow.className = 'item-row mb-3 border p-3';
        
        let fieldsHtml = '';
        if (businessTypeId == 1) {
            fieldsHtml = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="items[${newIndex}][description]" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Item Type</label>
                        <select class="form-control item-type-select" name="items[${newIndex}][item_type]" required>
                            <option value="article">Article</option>
                            <option value="class">Class</option>
                            <option value="research">Research</option>
                            <option value="powerpoint">PowerPoint</option>
                        </select>
                    </div>
                    
                    <!-- Article Fields -->
                    <div class="col-md-3 mb-3 article-fields">
                        <label class="form-label">Pages</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][pages]" value="0">
                    </div>
                    <div class="col-md-3 mb-3 article-fields">
                        <label class="form-label">Price per Page</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][price_per_page]" value="0">
                    </div>
                    
                    <!-- Class Fields (hidden by default) -->
                    <div class="col-md-3 mb-3 class-fields" style="display:none;">
                        <label class="form-label">Classes</label>
                        <input type="number" class="form-control" name="items[${newIndex}][classes]" value="0">
                    </div>
                    <div class="col-md-3 mb-3 class-fields" style="display:none;">
                        <label class="form-label">Price per Class</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][price_per_class]" value="0">
                    </div>
                    
                    <!-- Research Fields (hidden by default) -->
                    <div class="col-md-3 mb-3 research-fields" style="display:none;">
                        <label class="form-label">Research Hours</label>
                        <input type="number" step="0.1" class="form-control" name="items[${newIndex}][research_hours]" value="0">
                    </div>
                    <div class="col-md-3 mb-3 research-fields" style="display:none;">
                        <label class="form-label">Price per Hour</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][price_per_hour]" value="0">
                    </div>
                    
                    <!-- PowerPoint Fields (hidden by default) -->
                    <div class="col-md-3 mb-3 powerpoint-fields" style="display:none;">
                        <label class="form-label">Slides</label>
                        <input type="number" class="form-control" name="items[${newIndex}][slides]" value="0">
                    </div>
                    <div class="col-md-3 mb-3 powerpoint-fields" style="display:none;">
                        <label class="form-label">Price per Slide</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][price_per_slide]" value="0">
                    </div>
                </div>
            `;
        } else if (businessTypeId == 2) {
            fieldsHtml = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="items[${newIndex}][description]" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="items[${newIndex}][quantity]" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Unit Price</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][unit_price]" required>
                    </div>
                </div>
            `;
        } else if (businessTypeId == 3) {
            fieldsHtml = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="items[${newIndex}][description]" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Subscription Period</label>
                        <input type="text" class="form-control" name="items[${newIndex}][subscription_period]" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" name="items[${newIndex}][amount]" required>
                    </div>
                </div>
            `;
        }
        
        itemRow.innerHTML = fieldsHtml + '<button type="button" class="btn btn-sm btn-danger remove-item">Remove</button>';
        itemsContainer.appendChild(itemRow);

        // Add event listener for item type change if freelancing
        if (businessTypeId == 1) {
            const select = itemRow.querySelector('.item-type-select');
            select.addEventListener('change', function() {
                toggleFreelancingFields(itemRow, this.value);
            });
        }
    });

    // Remove item
    itemsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item')) {
            e.target.closest('.item-row').remove();
        }
    });

    // Toggle freelancing fields based on item type
    function toggleFreelancingFields(row, type) {
        const fields = {
            'article': row.querySelectorAll('.article-fields'),
            'class': row.querySelectorAll('.class-fields'),
            'research': row.querySelectorAll('.research-fields'),
            'powerpoint': row.querySelectorAll('.powerpoint-fields')
        };

        // Hide all fields first
        Object.values(fields).forEach(fieldGroup => {
            fieldGroup.forEach(field => field.style.display = 'none');
        });

        // Show only the relevant fields
        if (fields[type]) {
            fields[type].forEach(field => field.style.display = 'block');
        }
    }

    // Initialize event listeners for existing item type selects
    document.querySelectorAll('.item-type-select').forEach(select => {
        select.addEventListener('change', function() {
            toggleFreelancingFields(this.closest('.item-row'), this.value);
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>