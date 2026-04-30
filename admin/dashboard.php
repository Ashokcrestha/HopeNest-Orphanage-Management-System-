<?php
/**
 * Admin Dashboard
 * Orphanage Management System
 * 
 * All data is retrieved using JOIN queries for cross-table relationships.
 * Adoption updates both the children table and adoption records.
 * Stats reflect the full adoption lifecycle: available → reserved → adopted.
 */
$pageTitle = 'Admin Dashboard';
require_once '../includes/auth.php';
requireAdminLogin();

// ============================================
// STATISTICS (using joined & aggregate queries)
// ============================================

// Child status counts (lifecycle: available / reserved / adopted)
$childStats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN availability_status = 'pending' THEN 1 ELSE 0 END) as reserved,
        SUM(CASE WHEN availability_status = 'adopted' THEN 1 ELSE 0 END) as adopted
    FROM orphans
")->fetch();

// User & donation stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalDonations = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM donations")->fetchColumn();
$pendingVerifications = $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'pending'")->fetchColumn();

// Adoption request stats (full lifecycle)
$adoptionStats = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'meeting_scheduled' THEN 1 ELSE 0 END) as meetings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM adoption_requests
")->fetch();

// Recent adoption requests (JOIN: users + orphans + requests)
$recentRequests = $pdo->query("
    SELECT ar.id, ar.status, ar.matching_score, ar.applied_at,
           u.full_name as user_name, u.profile_status,
           o.full_name as orphan_name, o.availability_status
    FROM adoption_requests ar
    JOIN users u ON ar.user_id = u.id
    JOIN orphans o ON ar.orphan_id = o.id
    ORDER BY ar.applied_at DESC
    LIMIT 5
")->fetchAll();

// Recent donations (JOIN: users + donations)
$recentDonations = $pdo->query("
    SELECT d.amount, d.donated_at, d.payment_method, d.donation_type, d.item_description,
           u.full_name as user_name
    FROM donations d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.donated_at DESC
    LIMIT 5
")->fetchAll();

// Monthly donations for chart
$monthlyDonations = $pdo->query("
    SELECT DATE_FORMAT(donated_at, '%Y-%m') as month, SUM(amount) as total
    FROM donations
    GROUP BY DATE_FORMAT(donated_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
")->fetchAll();

// Preference distribution stats (JOIN: users + preferences)
$prefStats = $pdo->query("
    SELECT 
        COUNT(CASE WHEN gender_preference = 'male' THEN 1 END) as pref_male,
        COUNT(CASE WHEN gender_preference = 'female' THEN 1 END) as pref_female,
        COUNT(CASE WHEN gender_preference = 'any' THEN 1 END) as pref_any_gender,
        COUNT(CASE WHEN health_preference = 'healthy' THEN 1 END) as pref_healthy,
        COUNT(CASE WHEN health_preference = 'special_needs' THEN 1 END) as pref_special,
        COUNT(CASE WHEN behavior_preference IS NOT NULL AND behavior_preference != '' THEN 1 END) as has_behavior_pref,
        COUNT(CASE WHEN family_background_preference IS NOT NULL AND family_background_preference != '' THEN 1 END) as has_family_pref,
        AVG(age_preference_min) as avg_age_min,
        AVG(age_preference_max) as avg_age_max
    FROM users
    WHERE profile_status = 'verified'
")->fetch();

// Recent preference updates (users who recently updated their preferences)
$recentPrefUpdates = $pdo->query("
    SELECT u.id, u.full_name, u.email, u.profile_status, u.updated_at,
           u.gender_preference, u.health_preference, u.behavior_preference,
           u.family_background_preference, u.age_preference_min, u.age_preference_max
    FROM users u
    WHERE u.profile_status IN ('verified', 'pending')
      AND (u.gender_preference IS NOT NULL OR u.health_preference IS NOT NULL)
    ORDER BY u.updated_at DESC
    LIMIT 5
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-th-large"></i> Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?php echo $_SESSION['admin_name']; ?>! Here's your overview.</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stat-grid">
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-child"></i></div>
            <div class="stat-value"><?php echo $childStats['total']; ?></div>
            <div class="stat-label">Total Children</div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
        <div class="stat-card pink">
            <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div class="stat-value"><?php echo formatCurrency($totalDonations); ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-value"><?php echo $adoptionStats['completed'] ?? 0; ?></div>
            <div class="stat-label">Completed Adoptions</div>
        </div>
    </div>

    <!-- Pending Verifications Alert -->
    <?php if ($pendingVerifications > 0): ?>
    <div class="verification-banner verification-pending" style="margin-bottom: 24px;">
        <div class="verification-banner-icon"><i class="fas fa-user-check"></i></div>
        <div class="verification-banner-content">
            <strong><?php echo $pendingVerifications; ?> Profile<?php echo $pendingVerifications > 1 ? 's' : ''; ?> Awaiting Verification</strong>
            <p>User profiles are waiting for your review and approval before they can access adoption features.</p>
        </div>
        <a href="verify_users.php?status=pending" class="btn btn-primary btn-sm">Review Now</a>
    </div>
    <?php endif; ?>

    <!-- Child Status Lifecycle + Adoption Pipeline -->
    <div class="grid-2 mb-3">
        <!-- Child Status Lifecycle -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-child text-primary"></i> Child Status Lifecycle</h3>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label"><span class="badge badge-success" style="margin-right: 6px;">&#9679;</span> Available</span>
                    <span class="detail-value" style="font-weight: 700; font-size: 1.1rem;"><?php echo $childStats['available']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><span class="badge badge-warning" style="margin-right: 6px;">&#9679;</span> Reserved</span>
                    <span class="detail-value" style="font-weight: 700; font-size: 1.1rem;"><?php echo $childStats['reserved']; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><span class="badge badge-info" style="margin-right: 6px;">&#9679;</span> Adopted</span>
                    <span class="detail-value" style="font-weight: 700; font-size: 1.1rem;"><?php echo $childStats['adopted']; ?></span>
                </div>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light); text-align: center;">
                    <span style="font-size: 0.75rem; color: var(--text-muted); letter-spacing: 0.3px;">
                        Available &rarr; Reserved &rarr; Adopted (or reverted to Available)
                    </span>
                </div>
            </div>
        </div>

        <!-- Adoption Pipeline -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-stream text-info"></i> Adoption Pipeline</h3>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-clock text-warning"></i> Pending Review</span>
                    <span class="detail-value text-warning" style="font-weight: 700;"><?php echo $adoptionStats['pending'] ?? 0; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-check-circle text-info"></i> Approved</span>
                    <span class="detail-value text-info" style="font-weight: 700;"><?php echo $adoptionStats['approved'] ?? 0; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-handshake text-primary"></i> Meetings Scheduled</span>
                    <span class="detail-value text-primary" style="font-weight: 700;"><?php echo $adoptionStats['meetings'] ?? 0; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-check-double text-success"></i> Completed</span>
                    <span class="detail-value text-success" style="font-weight: 700;"><?php echo $adoptionStats['completed'] ?? 0; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-ban" style="color: var(--text-muted);"></i> Cancelled / Rejected</span>
                    <span class="detail-value" style="font-weight: 700; color: var(--text-muted);"><?php echo ($adoptionStats['cancelled'] ?? 0) + ($adoptionStats['rejected'] ?? 0); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Donations + Verification Summary -->
    <div class="grid-2 mb-3">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar text-info"></i> Monthly Donations</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($monthlyDonations)): ?>
                    <?php foreach (array_reverse($monthlyDonations) as $md): ?>
                    <div class="detail-row">
                        <span class="detail-label"><?php echo date('M Y', strtotime($md['month'] . '-01')); ?></span>
                        <span class="detail-value"><?php echo formatCurrency($md['total']); ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">No donation data yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-shield-alt text-warning"></i> Verification Summary</h3>
            </div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="detail-label">Pending Verifications</span>
                    <span class="detail-value text-warning" style="font-weight: 700;"><?php echo $pendingVerifications; ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Verified Users</span>
                    <span class="detail-value text-success" style="font-weight: 700;"><?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'verified'")->fetchColumn(); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Incomplete Profiles</span>
                    <span class="detail-value" style="font-weight: 700; color: var(--text-muted);"><?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'incomplete'")->fetchColumn(); ?></span>
                </div>
                <div style="margin-top: 16px;">
                    <a href="verify_users.php" class="btn btn-outline btn-sm btn-block"><i class="fas fa-user-check"></i> Manage Verifications</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid-2">
        <!-- Recent Adoption Requests (JOIN: users + orphans + requests) -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt text-primary"></i> Recent Adoption Requests</h3>
                <a href="adoptions.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentRequests)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Child</th>
                                <th>Status</th>
                                <th>Score</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRequests as $req): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($req['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($req['orphan_name']); ?></td>
                                <td><span class="badge <?php echo getStatusBadge($req['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span></td>
                                <td>
                                    <?php if ($req['matching_score']): ?>
                                    <span class="<?php echo $req['matching_score'] >= 70 ? 'text-success' : ($req['matching_score'] >= 40 ? 'text-warning' : 'text-danger'); ?>" style="font-weight: 700;"><?php echo $req['matching_score']; ?>%</span>
                                    <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($req['applied_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No adoption requests yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Donations (JOIN: users + donations) -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hand-holding-heart" style="color: var(--accent);"></i> Recent Donations</h3>
                <a href="donations.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentDonations)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Donor</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $typeIcons = ['monetary'=>'fa-money-bill-wave','clothing'=>'fa-tshirt','food'=>'fa-utensils','toys'=>'fa-puzzle-piece','educational'=>'fa-book-open','supplies'=>'fa-box-open','other'=>'fa-gift'];
                            foreach ($recentDonations as $don):
                                $dtype = $don['donation_type'] ?? 'monetary';
                                $icon = $typeIcons[$dtype] ?? 'fa-gift';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($don['user_name']); ?></td>
                                <td><i class="fas <?php echo $icon; ?>" title="<?php echo ucfirst($dtype); ?>"></i> <?php echo ucfirst($dtype); ?></td>
                                <td class="text-success"><?php echo $don['amount'] > 0 ? formatCurrency($don['amount']) : '—'; ?></td>
                                <td><?php echo formatDate($don['donated_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No donations yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Preference Insights (Real-time JOIN: users preference data) -->
    <div class="grid-2 mb-3" style="margin-top: 24px;">
        <!-- Preference Distribution -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sliders-h text-info"></i> Preference Distribution</h3>
                <span class="badge badge-primary" style="font-size: 0.7rem;">Live from user data</span>
            </div>
            <div class="card-body">
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 16px;">
                    <i class="fas fa-database"></i> Aggregated from verified user profiles via relational JOINs
                </p>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-birthday-cake" style="color: #6c5ce7;"></i> Avg Age Range</span>
                    <span class="detail-value" style="font-weight: 700;"><?php echo round($prefStats['avg_age_min'] ?? 0); ?> – <?php echo round($prefStats['avg_age_max'] ?? 18); ?> yrs</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-venus-mars" style="color: #fd79a8;"></i> Gender Preferences</span>
                    <span class="detail-value">
                        <span class="badge badge-primary" style="font-size: 0.7rem; margin-right: 4px;">M: <?php echo $prefStats['pref_male'] ?? 0; ?></span>
                        <span class="badge badge-info" style="font-size: 0.7rem; margin-right: 4px;">F: <?php echo $prefStats['pref_female'] ?? 0; ?></span>
                        <span class="badge badge-success" style="font-size: 0.7rem;">Any: <?php echo $prefStats['pref_any_gender'] ?? 0; ?></span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-heartbeat" style="color: #e17055;"></i> Health Preferences</span>
                    <span class="detail-value">
                        <span class="badge badge-success" style="font-size: 0.7rem; margin-right: 4px;">Healthy: <?php echo $prefStats['pref_healthy'] ?? 0; ?></span>
                        <span class="badge badge-warning" style="font-size: 0.7rem;">Special Needs: <?php echo $prefStats['pref_special'] ?? 0; ?></span>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-child" style="color: #6c5ce7;"></i> Behavior Pref Set</span>
                    <span class="detail-value" style="font-weight: 700;"><?php echo $prefStats['has_behavior_pref'] ?? 0; ?> users</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-home" style="color: #00cec9;"></i> Family Bg Pref Set</span>
                    <span class="detail-value" style="font-weight: 700;"><?php echo $prefStats['has_family_pref'] ?? 0; ?> users</span>
                </div>
            </div>
        </div>

        <!-- Recent Preference Updates -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sync-alt text-success"></i> Recent Preference Updates</h3>
                <a href="users.php" class="btn btn-sm btn-outline">View All Users</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentPrefUpdates)): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Age Pref</th>
                                <th>Gender</th>
                                <th>Behavior</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPrefUpdates as $pu): ?>
                            <tr>
                                <td>
                                    <a href="verify_users.php?user_id=<?php echo $pu['id']; ?>" style="color: var(--primary-light); text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($pu['full_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo $pu['age_preference_min']; ?>–<?php echo $pu['age_preference_max']; ?></td>
                                <td><?php echo ucfirst($pu['gender_preference'] ?? 'any'); ?></td>
                                <td><?php echo ucfirst($pu['behavior_preference'] ?: 'any'); ?></td>
                                <td><span class="badge <?php echo getVerificationBadge($pu['profile_status']); ?>"><?php echo ucfirst($pu['profile_status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-sliders-h"></i>
                        <p>No preference data yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
