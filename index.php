<?php
/**
 * Landing Page
 * Orphanage Management System
 */
$pageTitle = 'Home';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Fetch statistics
$totalOrphans = $pdo->query("SELECT COUNT(*) FROM orphans")->fetchColumn();
$totalAdopted = $pdo->query("SELECT COUNT(*) FROM orphans WHERE availability_status = 'adopted'")->fetchColumn();
$totalDonations = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM donations")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Fetch featured orphans (available)
$featuredOrphans = $pdo->query("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE availability_status = 'available' ORDER BY created_at DESC LIMIT 4")->fetchAll();

require_once 'includes/header.php';
?>

<!-- Hero Section -->
<section class="hero" id="heroSection">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-sparkles"></i>
            Making a difference, one child at a time
        </div>
        <h1>Every Child Deserves a Loving Home</h1>
        <p>HopeNest connects caring families with children in need. Browse profiles, donate to support their education and health, or begin your adoption journey today.</p>
        <div class="hero-buttons">
            <?php if (isLoggedIn()): ?>
                <a href="user/orphans.php" class="btn btn-primary btn-lg"><i class="fas fa-child"></i> View Children</a>
                <a href="user/donate.php" class="btn btn-secondary btn-lg"><i class="fas fa-hand-holding-heart"></i> Donate Now</a>
            <?php elseif (isAdmin()): ?>
                <a href="admin/dashboard.php" class="btn btn-primary btn-lg"><i class="fas fa-th-large"></i> Go to Dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary btn-lg"><i class="fas fa-user-plus"></i> Get Started</a>
                <a href="login.php" class="btn btn-outline btn-lg"><i class="fas fa-sign-in-alt"></i> Login</a>
            <?php endif; ?>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <div class="value" data-count="<?php echo $totalOrphans; ?>">0</div>
                <div class="label">Children in Care</div>
            </div>
            <div class="hero-stat">
                <div class="value" data-count="<?php echo $totalAdopted; ?>">0</div>
                <div class="label">Successfully Adopted</div>
            </div>
            <div class="hero-stat">
                <div class="value" data-count="<?php echo $totalUsers; ?>">0</div>
                <div class="label">Registered Supporters</div>
            </div>
            <div class="hero-stat">
                <div class="value" data-count="<?php echo round($totalDonations / 1000); ?>" data-suffix="K+">0</div>
                <div class="label">NPR Donated</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section" style="background: var(--bg-card);">
    <div class="section-header">
        <h2>How We Help</h2>
        <p>Our platform bridges the gap between children in need and families ready to make a difference.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-child"></i></div>
            <h3>Child Profiles</h3>
            <p>Browse detailed profiles of children waiting for a forever home. Learn about their stories, interests, and needs.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <h3>Easy Donations</h3>
            <p>Contribute directly to the orphanage. Every donation goes towards education, healthcare, and daily needs.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-magic"></i></div>
            <h3>Smart Matching</h3>
            <p>Our intelligent matching algorithm recommends the best matches based on your preferences and the child's needs.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-clipboard-check"></i></div>
            <h3>Adoption Tracking</h3>
            <p>Apply for adoption and track your application status in real-time through every step of the process.</p>
        </div>
    </div>
</section>

<!-- Featured Children Section -->
<?php if (!empty($featuredOrphans)): ?>
<section class="section">
    <div class="section-header">
        <h2>Children Waiting for a Home</h2>
        <p>These children are looking for a loving family. Could you be the one?</p>
    </div>
    <div class="orphan-grid" style="max-width: 1200px; margin: 0 auto;">
        <?php foreach ($featuredOrphans as $orphan): ?>
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
                </div>
                <div class="orphan-card-actions">
                    <?php if (isLoggedIn()): ?>
                        <a href="user/orphan_detail.php?id=<?php echo $orphan['id']; ?>" class="btn btn-primary btn-sm btn-block">View Profile</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-primary btn-sm btn-block">Login to View</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
