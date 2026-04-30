<?php
/**
 * User - Donate (Multi-Category)
 * Orphanage Management System
 * 
 * Supports both monetary and material donations:
 * - Monetary:     Cash via online/bank/mobile wallet
 * - Clothing:     Clothes, shoes, uniforms
 * - Food:         Food items, groceries
 * - Toys:         Toys, games, play equipment
 * - Educational:  Books, stationery, school supplies
 * - Supplies:     Hygiene, bedding, medicine
 * - Other:        Any other useful donations
 */
$pageTitle = 'Make a Donation';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Donation categories with icons and descriptions
$donationCategories = [
    'monetary'    => ['icon' => 'fas fa-money-bill-wave', 'label' => 'Monetary', 'color' => '#00b894', 'desc' => 'Cash donations via online payment, bank transfer, or mobile wallet'],
    'clothing'    => ['icon' => 'fas fa-tshirt', 'label' => 'Clothing', 'color' => '#6c5ce7', 'desc' => 'Clothes, shoes, uniforms, blankets, and warm wear'],
    'food'        => ['icon' => 'fas fa-utensils', 'label' => 'Food Items', 'color' => '#e17055', 'desc' => 'Groceries, dry food, canned goods, baby food, and nutrition items'],
    'toys'        => ['icon' => 'fas fa-puzzle-piece', 'label' => 'Toys & Games', 'color' => '#fdcb6e', 'desc' => 'Toys, board games, play equipment, and recreational items'],
    'educational' => ['icon' => 'fas fa-book-open', 'label' => 'Educational', 'color' => '#0984e3', 'desc' => 'Books, stationery, school bags, notebooks, and learning materials'],
    'supplies'    => ['icon' => 'fas fa-box-open', 'label' => 'Essential Supplies', 'color' => '#00cec9', 'desc' => 'Hygiene products, bedding, medicine, and daily essentials'],
    'other'       => ['icon' => 'fas fa-gift', 'label' => 'Other', 'color' => '#a29bfe', 'desc' => 'Any other useful items for the children'],
];

// Handle donation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donationType = sanitize($_POST['donation_type'] ?? 'monetary');
    // Pick the correct amount field based on donation type
    $rawAmount = $donationType === 'monetary'
        ? ($_POST['amount'] ?? '')
        : ($_POST['estimated_value'] ?? '');
    $itemDescription = sanitize($_POST['item_description'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'online');

    // Sanitize and convert amount to numeric value
    // Strip commas, spaces, and currency symbols before conversion
    $cleanAmount = preg_replace('/[^0-9.]/', '', str_replace(',', '', trim($rawAmount)));
    $amount = is_numeric($cleanAmount) ? (float)$cleanAmount : 0;

    $errors = [];

    if (!array_key_exists($donationType, $donationCategories)) {
        $errors[] = 'Invalid donation type.';
    }

    if ($donationType === 'monetary') {
        // Monetary donation: amount must be a valid number > 0
        if (!is_numeric($cleanAmount) || $amount <= 0) {
            $errors[] = 'Please enter a valid donation amount greater than zero.';
        } elseif ($amount > 10000000) {
            $errors[] = 'Donation amount exceeds the maximum allowed limit (NPR 10,000,000).';
        }
    } else {
        // Material donation — item description is required
        if (empty($itemDescription)) {
            $errors[] = 'Please describe the items you are donating.';
        }
        // For material donations, validate estimated value if provided
        if (!empty($rawAmount) && (!is_numeric($cleanAmount) || $amount < 0)) {
            $errors[] = 'Please enter a valid estimated value for the donated items.';
            $amount = 0;
        }
    }

    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
    } else {
        $transactionId = generateTransactionId();
        $stmt = $pdo->prepare("
            INSERT INTO donations (user_id, donation_type, amount, item_description, message, payment_method, transaction_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $donationType, $amount, $itemDescription ?: null, $message, $paymentMethod, $transactionId]);

        if ($donationType === 'monetary') {
            setFlash('success', "Thank you! Your monetary donation of " . formatCurrency($amount) . " has been recorded. Transaction ID: $transactionId");
        } else {
            $label = $donationCategories[$donationType]['label'];
            setFlash('success', "Thank you! Your $label donation has been recorded. Our team will coordinate pickup/delivery. Reference: $transactionId");
        }
        redirect('donate.php');
    }
}

