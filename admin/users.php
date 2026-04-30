<?php
/**
 * Admin - Manage Users
 * Orphanage Management System
 */
$pageTitle = 'Manage Users';
require_once '../includes/auth.php';
requireAdminLogin();

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'User record deleted successfully.');
    redirect('users.php');
}

// Fetch all users
$search = sanitize($_GET['search'] ?? '');
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE full_name LIKE ? OR email LIKE ? OR location LIKE ? ORDER BY created_at DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
}
$users = $stmt->fetchAll();

// Get user stats
foreach ($users as &$user) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user['total_donated'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM adoption_requests WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $user['adoption_requests'] = $stmt->fetchColumn();
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Manage Users</h1>
            <p class="page-subtitle"><?php echo count($users); ?> registered users</p>
        </div>
    </div>

    <!-- Search -->
    <div class="filter-bar">
        <form method="GET" action="" style="display: contents;">
            <div class="form-group" style="flex: 3;">
                <label for="searchInput">Search Users</label>
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search by name, email, or location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <?php if (!empty($users)): ?>
    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Verification</th>
                        <th>Preferences</th>
                        <th>Donations</th>
                        <th>Requests</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $user): ?>
                    <tr data-searchable>
                        <td><?php echo $i + 1; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($user['location'] ?? '-'); ?></td>
                        <td><span class="badge <?php echo getVerificationBadge($user['profile_status'] ?? 'incomplete'); ?>"><?php echo ucfirst($user['profile_status'] ?? 'incomplete'); ?></span></td>
                        <td>
                            <?php
                            $hasPrefs = !empty($user['gender_preference']) && $user['gender_preference'] !== 'any';
                            $hasPrefs = $hasPrefs || !empty($user['health_preference']) && $user['health_preference'] !== 'any';
                            $hasPrefs = $hasPrefs || !empty($user['behavior_preference']);
                            $hasPrefs = $hasPrefs || !empty($user['family_background_preference']);
                            if ($hasPrefs || ($user['age_preference_min'] > 0 || ($user['age_preference_max'] ?? 18) < 18)):
                            ?>
                            <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                                <span class="badge badge-primary" style="font-size: 0.65rem;" title="Age: <?php echo $user['age_preference_min']; ?>-<?php echo $user['age_preference_max']; ?>"><?php echo $user['age_preference_min']; ?>-<?php echo $user['age_preference_max']; ?>y</span>
                                <?php if (!empty($user['gender_preference']) && $user['gender_preference'] !== 'any'): ?>
                                <span class="badge badge-info" style="font-size: 0.65rem;"><?php echo ucfirst($user['gender_preference']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($user['behavior_preference'])): ?>
                                <span class="badge badge-warning" style="font-size: 0.65rem;"><?php echo ucfirst($user['behavior_preference']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted" style="font-size: 0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-success"><?php echo formatCurrency($user['total_donated']); ?></td>
                        <td><span class="badge badge-primary"><?php echo $user['adoption_requests']; ?></span></td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <a href="verify_users.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline" title="Review Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Are you sure you want to delete this user and all their data?">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>No users found</h3>
                <p>No registered users match your search.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
