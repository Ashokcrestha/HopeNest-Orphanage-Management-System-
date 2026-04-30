<?php
/**
 * User - Orphan Detail Page
 * Orphanage Management System
 */
$pageTitle = 'Child Profile';
require_once '../includes/auth.php';
require_once '../includes/matching_algorithm.php';
requireUserLogin();

$userId = $_SESSION['user_id'];
$profileVerified = isProfileVerified($pdo, $userId);
$profileStatus = getProfileStatus($pdo, $userId);

$orphanId = (int)($_GET['id'] ?? 0);
if (!$orphanId) {
    setFlash('error', 'Invalid child ID.');
    redirect('orphans.php');
}

// Fetch orphan
$stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE id = ?");
$stmt->execute([$orphanId]);
$orphan = $stmt->fetch();

if (!$orphan) {
    setFlash('error', 'Child not found.');
    redirect('orphans.php');
}

// Get adopter info for matching score
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$adopter = $stmt->fetch();

$matchScore = null;
$existingRequest = null;

if ($profileVerified) {
    $matchScore = calculateMatchScore($adopter, $orphan);

    // Check if already applied
    $stmt = $pdo->prepare("SELECT * FROM adoption_requests WHERE user_id = ? AND orphan_id = ?");
    $stmt->execute([$userId, $orphanId]);
    $existingRequest = $stmt->fetch();
}

