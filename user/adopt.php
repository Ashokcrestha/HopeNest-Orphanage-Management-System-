<?php
/**
 * User - Adoption Application
 * Orphanage Management System
 * 
 * Smart preference detection:
 * - If user already has adoption preferences set during profile completion,
 *   the preference form is HIDDEN and the system directly shows matching children.
 * - This prevents duplicate data entry and improves user experience.
 * - User can still update preferences via an expandable section if needed.
 * 
 * The system automatically retrieves verified biodata and documents.
 */
$pageTitle = 'Apply for Adoption';
require_once '../includes/auth.php';
require_once '../includes/matching_algorithm.php';
requireUserLogin();

// Require verified profile for adoption
requireVerifiedProfile($pdo);

$userId = $_SESSION['user_id'];

// Fetch user data (already verified)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch user's verified documents
$userDocs = getUserDocuments($pdo, $userId);

// CHECK: Does user have preferences already set?
// Preferences are considered "set" if age_preference_max > 0 and profile is verified
$hasPreferences = (
    $user['profile_status'] === 'verified' &&
    (int)$user['age_preference_max'] > 0
);

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orphanId = (int)$_POST['orphan_id'];
    $reason = sanitize($_POST['reason'] ?? '');

    // Use stored preferences directly — no re-entry needed
    $prefAgeMin = (int)$user['age_preference_min'];
    $prefAgeMax = (int)$user['age_preference_max'];
    $prefGender = $user['gender_preference'];
    $income = (float)$user['annual_income'];
    $familySize = (int)$user['family_size'];

    $errors = [];

    if (!$orphanId) {
        $errors[] = 'Please select a child.';
    }
    if (empty($reason)) {
        $errors[] = 'Please provide a reason for adoption.';
    }

    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
    } else {
        // Check for existing request
        $stmt = $pdo->prepare("SELECT id FROM adoption_requests WHERE user_id = ? AND orphan_id = ? AND status IN ('pending','approved','meeting_scheduled')");
        $stmt->execute([$userId, $orphanId]);
        if ($stmt->fetch()) {
            setFlash('error', 'You already have an active request for this child.');
        } else {
            // Calculate matching score
            $stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE id = ?");
            $stmt->execute([$orphanId]);
            $orphan = $stmt->fetch();

            $matchScore = calculateMatchScore($user, $orphan);

            // Insert adoption request
            $stmt = $pdo->prepare("
                INSERT INTO adoption_requests (user_id, orphan_id, reason, preferred_age_min, preferred_age_max, preferred_gender, income, family_size, matching_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $orphanId, $reason, $prefAgeMin, $prefAgeMax, $prefGender, $income, $familySize, $matchScore['total_score']
            ]);

            // Store detailed matching score
            storeMatchingScore($pdo, $userId, $orphanId);

            // Update orphan status to reserved
            $stmt = $pdo->prepare("UPDATE orphans SET availability_status = 'pending' WHERE id = ? AND availability_status = 'available'");
            $stmt->execute([$orphanId]);

            setFlash('success', 'Your adoption application has been submitted successfully! Match Score: ' . $matchScore['total_score'] . '%');
            redirect('my_requests.php');
        }
    }
}

// Fetch available orphans
$orphans = $pdo->query("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE availability_status = 'available' ORDER BY full_name")->fetchAll();

// Get matching recommendations (only works for verified users)
$recommendations = getMatchingRecommendations($pdo, $userId);

