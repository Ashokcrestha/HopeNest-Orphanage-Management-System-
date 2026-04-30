<?php
/**
 * Admin - Matching Algorithm Page
 * Orphanage Management System
 * 
 * Enhanced weighted matching using 8 scoring dimensions:
 * Age(25) + Gender(15) + Location(10) + Health(15) +
 * Education(10) + Behavioral(10) + Emotional(8) + Adaptability(7) = 100
 */
$pageTitle = 'Matching Algorithm';
require_once '../includes/auth.php';
require_once '../includes/matching_algorithm.php';
requireAdminLogin();

$userId = (int)($_GET['user_id'] ?? 0);
$results = [];
$adopter = null;

// Fetch all users for dropdown
$allUsers = $pdo->query("SELECT id, full_name, email, profile_status FROM users ORDER BY full_name")->fetchAll();

if ($userId > 0) {
    // Fetch adopter details with all preference fields
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $adopter = $stmt->fetch();

    if ($adopter) {
        // Run enhanced matching algorithm
        $results = getMatchingRecommendations($pdo, $userId);

        // Store scores
        foreach ($results as $result) {
            storeMatchingScore($pdo, $userId, $result['id']);
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-magic"></i> Child-Adopter Matching</h1>
            <p class="page-subtitle">Enhanced weighted algorithm with 8 scoring dimensions</p>
        </div>
        <a href="adoptions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Requests</a>
    </div>

    <!-- Algorithm Info Card (Updated 8 Dimensions) -->
    <div class="card mb-3" style="border-left: 3px solid var(--primary);">
        <div class="card-header">
            <h3><i class="fas fa-info-circle text-info"></i> Weighted Scoring Mechanism</h3>
        </div>
        <div class="card-body">
            <p style="color: var(--text-secondary); margin-bottom: 16px;">
                The <strong>Enhanced Child-Adopter Matching Algorithm</strong> evaluates compatibility across <strong>8 weighted dimensions</strong> totaling 100 points. It incorporates health, education, behavioral, personality, emotional, and adaptability factors for human-centered recommendations.
            </p>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">
                <?php
                $dimensions = [
                    ['label' => 'Age Match', 'pts' => 25, 'color' => '#6c5ce7', 'icon' => 'fa-birthday-cake', 'desc' => 'Child age within preferred range'],
                    ['label' => 'Gender Match', 'pts' => 15, 'color' => '#fd79a8', 'icon' => 'fa-venus-mars', 'desc' => 'Gender preference alignment'],
                    ['label' => 'Health Factor', 'pts' => 15, 'color' => '#e17055', 'icon' => 'fa-heartbeat', 'desc' => 'Health status compatibility'],
                    ['label' => 'Location', 'pts' => 10, 'color' => '#00cec9', 'icon' => 'fa-map-marker-alt', 'desc' => 'Geographic proximity'],
                    ['label' => 'Education', 'pts' => 10, 'color' => '#0984e3', 'icon' => 'fa-graduation-cap', 'desc' => 'Education level alignment'],
                    ['label' => 'Behavioral', 'pts' => 10, 'color' => '#fdcb6e', 'icon' => 'fa-smile', 'desc' => 'Personality & trait overlap'],
                    ['label' => 'Emotional', 'pts' => 8, 'color' => '#e84393', 'icon' => 'fa-heart', 'desc' => 'Emotional needs compatibility'],
                    ['label' => 'Adaptability', 'pts' => 7, 'color' => '#00b894', 'icon' => 'fa-sync-alt', 'desc' => 'Adaptability level match'],
                ];
                foreach ($dimensions as $dim):
                ?>
                <div style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                    <i class="fas <?php echo $dim['icon']; ?>" style="color: <?php echo $dim['color']; ?>; font-size: 1rem; width: 20px; text-align: center;"></i>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.82rem; font-weight: 600;"><?php echo $dim['label']; ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo $dim['desc']; ?></div>
                    </div>
                    <span style="font-size: 0.82rem; font-weight: 800; color: <?php echo $dim['color']; ?>;"><?php echo $dim['pts']; ?>pt</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Select Adopter -->
    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-user-check"></i> Select Adopter</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="form-row" style="align-items: end;">
                <div class="form-group" style="flex: 2;">
                    <label for="userSelect">Choose an adopter to run the matching algorithm</label>
                    <select id="userSelect" name="user_id" class="form-control" required>
                        <option value="">-- Select Adopter --</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $userId == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['full_name']); ?> (<?php echo $u['email']; ?>)
                            <?php echo $u['profile_status'] === 'verified' ? ' ✓' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Run Algorithm</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($adopter && !empty($results)): ?>
    <!-- Adopter Preferences (Extended) -->
    <div class="card mb-3">
        <div class="card-header">
            <h3><i class="fas fa-sliders-h"></i> Adopter Preferences: <?php echo htmlspecialchars($adopter['full_name']); ?></h3>
            <span class="badge <?php echo $adopter['profile_status'] === 'verified' ? 'badge-success' : 'badge-warning'; ?>"><?php echo ucfirst($adopter['profile_status']); ?></span>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-birthday-cake" style="color: #6c5ce7;"></i> Age Range</span>
                    <span class="detail-value"><?php echo $adopter['age_preference_min']; ?> – <?php echo $adopter['age_preference_max']; ?> yrs</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-venus-mars" style="color: #fd79a8;"></i> Gender</span>
                    <span class="detail-value"><?php echo ucfirst($adopter['gender_preference']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-map-marker-alt" style="color: #00cec9;"></i> Location</span>
                    <span class="detail-value"><?php echo htmlspecialchars($adopter['location'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-heartbeat" style="color: #e17055;"></i> Health Pref</span>
                    <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $adopter['health_preference'] ?? 'any')); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-graduation-cap" style="color: #0984e3;"></i> Education</span>
                    <span class="detail-value"><?php echo htmlspecialchars($adopter['education_preference'] ?: 'Any'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-sync-alt" style="color: #00b894;"></i> Adaptability</span>
                    <span class="detail-value"><?php echo ucfirst($adopter['adaptability_preference'] ?? 'any'); ?></span>
                </div>
                <div class="detail-row" style="grid-column: span 2;">
                    <span class="detail-label"><i class="fas fa-heart" style="color: #e84393;"></i> Emotional Env</span>
                    <span class="detail-value"><?php echo htmlspecialchars($adopter['emotional_preference'] ?: '— Not specified'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-child" style="color: #6c5ce7;"></i> Behavior Pref</span>
                    <span class="detail-value">
                        <?php
                        $bpLabels = ['calm' => 'Calm & Quiet', 'active' => 'Active & Energetic', 'social' => 'Social & Outgoing', 'independent' => 'Independent', 'creative' => 'Creative & Artistic'];
                        echo $bpLabels[$adopter['behavior_preference'] ?? ''] ?? 'Any';
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-home" style="color: #00cec9;"></i> Family Background</span>
                    <span class="detail-value">
                        <?php
                        $fbLabels = ['orphaned' => 'Fully Orphaned', 'single_parent' => 'Single Parent', 'abandoned' => 'Abandoned / Surrendered', 'displaced' => 'Displaced / Refugee'];
                        echo $fbLabels[$adopter['family_background_preference'] ?? ''] ?? 'Any';
                        ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Matching Results -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-trophy"></i> Matching Results (<?php echo count($results); ?> children)</h3>
        </div>
        <div class="card-body">
            <?php foreach ($results as $rank => $child): ?>
            <div class="match-card">
                <div class="match-rank">#<?php echo $rank + 1; ?></div>
                <div class="match-card-info">
                    <h4><?php echo htmlspecialchars($child['full_name']); ?></h4>
                    <div class="match-card-meta">
                        <span><i class="fas fa-birthday-cake"></i> <?php echo $child['age']; ?> yrs</span>
                        <span><i class="fas fa-venus-mars"></i> <?php echo ucfirst($child['gender']); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($child['location']); ?></span>
                        <span class="badge <?php echo getHealthBadge($child['health_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $child['health_status'])); ?></span>
                        <?php if (!empty($child['personality'])): ?>
                        <span><i class="fas fa-star"></i> <?php echo htmlspecialchars($child['personality']); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- 8-Dimension Score Breakdown -->
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 12px;">
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Age (<?php echo $child['scores']['age_score']; ?>/25)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill purple" style="width: <?php echo ($child['scores']['age_score'] / 25) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Gender (<?php echo $child['scores']['gender_score']; ?>/15)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill pink" style="width: <?php echo ($child['scores']['gender_score'] / 15) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Health (<?php echo $child['scores']['health_score']; ?>/15)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill green" style="width: <?php echo ($child['scores']['health_score'] / 15) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Location (<?php echo $child['scores']['location_score']; ?>/10)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill teal" style="width: <?php echo ($child['scores']['location_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Education (<?php echo $child['scores']['education_score']; ?>/10)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill teal" style="width: <?php echo ($child['scores']['education_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Behavioral (<?php echo $child['scores']['behavioral_score']; ?>/10)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill purple" style="width: <?php echo ($child['scores']['behavioral_score'] / 10) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Emotional (<?php echo $child['scores']['emotional_score']; ?>/8)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill pink" style="width: <?php echo ($child['scores']['emotional_score'] / 8) * 100; ?>%;"></div>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-bottom: 2px;">Adapt (<?php echo $child['scores']['adaptability_score']; ?>/7)</div>
                            <div class="score-bar">
                                <div class="score-bar-fill green" style="width: <?php echo ($child['scores']['adaptability_score'] / 7) * 100; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="match-score-badge <?php echo $child['scores']['total_score'] >= 70 ? 'text-success' : ($child['scores']['total_score'] >= 40 ? 'text-warning' : 'text-danger'); ?>" style="background: var(--bg-surface); border-radius: var(--radius-sm); min-width: 70px; text-align: center;">
                    <?php echo $child['scores']['total_score']; ?>%
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php elseif ($adopter && $adopter['profile_status'] !== 'verified'): ?>
        <div class="card">
            <div class="card-body" style="padding: 40px; text-align: center;">
                <i class="fas fa-lock" style="font-size: 2.5rem; color: var(--warning); margin-bottom: 16px;"></i>
                <h3 style="font-size: 1.1rem;">Profile Not Verified</h3>
                <p style="color: var(--text-secondary); margin-bottom: 16px;">This user's profile status is <strong><?php echo ucfirst($adopter['profile_status']); ?></strong>. The matching algorithm only runs for verified profiles.</p>
                <a href="verify_users.php?user_id=<?php echo $adopter['id']; ?>" class="btn btn-primary"><i class="fas fa-user-check"></i> Review Profile</a>
            </div>
        </div>
    <?php elseif ($adopter): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-child"></i>
                <h3>No available children</h3>
                <p>There are no available children in the system to match with.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
