<?php
/**
 * User - My Adoption Requests
 * Orphanage Management System
 * 
 * Shows all adoption requests with multi-stage status tracking:
 * pending → approved → meeting_scheduled → completed / cancelled
 */
$pageTitle = 'My Requests';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Fetch all requests for this user
$stmt = $pdo->prepare("
    SELECT ar.*, o.full_name as orphan_name, TIMESTAMPDIFF(YEAR, o.date_of_birth, CURDATE()) AS age, o.gender, o.health_status, o.location as orphan_location, o.photo as orphan_photo
    FROM adoption_requests ar
    JOIN orphans o ON ar.orphan_id = o.id
    WHERE ar.user_id = ?
    ORDER BY ar.applied_at DESC
");
$stmt->execute([$userId]);
$requests = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> My Adoption Requests</h1>
            <p class="page-subtitle">Track the status of your adoption applications</p>
        </div>
        <a href="adopt.php" class="btn btn-accent"><i class="fas fa-plus"></i> New Application</a>
    </div>

    <?php if (!empty($requests)): ?>
        <?php foreach ($requests as $req): ?>
        <?php
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
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="badge <?php echo getStatusBadge($req['status']); ?>" style="font-size: 0.85rem; padding: 6px 16px;">
                        <?php
                        $statusIcons = [
                            'pending' => 'fa-clock',
                            'approved' => 'fa-check-circle',
                            'meeting_scheduled' => 'fa-handshake',
                            'completed' => 'fa-check-double',
                            'rejected' => 'fa-times-circle',
                            'cancelled' => 'fa-ban',
                        ];
                        $icon = $statusIcons[$req['status']] ?? 'fa-info-circle';
                        echo '<i class="fas ' . $icon . '"></i> ';
                        echo ucfirst(str_replace('_', ' ', $req['status']));
                        ?>
                    </span>
                    <span class="text-muted" style="font-size: 0.8rem;">Applied on <?php echo formatDate($req['applied_at']); ?></span>
                </div>
                <?php if ($req['matching_score']): ?>
                <div style="font-size: 1.2rem; font-weight: 700; color: <?php echo $req['matching_score'] >= 70 ? 'var(--success)' : ($req['matching_score'] >= 40 ? 'var(--warning)' : 'var(--danger)'); ?>;">
                    <i class="fas fa-magic"></i> <?php echo $req['matching_score']; ?>%
                </div>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <!-- Child Info -->
                <div>
                    <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-child"></i> Child Details
                    </h4>
                    <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($req['orphan_name']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Age</span><span class="detail-value"><?php echo $req['age']; ?> years</span></div>
                    <div class="detail-row"><span class="detail-label">Gender</span><span class="detail-value"><?php echo ucfirst($req['gender']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Health</span><span class="detail-value"><span class="badge <?php echo getHealthBadge($req['health_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $req['health_status'])); ?></span></span></div>
                    <div class="detail-row"><span class="detail-label">Location</span><span class="detail-value"><?php echo htmlspecialchars($req['orphan_location']); ?></span></div>
                </div>

                <!-- Application Info -->
                <div>
                    <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <i class="fas fa-file-alt"></i> Application Details
                    </h4>
                    <div class="detail-row"><span class="detail-label">Preferred Age</span><span class="detail-value"><?php echo $req['preferred_age_min']; ?> - <?php echo $req['preferred_age_max']; ?> years</span></div>
                    <div class="detail-row"><span class="detail-label">Gender Pref</span><span class="detail-value"><?php echo ucfirst($req['preferred_gender']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Income</span><span class="detail-value"><?php echo $req['income'] ? formatCurrency($req['income']) : '-'; ?></span></div>
                    <div class="detail-row"><span class="detail-label">Family Size</span><span class="detail-value"><?php echo $req['family_size'] ?? '-'; ?></span></div>
                </div>
            </div>

            <!-- Reason -->
            <?php if ($req['reason']): ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <h4 style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 8px;"><i class="fas fa-comment-alt"></i> Your Reason</h4>
                <p style="font-size: 0.9rem; line-height: 1.6;"><?php echo htmlspecialchars($req['reason']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Admin Notes -->
            <?php if ($req['admin_notes']): ?>
            <div style="margin-top: 12px; padding: 14px 18px; background: var(--bg-surface); border-radius: var(--radius-sm); font-size: 0.9rem; border: 1px solid var(--border-light);">
                <strong><i class="fas fa-comment-dots"></i> Admin Response:</strong> <?php echo htmlspecialchars($req['admin_notes']); ?>
            </div>
            <?php endif; ?>

            <!-- ==================== STATUS-SPECIFIC MESSAGES ==================== -->

            <!-- PENDING -->
            <?php if ($req['status'] === 'pending'): ?>
            <div style="margin-top: 16px; padding: 12px 16px; background: rgba(253,203,110,0.1); border: 1px solid rgba(253,203,110,0.2); border-radius: var(--radius-sm); text-align: center;">
                <p style="color: var(--warning); font-size: 0.85rem;"><i class="fas fa-hourglass-half"></i> Your application is being reviewed by our team. We'll update you soon.</p>
            </div>

            <!-- APPROVED -->
            <?php elseif ($req['status'] === 'approved'): ?>
            <div style="margin-top: 16px; padding: 16px; background: rgba(116,185,255,0.1); border: 1px solid rgba(116,185,255,0.2); border-radius: var(--radius-sm); text-align: center;">
                <i class="fas fa-check-circle" style="color: var(--info); font-size: 1.5rem; margin-bottom: 8px;"></i>
                <p style="color: var(--info); font-weight: 600;">Your adoption request has been approved!</p>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 4px;">A face-to-face meeting will be scheduled soon for you to meet the child.</p>
            </div>

            <!-- MEETING SCHEDULED -->
            <?php elseif ($req['status'] === 'meeting_scheduled'): ?>
            <div style="margin-top: 16px; padding: 20px; background: linear-gradient(135deg, rgba(108,92,231,0.08), rgba(253,121,168,0.05)); border: 1px solid rgba(108,92,231,0.2); border-radius: var(--radius-sm); text-align: center;">
                <i class="fas fa-handshake" style="color: var(--primary-light); font-size: 2rem; margin-bottom: 12px;"></i>
                <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 8px;">Face-to-Face Meeting Scheduled</h4>
                <p style="font-size: 1.1rem; font-weight: 800; color: var(--primary-light); margin-bottom: 8px;">
                    <i class="fas fa-calendar-alt"></i> <?php echo formatDate($req['meeting_date']); ?>
                </p>
                <?php if ($req['meeting_notes']): ?>
                <p style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo htmlspecialchars($req['meeting_notes']); ?></p>
                <?php endif; ?>
                <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 8px;">Please arrive on time. This meeting ensures the child's comfort and emotional safety.</p>
            </div>

            <!-- COMPLETED -->
            <?php elseif ($req['status'] === 'completed'): ?>
            <div style="margin-top: 16px; padding: 20px; background: rgba(0,184,148,0.1); border: 1px solid rgba(0,184,148,0.2); border-radius: var(--radius-sm); text-align: center;">
                <i class="fas fa-heart" style="color: var(--success); font-size: 2rem; margin-bottom: 12px;"></i>
                <p style="color: var(--success); font-weight: 700; font-size: 1.1rem;">🎉 Adoption Finalized!</p>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 6px;">Congratulations! Your adoption has been completed. Thank you for providing a loving home.</p>
            </div>

            <!-- CANCELLED -->
            <?php elseif ($req['status'] === 'cancelled'): ?>
            <div style="margin-top: 16px; padding: 16px; background: rgba(154,154,176,0.08); border: 1px solid rgba(154,154,176,0.2); border-radius: var(--radius-sm);">
                <p style="color: var(--text-muted); font-weight: 600; margin-bottom: 8px;"><i class="fas fa-ban"></i> Adoption Cancelled</p>
                <?php if ($req['cancellation_reason']): ?>
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><strong>Reason:</strong> <?php echo htmlspecialchars($req['cancellation_reason']); ?></p>
                <?php endif; ?>
                <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 6px;">The adoption was not finalized to ensure the child's well-being. You may apply for adoption of other children.</p>
            </div>

            <!-- REJECTED -->
            <?php elseif ($req['status'] === 'rejected'): ?>
            <div style="margin-top: 16px; padding: 12px 16px; background: rgba(225,112,85,0.08); border: 1px solid rgba(225,112,85,0.2); border-radius: var(--radius-sm); text-align: center;">
                <p style="color: var(--danger); font-size: 0.85rem;"><i class="fas fa-times-circle"></i> Your request was not approved. You may apply for other children.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>No adoption requests yet</h3>
                <p>You haven't submitted any adoption applications. Browse available children to get started.</p>
                <a href="orphans.php" class="btn btn-primary"><i class="fas fa-child"></i> Browse Children</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
