<?php
/**
 * Admin - Add/Edit Orphan Form
 * Orphanage Management System
 */
$pageTitle = 'Orphan Form';
require_once '../includes/auth.php';
requireAdminLogin();

$orphan = null;
$isEdit = false;

// Load existing data for editing
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM orphans WHERE id = ?");
    $stmt->execute([$id]);
    $orphan = $stmt->fetch();
    if ($orphan) {
        $isEdit = true;
        $pageTitle = 'Edit Orphan';
    }
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $healthStatus = sanitize($_POST['health_status'] ?? '');
    $healthDetails = sanitize($_POST['health_details'] ?? '');
    $bloodGroup = sanitize($_POST['blood_group'] ?? '');
    $behavioralTraits = sanitize($_POST['behavioral_traits'] ?? '');
    $personality = sanitize($_POST['personality'] ?? '');
    $emotionalNeeds = sanitize($_POST['emotional_needs'] ?? '');
    $adaptabilityLevel = sanitize($_POST['adaptability_level'] ?? 'moderate');
    $background = sanitize($_POST['background'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $educationLevel = sanitize($_POST['education_level'] ?? '');
    $availabilityStatus = sanitize($_POST['availability_status'] ?? 'available');
    $dateAdmitted = sanitize($_POST['date_admitted'] ?? '');

    // Handle photo upload
    $photoFileName = $orphan['photo'] ?? 'default.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);
        $fileSize = $_FILES['photo']['size'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Invalid file type. Please upload JPG, PNG, or WebP.';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $error = 'File too large. Maximum size is 5MB.';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photoFileName = 'child_' . time() . '_' . uniqid() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/orphans/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photoFileName)) {
                $error = 'Failed to upload photo. Please try again.';
                $photoFileName = $orphan['photo'] ?? 'default.png';
            }
        }
    }

    if (empty($fullName) || empty($dob) || empty($gender)) {
        $error = 'Please fill in all required fields.';
    }

    if (empty($error)) {
        if ($isEdit) {
            $stmt = $pdo->prepare("
                UPDATE orphans SET
                    full_name = ?, date_of_birth = ?, gender = ?, health_status = ?,
                    health_details = ?, blood_group = ?, behavioral_traits = ?, personality = ?,
                    emotional_needs = ?, adaptability_level = ?, background = ?, location = ?,
                    education_level = ?, availability_status = ?, date_admitted = ?, photo = ?
                WHERE id = ?
            ");
            $stmt->execute([$fullName, $dob, $gender, $healthStatus, $healthDetails, $bloodGroup, $behavioralTraits, $personality, $emotionalNeeds, $adaptabilityLevel, $background, $location, $educationLevel, $availabilityStatus, $dateAdmitted ?: null, $photoFileName, $orphan['id']]);
            setFlash('success', 'Orphan record updated successfully.');
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO orphans (full_name, date_of_birth, gender, health_status, health_details, blood_group, behavioral_traits, personality, emotional_needs, adaptability_level, background, location, education_level, availability_status, date_admitted, photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$fullName, $dob, $gender, $healthStatus, $healthDetails, $bloodGroup, $behavioralTraits, $personality, $emotionalNeeds, $adaptabilityLevel, $background, $location, $educationLevel, $availabilityStatus, $dateAdmitted ?: null, $photoFileName]);
            setFlash('success', 'New orphan record added successfully.');
        }
        redirect('orphans.php');
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $isEdit ? 'Edit' : 'Add New'; ?> Child Record</h1>
            <p class="page-subtitle"><?php echo $isEdit ? 'Update child information' : 'Register a new child in the system'; ?></p>
        </div>
        <a href="orphans.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to List</a>
    </div>

    <div class="card" style="max-width: 800px;">
        <?php if ($error): ?>
            <div style="background: rgba(225,112,85,0.1); border: 1px solid rgba(225,112,85,0.3); color: var(--danger); padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 20px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" data-validate>
            <!-- Photo Upload Section -->
            <div class="photo-upload-section">
                <div class="photo-preview-container">
                    <?php
                    $currentPhoto = $orphan['photo'] ?? 'default.png';
                    $photoPath = '/orphanage-management-system/uploads/orphans/' . $currentPhoto;
                    ?>
                    <img src="<?php echo $photoPath; ?>" alt="Child Photo" class="photo-preview" id="photoPreview">
                    <label for="photoInput" class="photo-upload-overlay">
                        <i class="fas fa-camera"></i>
                        <span><?php echo $isEdit ? 'Change Photo' : 'Upload Photo'; ?></span>
                    </label>
                </div>
                <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" style="display: none;">
                <p class="form-text" style="text-align: center; margin-top: 8px;">JPG, PNG or WebP • Max 5MB</p>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" class="form-control" placeholder="Child's full name" value="<?php echo htmlspecialchars($orphan['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth *</label>
                    <input type="date" id="dob" name="date_of_birth" class="form-control" value="<?php echo $orphan['date_of_birth'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Gender *</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="male" <?php echo ($orphan['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($orphan['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="healthStatus">Health Status</label>
                    <select id="healthStatus" name="health_status" class="form-control">
                        <option value="healthy" <?php echo ($orphan['health_status'] ?? '') === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                        <option value="minor_issues" <?php echo ($orphan['health_status'] ?? '') === 'minor_issues' ? 'selected' : ''; ?>>Minor Issues</option>
                        <option value="special_needs" <?php echo ($orphan['health_status'] ?? '') === 'special_needs' ? 'selected' : ''; ?>>Special Needs</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="healthDetails">Health Details</label>
                <textarea id="healthDetails" name="health_details" class="form-control" placeholder="Details about child's health condition..."><?php echo htmlspecialchars($orphan['health_details'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label for="bloodGroup">Blood Group</label>
                <select id="bloodGroup" name="blood_group" class="form-control">
                    <option value="">-- Select --</option>
                    <?php
                    $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                    foreach ($bloodGroups as $bg):
                    ?>
                    <option value="<?php echo $bg; ?>" <?php echo ($orphan['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="behavioralTraits"><i class="fas fa-smile text-warning"></i> Behavioral Traits</label>
                <textarea id="behavioralTraits" name="behavioral_traits" class="form-control" placeholder="e.g., Cheerful, curious, enjoys drawing, good with other children..."><?php echo htmlspecialchars($orphan['behavioral_traits'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="personality"><i class="fas fa-star text-info"></i> Personality</label>
                    <input type="text" id="personality" name="personality" class="form-control" placeholder="e.g., Curious and joyful, Gentle and thoughtful" value="<?php echo htmlspecialchars($orphan['personality'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="adaptabilityLevel"><i class="fas fa-sync-alt text-success"></i> Adaptability Level</label>
                    <select id="adaptabilityLevel" name="adaptability_level" class="form-control">
                        <option value="high" <?php echo ($orphan['adaptability_level'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="moderate" <?php echo ($orphan['adaptability_level'] ?? 'moderate') === 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                        <option value="low" <?php echo ($orphan['adaptability_level'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="emotionalNeeds"><i class="fas fa-heart text-danger"></i> Emotional Needs</label>
                <input type="text" id="emotionalNeeds" name="emotional_needs" class="form-control" placeholder="e.g., Needs nurturing attention, Thrives in calm environments" value="<?php echo htmlspecialchars($orphan['emotional_needs'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="background">Background Story</label>
                <textarea id="background" name="background" class="form-control" placeholder="Brief background of the child..." style="min-height: 120px;"><?php echo htmlspecialchars($orphan['background'] ?? ''); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" class="form-control" placeholder="City/District" value="<?php echo htmlspecialchars($orphan['location'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="educationLevel">Education Level</label>
                    <input type="text" id="educationLevel" name="education_level" class="form-control" placeholder="e.g., Grade 3, Pre-school" value="<?php echo htmlspecialchars($orphan['education_level'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="availabilityStatus">Availability Status</label>
                    <select id="availabilityStatus" name="availability_status" class="form-control">
                        <option value="available" <?php echo ($orphan['availability_status'] ?? '') === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="pending" <?php echo ($orphan['availability_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="adopted" <?php echo ($orphan['availability_status'] ?? '') === 'adopted' ? 'selected' : ''; ?>>Adopted</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dateAdmitted">Date Admitted</label>
                    <input type="date" id="dateAdmitted" name="date_admitted" class="form-control" value="<?php echo $orphan['date_admitted'] ?? ''; ?>">
                </div>
            </div>

            <div class="btn-group mt-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-<?php echo $isEdit ? 'save' : 'plus'; ?>"></i>
                    <?php echo $isEdit ? 'Update Record' : 'Add Child'; ?>
                </button>
                <a href="orphans.php" class="btn btn-outline btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Photo preview
document.getElementById('photoInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
