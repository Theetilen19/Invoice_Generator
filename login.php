<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Check user credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Redirect to dashboard
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Tee - Login</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            height: 80px;
            margin-bottom: 15px;
        }
        .login-logo h2 {
            color: #2c3e50;
            font-weight: 600;
        }
        .form-control {
            height: 45px;
            border-radius: 5px;
        }
        .btn-login {
            background-color: #3498db;
            border: none;
            height: 45px;
            font-weight: 600;
            border-radius: 5px;
        }
        .btn-login:hover {
            background-color: #2980b9;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <img src="assets/images/LOGO-TEE.png" alt="Master Tee Logo">
                <h2>Invoice System</h2>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-login">Login</button>
                </div>
            </form>
            
            <div class="login-footer mt-3">
                <p class="mb-0">© <?php echo date('Y'); ?> Master Tee. All rights reserved.</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>