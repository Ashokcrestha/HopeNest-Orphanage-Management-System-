<?php
/**
 * Login Page
 * Orphanage Management System
 */
$pageTitle = 'Login';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn())
    redirect('user/dashboard.php');
if (isAdmin())
    redirect('admin/dashboard.php');

$error = '';

// Handle Admin Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_type'])) {
    $loginType = $_POST['login_type'];

    if ($loginType === 'admin') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['role'] = 'admin';
                setFlash('success', 'Welcome back, ' . $admin['full_name'] . '!');
                redirect('admin/dashboard.php');
            } else {
                $error = 'Invalid username or password.';
            }
        }
    } elseif ($loginType === 'user') {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['role'] = 'user';
                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                redirect('user/dashboard.php');
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1><i class="fas fa-heart" style="color: var(--accent);"></i> Welcome Back</h1>
            <p>Login to your account to continue</p>
        </div>

        <?php if ($error): ?>
            <div
                style="background: rgba(225,112,85,0.1); border: 1px solid rgba(225,112,85,0.3); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Login Type Tabs -->
        <div class="auth-tabs">
            <button class="auth-tab active" data-tab="userLogin" type="button">
                <i class="fas fa-user"></i> User Login
            </button>
            <button class="auth-tab" data-tab="adminLogin" type="button">
                <i class="fas fa-user-shield"></i> Admin Login
            </button>
        </div>

        <!-- User Login Form -->
        <div class="auth-form-section active" id="userLogin">
            <form method="POST" action="" data-validate>
                <input type="hidden" name="login_type" value="user">
                <div class="form-group">
                    <label for="userEmail"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="userEmail" name="email" class="form-control" placeholder="Enter your email"
                        required>
                </div>
                <div class="form-group">
                    <label for="userPassword"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="userPassword" name="password" class="form-control"
                        placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            <div class="auth-footer">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>

        <!-- Admin Login Form -->
        <div class="auth-form-section" id="adminLogin">
            <form method="POST" action="" data-validate>
                <input type="hidden" name="login_type" value="admin">
                <div class="form-group">
                    <label for="adminUsername"><i class="fas fa-user-shield"></i> Username</label>
                    <input type="text" id="adminUsername" name="username" class="form-control"
                        placeholder="Enter admin username" required>
                </div>
                <div class="form-group">
                    <label for="adminPassword"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="adminPassword" name="password" class="form-control"
                        placeholder="Enter admin password" required>
                </div>
                <button type="submit" class="btn btn-accent btn-block btn-lg">
                    <i class="fas fa-sign-in-alt"></i> Admin Login
                </button>
            </form>
            <div class="auth-footer mt-2">
                <span class="text-muted"><i class="fas fa-info-circle"></i> Default: admin / password</span>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>