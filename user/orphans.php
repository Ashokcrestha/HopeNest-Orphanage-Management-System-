<?php
/**
 * User - Browse Orphans
 * Orphanage Management System
 */
$pageTitle = 'Browse Children';
require_once '../includes/auth.php';
requireUserLogin();

// Filters
$search = sanitize($_GET['search'] ?? '');
$genderFilter = sanitize($_GET['gender'] ?? '');
$ageFilter = sanitize($_GET['age'] ?? '');

$where = ["availability_status = 'available'"];
$params = [];

if ($search) {
    $where[] = "(full_name LIKE ? OR location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($genderFilter) {
    $where[] = "gender = ?";
    $params[] = $genderFilter;
}
if ($ageFilter) {
    switch ($ageFilter) {
        case '0-3':
            $where[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 0 AND 3";
            break;
        case '4-7':
            $where[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 4 AND 7";
            break;
        case '8-12':
            $where[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 8 AND 12";
            break;
        case '13-18':
            $where[] = "TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 13 AND 18";
            break;
    }
}

$whereClause = 'WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$orphans = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-child"></i> Available Children</h1>
            <p class="page-subtitle"><?php echo count($orphans); ?> children awaiting a loving home</p>
        </div>
    </div>

    <?php
    $profileStatus = getProfileStatus($pdo, $_SESSION['user_id']);
    if ($profileStatus !== 'verified'):
    ?>
    <div class="verification-banner verification-info">
        <div class="verification-banner-icon"><i class="fas fa-info-circle"></i></div>
        <div class="verification-banner-content">
            <strong>Want to adopt?</strong>
            <p>Complete and verify your profile to unlock adoption features and see your compatibility scores.</p>
        </div>
        <a href="complete_profile.php" class="btn btn-primary btn-sm">Complete Profile</a>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" style="display: contents;">
            <div class="form-group" style="flex: 2;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Search by name or location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" class="form-control">
                    <option value="">All</option>
                    <option value="male" <?php echo $genderFilter === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $genderFilter === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="age">Age Range</label>
                <select id="age" name="age" class="form-control">
                    <option value="">All Ages</option>
                    <option value="0-3" <?php echo $ageFilter === '0-3' ? 'selected' : ''; ?>>0-3 years</option>
                    <option value="4-7" <?php echo $ageFilter === '4-7' ? 'selected' : ''; ?>>4-7 years</option>
                    <option value="8-12" <?php echo $ageFilter === '8-12' ? 'selected' : ''; ?>>8-12 years</option>
                    <option value="13-18" <?php echo $ageFilter === '13-18' ? 'selected' : ''; ?>>13-18 years</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>

    <!-- Orphan Cards Grid -->
    <?php if (!empty($orphans)): ?>
    <div class="orphan-grid">
        <?php foreach ($orphans as $orphan): ?>
        <div class="orphan-card" data-searchable>
            <div class="orphan-card-image">
                <?php
                $photo = $orphan['photo'] ?? 'default.png';
                $photoPath = '/orphanage-management-system/uploads/orphans/' . $photo;
                ?>
                <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($orphan['full_name']); ?>" class="orphan-card-photo">
                <span class="status-tag badge <?php echo getHealthBadge($orphan['health_status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $orphan['health_status'])); ?>
                </span>
            </div>
            <div class="orphan-card-body">
                <h3><?php echo htmlspecialchars($orphan['full_name']); ?></h3>
                <div class="orphan-card-info">
                    <span><i class="fas fa-birthday-cake"></i> <?php echo $orphan['age']; ?> years old</span>
                    <span><i class="fas fa-venus-mars"></i> <?php echo ucfirst($orphan['gender']); ?></span>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($orphan['location']); ?></span>
                    <span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($orphan['education_level'] ?? 'N/A'); ?></span>
                </div>
                <div class="orphan-card-actions">
                    <a href="orphan_detail.php?id=<?php echo $orphan['id']; ?>" class="btn btn-primary btn-sm" style="flex: 1;"><i class="fas fa-eye"></i> View Profile</a>
                    <a href="adopt.php?orphan_id=<?php echo $orphan['id']; ?>" class="btn btn-accent btn-sm"><i class="fas fa-heart"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-child"></i>
                <h3>No children found</h3>
                <p>No available children match your search criteria. Try adjusting your filters.</p>
                <a href="orphans.php" class="btn btn-primary"><i class="fas fa-refresh"></i> Reset Filters</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