// Fetch donation history
$stmt = $pdo->prepare("SELECT * FROM donations WHERE user_id = ? ORDER BY donated_at DESC");
$stmt->execute([$userId]);
$donations = $stmt->fetchAll();

$totalMonetary = 0;
$totalMaterial = 0;
foreach ($donations as $d) {
    if (($d['donation_type'] ?? 'monetary') === 'monetary') {
        $totalMonetary += $d['amount'];
    } else {
        $totalMaterial++;
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-heart"></i> Make a Donation</h1>
            <p class="page-subtitle">Your contribution helps provide education, healthcare, and a better life for our children</p>
        </div>
    </div>

    <!-- Donation Category Selection -->
    <div class="card mb-2">
        <div class="card-header">
            <h3><i class="fas fa-th-large text-primary"></i> Choose Donation Type</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;">
                <?php foreach ($donationCategories as $key => $cat): ?>
                <div class="donation-type-card <?php echo $key === 'monetary' ? 'active' : ''; ?>"
                     data-type="<?php echo $key; ?>"
                     onclick="selectDonationType('<?php echo $key; ?>')"
                     style="cursor: pointer; padding: 16px 12px; border-radius: var(--radius-sm); border: 2px solid var(--border-light); text-align: center; transition: all 0.3s;">
                    <i class="<?php echo $cat['icon']; ?>" style="font-size: 1.5rem; color: <?php echo $cat['color']; ?>; display: block; margin-bottom: 8px;"></i>
                    <span style="font-size: 0.8rem; font-weight: 600;"><?php echo $cat['label']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="detail-grid">
        <!-- Donation Form -->
        <div>
            <div class="card mb-2">
                <div class="card-header">
                    <h3 id="formTitle"><i class="fas fa-money-bill-wave text-success"></i> Monetary Donation</h3>
                </div>
                <div class="card-body">
                    <p id="formDesc" style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px;">
                        Cash donations via online payment, bank transfer, or mobile wallet
                    </p>

                    <form method="POST" action="" data-validate onsubmit="return validateDonationForm()">
                        <input type="hidden" id="donationType" name="donation_type" value="monetary">

                        <!-- Validation Error Display -->
                        <div id="donationError" style="display: none; padding: 10px 16px; background: rgba(214,48,49,0.08); border: 1px solid rgba(214,48,49,0.2); border-radius: var(--radius-sm); color: var(--danger); font-size: 0.85rem; margin-bottom: 16px;">
                            <i class="fas fa-exclamation-circle"></i> <span id="donationErrorMsg"></span>
                        </div>

                        <!-- Monetary Fields -->
                        <div id="monetaryFields">
                            <label style="display: block; margin-bottom: 10px; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);">Select Amount (NPR)</label>
                            <div class="amount-grid">
                                <div class="amount-option" data-amount="500">500</div>
                                <div class="amount-option" data-amount="1000">1,000</div>
                                <div class="amount-option" data-amount="2500">2,500</div>
                                <div class="amount-option" data-amount="5000">5,000</div>
                                <div class="amount-option" data-amount="10000">10,000</div>
                                <div class="amount-option" data-amount="25000">25,000</div>
                            </div>

                            <div class="form-group">
                                <label for="donationAmount">Or Enter Custom Amount *</label>
                                <input type="number" id="donationAmount" name="amount" class="form-control" placeholder="Enter amount in NPR" min="1" max="10000000" step="0.01">
                            </div>

                            <div class="form-group">
                                <label for="paymentMethod">Payment Method</label>
                                <select id="paymentMethod" name="payment_method" class="form-control">
                                    <option value="online">Online Payment</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_wallet">Mobile Wallet</option>
                                </select>
                            </div>
                        </div>

                        <!-- Material Donation Fields -->
                        <div id="materialFields" style="display: none;">
                            <div class="form-group">
                                <label for="itemDescription">Describe Items You Are Donating *</label>
                                <textarea id="itemDescription" name="item_description" class="form-control" placeholder="e.g., 10 pairs of children's shoes (sizes 3-6), 5 school uniforms..." rows="4"></textarea>
                            </div>

                            <div class="form-group">
                                <label for="estimatedValue">Estimated Value (NPR) <span style="color: var(--text-muted); font-weight: 400;">— optional</span></label>
                                <input type="number" id="estimatedValue" name="estimated_value" class="form-control" placeholder="Estimated value of items" min="0" max="10000000" step="0.01">
                            </div>

                            <div style="background: rgba(0,206,201,0.08); border: 1px solid rgba(0,206,201,0.15); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 16px; font-size: 0.82rem; color: var(--info);">
                                <i class="fas fa-truck"></i>
                                <strong>Delivery:</strong> After submission, our team will contact you to coordinate pickup or drop-off of donated items.
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Message (Optional)</label>
                            <textarea id="message" name="message" class="form-control" placeholder="Leave a message of support..." rows="2"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success btn-block btn-lg">
                            <i class="fas fa-heart"></i> <span id="submitBtnText">Donate Now</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Stats + History -->
        <div>
            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div class="stat-card green" style="margin-bottom: 0; padding: 16px;">
                    <div class="stat-icon" style="font-size: 1.2rem;"><i class="fas fa-coins"></i></div>
                    <div class="stat-value" style="font-size: 1.1rem;"><?php echo formatCurrency($totalMonetary); ?></div>
                    <div class="stat-label" style="font-size: 0.72rem;">Monetary Donations</div>
                </div>
                <div class="stat-card purple" style="margin-bottom: 0; padding: 16px;">
                    <div class="stat-icon" style="font-size: 1.2rem;"><i class="fas fa-gift"></i></div>
                    <div class="stat-value" style="font-size: 1.1rem;"><?php echo $totalMaterial; ?></div>
                    <div class="stat-label" style="font-size: 0.72rem;">Material Donations</div>
                </div>
            </div>

            <!-- Donation History -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Donation History</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($donations)): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Details</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $don):
                                    $type = $don['donation_type'] ?? 'monetary';
                                    $catInfo = $donationCategories[$type] ?? $donationCategories['other'];
                                ?>
                                <tr>
                                    <td>
                                        <span style="display: flex; align-items: center; gap: 8px;">
                                            <i class="<?php echo $catInfo['icon']; ?>" style="color: <?php echo $catInfo['color']; ?>;"></i>
                                            <span style="font-size: 0.82rem;"><?php echo $catInfo['label']; ?></span>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($type === 'monetary'): ?>
                                            <strong class="text-success"><?php echo formatCurrency($don['amount']); ?></strong>
                                            <br><span class="badge badge-info" style="font-size: 0.65rem;"><?php echo ucfirst(str_replace('_', ' ', $don['payment_method'])); ?></span>
                                        <?php else: ?>
                                            <span style="font-size: 0.82rem;"><?php echo htmlspecialchars(mb_strimwidth($don['item_description'] ?? '', 0, 60, '...')); ?></span>
                                            <?php if ($don['amount'] > 0): ?>
                                            <br><span style="font-size: 0.75rem; color: var(--text-muted);">Est. <?php echo formatCurrency($don['amount']); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.82rem;"><?php echo formatDate($don['donated_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: 30px;">
                            <i class="fas fa-coins" style="font-size: 2rem;"></i>
                            <p>You haven't made any donations yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .donation-type-card.active {
        border-color: var(--primary-light) !important;
        background: rgba(99, 110, 255, 0.06);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(99, 110, 255, 0.15);
    }
    .donation-type-card:hover {
        transform: translateY(-2px);
        border-color: var(--primary-light);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
</style>

<script>
const categories = <?php echo json_encode($donationCategories); ?>;

function selectDonationType(type) {
    // Update hidden field
    document.getElementById('donationType').value = type;

    // Update active card styling
    document.querySelectorAll('.donation-type-card').forEach(card => card.classList.remove('active'));
    document.querySelector('[data-type="' + type + '"]').classList.add('active');

    // Toggle form fields
    const monetaryFields = document.getElementById('monetaryFields');
    const materialFields = document.getElementById('materialFields');
    const formTitle = document.getElementById('formTitle');
    const formDesc = document.getElementById('formDesc');
    const submitBtn = document.getElementById('submitBtnText');

    const cat = categories[type];
    formTitle.innerHTML = '<i class="' + cat.icon + '" style="color: ' + cat.color + ';"></i> ' + cat.label + ' Donation';
    formDesc.textContent = cat.desc;

    if (type === 'monetary') {
        monetaryFields.style.display = 'block';
        materialFields.style.display = 'none';
        submitBtn.textContent = 'Donate Now';
        document.getElementById('donationAmount').setAttribute('required', 'required');
        document.getElementById('itemDescription').removeAttribute('required');
    } else {
        monetaryFields.style.display = 'none';
        materialFields.style.display = 'block';
        submitBtn.textContent = 'Submit ' + cat.label + ' Donation';
        document.getElementById('donationAmount').removeAttribute('required');
        document.getElementById('itemDescription').setAttribute('required', 'required');
    }
}

// Amount grid click handler
document.querySelectorAll('.amount-option').forEach(function(option) {
    option.addEventListener('click', function() {
        document.querySelectorAll('.amount-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('donationAmount').value = this.dataset.amount;
        // Clear any error when amount is selected
        hideDonationError();
    });
});

// Client-side donation validation
function validateDonationForm() {
    const type = document.getElementById('donationType').value;
    const errorDiv = document.getElementById('donationError');
    const errorMsg = document.getElementById('donationErrorMsg');

    hideDonationError();

    if (type === 'monetary') {
        const amountInput = document.getElementById('donationAmount');
        const rawValue = amountInput.value.trim();

        // Check if empty
        if (rawValue === '') {
            showDonationError('Please enter a donation amount or select a preset amount.');
            amountInput.focus();
            return false;
        }

        // Convert to number and validate
        const amount = parseFloat(rawValue);

        if (isNaN(amount) || !isFinite(amount)) {
            showDonationError('Please enter a valid numeric donation amount.');
            amountInput.focus();
            return false;
        }

        if (amount <= 0) {
            showDonationError('Donation amount must be greater than zero.');
            amountInput.focus();
            return false;
        }

        if (amount > 10000000) {
            showDonationError('Donation amount exceeds the maximum limit of NPR 10,000,000.');
            amountInput.focus();
            return false;
        }
    } else {
        // Material donation — check description
        const desc = document.getElementById('itemDescription').value.trim();
        if (desc === '') {
            showDonationError('Please describe the items you are donating.');
            document.getElementById('itemDescription').focus();
            return false;
        }

        // Validate estimated value if provided
        const estInput = document.getElementById('estimatedValue');
        if (estInput.value.trim() !== '') {
            const estValue = parseFloat(estInput.value);
            if (isNaN(estValue) || estValue < 0) {
                showDonationError('Estimated value must be a valid non-negative number.');
                estInput.focus();
                return false;
            }
        }
    }

    return true; // All validations passed
}

function showDonationError(msg) {
    const errorDiv = document.getElementById('donationError');
    const errorMsg = document.getElementById('donationErrorMsg');
    errorMsg.textContent = msg;
    errorDiv.style.display = 'block';
    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function hideDonationError() {
    document.getElementById('donationError').style.display = 'none';
}

// Clear error on input
document.getElementById('donationAmount').addEventListener('input', hideDonationError);
document.getElementById('itemDescription').addEventListener('input', hideDonationError);
document.getElementById('estimatedValue').addEventListener('input', hideDonationError);
</script>

<?php require_once '../includes/footer.php'; ?>
