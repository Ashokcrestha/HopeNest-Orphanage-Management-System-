<?php
/**
 * Helper Functions
 * Orphanage Management System
 */

/**
 * Sanitize user input
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'user';
}

/**
 * Check if admin is logged in
 */
function isAdmin() {
    return isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin';
}

/**
 * Format date for display
 */
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Format currency (NPR)
 */
function formatCurrency($amount) {
    return 'NPR ' . number_format($amount, 2);
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}

/**
 * Generate a random transaction ID
 */
function generateTransactionId() {
    return 'TXN' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get health status badge class
 */
function getHealthBadge($status) {
    switch ($status) {
        case 'healthy':
            return 'badge-success';
        case 'minor_issues':
            return 'badge-warning';
        case 'special_needs':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

/**
 * Get adoption status badge class
 */
function getStatusBadge($status) {
    switch ($status) {
        case 'approved':
            return 'badge-info';
        case 'pending':
            return 'badge-warning';
        case 'meeting_scheduled':
            return 'badge-primary';
        case 'completed':
            return 'badge-success';
        case 'rejected':
            return 'badge-danger';
        case 'cancelled':
            return 'badge-secondary';
        default:
            return 'badge-secondary';
    }
}

/**
 * Get availability badge class
 */
function getAvailabilityBadge($status) {
    switch ($status) {
        case 'available':
            return 'badge-success';
        case 'pending':
            return 'badge-warning';
        case 'adopted':
            return 'badge-info';
        default:
            return 'badge-secondary';
    }
}

/**
 * Get child availability display label
 * Maps internal status to user-friendly names
 */
function getAvailabilityLabel($status) {
    switch ($status) {
        case 'available':
            return 'Available';
        case 'pending':
            return 'Reserved';
        case 'adopted':
            return 'Adopted';
        default:
            return ucfirst($status);
    }
}

/**
 * Check if a user's profile is verified
 */
function isProfileVerified($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT profile_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $status = $stmt->fetchColumn();
    return $status === 'verified';
}

/**
 * Get user's profile verification status
 */
function getProfileStatus($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT profile_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() ?: 'incomplete';
}

/**
 * Get verification status badge class
 */
function getVerificationBadge($status) {
    switch ($status) {
        case 'verified':
            return 'badge-success';
        case 'pending':
            return 'badge-warning';
        case 'rejected':
            return 'badge-danger';
        case 'incomplete':
        default:
            return 'badge-info';
    }
}

/**
 * Require a verified profile — redirects to complete_profile if not verified
 */
function requireVerifiedProfile($pdo) {
    if (!isLoggedIn()) return;
    $userId = $_SESSION['user_id'];
    if (!isProfileVerified($pdo, $userId)) {
        $status = getProfileStatus($pdo, $userId);
        if ($status === 'incomplete') {
            setFlash('error', 'Please complete your profile before applying for adoption.');
        } elseif ($status === 'pending') {
            setFlash('error', 'Your profile is under review. You will be able to apply for adoption once verified.');
        } elseif ($status === 'rejected') {
            setFlash('error', 'Your profile verification was rejected. Please update and re-submit your profile.');
        }
        redirect('/orphanage-management-system/user/complete_profile.php');
    }
}

/**
 * Get user's uploaded documents
 */
function getUserDocuments($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ? ORDER BY doc_type");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get document type label
 */
function getDocTypeLabel($docType) {
    $labels = [
        'national_id' => 'National ID / Passport',
        'citizenship' => 'Citizenship Certificate',
        'occupation_proof' => 'Occupation / Employment Proof',
        'health_certificate' => 'Health Certificate'
    ];
    return $labels[$docType] ?? ucfirst(str_replace('_', ' ', $docType));
}

/**
 * Get document type icon
 */
function getDocTypeIcon($docType) {
    $icons = [
        'national_id' => 'fa-id-card',
        'citizenship' => 'fa-passport',
        'occupation_proof' => 'fa-briefcase',
        'health_certificate' => 'fa-file-medical'
    ];
    return $icons[$docType] ?? 'fa-file';
}
?>
