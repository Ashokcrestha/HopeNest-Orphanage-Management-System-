<?php
/**
 * Registration Page
 * Orphanage Management System
 */
$pageTitle = 'Register';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) redirect('user/dashboard.php');
if (isAdmin()) redirect('admin/dashboard.php');

$error = '';
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    // Validation
    if (empty($fullName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            // Create account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, address)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $email, $hashedPassword, $phone, $address]);

            // Auto login
            $userId = $pdo->lastInsertId();
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['role'] = 'user';
            setFlash('success', 'Registration successful! Complete your profile to unlock adoption features.');
            redirect('user/dashboard.php');
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-container" style="padding: 40px 20px;">
    <div class="auth-card" style="max-width: 600px;">
        <div class="auth-header">
            <h1><i class="fas fa-user-plus" style="color: var(--secondary);"></i> Create Account</h1>
            <p>Join HopeNest to donate, adopt, and make a difference</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(225,112,85,0.1); border: 1px solid rgba(225,112,85,0.3); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" data-validate>
            <div class="form-row">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" class="form-control" placeholder="Your full name" value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="Your phone number" value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" class="form-control" placeholder="Your full address" value="<?php echo htmlspecialchars($old['address'] ?? ''); ?>">
                </div>
            </div>



            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