// Pre-select orphan from URL
$preSelectedOrphanId = isset($_GET['orphan_id']) ? (int)$_GET['orphan_id'] : 0;

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-heart"></i> Apply for Adoption</h1>
            <p class="page-subtitle">Start your journey to provide a loving home</p>
        </div>
    </div>

    <?php if ($hasPreferences): ?>
    <!-- ====================================================== -->
    <!-- MODE: Preferences EXIST — Show matches directly        -->
    <!-- ====================================================== -->

    <!-- Verified Profile + Preferences Summary -->
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="fas fa-check-circle text-success"></i> Your Verified Profile & Preferences</h3>
            <span class="badge badge-success"><i class="fas fa-shield-alt"></i> Verified</span>
        </div>
        <div class="card-body">
            <div style="background: rgba(0,184,148,0.08); border: 1px solid rgba(0,184,148,0.15); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 16px; font-size: 0.85rem; color: var(--success);">
                <i class="fas fa-magic"></i>
                Your adoption preferences are already set. The system has automatically matched you with compatible children below. Simply choose a child and provide your reason to apply.
            </div>

            <div class="grid-2" style="gap: 12px; font-size: 0.85rem;">
                <div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Name</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Location</span>
                        <span class="detail-value"><?php echo htmlspecialchars($user['location'] ?? '-'); ?></span>
                    </div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Income</span>
                        <span class="detail-value"><?php echo $user['annual_income'] ? formatCurrency($user['annual_income']) : '-'; ?></span>
                    </div>
                </div>
                <div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Age Pref</span>
                        <span class="detail-value"><?php echo $user['age_preference_min']; ?> – <?php echo $user['age_preference_max']; ?> years</span>
                    </div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Gender Pref</span>
                        <span class="detail-value"><?php echo ucfirst($user['gender_preference']); ?></span>
                    </div>
                    <div class="detail-row" style="padding: 6px 0;">
                        <span class="detail-label">Health Pref</span>
                        <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $user['health_preference'] ?? 'any')); ?></span>
                    </div>
                </div>
            </div>

            <div style="margin-top: 12px; text-align: center;">
                <a href="complete_profile.php" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Update Preferences</a>
            </div>
        </div>
    </div>

    <div class="detail-grid">
        <!-- Left: Matching Children (Primary Focus) -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-magic text-warning"></i> Recommended Children</h3>
                    <span class="badge badge-primary"><?php echo count($recommendations); ?> matches</span>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px;">
                        Children ranked by compatibility with your preferences. Click <strong>"Apply"</strong> to begin the adoption process.
                    </p>

                    <?php if (!empty($recommendations)): ?>
                        <?php foreach ($recommendations as $rank => $child): ?>
                        <div class="match-card" style="display: flex; align-items: center; gap: 16px; padding: 16px; margin-bottom: 10px; border: 1px solid var(--border-light); border-radius: var(--radius-sm); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--primary-light)'" onmouseout="this.style.borderColor='var(--border-light)'">
                            <div class="match-rank" style="width: 36px; height: 36px; font-size: 0.85rem; flex-shrink: 0;">#<?php echo $rank + 1; ?></div>
                            <div style="flex: 1; min-width: 0;">
                                <h4 style="font-size: 0.95rem; margin-bottom: 4px;">
                                    <a href="orphan_detail.php?id=<?php echo $child['id']; ?>" style="text-decoration: none; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($child['full_name']); ?>
                                    </a>
                                </h4>
                                <div style="display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.8rem; color: var(--text-muted);">
                                    <span><i class="fas fa-birthday-cake"></i> <?php echo $child['age']; ?>yrs</span>
                                    <span><i class="fas fa-venus-mars"></i> <?php echo ucfirst($child['gender']); ?></span>
                                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($child['location']); ?></span>
                                    <span class="badge <?php echo getHealthBadge($child['health_status']); ?>" style="font-size: 0.7rem;"><?php echo ucfirst(str_replace('_', ' ', $child['health_status'])); ?></span>
                                </div>
                                <?php if (!empty($child['personality'])): ?>
                                <p style="font-size: 0.78rem; color: var(--text-secondary); margin-top: 4px;"><i class="fas fa-star"></i> <?php echo htmlspecialchars($child['personality']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; flex-shrink: 0;">
                                <div class="<?php echo $child['scores']['total_score'] >= 70 ? 'text-success' : ($child['scores']['total_score'] >= 40 ? 'text-warning' : 'text-danger'); ?>" style="font-size: 1.2rem; font-weight: 800;">
                                    <?php echo $child['scores']['total_score']; ?>%
                                </div>
                                <button type="button" class="btn btn-accent btn-sm mt-1" onclick="selectChild(<?php echo $child['id']; ?>, '<?php echo htmlspecialchars($child['full_name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-heart"></i> Apply
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-child" style="font-size: 2rem;"></i>
                            <p>No available children for matching right now.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Application Form (compact) -->
        <div>
            <div class="card" id="applicationForm">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt text-primary"></i> Quick Application</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" data-validate>
                        <div class="form-group">
                            <label for="orphanSelect">Selected Child *</label>
                            <select id="orphanSelect" name="orphan_id" class="form-control" required>
                                <option value="">-- Click "Apply" on a match --</option>
                                <?php foreach ($orphans as $o): ?>
                                <option value="<?php echo $o['id']; ?>" <?php echo ($preSelectedOrphanId == $o['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['full_name']); ?> (<?php echo $o['age']; ?>yrs, <?php echo ucfirst($o['gender']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reason">Reason for Adoption *</label>
                            <textarea id="reason" name="reason" class="form-control" placeholder="Explain why you want to adopt this child and how you plan to provide for them..." rows="5" required></textarea>
                        </div>

                        <div style="background: var(--bg-surface); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 16px; font-size: 0.8rem; color: var(--text-muted);">
                            <i class="fas fa-info-circle"></i> Your verified profile data (income, family size, documents) will be automatically attached to this application.
                        </div>

                        <button type="submit" class="btn btn-accent btn-block btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- ====================================================== -->
    <!-- MODE: No preferences — Show full form                  -->
    <!-- ====================================================== -->
    <div class="detail-grid">
        <div>
            <!-- Verified Profile Summary -->
            <div class="card mb-2">
                <div class="card-header">
                    <h3><i class="fas fa-check-circle text-success"></i> Your Verified Profile</h3>
                    <span class="badge badge-success"><i class="fas fa-shield-alt"></i> Verified</span>
                </div>
                <div class="card-body">
                    <div style="background: rgba(253,203,110,0.1); border: 1px solid rgba(253,203,110,0.2); border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 16px; font-size: 0.85rem; color: var(--warning);">
                        <i class="fas fa-exclamation-triangle"></i>
                        Please set your adoption preferences below. These preferences help our matching algorithm recommend the best children for you.
                    </div>
                    <div class="grid-2" style="gap: 12px; font-size: 0.85rem;">
                        <div>
                            <div class="detail-row" style="padding: 6px 0;">
                                <span class="detail-label">Name</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                            </div>
                            <div class="detail-row" style="padding: 6px 0;">
                                <span class="detail-label">Occupation</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['occupation'] ?? '-'); ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="detail-row" style="padding: 6px 0;">
                                <span class="detail-label">Location</span>
                                <span class="detail-value"><?php echo htmlspecialchars($user['location'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-row" style="padding: 6px 0;">
                                <span class="detail-label">Family Size</span>
                                <span class="detail-value"><?php echo $user['family_size'] ?? '-'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full Application Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt text-primary"></i> Adoption Application</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="" data-validate>
                        <div class="form-group">
                            <label for="orphanSelect2">Select Child *</label>
                            <select id="orphanSelect2" name="orphan_id" class="form-control" required>
                                <option value="">-- Choose a child --</option>
                                <?php foreach ($orphans as $o): ?>
                                <option value="<?php echo $o['id']; ?>" <?php echo ($preSelectedOrphanId == $o['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($o['full_name']); ?> (<?php echo $o['age']; ?>yrs, <?php echo ucfirst($o['gender']); ?>, <?php echo $o['location']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reason2">Reason for Adoption *</label>
                            <textarea id="reason2" name="reason" class="form-control" placeholder="Explain why you want to adopt this child..." rows="5" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-accent btn-block btn-lg mt-2">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recommendations (if any) -->
        <div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-magic text-warning"></i> Available Children</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($orphans)): ?>
                        <?php foreach (array_slice($orphans, 0, 8) as $child): ?>
                        <div style="display: flex; align-items: center; gap: 12px; padding: 10px; margin-bottom: 6px; border-bottom: 1px solid var(--border-light);">
                            <div style="flex: 1;">
                                <strong style="font-size: 0.9rem;"><?php echo htmlspecialchars($child['full_name']); ?></strong>
                                <div style="font-size: 0.78rem; color: var(--text-muted);">
                                    <?php echo $child['age']; ?>yrs · <?php echo ucfirst($child['gender']); ?> · <?php echo $child['location']; ?>
                                </div>
                            </div>
                            <a href="orphan_detail.php?id=<?php echo $child['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-child" style="font-size: 2rem;"></i>
                            <p>No available children right now.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Auto-select child from recommendation card
function selectChild(childId, childName) {
    const select = document.getElementById('orphanSelect');
    if (select) {
        select.value = childId;
        // Scroll to form
        document.getElementById('applicationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        // Focus on reason field
        setTimeout(function() {
            document.getElementById('reason').focus();
        }, 500);
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
