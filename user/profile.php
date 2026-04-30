<?php
/**
 * User - Profile
 * Orphanage Management System
 */
$pageTitle = 'My Profile';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $occupation = sanitize($_POST['occupation'] ?? '');
    $annualIncome = (float)($_POST['annual_income'] ?? 0);
    $familySize = (int)($_POST['family_size'] ?? 1);

    // Handle password change
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullName)) {
        setFlash('error', 'Name cannot be empty.');
    } else {
        // Update profile (preferences are managed via complete_profile.php)
        $stmt = $pdo->prepare("
            UPDATE users SET
                full_name = ?, phone = ?, address = ?, location = ?, occupation = ?,
                annual_income = ?, family_size = ?
            WHERE id = ?
        ");
        $stmt->execute([$fullName, $phone, $address, $location, $occupation, $annualIncome, $familySize, $userId]);

        // Update password if provided
        if (!empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                setFlash('error', 'Passwords do not match.');
                redirect('profile.php');
                exit;
            }
            if (strlen($newPassword) < 6) {
                setFlash('error', 'Password must be at least 6 characters.');
                redirect('profile.php');
                exit;
            }
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $userId]);
        }

        $_SESSION['user_name'] = $fullName;
        setFlash('success', 'Profile updated successfully!');
        redirect('profile.php');
    }
}

// Stats
$totalDonated = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE user_id = ?");
$totalDonated->execute([$userId]);
$totalDonated = $totalDonated->fetchColumn();

