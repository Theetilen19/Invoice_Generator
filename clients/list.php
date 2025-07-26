<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/header.php';

// Get all clients
$stmt = $pdo->query("SELECT * FROM clients ORDER BY name");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4>Clients</h4>
    <a href="manage.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add New Client
    </a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (count($clients) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['name']); ?></td>
                                <td><?php echo !empty($client['company']) ? htmlspecialchars($client['company']) : '-'; ?></td>
                                <td><?php echo !empty($client['email']) ? htmlspecialchars($client['email']) : '-'; ?></td>
                                <td><?php echo !empty($client['phone']) ? htmlspecialchars($client['phone']) : '-'; ?></td>
                                <td>
                                    <a href="manage.php?id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-danger delete-client" data-id="<?php echo $client['client_id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="../invoices/create.php?client_id=<?php echo $client['client_id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-file-invoice"></i> Create Invoice
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No clients found. <a href="manage.php">Add your first client</a>.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete client confirmation
    document.querySelectorAll('.delete-client').forEach(button => {
        button.addEventListener('click', function() {
            const clientId = this.getAttribute('data-id');
            
            if (confirm('Are you sure you want to delete this client? This action cannot be undone.')) {
                fetch(`delete.php?id=${clientId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error deleting client: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the client.');
                });
            }
        });
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>