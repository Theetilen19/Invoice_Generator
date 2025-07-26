<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

$client = null;
$isEdit = false;

// Check if we're editing an existing client
if (isset($_GET['id'])) {
    $isEdit = true;
    $clientId = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        header("Location: list.php");
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $company = $_POST['company'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    
    if ($isEdit) {
        // Update existing client
        $stmt = $pdo->prepare("UPDATE clients SET name = ?, company = ?, email = ?, phone = ?, address = ? WHERE client_id = ?");
        $stmt->execute([$name, $company, $email, $phone, $address, $clientId]);
        $message = "Client updated successfully!";
    } else {
        // Create new client
        $stmt = $pdo->prepare("INSERT INTO clients (name, company, email, phone, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $company, $email, $phone, $address]);
        $clientId = $pdo->lastInsertId();
        $message = "Client added successfully!";
    }
    
    $_SESSION['success_message'] = $message;
    header("Location: list.php");
    exit;
}
?>

<div class="card">
    <div class="card-header">
        <h4><?php echo $isEdit ? 'Edit Client' : 'Add New Client'; ?></h4>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full Name*</label>
                    <input type="text" class="form-control" id="name" name="name" 
                           value="<?php echo $client ? htmlspecialchars($client['name']) : ''; ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="company" class="form-label">Company</label>
                    <input type="text" class="form-control" id="company" name="company" 
                           value="<?php echo $client ? htmlspecialchars($client['company']) : ''; ?>">
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo $client ? htmlspecialchars($client['email']) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo $client ? htmlspecialchars($client['phone']) : ''; ?>">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?php 
                    echo $client ? htmlspecialchars($client['address']) : ''; 
                ?></textarea>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="list.php" class="btn btn-secondary me-md-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <?php echo $isEdit ? 'Update Client' : 'Add Client'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>