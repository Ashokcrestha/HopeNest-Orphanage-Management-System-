<?php
/**
 * Admin - Manage Orphans
 * Orphanage Management System
 */
$pageTitle = 'Manage Orphans';
require_once '../includes/auth.php';
requireAdminLogin();

// Handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM orphans WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Orphan record deleted successfully.');
    redirect('orphans.php');
}

// Filters
$search = sanitize($_GET['search'] ?? '');
$genderFilter = sanitize($_GET['gender'] ?? '');
$healthFilter = sanitize($_GET['health'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = [];
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
if ($healthFilter) {
    $where[] = "health_status = ?";
    $params[] = $healthFilter;
}
if ($statusFilter) {
    $where[] = "availability_status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans $whereClause ORDER BY created_at DESC");
$stmt->execute($params);
$orphans = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-child"></i> Manage Orphans</h1>
            <p class="page-subtitle"><?php echo count($orphans); ?> children in the system</p>
        </div>
        <a href="orphan_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Child</a>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <form method="GET" action="" style="display: contents;">
            <div class="form-group">
                <label for="searchInput">Search</label>
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="Name or location..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="form-group">
                <label for="genderFilter">Gender</label>
                <select id="genderFilter" name="gender" class="form-control">
                    <option value="">All</option>
                    <option value="male" <?php echo $genderFilter === 'male' ? 'selected' : ''; ?>>Male</option>
                    <option value="female" <?php echo $genderFilter === 'female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="form-group">
                <label for="healthFilter">Health</label>
                <select id="healthFilter" name="health" class="form-control">
                    <option value="">All</option>
                    <option value="healthy" <?php echo $healthFilter === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                    <option value="minor_issues" <?php echo $healthFilter === 'minor_issues' ? 'selected' : ''; ?>>Minor Issues</option>
                    <option value="special_needs" <?php echo $healthFilter === 'special_needs' ? 'selected' : ''; ?>>Special Needs</option>
                </select>
            </div>
            <div class="form-group">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" name="status" class="form-control">
                    <option value="">All</option>
                    <option value="available" <?php echo $statusFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="adopted" <?php echo $statusFilter === 'adopted' ? 'selected' : ''; ?>>Adopted</option>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>

    <!-- Orphans Table -->
    <?php if (!empty($orphans)): ?>
    <div class="card">
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Health</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Admitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphans as $i => $orphan): ?>
                    <tr data-searchable>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <?php
                            $photo = $orphan['photo'] ?? 'default.png';
                            $photoPath = '/orphanage-management-system/uploads/orphans/' . $photo;
                            ?>
                            <img src="<?php echo $photoPath; ?>" alt="<?php echo htmlspecialchars($orphan['full_name']); ?>" class="table-thumbnail">
                        </td>
                        <td><strong><?php echo htmlspecialchars($orphan['full_name']); ?></strong></td>
                        <td><?php echo $orphan['age']; ?> yrs</td>
                        <td><?php echo ucfirst($orphan['gender']); ?></td>
                        <td><span class="badge <?php echo getHealthBadge($orphan['health_status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $orphan['health_status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($orphan['location']); ?></td>
                        <td><span class="badge <?php echo getAvailabilityBadge($orphan['availability_status']); ?>"><?php echo getAvailabilityLabel($orphan['availability_status']); ?></span></td>
                        <td><?php echo $orphan['date_admitted'] ? formatDate($orphan['date_admitted']) : '-'; ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="orphan_form.php?id=<?php echo $orphan['id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-edit"></i></a>
                                <a href="orphans.php?delete=<?php echo $orphan['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Are you sure you want to delete this record?"><i class="fas fa-trash"></i></a>
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
                <i class="fas fa-child"></i>
                <h3>No orphans found</h3>
                <p>No records match your filters. Try adjusting your search criteria.</p>
                <a href="orphan_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add First Child</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
