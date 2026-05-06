<?php
/**
 * User - Adoption Feedback
 * Orphanage Management System
 */
$pageTitle = 'Adoption Feedback';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $adoptionRequestId = (int)$_POST['adoption_request_id'];
    $rating = (int)$_POST['rating'];
    $processRating = sanitize($_POST['process_rating'] ?? 'good');
    $communicationRating = sanitize($_POST['communication_rating'] ?? 'good');
    $supportRating = sanitize($_POST['support_rating'] ?? 'good');
    $feedbackText = sanitize($_POST['feedback_text'] ?? '');
    $suggestions = sanitize($_POST['suggestions'] ?? '');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    $errors = [];
    if ($rating < 1 || $rating > 5) $errors[] = 'Please select a rating (1-5 stars).';
    if (empty($feedbackText)) $errors[] = 'Please write your feedback.';
    if (strlen($feedbackText) < 10) $errors[] = 'Feedback must be at least 10 characters.';

    // Verify adoption belongs to user and is completed
    $stmt = $pdo->prepare("SELECT id FROM adoption_requests WHERE id = ? AND user_id = ? AND status = 'completed'");
    $stmt->execute([$adoptionRequestId, $userId]);
    if (!$stmt->fetch()) $errors[] = 'Invalid adoption request.';

    // Check duplicate
    $stmt = $pdo->prepare("SELECT id FROM adoption_feedback WHERE adoption_request_id = ?");
    $stmt->execute([$adoptionRequestId]);
    if ($stmt->fetch()) $errors[] = 'Feedback already submitted for this adoption.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO adoption_feedback (adoption_request_id, user_id, rating, process_rating, communication_rating, support_rating, feedback_text, suggestions, is_anonymous) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adoptionRequestId, $userId, $rating, $processRating, $communicationRating, $supportRating, $feedbackText, $suggestions ?: null, $isAnonymous]);
        setFlash('success', 'Thank you! Your feedback has been submitted successfully.');
        redirect('feedback.php');
    } else {
        setFlash('error', implode(' ', $errors));
    }
}

