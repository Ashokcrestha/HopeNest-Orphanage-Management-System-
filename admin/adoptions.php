<?php
/**
 * Admin - Manage Adoption Requests
 * Orphanage Management System
 * 
 * Multi-stage adoption workflow:
 * pending → approved → meeting_scheduled → completed
 *                    → cancelled (with reason)
 * pending → rejected
 */
$pageTitle = 'Adoption Requests';
require_once '../includes/auth.php';
require_once '../includes/matching_algorithm.php';
requireAdminLogin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = (int)$_POST['request_id'];
    $action = sanitize($_POST['action']);
    $adminNotes = sanitize($_POST['admin_notes'] ?? '');

    if ($action === 'approved') {
        $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
        $stmt->execute([$adminNotes, $requestId]);
        setFlash('success', 'Adoption request approved. Schedule a face-to-face meeting next.');

    } elseif ($action === 'rejected') {
        $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
        $stmt->execute([$adminNotes, $requestId]);

        // Reset orphan to available
        $stmt = $pdo->prepare("SELECT orphan_id FROM adoption_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if ($req) {
            $stmt = $pdo->prepare("UPDATE orphans SET availability_status = 'available' WHERE id = ? AND availability_status = 'pending'");
            $stmt->execute([$req['orphan_id']]);
        }

        // Reject other pending requests for the same orphan
        setFlash('success', 'Adoption request has been rejected.');

    } elseif ($action === 'schedule_meeting') {
        $meetingDate = sanitize($_POST['meeting_date'] ?? '');
        $meetingNotes = sanitize($_POST['meeting_notes'] ?? '');
        
        if (empty($meetingDate)) {
            setFlash('error', 'Please select a meeting date.');
        } else {
            $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'meeting_scheduled', meeting_date = ?, meeting_notes = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$meetingDate, $meetingNotes, $adminNotes, $requestId]);
            setFlash('success', 'Face-to-face meeting scheduled for ' . date('M d, Y', strtotime($meetingDate)));
        }

    } elseif ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'completed', admin_notes = ? WHERE id = ?");
        $stmt->execute([$adminNotes, $requestId]);

        // Mark orphan as adopted
        $stmt = $pdo->prepare("SELECT orphan_id FROM adoption_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();
        if ($req) {
            $stmt = $pdo->prepare("UPDATE orphans SET availability_status = 'adopted' WHERE id = ?");
            $stmt->execute([$req['orphan_id']]);

            // Reject other pending requests for the same orphan
            $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'rejected', admin_notes = 'Another applicant was selected for this child.' WHERE orphan_id = ? AND id != ? AND status IN ('pending','approved','meeting_scheduled')");
            $stmt->execute([$req['orphan_id'], $requestId]);
        }
        setFlash('success', 'Adoption finalized successfully! The child has been marked as adopted.');

    } elseif ($action === 'cancel') {
        $cancellationReason = sanitize($_POST['cancellation_reason'] ?? '');
        if (empty($cancellationReason)) {
            setFlash('error', 'Please provide a reason for cancellation.');
        } else {
            $stmt = $pdo->prepare("UPDATE adoption_requests SET status = 'cancelled', cancellation_reason = ?, admin_notes = ? WHERE id = ?");
            $stmt->execute([$cancellationReason, $adminNotes, $requestId]);

            // Reset orphan back to available
            $stmt = $pdo->prepare("SELECT orphan_id FROM adoption_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $req = $stmt->fetch();
            if ($req) {
                $stmt = $pdo->prepare("UPDATE orphans SET availability_status = 'available' WHERE id = ?");
                $stmt->execute([$req['orphan_id']]);
            }
            setFlash('success', 'Adoption cancelled. The child is now available for other applicants.');
        }
    }

    redirect('adoptions.php');
}

// Filters
$statusFilter = sanitize($_GET['status'] ?? '');
$where = '';
$params = [];
if ($statusFilter) {
    $where = 'WHERE ar.status = ?';
    $params[] = $statusFilter;
}

