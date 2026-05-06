<?php
/**
 * Admin - Manage Adoption Feedback
 * Orphanage Management System
 */
$pageTitle = 'Adoption Feedback';
require_once '../includes/auth.php';
requireAdminLogin();

// Handle admin response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond'])) {
    $feedbackId = (int)$_POST['feedback_id'];
    $response = sanitize($_POST['admin_response'] ?? '');
    if (!empty($response)) {
        $stmt = $pdo->prepare("UPDATE adoption_feedback SET admin_response = ?, responded_at = NOW() WHERE id = ?");
        $stmt->execute([$response, $feedbackId]);
        setFlash('success', 'Response submitted successfully.');
    } else {
        setFlash('error', 'Please write a response.');
    }
    redirect('feedback.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
}

// Filter
$filter = sanitize($_GET['filter'] ?? '');
$where = '';
if ($filter === 'pending') $where = 'WHERE af.admin_response IS NULL';
elseif ($filter === 'responded') $where = 'WHERE af.admin_response IS NOT NULL';

// Fetch all feedback
$feedbacks = $pdo->query("
    SELECT af.*,
           u.full_name as user_name,
           o.full_name as orphan_name,
           TIMESTAMPDIFF(YEAR, o.date_of_birth, CURDATE()) AS orphan_age,
           o.gender as orphan_gender
    FROM adoption_feedback af
    JOIN users u ON af.user_id = u.id
    JOIN adoption_requests ar ON af.adoption_request_id = ar.id
    JOIN orphans o ON ar.orphan_id = o.id
    $where
    ORDER BY af.created_at DESC
")->fetchAll();

// Stats
$totalFeedback = $pdo->query("SELECT COUNT(*) FROM adoption_feedback")->fetchColumn();
$avgRating = $pdo->query("SELECT COALESCE(ROUND(AVG(rating), 1), 0) FROM adoption_feedback")->fetchColumn();
$pendingResponse = $pdo->query("SELECT COUNT(*) FROM adoption_feedback WHERE admin_response IS NULL")->fetchColumn();
$respondedCount = $pdo->query("SELECT COUNT(*) FROM adoption_feedback WHERE admin_response IS NOT NULL")->fetchColumn();

// Rating distribution
$ratingDist = [];
for ($i = 1; $i <= 5; $i++) {
    $ratingDist[$i] = $pdo->query("SELECT COUNT(*) FROM adoption_feedback WHERE rating = $i")->fetchColumn();
}

function renderStarsAdmin($rating, $large = false) {
    $class = $large ? 'star-display star-display-lg' : 'star-display';
    $html = '<div class="' . $class . '">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rating ? '<i class="fas fa-star star-filled"></i>' : '<i class="fas fa-star star-empty"></i>';
    }
    return $html . '</div>';
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-comment-dots"></i> Adoption Feedback</h1>
            <p class="page-subtitle">Review and respond to adopter feedback</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="feedback-stat-grid">
        <div class="feedback-stat">
            <div class="stat-number" style="color: var(--primary-light);"><?php echo $totalFeedback; ?></div>
            <div class="stat-text">Total Feedback</div>
        </div>
        <div class="feedback-stat">
            <div class="stat-number" style="color: #f9a825;"><?php echo $avgRating; ?> <span style="font-size: 1rem;">⭐</span></div>
            <div class="stat-text">Average Rating</div>
        </div>
        <div class="feedback-stat">
            <div class="stat-number" style="color: var(--warning);"><?php echo $pendingResponse; ?></div>
            <div class="stat-text">Awaiting Response</div>
        </div>
        <div class="feedback-stat">
            <div class="stat-number" style="color: var(--success);"><?php echo $respondedCount; ?></div>
            <div class="stat-text">Responded</div>
        </div>
    </div>

    <!-- Rating Distribution -->
    <?php if ($totalFeedback > 0): ?>
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar" style="color: #f9a825;"></i> Rating Distribution</h3>
        </div>
        <div class="card-body">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <?php $pct = $totalFeedback > 0 ? round(($ratingDist[$i] / $totalFeedback) * 100) : 0; ?>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                <span style="font-size: 0.85rem; font-weight: 600; min-width: 60px;"><?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?></span>
                <div style="flex: 1; height: 8px; background: var(--bg-surface); border-radius: 4px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo $pct; ?>%; background: linear-gradient(90deg, #f9a825, #ff8f00); border-radius: 4px; transition: width 0.5s ease;"></div>
                </div>
                <span style="font-size: 0.8rem; color: var(--text-muted); min-width: 50px; text-align: right;"><?php echo $ratingDist[$i]; ?> (<?php echo $pct; ?>%)</span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="filter-bar">
        <div class="auth-tabs" style="flex: 1; max-width: 500px; margin-bottom: 0;">
            <a href="feedback.php" class="auth-tab <?php echo !$filter ? 'active' : ''; ?>" style="text-decoration:none;">All (<?php echo $totalFeedback; ?>)</a>
            <a href="feedback.php?filter=pending" class="auth-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" style="text-decoration:none;">Pending (<?php echo $pendingResponse; ?>)</a>
            <a href="feedback.php?filter=responded" class="auth-tab <?php echo $filter === 'responded' ? 'active' : ''; ?>" style="text-decoration:none;">Responded (<?php echo $respondedCount; ?>)</a>
        </div>
    </div>

    <!-- Feedback List -->
    <?php if (!empty($feedbacks)): ?>
        <?php foreach ($feedbacks as $fb): ?>
        <div class="feedback-card">
            <div class="feedback-card-header">
                <div class="feedback-user-info">
                    <?php if ($fb['is_anonymous']): ?>
                    <div class="feedback-avatar anonymous"><i class="fas fa-user-secret"></i></div>
                    <div class="feedback-meta">
                        <h4>Anonymous User <span class="badge badge-anonymous" style="margin-left: 6px;">Anonymous</span></h4>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($fb['created_at']); ?> • Adopted <?php echo htmlspecialchars($fb['orphan_name']); ?> (<?php echo ucfirst($fb['orphan_gender']); ?>, <?php echo $fb['orphan_age']; ?> yrs)</span>
                    </div>
                    <?php else: ?>
                    <div class="feedback-avatar"><?php echo strtoupper(substr($fb['user_name'], 0, 1)); ?></div>
                    <div class="feedback-meta">
                        <h4><?php echo htmlspecialchars($fb['user_name']); ?></h4>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($fb['created_at']); ?> • Adopted <?php echo htmlspecialchars($fb['orphan_name']); ?> (<?php echo ucfirst($fb['orphan_gender']); ?>, <?php echo $fb['orphan_age']; ?> yrs)</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <?php echo renderStarsAdmin($fb['rating'], true); ?>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;"><?php echo $fb['rating']; ?>/5 stars</div>
                </div>
            </div>

            <!-- Category Ratings -->
            <div class="category-ratings">
                <div class="category-rating-item"><i class="fas fa-cogs" style="color: var(--primary-light);"></i><span class="cat-label">Process</span><span class="cat-value <?php echo $fb['process_rating']; ?>"><?php echo ucfirst($fb['process_rating']); ?></span></div>
                <div class="category-rating-item"><i class="fas fa-comments" style="color: var(--info);"></i><span class="cat-label">Communication</span><span class="cat-value <?php echo $fb['communication_rating']; ?>"><?php echo ucfirst($fb['communication_rating']); ?></span></div>
                <div class="category-rating-item"><i class="fas fa-hands-helping" style="color: var(--success);"></i><span class="cat-label">Support</span><span class="cat-value <?php echo $fb['support_rating']; ?>"><?php echo ucfirst($fb['support_rating']); ?></span></div>
            </div>

            <!-- Feedback Content -->
            <div class="feedback-body" style="margin-top: 16px;">
                <div class="feedback-text"><?php echo nl2br(htmlspecialchars($fb['feedback_text'])); ?></div>
                <?php if ($fb['suggestions']): ?>
                <div class="feedback-suggestions">
                    <strong><i class="fas fa-lightbulb"></i> Suggestions</strong>
                    <p style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars($fb['suggestions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Admin Response / Response Form -->
            <?php if ($fb['admin_response']): ?>
            <div class="admin-response">
                <div class="admin-response-header">
                    <i class="fas fa-reply"></i> Your Response
                    <span style="font-weight: 400; color: var(--text-muted); text-transform: none; letter-spacing: 0;">— <?php echo formatDate($fb['responded_at']); ?></span>
                </div>
                <p><?php echo nl2br(htmlspecialchars($fb['admin_response'])); ?></p>
            </div>
            <?php else: ?>
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                <form method="POST" action="feedback.php<?php echo $filter ? '?filter=' . $filter : ''; ?>" style="display: flex; gap: 12px; align-items: end; flex-wrap: wrap;">
                    <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                    <div class="form-group" style="flex: 1; min-width: 250px; margin-bottom: 0;">
                        <label for="response_<?php echo $fb['id']; ?>"><i class="fas fa-reply"></i> Write a Response</label>
                        <textarea name="admin_response" id="response_<?php echo $fb['id']; ?>" class="form-control" rows="2" placeholder="Thank you for your feedback..." required></textarea>
                    </div>
                    <button type="submit" name="respond" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Respond</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-comment-dots"></i>
                <h3>No feedback yet</h3>
                <p>Feedback will appear here once adopters submit their experiences.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
