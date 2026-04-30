<?php
/**
 * Admin - Verify Users
 * Orphanage Management System
 * 
 * Admin panel for reviewing and verifying user profiles.
 * Admins can view biodata, documents, health records, and approve/reject profiles.
 */
$pageTitle = 'Verify Users';
require_once '../includes/auth.php';
requireAdminLogin();

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetUserId = (int)$_POST['user_id'];
    $action = sanitize($_POST['action']);
    $note = sanitize($_POST['rejection_note'] ?? '');

    if ($action === 'verify') {
        $stmt = $pdo->prepare("UPDATE users SET profile_status = 'verified', profile_verified_at = NOW(), profile_rejection_note = NULL WHERE id = ?");
        $stmt->execute([$targetUserId]);
        setFlash('success', 'User profile has been verified successfully.');
    } elseif ($action === 'reject') {
        if (empty($note)) {
            setFlash('error', 'Please provide a rejection reason.');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET profile_status = 'rejected', profile_rejection_note = ?, profile_verified_at = NULL WHERE id = ?");
            $stmt->execute([$note, $targetUserId]);
            setFlash('success', 'User profile has been rejected with notes.');
        }
    }
    redirect('verify_users.php' . (isset($_GET['user_id']) ? '?user_id=' . $targetUserId : ''));
}