// Fetch completed adoptions with feedback status
$stmt = $pdo->prepare("
    SELECT ar.id as request_id, ar.applied_at, ar.updated_at,
           o.full_name as orphan_name, TIMESTAMPDIFF(YEAR, o.date_of_birth, CURDATE()) AS age, o.gender,
           af.id as feedback_id, af.rating, af.process_rating, af.communication_rating,
           af.support_rating, af.feedback_text, af.suggestions, af.is_anonymous,
           af.admin_response, af.responded_at, af.created_at as feedback_date
    FROM adoption_requests ar
    JOIN orphans o ON ar.orphan_id = o.id
    LEFT JOIN adoption_feedback af ON af.adoption_request_id = ar.id
    WHERE ar.user_id = ? AND ar.status = 'completed'
    ORDER BY ar.updated_at DESC
");
$stmt->execute([$userId]);
$completedAdoptions = $stmt->fetchAll();

function renderStarsHTML($rating, $large = false) {
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
            <p class="page-subtitle">Share your adoption experience to help us improve</p>
        </div>
        <a href="my_requests.php" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> My Requests</a>
    </div>

    <?php if (empty($completedAdoptions)): ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-comment-dots"></i>
                <h3>No completed adoptions yet</h3>
                <p>Once your adoption is finalized, you'll be able to share your experience here.</p>
                <a href="orphans.php" class="btn btn-primary"><i class="fas fa-child"></i> Browse Children</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($completedAdoptions as $adoption): ?>
            <?php if ($adoption['feedback_id']): ?>
                <!-- EXISTING FEEDBACK -->
                <div class="feedback-card">
                    <div class="feedback-card-header">
                        <div class="feedback-user-info">
                            <div class="feedback-avatar"><i class="fas fa-child"></i></div>
                            <div class="feedback-meta">
                                <h4>Feedback for <?php echo htmlspecialchars($adoption['orphan_name']); ?>'s Adoption</h4>
                                <span><i class="fas fa-calendar-alt"></i> Submitted on <?php echo formatDate($adoption['feedback_date']); ?></span>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <?php echo renderStarsHTML($adoption['rating'], true); ?>
                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                                <?php echo $adoption['rating']; ?>/5 stars
                                <?php if ($adoption['is_anonymous']): ?>
                                    <span class="badge badge-anonymous" style="margin-left: 6px;"><i class="fas fa-user-secret"></i> Anonymous</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="category-ratings">
                        <div class="category-rating-item"><i class="fas fa-cogs" style="color: var(--primary-light);"></i><span class="cat-label">Process</span><span class="cat-value <?php echo $adoption['process_rating']; ?>"><?php echo ucfirst($adoption['process_rating']); ?></span></div>
                        <div class="category-rating-item"><i class="fas fa-comments" style="color: var(--info);"></i><span class="cat-label">Communication</span><span class="cat-value <?php echo $adoption['communication_rating']; ?>"><?php echo ucfirst($adoption['communication_rating']); ?></span></div>
                        <div class="category-rating-item"><i class="fas fa-hands-helping" style="color: var(--success);"></i><span class="cat-label">Support</span><span class="cat-value <?php echo $adoption['support_rating']; ?>"><?php echo ucfirst($adoption['support_rating']); ?></span></div>
                    </div>

                    <div class="feedback-body" style="margin-top: 16px;">
                        <div class="feedback-text"><?php echo nl2br(htmlspecialchars($adoption['feedback_text'])); ?></div>
                        <?php if ($adoption['suggestions']): ?>
                        <div class="feedback-suggestions">
                            <strong><i class="fas fa-lightbulb"></i> Your Suggestions</strong>
                            <p style="margin-top: 6px;"><?php echo nl2br(htmlspecialchars($adoption['suggestions'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($adoption['admin_response']): ?>
                    <div class="admin-response">
                        <div class="admin-response-header">
                            <i class="fas fa-reply"></i> Admin Response
                            <span style="font-weight: 400; color: var(--text-muted); text-transform: none; letter-spacing: 0;">— <?php echo formatDate($adoption['responded_at']); ?></span>
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($adoption['admin_response'])); ?></p>
                    </div>
                    <?php else: ?>
                    <div style="margin-top: 12px; padding: 12px 16px; background: var(--bg-surface); border-radius: var(--radius-sm); text-align: center;">
                        <p style="color: var(--text-muted); font-size: 0.85rem;"><i class="fas fa-clock"></i> Awaiting admin response...</p>
                    </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- FEEDBACK FORM -->
                <div class="card mb-2" style="border-left: 3px solid var(--success);">
                    <div class="card-header">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="feedback-avatar" style="width: 38px; height: 38px; font-size: 0.85rem;"><i class="fas fa-child"></i></div>
                            <div>
                                <h3 style="margin-bottom: 2px;"><?php echo htmlspecialchars($adoption['orphan_name']); ?></h3>
                                <span class="text-muted" style="font-size: 0.8rem;"><?php echo ucfirst($adoption['gender']); ?>, <?php echo $adoption['age']; ?> years • Adopted on <?php echo formatDate($adoption['updated_at']); ?></span>
                            </div>
                        </div>
                        <span class="badge badge-success"><i class="fas fa-check-double"></i> Completed</span>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="adoption_request_id" value="<?php echo $adoption['request_id']; ?>">

                        <div class="form-group">
                            <label style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">
                                <i class="fas fa-star" style="color: #f9a825;"></i> How was your overall experience?
                            </label>
                            <div class="star-rating">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>_<?php echo $adoption['request_id']; ?>" <?php echo $i === 5 ? '' : ''; ?>>
                                <label for="star<?php echo $i; ?>_<?php echo $adoption['request_id']; ?>"><i class="fas fa-star"></i></label>
                                <?php endfor; ?>
                            </div>
                            <p class="form-text">Click a star to rate your experience</p>
                        </div>

                        <div class="form-group">
                            <label style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                <i class="fas fa-sliders-h" style="color: var(--primary-light);"></i> Rate specific areas
                            </label>
                            <div class="rating-select-group">
                                <div class="rating-select-item">
                                    <label><i class="fas fa-cogs" style="color: var(--primary-light);"></i> Adoption Process</label>
                                    <select name="process_rating" class="form-control">
                                        <option value="excellent">⭐ Excellent</option>
                                        <option value="good" selected>👍 Good</option>
                                        <option value="average">😐 Average</option>
                                        <option value="poor">👎 Poor</option>
                                    </select>
                                </div>
                                <div class="rating-select-item">
                                    <label><i class="fas fa-comments" style="color: var(--info);"></i> Communication</label>
                                    <select name="communication_rating" class="form-control">
                                        <option value="excellent">⭐ Excellent</option>
                                        <option value="good" selected>👍 Good</option>
                                        <option value="average">😐 Average</option>
                                        <option value="poor">👎 Poor</option>
                                    </select>
                                </div>
                                <div class="rating-select-item">
                                    <label><i class="fas fa-hands-helping" style="color: var(--success);"></i> Support & Guidance</label>
                                    <select name="support_rating" class="form-control">
                                        <option value="excellent">⭐ Excellent</option>
                                        <option value="good" selected>👍 Good</option>
                                        <option value="average">😐 Average</option>
                                        <option value="poor">👎 Poor</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="feedbackText_<?php echo $adoption['request_id']; ?>"><i class="fas fa-pen"></i> Your Feedback *</label>
                            <textarea name="feedback_text" id="feedbackText_<?php echo $adoption['request_id']; ?>" class="form-control" rows="4" placeholder="Tell us about your adoption experience..." required minlength="10"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="suggestions_<?php echo $adoption['request_id']; ?>"><i class="fas fa-lightbulb" style="color: var(--warning);"></i> Suggestions (Optional)</label>
                            <textarea name="suggestions" id="suggestions_<?php echo $adoption['request_id']; ?>" class="form-control" rows="3" placeholder="Any suggestions to improve the process?"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_anonymous" value="1">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label"><i class="fas fa-user-secret"></i> Submit anonymously</span>
                            </label>
                            <p class="form-text">Your name will be hidden from admin when reviewing this feedback.</p>
                        </div>

                        <div style="display: flex; gap: 12px; padding-top: 16px; border-top: 1px solid var(--border-light);">
                            <button type="submit" name="submit_feedback" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
                            <a href="my_requests.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Requests</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