$requestCount = $pdo->prepare("SELECT COUNT(*) FROM adoption_requests WHERE user_id = ?");
$requestCount->execute([$userId]);
$requestCount = $requestCount->fetchColumn();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user"></i> My Profile</h1>
            <p class="page-subtitle">Manage your account settings and preferences</p>
        </div>
    </div>

    <div class="detail-grid">
        <!-- Profile Form -->
        <div>
            <div class="card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge <?php echo getVerificationBadge($user['profile_status'] ?? 'incomplete'); ?>" style="margin-top: 6px;">
                            <i class="fas fa-<?php echo ($user['profile_status'] ?? 'incomplete') === 'verified' ? 'check-circle' : (($user['profile_status'] ?? 'incomplete') === 'pending' ? 'clock' : (($user['profile_status'] ?? 'incomplete') === 'rejected' ? 'times-circle' : 'info-circle')); ?>"></i>
                            Profile <?php echo ucfirst($user['profile_status'] ?? 'incomplete'); ?>
                        </span>
                    </div>
                </div>

                <?php if (($user['profile_status'] ?? 'incomplete') === 'incomplete'): ?>
                <div class="verification-banner verification-info" style="margin-bottom: 24px;">
                    <div class="verification-banner-icon"><i class="fas fa-user-edit"></i></div>
                    <div class="verification-banner-content">
                        <strong>Profile Incomplete</strong>
                        <p>Complete your profile with biodata, documents, and health records to apply for adoption.</p>
                    </div>
                    <a href="complete_profile.php" class="btn btn-primary btn-sm">Complete Profile</a>
                </div>
                <?php elseif (($user['profile_status'] ?? 'incomplete') === 'rejected'): ?>
                <div class="verification-banner verification-rejected" style="margin-bottom: 24px;">
                    <div class="verification-banner-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="verification-banner-content">
                        <strong>Profile Rejected</strong>
                        <p><?php echo htmlspecialchars($user['profile_rejection_note'] ?? 'Please update your profile.'); ?></p>
                    </div>
                    <a href="complete_profile.php" class="btn btn-danger btn-sm">Re-submit Profile</a>
                </div>
                <?php elseif (($user['profile_status'] ?? 'incomplete') === 'pending'): ?>
                <div class="verification-banner verification-pending" style="margin-bottom: 24px;">
                    <div class="verification-banner-icon"><i class="fas fa-clock"></i></div>
                    <div class="verification-banner-content">
                        <strong>Under Review</strong>
                        <p>Your profile is being reviewed by our team.</p>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" data-validate>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 16px; color: var(--text-secondary);"><i class="fas fa-id-card"></i> Personal Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email (cannot change)</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="occupation">Occupation</label>
                            <input type="text" id="occupation" name="occupation" class="form-control" value="<?php echo htmlspecialchars($user['occupation'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="annualIncome">Annual Income (NPR)</label>
                            <input type="number" id="annualIncome" name="annual_income" class="form-control" value="<?php echo $user['annual_income'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="familySize">Family Size</label>
                            <input type="number" id="familySize" name="family_size" class="form-control" min="1" value="<?php echo $user['family_size'] ?? 1; ?>">
                        </div>
                    </div>


                    <hr style="border-color: var(--border-light); margin: 24px 0;">
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 16px; color: var(--text-secondary);"><i class="fas fa-lock"></i> Change Password (Optional)</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newPassword">New Password</label>
                            <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Repeat new password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg mt-2">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats Sidebar -->
        <div>
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie text-primary"></i> Account Summary</h3>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <span class="detail-label">Member Since</span>
                        <span class="detail-value"><?php echo formatDate($user['created_at']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total Donated</span>
                        <span class="detail-value text-success"><?php echo formatCurrency($totalDonated); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Adoption Requests</span>
                        <span class="detail-value"><?php echo $requestCount; ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sliders-h text-info"></i> Adoption Preferences</h3>
                    <?php if (($user['profile_status'] ?? 'incomplete') !== 'incomplete'): ?>
                    <a href="complete_profile.php" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i> Update</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (($user['profile_status'] ?? 'incomplete') === 'incomplete'): ?>
                    <div style="text-align: center; padding: 20px 0;">
                        <i class="fas fa-sliders-h" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 10px;"></i>
                        <p style="font-size: 0.85rem; color: var(--text-secondary);">Complete your profile to set adoption preferences.</p>
                        <a href="complete_profile.php" class="btn btn-sm btn-primary" style="margin-top: 8px;">Set Preferences</a>
                    </div>
                    <?php else: ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-birthday-cake" style="color: var(--primary); width: 18px;"></i> Age Range</span>
                        <span class="detail-value"><?php echo ($user['age_preference_min'] ?? 0) . ' - ' . ($user['age_preference_max'] ?? 18) . ' years'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-venus-mars" style="color: var(--secondary); width: 18px;"></i> Gender</span>
                        <span class="detail-value"><?php echo ucfirst($user['gender_preference'] ?? 'any'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-heartbeat" style="color: var(--danger); width: 18px;"></i> Health</span>
                        <span class="detail-value">
                            <?php
                            $hpLabels = ['any' => 'Any', 'healthy' => 'Healthy Only', 'minor_issues' => 'Minor Issues OK', 'special_needs' => 'Special Needs'];
                            echo $hpLabels[$user['health_preference'] ?? 'any'] ?? 'Any';
                            ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-graduation-cap" style="color: var(--info); width: 18px;"></i> Education</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['education_preference'] ?? '') ?: 'Any'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-child" style="color: var(--primary); width: 18px;"></i> Behavior</span>
                        <span class="detail-value">
                            <?php
                            $bLabels = ['calm' => 'Calm & Quiet', 'active' => 'Active & Energetic', 'social' => 'Social & Outgoing', 'independent' => 'Independent', 'creative' => 'Creative & Artistic'];
                            echo $bLabels[$user['behavior_preference'] ?? ''] ?? 'Any';
                            ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-heart" style="color: var(--warning); width: 18px;"></i> Personality / Emotional</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['emotional_preference'] ?? '') ?: 'Any'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sync-alt" style="color: var(--success); width: 18px;"></i> Adaptability</span>
                        <span class="detail-value"><?php echo ucfirst($user['adaptability_preference'] ?? 'any'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-home" style="color: var(--info); width: 18px;"></i> Family Background</span>
                        <span class="detail-value">
                            <?php
                            $fbLabels = ['orphaned' => 'Fully Orphaned', 'single_parent' => 'Single Parent', 'abandoned' => 'Abandoned / Surrendered', 'displaced' => 'Displaced / Refugee'];
                            echo $fbLabels[$user['family_background_preference'] ?? ''] ?? 'Any';
                            ?>
                        </span>
                    </div>
                    <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-light);">
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.6;">
                            <i class="fas fa-info-circle"></i> These preferences power our <strong>Child-Adopter Matching Algorithm</strong>. Update them via <a href="complete_profile.php" style="color: var(--primary);">profile completion</a>.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