// Single user view
$singleUser = null;
$singleUserDocs = [];
if (isset($_GET['user_id'])) {
    $viewUserId = (int)$_GET['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$viewUserId]);
    $singleUser = $stmt->fetch();

    if ($singleUser) {
        $stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? ORDER BY doc_type");
        $stmt->execute([$viewUserId]);
        $singleUserDocs = $stmt->fetchAll();
    }
}

// Filter
$statusFilter = sanitize($_GET['status'] ?? '');
$where = "WHERE profile_status != 'incomplete'";
$params = [];
if ($statusFilter && in_array($statusFilter, ['pending', 'verified', 'rejected'])) {
    $where = "WHERE profile_status = ?";
    $params[] = $statusFilter;
}

// Fetch users for list view
$stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY profile_submitted_at DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Count by status
$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'pending'")->fetchColumn();
$verifiedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'verified'")->fetchColumn();
$rejectedCount = $pdo->query("SELECT COUNT(*) FROM users WHERE profile_status = 'rejected'")->fetchColumn();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-check"></i> Verify User Profiles</h1>
            <p class="page-subtitle"><?php echo $pendingCount; ?> profiles awaiting verification</p>
        </div>
        <?php if ($singleUser): ?>
        <a href="verify_users.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to List</a>
        <?php endif; ?>
    </div>

    <?php if ($singleUser): ?>
    <!-- ==================== SINGLE USER DETAIL VIEW ==================== -->
    <div class="detail-grid">
        <!-- Left Column: Biodata & Health -->
        <div>
            <!-- Verification Status -->
            <div class="card mb-2">
                <div class="card-body" style="text-align: center; padding: 24px;">
                    <span class="badge <?php echo getVerificationBadge($singleUser['profile_status']); ?>" style="font-size: 0.9rem; padding: 8px 20px;">
                        <?php echo ucfirst($singleUser['profile_status']); ?>
                    </span>
                    <?php if ($singleUser['profile_submitted_at']): ?>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">
                        Submitted: <?php echo formatDate($singleUser['profile_submitted_at']); ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($singleUser['profile_verified_at']): ?>
                    <p style="font-size: 0.8rem; color: var(--text-muted);">
                        Verified: <?php echo formatDate($singleUser['profile_verified_at']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Biodata Card -->
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-id-card text-primary"></i> Personal Biodata</h3>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <span class="detail-label">Full Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['full_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['phone'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date of Birth</span>
                        <span class="detail-value"><?php echo $singleUser['date_of_birth'] ? formatDate($singleUser['date_of_birth']) : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Gender</span>
                        <span class="detail-value"><?php echo ucfirst($singleUser['gender'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Marital Status</span>
                        <span class="detail-value"><?php echo ucfirst($singleUser['marital_status'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Address</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['address'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Location</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['location'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Occupation</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['occupation'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Annual Income</span>
                        <span class="detail-value"><?php echo $singleUser['annual_income'] ? formatCurrency($singleUser['annual_income']) : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Family Size</span>
                        <span class="detail-value"><?php echo $singleUser['family_size'] ?? '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Member Since</span>
                        <span class="detail-value"><?php echo formatDate($singleUser['created_at']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Health Records -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-heartbeat text-danger"></i> Health Records</h3>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <span class="detail-label">Blood Group</span>
                        <span class="detail-value"><span class="badge badge-primary"><?php echo $singleUser['blood_group'] ?? '-'; ?></span></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Health Conditions</span>
                        <span class="detail-value"><?php echo htmlspecialchars($singleUser['health_conditions'] ?: 'None reported'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Documents & Actions -->
        <div>
            <!-- Documents Card -->
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt text-warning"></i> Submitted Documents</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($singleUserDocs)): ?>
                    <div class="doc-review-grid">
                        <?php foreach ($singleUserDocs as $doc): ?>
                        <div class="doc-review-item">
                            <div class="doc-review-icon" style="background: rgba(108,92,231,0.15); color: var(--primary-light);">
                                <i class="fas <?php echo getDocTypeIcon($doc['doc_type']); ?>"></i>
                            </div>
                            <div class="doc-review-info">
                                <span class="doc-review-label"><?php echo getDocTypeLabel($doc['doc_type']); ?></span>
                                <span class="doc-review-status"><i class="fas fa-check-circle text-success"></i> Uploaded <?php echo formatDate($doc['uploaded_at']); ?></span>
                            </div>
                            <a href="/orphanage-management-system/uploads/profile_docs/<?php echo $doc['file_name']; ?>" target="_blank" class="btn btn-outline btn-sm">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state" style="padding: 30px;">
                        <i class="fas fa-file-alt" style="font-size: 2rem;"></i>
                        <p>No documents submitted yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Adoption Preferences (Extended) -->
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-sliders-h text-info"></i> Adoption Preferences</h3>
                </div>
                <div class="card-body">
                    <h4 style="font-size: 0.78rem; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-filter"></i> Basic Preferences
                    </h4>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-birthday-cake" style="color: var(--primary-light);"></i> Age Range</span>
                        <span class="detail-value"><?php echo $singleUser['age_preference_min']; ?> – <?php echo $singleUser['age_preference_max']; ?> years</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-venus-mars" style="color: var(--accent);"></i> Gender</span>
                        <span class="detail-value"><?php echo ucfirst($singleUser['gender_preference']); ?></span>
                    </div>

                    <div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-light);">
                        <h4 style="font-size: 0.78rem; color: var(--primary-light); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-sliders-h"></i> Advanced Preferences
                        </h4>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-heartbeat" style="color: #e17055;"></i> Health Condition</span>
                            <span class="detail-value">
                                <?php
                                $hp = $singleUser['health_preference'] ?? 'any';
                                $hpLabels = ['any' => 'Any (No Preference)', 'healthy' => 'Healthy Only', 'minor_issues' => 'Healthy or Minor Issues', 'special_needs' => 'Special Needs (Willing)'];
                                echo $hpLabels[$hp] ?? ucfirst($hp);
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-graduation-cap" style="color: #0984e3;"></i> Education Level</span>
                            <span class="detail-value"><?php echo htmlspecialchars($singleUser['education_preference'] ?: 'Any'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-sync-alt" style="color: #00b894;"></i> Child Adaptability</span>
                            <span class="detail-value">
                                <?php
                                $ap = $singleUser['adaptability_preference'] ?? 'any';
                                $apLabels = ['any' => 'Any (No Preference)', 'high' => 'High Adaptability', 'moderate' => 'Moderate Adaptability', 'low' => 'Low (Needs Extra Care)'];
                                echo $apLabels[$ap] ?? ucfirst($ap);
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-heart" style="color: #fdcb6e;"></i> Emotional Environment</span>
                            <span class="detail-value"><?php echo htmlspecialchars($singleUser['emotional_preference'] ?: '— Not specified'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-child" style="color: #6c5ce7;"></i> Behavior Preference</span>
                            <span class="detail-value">
                                <?php
                                $bp = $singleUser['behavior_preference'] ?? '';
                                $bpLabels = ['' => 'Any (No Preference)', 'calm' => 'Calm & Quiet', 'active' => 'Active & Energetic', 'social' => 'Social & Outgoing', 'independent' => 'Independent & Self-reliant', 'creative' => 'Creative & Artistic'];
                                echo $bpLabels[$bp] ?? ucfirst($bp);
                                ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-home" style="color: #00cec9;"></i> Family Background</span>
                            <span class="detail-value">
                                <?php
                                $fb = $singleUser['family_background_preference'] ?? '';
                                $fbLabels = ['' => 'Any (No Preference)', 'orphaned' => 'Fully Orphaned', 'single_parent' => 'Single Parent Background', 'abandoned' => 'Abandoned / Surrendered', 'displaced' => 'Displaced / Refugee'];
                                echo $fbLabels[$fb] ?? ucfirst($fb);
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejection Note (if rejected) -->
            <?php if ($singleUser['profile_status'] === 'rejected' && $singleUser['profile_rejection_note']): ?>
            <div class="card mb-2">
                <div class="card-body" style="background: rgba(225,112,85,0.08); border: 1px solid rgba(225,112,85,0.2);">
                    <strong style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Rejection Note:</strong>
                    <p style="margin-top: 6px; font-size: 0.9rem;"><?php echo htmlspecialchars($singleUser['profile_rejection_note']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Card -->
            <?php if ($singleUser['profile_status'] === 'pending' || $singleUser['profile_status'] === 'rejected'): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-gavel text-primary"></i> Verification Action</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="user_id" value="<?php echo $singleUser['id']; ?>">

                        <div class="form-group">
                            <label for="rejectionNote">Rejection Note (required if rejecting)</label>
                            <textarea id="rejectionNote" name="rejection_note" class="form-control" rows="3" placeholder="Explain why the profile is being rejected..."><?php echo htmlspecialchars($singleUser['profile_rejection_note'] ?? ''); ?></textarea>
                        </div>

                        <div class="btn-group" style="justify-content: stretch;">
                            <button type="submit" name="action" value="verify" class="btn btn-success" style="flex: 1;" data-confirm="Verify this user's profile? They will gain access to adoption features.">
                                <i class="fas fa-check-circle"></i> Verify Profile
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger" style="flex: 1;" data-confirm="Reject this profile? The user will be notified.">
                                <i class="fas fa-times-circle"></i> Reject Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php elseif ($singleUser['profile_status'] === 'verified'): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 30px;">
                    <i class="fas fa-check-circle text-success" style="font-size: 2.5rem; margin-bottom: 12px;"></i>
                    <h3 style="font-size: 1.1rem;">Profile Verified</h3>
                    <p style="font-size: 0.85rem; color: var(--text-secondary);">This user has been verified and has access to adoption features.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <!-- ==================== LIST VIEW ==================== -->

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <div class="auth-tabs" style="flex: 1; max-width: 600px; margin-bottom: 0;">
            <a href="verify_users.php" class="auth-tab <?php echo !$statusFilter ? 'active' : ''; ?>" style="text-decoration:none;">All (<?php echo count($users); ?>)</a>
            <a href="verify_users.php?status=pending" class="auth-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" style="text-decoration:none;">Pending (<?php echo $pendingCount; ?>)</a>
            <a href="verify_users.php?status=verified" class="auth-tab <?php echo $statusFilter === 'verified' ? 'active' : ''; ?>" style="text-decoration:none;">Verified (<?php echo $verifiedCount; ?>)</a>
            <a href="verify_users.php?status=rejected" class="auth-tab <?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" style="text-decoration:none;">Rejected (<?php echo $rejectedCount; ?>)</a>
        </div>
    </div>

    <?php if (!empty($users)): ?>
        <?php foreach ($users as $u): ?>
        <div class="card mb-2" style="border-left: 3px solid <?php echo $u['profile_status'] === 'verified' ? 'var(--success)' : ($u['profile_status'] === 'rejected' ? 'var(--danger)' : 'var(--warning)'); ?>;">
            <div class="card-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="profile-avatar" style="width: 45px; height: 45px; font-size: 1.1rem; flex-shrink: 0;">
                        <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h3 style="margin-bottom: 2px;"><?php echo htmlspecialchars($u['full_name']); ?></h3>
                        <span class="text-muted" style="font-size: 0.8rem;"><?php echo htmlspecialchars($u['email']); ?></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="badge <?php echo getVerificationBadge($u['profile_status']); ?>"><?php echo ucfirst($u['profile_status']); ?></span>
                    <a href="verify_users.php?user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Review
                    </a>
                </div>
            </div>

            <div class="grid-2" style="gap: 16px; font-size: 0.85rem;">
                <div>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label">Location</span>
                        <span class="detail-value"><?php echo htmlspecialchars($u['location'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label">Occupation</span>
                        <span class="detail-value"><?php echo htmlspecialchars($u['occupation'] ?? '-'); ?></span>
                    </div>
                </div>
                <div>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label">Blood Group</span>
                        <span class="detail-value"><?php echo $u['blood_group'] ?? '-'; ?></span>
                    </div>
                    <div class="detail-row" style="padding: 8px 0;">
                        <span class="detail-label">Submitted</span>
                        <span class="detail-value"><?php echo $u['profile_submitted_at'] ? formatDate($u['profile_submitted_at']) : '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-user-check"></i>
                <h3>No profiles to verify</h3>
                <p>No user profiles match the selected filter.</p>
            </div>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
