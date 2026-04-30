<?php
/**
 * User Dashboard
 * Orphanage Management System
 */
$pageTitle = 'Dashboard';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Stats
$totalDonated = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE user_id = ?");
$totalDonated->execute([$userId]);
$totalDonated = $totalDonated->fetchColumn();

$donationCount = $pdo->prepare("SELECT COUNT(*) FROM donations WHERE user_id = ?");
$donationCount->execute([$userId]);
$donationCount = $donationCount->fetchColumn();

$pendingRequests = $pdo->prepare("SELECT COUNT(*) FROM adoption_requests WHERE user_id = ? AND status = 'pending'");
$pendingRequests->execute([$userId]);
$pendingRequests = $pendingRequests->fetchColumn();

$approvedRequests = $pdo->prepare("SELECT COUNT(*) FROM adoption_requests WHERE user_id = ? AND status = 'approved'");
$approvedRequests->execute([$userId]);
$approvedRequests = $approvedRequests->fetchColumn();

$availableChildren = $pdo->query("SELECT COUNT(*) FROM orphans WHERE availability_status = 'available'")->fetchColumn();

// Recent donations
$stmt = $pdo->prepare("SELECT * FROM donations WHERE user_id = ? ORDER BY donated_at DESC LIMIT 3");
$stmt->execute([$userId]);
$recentDonations = $stmt->fetchAll();

// Recent adoption requests
$stmt = $pdo->prepare("
    SELECT ar.*, o.full_name as orphan_name
    FROM adoption_requests ar
    JOIN orphans o ON ar.orphan_id = o.id
    WHERE ar.user_id = ?
    ORDER BY ar.applied_at DESC LIMIT 3
");
$stmt->execute([$userId]);
$recentRequests = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-th-large"></i> Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
            <p class="page-subtitle">Here's a summary of your activity on HopeNest</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stat-grid">
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="stat-value"><?php echo formatCurrency($totalDonated); ?></div>
            <div class="stat-label">Total Donated</div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-value"><?php echo $donationCount; ?></div>
            <div class="stat-label">Donations Made</div>
        </div>
        <div class="stat-card pink">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo $pendingRequests; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-child"></i></div>
            <div class="stat-value"><?php echo $availableChildren; ?></div>
            <div class="stat-label">Children Available</div>
        </div>
    </div>

    <?php
    $profileStatus = getProfileStatus($pdo, $userId);
    ?>

    <!-- Profile Verification Status -->
    <?php if ($profileStatus === 'incomplete'): ?>
    <div class="verification-banner verification-info">
        <div class="verification-banner-icon"><i class="fas fa-user-edit"></i></div>
        <div class="verification-banner-content">
            <strong>Complete Your Profile to Unlock Adoption</strong>
            <p>Submit your biodata, identity documents, and health records. Once verified by our team, you'll gain access to adoption features and child recommendations.</p>
        </div>
        <a href="complete_profile.php" class="btn btn-primary btn-sm">Complete Profile</a>
    </div>
    <?php elseif ($profileStatus === 'pending'): ?>
    <div class="verification-banner verification-pending">
        <div class="verification-banner-icon"><i class="fas fa-clock"></i></div>
        <div class="verification-banner-content">
            <strong>Profile Under Review</strong>
            <p>Your profile has been submitted for verification. You can continue browsing children and donating while our team reviews your information.</p>
        </div>
    </div>
    <?php elseif ($profileStatus === 'rejected'): ?>
    <div class="verification-banner verification-rejected">
        <div class="verification-banner-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="verification-banner-content">
            <strong>Profile Verification Rejected</strong>
            <p>Please review the feedback and update your profile to re-submit for verification.</p>
        </div>
        <a href="complete_profile.php" class="btn btn-danger btn-sm">Update Profile</a>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-bolt text-warning"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="btn-group">
                <a href="orphans.php" class="btn btn-primary"><i class="fas fa-child"></i> Browse Children</a>
                <a href="donate.php" class="btn btn-secondary"><i class="fas fa-hand-holding-heart"></i> Make a Donation</a>
                <?php if ($profileStatus === 'verified'): ?>
                <a href="adopt.php" class="btn btn-accent"><i class="fas fa-heart"></i> Apply for Adoption</a>
                <?php else: ?>
                <a href="complete_profile.php" class="btn btn-accent"><i class="fas fa-user-edit"></i> Complete Profile</a>
                <?php endif; ?>
                <a href="my_requests.php" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> View My Requests</a>
            </div>
        </div>
    </div>

    <div class="grid-2">
        <!-- Recent Donations -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hand-holding-heart" style="color: var(--accent);"></i> Recent Donations</h3>
                <a href="donate.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentDonations)): ?>
                    <?php foreach ($recentDonations as $don): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php echo formatDate($don['donated_at']); ?></span>
                        <span class="detail-value text-success"><?php echo formatCurrency($don['amount']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 30px;">
                        <i class="fas fa-hand-holding-heart" style="font-size: 2rem;"></i>
                        <p>No donations yet.</p>
                        <a href="donate.php" class="btn btn-sm btn-primary">Make First Donation</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Requests -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt text-primary"></i> Adoption Requests</h3>
                <a href="my_requests.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentRequests)): ?>
                    <?php foreach ($recentRequests as $req): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php echo htmlspecialchars($req['orphan_name']); ?></span>
                        <span class="detail-value"><span class="badge <?php echo getStatusBadge($req['status']); ?>"><?php echo ucfirst($req['status']); ?></span></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 30px;">
                        <i class="fas fa-file-alt" style="font-size: 2rem;"></i>
                        <p>No adoption requests yet.</p>
                        <a href="orphans.php" class="btn btn-sm btn-primary">Browse Children</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