// Fetch all adoption requests with FULL extended attributes via JOINs
$stmt = $pdo->prepare("
    SELECT ar.*,
           -- Adopter info + extended preferences
           u.full_name as user_name, u.email as user_email, u.phone as user_phone,
           u.location as user_location, u.occupation, u.annual_income, u.family_size,
           u.profile_status, u.gender as user_gender, u.marital_status,
           u.age_preference_min, u.age_preference_max, u.gender_preference,
           u.health_preference, u.education_preference,
           u.emotional_preference, u.adaptability_preference,
           u.behavior_preference, u.family_background_preference,
           -- Child info + extended attributes
           o.full_name as orphan_name, TIMESTAMPDIFF(YEAR, o.date_of_birth, CURDATE()) AS age,
           o.gender, o.health_status, o.health_details, o.location as orphan_location,
           o.education_level, o.behavioral_traits, o.personality, o.emotional_needs,
           o.adaptability_level, o.background, o.photo as orphan_photo
    FROM adoption_requests ar
    JOIN users u ON ar.user_id = u.id
    JOIN orphans o ON ar.orphan_id = o.id
    $where
    ORDER BY ar.applied_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Calculate matching scores for pending requests
foreach ($requests as &$req) {
    if ($req['status'] === 'pending' && empty($req['matching_score'])) {
        $scores = storeMatchingScore($pdo, $req['user_id'], $req['orphan_id']);
        if ($scores) {
            $req['matching_score'] = $scores['total_score'];
            $stmt2 = $pdo->prepare("UPDATE adoption_requests SET matching_score = ? WHERE id = ?");
            $stmt2->execute([$scores['total_score'], $req['id']]);
        }
    }
}

// Status counts
$counts = [];
foreach (['pending', 'approved', 'meeting_scheduled', 'completed', 'rejected', 'cancelled'] as $s) {
    $counts[$s] = $pdo->query("SELECT COUNT(*) FROM adoption_requests WHERE status = '$s'")->fetchColumn();
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-alt"></i> Adoption Requests</h1>
            <p class="page-subtitle"><?php echo count($requests); ?> total requests</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <div class="auth-tabs" style="flex: 1; max-width: 800px; margin-bottom: 0;">
            <a href="adoptions.php" class="auth-tab <?php echo !$statusFilter ? 'active' : ''; ?>" style="text-decoration:none;">All</a>
            <a href="adoptions.php?status=pending" class="auth-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" style="text-decoration:none;">Pending (<?php echo $counts['pending']; ?>)</a>
            <a href="adoptions.php?status=approved" class="auth-tab <?php echo $statusFilter === 'approved' ? 'active' : ''; ?>" style="text-decoration:none;">Approved (<?php echo $counts['approved']; ?>)</a>
            <a href="adoptions.php?status=meeting_scheduled" class="auth-tab <?php echo $statusFilter === 'meeting_scheduled' ? 'active' : ''; ?>" style="text-decoration:none;">Meeting (<?php echo $counts['meeting_scheduled']; ?>)</a>
            <a href="adoptions.php?status=completed" class="auth-tab <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>" style="text-decoration:none;">Completed (<?php echo $counts['completed']; ?>)</a>
            <a href="adoptions.php?status=cancelled" class="auth-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>" style="text-decoration:none;">Cancelled (<?php echo $counts['cancelled']; ?>)</a>
        </div>
    </div>

    <!-- Requests -->
    <?php if (!empty($requests)): ?>
        <?php foreach ($requests as $req): ?>
        <?php
            // Status-based border color
            $borderColors = [
                'pending' => 'var(--warning)',
                'approved' => 'var(--info)',
                'meeting_scheduled' => 'var(--primary-light)',
                'completed' => 'var(--success)',
                'rejected' => 'var(--danger)',
                'cancelled' => 'var(--text-muted)',
            ];
            $borderColor = $borderColors[$req['status']] ?? 'var(--border-light)';
        ?>
        <div class="card mb-2" style="border-left: 3px solid <?php echo $borderColor; ?>;">
            <div class="card-header">
                <div>
                    <h3>
                        <span class="badge <?php echo getStatusBadge($req['status']); ?>" style="margin-right: 8px;"><?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?></span>
                        <?php echo htmlspecialchars($req['user_name']); ?> → <?php echo htmlspecialchars($req['orphan_name']); ?>
                    </h3>
                    <span class="text-muted" style="font-size: 0.8rem;">Applied: <?php echo formatDate($req['applied_at']); ?></span>
                </div>
                <?php if ($req['matching_score']): ?>
                <div class="match-score-badge <?php echo $req['matching_score'] >= 70 ? 'text-success' : ($req['matching_score'] >= 40 ? 'text-warning' : 'text-danger'); ?>" style="background: var(--bg-surface); border-radius: var(--radius-sm);">
                    <?php echo $req['matching_score']; ?>%
                </div>
                <?php endif; ?>
            </div>

            <div class="grid-2" style="gap: 24px;">
                <!-- Adopter Info + Extended Preferences -->
                <div>
                    <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-user"></i> Adopter Information
                    </h4>
                    <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($req['user_name']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($req['user_email']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Location</span><span class="detail-value"><?php echo htmlspecialchars($req['user_location'] ?? '-'); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Occupation</span><span class="detail-value"><?php echo htmlspecialchars($req['occupation'] ?? '-'); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Income</span><span class="detail-value"><?php echo $req['annual_income'] ? formatCurrency($req['annual_income']) : '-'; ?></span></div>
                    <div class="detail-row"><span class="detail-label">Family Size</span><span class="detail-value"><?php echo $req['family_size'] ?? '-'; ?></span></div>

                    <!-- Extended Adopter Preferences -->
                    <div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-light);">
                        <h4 style="font-size: 0.78rem; color: var(--primary-light); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-sliders-h"></i> Adopter Preferences
                        </h4>
                        <div class="detail-row"><span class="detail-label">Age Range</span><span class="detail-value"><?php echo $req['age_preference_min']; ?> – <?php echo $req['age_preference_max']; ?> yrs</span></div>
                        <div class="detail-row"><span class="detail-label">Gender Pref</span><span class="detail-value"><?php echo ucfirst($req['gender_preference'] ?? 'any'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-heartbeat" style="color:#e17055;"></i> Health Pref</span><span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $req['health_preference'] ?? 'any')); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-graduation-cap" style="color:#0984e3;"></i> Education Pref</span><span class="detail-value"><?php echo htmlspecialchars($req['education_preference'] ?: 'Any'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-sync-alt" style="color:#00b894;"></i> Adaptability Pref</span><span class="detail-value"><?php echo ucfirst($req['adaptability_preference'] ?? 'any'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-heart" style="color:#fdcb6e;"></i> Emotional Env</span><span class="detail-value"><?php echo htmlspecialchars($req['emotional_preference'] ?: '—'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-child" style="color:#6c5ce7;"></i> Behavior Pref</span><span class="detail-value">
                            <?php
                            $bLabels = ['calm' => 'Calm & Quiet', 'active' => 'Active & Energetic', 'social' => 'Social & Outgoing', 'independent' => 'Independent', 'creative' => 'Creative & Artistic'];
                            echo $bLabels[$req['behavior_preference'] ?? ''] ?? 'Any';
                            ?>
                        </span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-home" style="color:#00cec9;"></i> Family Background</span><span class="detail-value">
                            <?php
                            $fbLabels = ['orphaned' => 'Fully Orphaned', 'single_parent' => 'Single Parent', 'abandoned' => 'Abandoned / Surrendered', 'displaced' => 'Displaced / Refugee'];
                            echo $fbLabels[$req['family_background_preference'] ?? ''] ?? 'Any';
                            ?>
                        </span></div>
                    </div>
                </div>

                <!-- Child Info + Extended Attributes -->
                <div>
                    <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-child"></i> Child Information
                    </h4>
                    <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($req['orphan_name']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Age</span><span class="detail-value"><?php echo $req['age']; ?> years</span></div>
                    <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?php echo ucfirst($req['gender']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Health</span><span class="detail-value"><span class="badge <?php echo getHealthBadge($req['health_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $req['health_status'])); ?></span></span></div>
                    <div class="detail-row"><span class="detail-label">Location</span><span class="detail-value"><?php echo htmlspecialchars($req['orphan_location']); ?></span></div>

                    <!-- Extended Child Attributes -->
                    <div style="margin-top: 14px; padding-top: 14px; border-top: 1px dashed var(--border-light);">
                        <h4 style="font-size: 0.78rem; color: var(--info); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fas fa-star"></i> Child Attributes
                        </h4>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-graduation-cap" style="color:#0984e3;"></i> Education</span><span class="detail-value"><?php echo htmlspecialchars($req['education_level'] ?: '—'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-smile" style="color:#fdcb6e;"></i> Behavioral</span><span class="detail-value" style="font-size:0.82rem;"><?php echo htmlspecialchars(mb_strimwidth($req['behavioral_traits'] ?? '', 0, 60, '...') ?: '—'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-star" style="color:#6c5ce7;"></i> Personality</span><span class="detail-value"><?php echo htmlspecialchars($req['personality'] ?: '—'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-heart" style="color:#e17055;"></i> Emotional</span><span class="detail-value"><?php echo htmlspecialchars($req['emotional_needs'] ?: '—'); ?></span></div>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-sync-alt" style="color:#00b894;"></i> Adaptability</span><span class="detail-value">
                            <?php if ($req['adaptability_level']): ?>
                            <span class="badge <?php echo $req['adaptability_level'] === 'high' ? 'badge-success' : ($req['adaptability_level'] === 'moderate' ? 'badge-warning' : 'badge-danger'); ?>"><?php echo ucfirst($req['adaptability_level']); ?></span>
                            <?php else: echo '—'; endif; ?>
                        </span></div>
                        <?php if (!empty($req['background'])): ?>
                        <div class="detail-row"><span class="detail-label"><i class="fas fa-book" style="color:#00cec9;"></i> Background</span><span class="detail-value" style="font-size:0.82rem;"><?php echo htmlspecialchars(mb_strimwidth($req['background'], 0, 80, '...')); ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Reason -->
            <?php if ($req['reason']): ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;"><i class="fas fa-comment-alt"></i> Reason for Adoption</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;"><?php echo htmlspecialchars($req['reason']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Verified Profile Documents -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-shield-alt"></i> Profile Verification
                </h4>
                <?php if ($req['profile_status'] === 'verified'): ?>
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <span class="badge badge-success" style="padding: 6px 14px;"><i class="fas fa-check-circle"></i> Profile Verified</span>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">All documents verified by admin.</span>
                    <a href="verify_users.php?user_id=<?php echo $req['user_id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i> View Documents</a>
                </div>
                <?php else: ?>
                <div style="padding: 12px 16px; background: rgba(253,203,110,0.1); border: 1px solid rgba(253,203,110,0.2); border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--warning);">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Profile: <?php echo ucfirst($req['profile_status'] ?? 'incomplete'); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Meeting Info (if scheduled or completed) -->
            <?php if (in_array($req['status'], ['meeting_scheduled', 'completed', 'cancelled']) && $req['meeting_date']): ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                    <i class="fas fa-handshake"></i> Face-to-Face Meeting
                </h4>
                <div style="display: flex; gap: 24px; flex-wrap: wrap; padding: 14px 18px; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                    <div>
                        <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Meeting Date</span>
                        <p style="font-size: 0.95rem; font-weight: 700; color: var(--primary-light);"><?php echo formatDate($req['meeting_date']); ?></p>
                    </div>
                    <?php if ($req['meeting_notes']): ?>
                    <div style="flex: 1; min-width: 200px;">
                        <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Meeting Notes</span>
                        <p style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($req['meeting_notes']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Cancellation Reason -->
            <?php if ($req['status'] === 'cancelled' && $req['cancellation_reason']): ?>
            <div style="margin-top: 12px; padding: 14px 18px; background: rgba(225,112,85,0.08); border: 1px solid rgba(225,112,85,0.2); border-radius: var(--radius-sm);">
                <strong style="color: var(--danger);"><i class="fas fa-ban"></i> Cancellation Reason:</strong>
                <p style="margin-top: 4px; font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($req['cancellation_reason']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Admin Notes -->
            <?php if ($req['admin_notes']): ?>
            <div style="margin-top: 12px; padding: 12px 16px; background: var(--bg-surface); border-radius: var(--radius-sm); font-size: 0.9rem;">
                <strong><i class="fas fa-sticky-note"></i> Admin Notes:</strong> <?php echo htmlspecialchars($req['admin_notes']); ?>
            </div>
            <?php endif; ?>

            <!-- ==================== ACTION BUTTONS ==================== -->
            
            <!-- PENDING: Approve or Reject -->
            <?php if ($req['status'] === 'pending'): ?>
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <form method="POST" action="" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label for="adminNotes_<?php echo $req['id']; ?>">Admin Notes</label>
                        <input type="text" id="adminNotes_<?php echo $req['id']; ?>" name="admin_notes" class="form-control" placeholder="Optional notes...">
                    </div>
                    <button type="submit" name="action" value="approved" class="btn btn-success" data-confirm="Approve this adoption request?">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button type="submit" name="action" value="rejected" class="btn btn-danger" data-confirm="Reject this adoption request?">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <a href="matching.php?user_id=<?php echo $req['user_id']; ?>" class="btn btn-outline"><i class="fas fa-magic"></i> View Matching</a>
                </form>
            </div>

            <!-- APPROVED: Schedule Meeting -->
            <?php elseif ($req['status'] === 'approved'): ?>
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <div style="background: rgba(116,185,255,0.08); border: 1px solid rgba(116,185,255,0.2); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 16px;">
                    <p style="font-size: 0.85rem; color: var(--info); margin-bottom: 0;">
                        <i class="fas fa-info-circle"></i> <strong>Next Step:</strong> Schedule a face-to-face meeting between the adopter and child. This ensures the child's comfort and emotional safety before finalizing adoption.
                    </p>
                </div>
                <form method="POST" action="" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <div class="form-group" style="min-width: 180px; margin-bottom: 0;">
                        <label for="meetingDate_<?php echo $req['id']; ?>">Meeting Date *</label>
                        <input type="date" id="meetingDate_<?php echo $req['id']; ?>" name="meeting_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label for="meetingNotes_<?php echo $req['id']; ?>">Meeting Notes</label>
                        <input type="text" id="meetingNotes_<?php echo $req['id']; ?>" name="meeting_notes" class="form-control" placeholder="Location, time, instructions...">
                    </div>
                    <input type="hidden" name="admin_notes" value="<?php echo htmlspecialchars($req['admin_notes'] ?? ''); ?>">
                    <button type="submit" name="action" value="schedule_meeting" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Schedule Meeting
                    </button>
                </form>
            </div>

            <!-- MEETING SCHEDULED: Complete or Cancel -->
            <?php elseif ($req['status'] === 'meeting_scheduled'): ?>
            <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <div style="background: rgba(108,92,231,0.08); border: 1px solid rgba(108,92,231,0.2); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 16px;">
                    <p style="font-size: 0.85rem; color: var(--primary-light); margin-bottom: 0;">
                        <i class="fas fa-handshake"></i> <strong>After the meeting:</strong> If the child is comfortable and the adoption is deemed suitable, finalize it. If not, cancel with a specific reason to protect the child's well-being.
                    </p>
                </div>
                
                <!-- Finalize -->
                <form method="POST" action="" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap; margin-bottom: 16px;">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label for="completeNotes_<?php echo $req['id']; ?>">Admin Notes</label>
                        <input type="text" id="completeNotes_<?php echo $req['id']; ?>" name="admin_notes" class="form-control" placeholder="Meeting went well, child is happy...">
                    </div>
                    <button type="submit" name="action" value="complete" class="btn btn-success" data-confirm="Finalize this adoption? This will mark the child as adopted.">
                        <i class="fas fa-check-double"></i> Finalize Adoption
                    </button>
                </form>

                <!-- Cancel -->
                <form method="POST" action="" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap; padding-top: 12px; border-top: 1px dashed var(--border-light);">
                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                    <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                        <label for="cancelReason_<?php echo $req['id']; ?>" style="color: var(--danger);">Cancellation Reason *</label>
                        <input type="text" id="cancelReason_<?php echo $req['id']; ?>" name="cancellation_reason" class="form-control" placeholder="e.g., Child was not comfortable, unsuitable environment..." required>
                    </div>
                    <input type="hidden" name="admin_notes" value="<?php echo htmlspecialchars($req['admin_notes'] ?? ''); ?>">
                    <button type="submit" name="action" value="cancel" class="btn btn-danger" data-confirm="Cancel this adoption? The child will be made available again.">
                        <i class="fas fa-ban"></i> Cancel Adoption
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>No adoption requests</h3>
                <p>No requests found for the selected filter.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