$pageTitle = $orphan['full_name'] . ' - Profile';
require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-child"></i> <?php echo htmlspecialchars($orphan['full_name']); ?></h1>
            <p class="page-subtitle">Child Profile & Details</p>
        </div>
        <a href="orphans.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="detail-grid">
        <!-- Left: Profile Details -->
        <div>
            <div class="card mb-2">
            <div class="child-profile-photo">
                    <?php
                    $photo = $orphan['photo'] ?? 'default.png';
                    $photoPath = '/orphanage-management-system/uploads/orphans/' . $photo;
                    ?>
                    <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($orphan['full_name']); ?>" class="profile-photo-img">
                </div>

                <h2 style="font-size: 1.4rem; margin-bottom: 4px;"><?php echo htmlspecialchars($orphan['full_name']); ?></h2>
                <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                    <span class="badge <?php echo getHealthBadge($orphan['health_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $orphan['health_status'])); ?></span>
                    <span class="badge <?php echo getAvailabilityBadge($orphan['availability_status']); ?>"><?php echo getAvailabilityLabel($orphan['availability_status']); ?></span>
                </div>

                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-birthday-cake text-primary"></i> Age</span>
                    <span class="detail-value"><?php echo $orphan['age']; ?> years old</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-venus-mars text-primary"></i> Gender</span>
                    <span class="detail-value"><?php echo ucfirst($orphan['gender']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar text-primary"></i> Date of Birth</span>
                    <span class="detail-value"><?php echo formatDate($orphan['date_of_birth']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-map-marker-alt text-primary"></i> Location</span>
                    <span class="detail-value"><?php echo htmlspecialchars($orphan['location']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-graduation-cap text-primary"></i> Education</span>
                    <span class="detail-value"><?php echo htmlspecialchars($orphan['education_level'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar-plus text-primary"></i> Admitted</span>
                    <span class="detail-value"><?php echo $orphan['date_admitted'] ? formatDate($orphan['date_admitted']) : 'N/A'; ?></span>
                </div>
            </div>

            <!-- Health Details -->
            <?php if ($orphan['health_details']): ?>
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-heartbeat text-danger"></i> Health Information</h3>
                </div>
                <div class="card-body">
                    <p style="line-height: 1.7;"><?php echo htmlspecialchars($orphan['health_details']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Behavioral Traits -->
            <?php if (!empty($orphan['behavioral_traits'])): ?>
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-smile text-warning"></i> Behavioral Traits</h3>
                </div>
                <div class="card-body">
                    <p style="line-height: 1.7;"><?php echo htmlspecialchars($orphan['behavioral_traits']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Personality & Social Profile -->
            <?php if (!empty($orphan['personality']) || !empty($orphan['emotional_needs']) || !empty($orphan['adaptability_level'])): ?>
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-star text-info"></i> Personality & Social Profile</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($orphan['personality'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-star"></i> Personality</span>
                        <span class="detail-value"><?php echo htmlspecialchars($orphan['personality']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($orphan['emotional_needs'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-heart"></i> Emotional Needs</span>
                        <span class="detail-value"><?php echo htmlspecialchars($orphan['emotional_needs']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($orphan['adaptability_level'])): ?>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fas fa-sync-alt"></i> Adaptability</span>
                        <span class="detail-value">
                            <span class="badge <?php echo $orphan['adaptability_level'] === 'high' ? 'badge-success' : ($orphan['adaptability_level'] === 'moderate' ? 'badge-warning' : 'badge-danger'); ?>">
                                <?php echo ucfirst($orphan['adaptability_level']); ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Background -->
            <?php if ($orphan['background']): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book text-info"></i> Background Story</h3>
                </div>
                <div class="card-body">
                    <p style="line-height: 1.7;"><?php echo htmlspecialchars($orphan['background']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Matching Score & Actions -->
        <div>
            <?php if ($profileVerified && $matchScore): ?>
            <!-- Matching Score Card (only for verified users) -->
            <div class="card mb-2" style="text-align: center;">
                <div class="card-header">
                    <h3 style="width: 100%; text-align: center;"><i class="fas fa-magic"></i> Your Match Score</h3>
                </div>
                <div class="card-body">
                    <div class="match-score-circle <?php echo $matchScore['total_score'] >= 70 ? 'match-score-high' : ($matchScore['total_score'] >= 40 ? 'match-score-medium' : 'match-score-low'); ?>" style="--score-percent: <?php echo $matchScore['total_score']; ?>%;">
                        <?php echo $matchScore['total_score']; ?>%
                    </div>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 20px;">
                        <?php
                        if ($matchScore['total_score'] >= 70) echo 'Excellent match based on your preferences!';
                        elseif ($matchScore['total_score'] >= 40) echo 'Good potential match.';
                        else echo 'Lower compatibility based on stated preferences.';
                        ?>
                    </p>

                    <div class="score-breakdown">
                        <div class="score-item">
                            <div class="score-label">Age</div>
                            <div class="score-value text-primary"><?php echo $matchScore['age_score']; ?>/25</div>
                            <div class="score-bar">
                                <div class="score-bar-fill purple" style="width: <?php echo ($matchScore['age_score'] / 25) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Gender</div>
                            <div class="score-value" style="color: var(--accent);"><?php echo $matchScore['gender_score']; ?>/15</div>
                            <div class="score-bar">
                                <div class="score-bar-fill pink" style="width: <?php echo ($matchScore['gender_score'] / 15) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Location</div>
                            <div class="score-value text-info"><?php echo $matchScore['location_score']; ?>/10</div>
                            <div class="score-bar">
                                <div class="score-bar-fill teal" style="width: <?php echo ($matchScore['location_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Health</div>
                            <div class="score-value text-danger"><?php echo $matchScore['health_score']; ?>/15</div>
                            <div class="score-bar">
                                <div class="score-bar-fill green" style="width: <?php echo ($matchScore['health_score'] / 15) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Education</div>
                            <div class="score-value text-info"><?php echo $matchScore['education_score']; ?>/10</div>
                            <div class="score-bar">
                                <div class="score-bar-fill teal" style="width: <?php echo ($matchScore['education_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Behavioral</div>
                            <div class="score-value text-warning"><?php echo $matchScore['behavioral_score']; ?>/10</div>
                            <div class="score-bar">
                                <div class="score-bar-fill purple" style="width: <?php echo ($matchScore['behavioral_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Emotional</div>
                            <div class="score-value" style="color: var(--accent);"><?php echo $matchScore['emotional_score']; ?>/8</div>
                            <div class="score-bar">
                                <div class="score-bar-fill pink" style="width: <?php echo ($matchScore['emotional_score'] / 8) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">Adaptability</div>
                            <div class="score-value text-success"><?php echo $matchScore['adaptability_score']; ?>/7</div>
                            <div class="score-bar">
                                <div class="score-bar-fill green" style="width: <?php echo ($matchScore['adaptability_score'] / 7) * 100; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Locked Matching Score Card -->
            <div class="card mb-2">
                <div class="locked-feature-card">
                    <i class="fas fa-lock"></i>
                    <h3>Match Score Locked</h3>
                    <p>Complete and verify your profile to see your compatibility score with this child.</p>
                    <a href="complete_profile.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-user-edit"></i> Complete Profile
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-pointer text-warning"></i> Take Action</h3>
                </div>
                <div class="card-body">
                    <?php if ($orphan['availability_status'] !== 'available'): ?>
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--info); margin-bottom: 12px;"></i>
                            <p>This child is currently not available for adoption.</p>
                        </div>
                    <?php elseif (!$profileVerified): ?>
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-shield-alt" style="font-size: 2rem; color: var(--warning); margin-bottom: 12px;"></i>
                            <p style="margin-bottom: 12px;">Profile verification required to apply for adoption.</p>
                            <a href="complete_profile.php" class="btn btn-accent btn-block">
                                <i class="fas fa-user-edit"></i> Complete Profile to Adopt
                            </a>
                            <a href="donate.php" class="btn btn-secondary btn-block mt-1">
                                <i class="fas fa-hand-holding-heart"></i> Donate Instead
                            </a>
                        </div>
                    <?php elseif ($existingRequest): ?>
                        <div style="text-align: center; padding: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--success); margin-bottom: 12px;"></i>
                            <p>You have already applied for this child.</p>
                            <p class="mt-1">Status: <span class="badge <?php echo getStatusBadge($existingRequest['status']); ?>"><?php echo ucfirst($existingRequest['status']); ?></span></p>
                            <a href="my_requests.php" class="btn btn-outline btn-sm mt-2"><i class="fas fa-eye"></i> View Request</a>
                        </div>
                    <?php else: ?>
                        <a href="adopt.php?orphan_id=<?php echo $orphan['id']; ?>" class="btn btn-accent btn-block btn-lg mb-2">
                            <i class="fas fa-heart"></i> Apply for Adoption
                        </a>
                        <a href="donate.php" class="btn btn-secondary btn-block">
                            <i class="fas fa-hand-holding-heart"></i> Donate Instead
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
