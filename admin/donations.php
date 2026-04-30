<?php
/**
 * Admin - Manage Donations (Multi-Category)
 * Orphanage Management System
 */
$pageTitle = 'Manage Donations';
require_once '../includes/auth.php';
requireAdminLogin();

// Category config
$donationCategories = [
    'monetary'    => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Monetary', 'color' => '#00b894'],
    'clothing'    => ['icon' => 'fas fa-tshirt', 'label' => 'Clothing', 'color' => '#6c5ce7'],
    'food'        => ['icon' => 'fas fa-utensils', 'label' => 'Food', 'color' => '#e17055'],
    'toys'        => ['icon' => 'fas fa-puzzle-piece', 'label' => 'Toys', 'color' => '#fdcb6e'],
    'educational' => ['icon' => 'fas fa-book-open', 'label' => 'Educational', 'color' => '#0984e3'],
    'supplies'    => ['icon' => 'fas fa-box-open', 'label' => 'Supplies', 'color' => '#00cec9'],
    'other'       => ['icon' => 'fas fa-gift', 'label' => 'Other', 'color' => '#a29bfe'],
];

// Fetch all donations (JOIN with users)
$stmt = $pdo->query("
    SELECT d.*, u.full_name as user_name, u.email as user_email
    FROM donations d
    JOIN users u ON d.user_id = u.id
    ORDER BY d.donated_at DESC
");
$donations = $stmt->fetchAll();

// Stats
$totalMonetary = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE donation_type = 'monetary' OR donation_type IS NULL")->fetchColumn();
$totalMaterial = $pdo->query("SELECT COUNT(*) FROM donations WHERE donation_type != 'monetary' AND donation_type IS NOT NULL")->fetchColumn();
$donationCount = count($donations);

// Category breakdown
$categoryBreakdown = $pdo->query("
    SELECT COALESCE(donation_type, 'monetary') as dtype, COUNT(*) as cnt
    FROM donations
    GROUP BY dtype
    ORDER BY cnt DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-heart"></i> Donations</h1>
            <p class="page-subtitle">Track all monetary and material donations</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card green">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-value"><?php echo formatCurrency($totalMonetary); ?></div>
            <div class="stat-label">Monetary Total</div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fas fa-gift"></i></div>
            <div class="stat-value"><?php echo $totalMaterial; ?></div>
            <div class="stat-label">Material Donations</div>
        </div>
        <div class="stat-card teal">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-value"><?php echo $donationCount; ?></div>
            <div class="stat-label">Total Transactions</div>
        </div>
    </div>

    <!-- Category Breakdown -->
    <?php if (!empty($categoryBreakdown)): ?>
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie text-info"></i> Donations by Category</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <?php foreach ($categoryBreakdown as $cb):
                    $type = $cb['dtype'];
                    $cat = $donationCategories[$type] ?? $donationCategories['other'];
                ?>
                <div style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border-light);">
                    <i class="<?php echo $cat['icon']; ?>" style="color: <?php echo $cat['color']; ?>;"></i>
                    <span style="font-size: 0.85rem; font-weight: 600;"><?php echo $cat['label']; ?></span>
                    <span class="badge badge-primary" style="font-size: 0.7rem;"><?php echo $cb['cnt']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Donations Table -->
    <?php if (!empty($donations)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Donations</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Donor</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $i => $don):
                        $type = $don['donation_type'] ?? 'monetary';
                        $cat = $donationCategories[$type] ?? $donationCategories['other'];
                    ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <span style="display: flex; align-items: center; gap: 6px;">
                                <i class="<?php echo $cat['icon']; ?>" style="color: <?php echo $cat['color']; ?>;"></i>
                                <span style="font-size: 0.82rem;"><?php echo $cat['label']; ?></span>
                            </span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($don['user_name']); ?></strong>
                            <br><span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($don['user_email']); ?></span>
                        </td>
                        <td style="max-width: 220px;">
                            <?php if ($type !== 'monetary' && !empty($don['item_description'])): ?>
                                <span style="font-size: 0.82rem;"><?php echo htmlspecialchars(mb_strimwidth($don['item_description'], 0, 80, '...')); ?></span>
                            <?php elseif (!empty($don['message'])): ?>
                                <span style="font-size: 0.82rem; color: var(--text-muted); font-style: italic;"><?php echo htmlspecialchars(mb_strimwidth($don['message'], 0, 60, '...')); ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($don['amount'] > 0): ?>
                                <strong class="text-success"><?php echo formatCurrency($don['amount']); ?></strong>
                                <?php if ($type !== 'monetary'): ?>
                                <br><span style="font-size: 0.7rem; color: var(--text-muted);">est. value</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><code style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $don['transaction_id'] ?? '-'; ?></code></td>
                        <td style="font-size: 0.82rem;"><?php echo formatDate($don['donated_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="card">
            <div class="empty-state">
                <i class="fas fa-hand-holding-heart"></i>
                <h3>No donations yet</h3>
                <p>Donations will appear here once users start contributing.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
