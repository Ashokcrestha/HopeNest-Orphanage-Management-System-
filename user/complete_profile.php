<?php
/**
 * User - Complete Profile
 * Orphanage Management System
 * 
 * Multi-section profile completion page for adoption verification.
 * Users must complete biodata, upload documents, provide health records,
 * and set adoption preferences before submitting for admin verification.
 */
$pageTitle = 'Complete Your Profile';
require_once '../includes/auth.php';
requireUserLogin();

$userId = $_SESSION['user_id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch existing documents
$existingDocs = [];
$stmt = $pdo->prepare("SELECT * FROM user_documents WHERE user_id = ?");
$stmt->execute([$userId]);
foreach ($stmt->fetchAll() as $doc) {
    $existingDocs[$doc['doc_type']] = $doc;
}

// If already verified, allow access for preference editing only
$preferencesOnlyMode = false;
if ($user['profile_status'] === 'verified') {
    $preferencesOnlyMode = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if ($preferencesOnlyMode) {
        // ---- Preferences-only update for verified users ----
        $ageMin    = (int)($_POST['age_preference_min'] ?? 0);
        $ageMax    = (int)($_POST['age_preference_max'] ?? 18);
        $genderPref = sanitize($_POST['gender_preference'] ?? 'any');
        $healthPref = sanitize($_POST['health_preference'] ?? 'any');
        $educationPref = sanitize($_POST['education_preference'] ?? '');
        $emotionalPref = sanitize($_POST['emotional_preference'] ?? '');
        $adaptabilityPref = sanitize($_POST['adaptability_preference'] ?? 'any');
        $behaviorPref = sanitize($_POST['behavior_preference'] ?? '');
        $familyBgPref = sanitize($_POST['family_background_preference'] ?? '');

        $stmt = $pdo->prepare("
            UPDATE users SET
                age_preference_min = ?, age_preference_max = ?,
                gender_preference = ?, health_preference = ?, education_preference = ?,
                emotional_preference = ?, adaptability_preference = ?,
                behavior_preference = ?, family_background_preference = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $ageMin, $ageMax,
            $genderPref, $healthPref, $educationPref,
            $emotionalPref, $adaptabilityPref,
            $behaviorPref, $familyBgPref, $userId
        ]);

        setFlash('success', 'Adoption preferences updated successfully! Your matching recommendations have been refreshed.');
        redirect('profile.php');
    } else {
        // ---- Full profile submission for non-verified users ----

        // ---- Biodata ----
        $fullName      = sanitize($_POST['full_name'] ?? '');
        $dob           = sanitize($_POST['date_of_birth'] ?? '');
        $gender        = sanitize($_POST['gender'] ?? '');
        $maritalStatus = sanitize($_POST['marital_status'] ?? '');
        $phone         = sanitize($_POST['phone'] ?? '');
        $address       = sanitize($_POST['address'] ?? '');
        $location      = sanitize($_POST['location'] ?? '');
        $occupation    = sanitize($_POST['occupation'] ?? '');
        $annualIncome  = (float)($_POST['annual_income'] ?? 0);
        $familySize    = (int)($_POST['family_size'] ?? 1);

        // ---- Health ----
        $bloodGroup       = sanitize($_POST['blood_group'] ?? '');
        $healthConditions = sanitize($_POST['health_conditions'] ?? '');

        // ---- Adoption Preferences ----
        $ageMin    = (int)($_POST['age_preference_min'] ?? 0);
        $ageMax    = (int)($_POST['age_preference_max'] ?? 18);
        $genderPref = sanitize($_POST['gender_preference'] ?? 'any');
        $healthPref = sanitize($_POST['health_preference'] ?? 'any');
        $educationPref = sanitize($_POST['education_preference'] ?? '');
        $emotionalPref = sanitize($_POST['emotional_preference'] ?? '');
        $adaptabilityPref = sanitize($_POST['adaptability_preference'] ?? 'any');
        $behaviorPref = sanitize($_POST['behavior_preference'] ?? '');
        $familyBgPref = sanitize($_POST['family_background_preference'] ?? '');

        // Validation
        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($dob)) $errors[] = 'Date of birth is required.';
        if (empty($gender)) $errors[] = 'Gender is required.';
        if (empty($maritalStatus)) $errors[] = 'Marital status is required.';
        if (empty($phone)) $errors[] = 'Phone number is required.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($location)) $errors[] = 'Location is required.';
        if (empty($occupation)) $errors[] = 'Occupation is required.';
        if (empty($bloodGroup)) $errors[] = 'Blood group is required.';

        // Document upload validation
        $docTypes = [
            'national_id'        => 'National ID / Passport',
            'citizenship'        => 'Citizenship Certificate',
            'occupation_proof'   => 'Occupation / Employment Proof',
            'health_certificate' => 'Health Certificate'
        ];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        $uploadFiles = [];

        foreach ($docTypes as $fieldName => $label) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $fileType = mime_content_type($_FILES[$fieldName]['tmp_name']);
                if (!in_array($fileType, $allowedTypes)) {
                    $errors[] = "$label must be PDF, JPG, PNG, or WebP.";
                } elseif ($_FILES[$fieldName]['size'] > $maxSize) {
                    $errors[] = "$label file is too large (max 10MB).";
                } else {
                    $uploadFiles[$fieldName] = $_FILES[$fieldName];
                }
            } elseif (!isset($existingDocs[$fieldName])) {
                // Only require if no existing document
                $errors[] = "$label document is required.";
            }
        }

        if (!empty($errors)) {
            setFlash('error', implode('<br>', $errors));
        } else {
            // Upload documents
            $uploadDir = __DIR__ . '/../uploads/profile_docs/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($uploadFiles as $fieldName => $file) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fileName = $fieldName . '_' . $userId . '_' . time() . '_' . uniqid() . '.' . $ext;

                if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                    // Delete old file if exists
                    if (isset($existingDocs[$fieldName])) {
                        $oldFile = $uploadDir . $existingDocs[$fieldName]['file_name'];
                        if (file_exists($oldFile)) unlink($oldFile);

                        $stmt = $pdo->prepare("UPDATE user_documents SET file_name = ?, original_name = ?, uploaded_at = NOW() WHERE user_id = ? AND doc_type = ?");
                        $stmt->execute([$fileName, $file['name'], $userId, $fieldName]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, doc_type, file_name, original_name) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$userId, $fieldName, $fileName, $file['name']]);
                    }
                } else {
                    $errors[] = "Failed to upload $fieldName.";
                }
            }

            if (!empty($errors)) {
                setFlash('error', implode('<br>', $errors));
            } else {
                // Update user profile
                $stmt = $pdo->prepare("
                    UPDATE users SET
                        full_name = ?, date_of_birth = ?, gender = ?, marital_status = ?,
                        phone = ?, address = ?, location = ?, occupation = ?,
                        annual_income = ?, family_size = ?, blood_group = ?,
                        health_conditions = ?, age_preference_min = ?, age_preference_max = ?,
                        gender_preference = ?, health_preference = ?, education_preference = ?,
                        emotional_preference = ?, adaptability_preference = ?,
                        behavior_preference = ?, family_background_preference = ?,
                        profile_status = 'pending', profile_submitted_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $fullName, $dob, $gender, $maritalStatus,
                    $phone, $address, $location, $occupation,
                    $annualIncome, $familySize, $bloodGroup,
                    $healthConditions, $ageMin, $ageMax,
                    $genderPref, $healthPref, $educationPref,
                    $emotionalPref, $adaptabilityPref,
                    $behaviorPref, $familyBgPref, $userId
                ]);

                $_SESSION['user_name'] = $fullName;
                setFlash('success', 'Profile submitted for verification! You can browse children and donate while we review your profile.');
                redirect('dashboard.php');
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <?php if ($preferencesOnlyMode): ?>
            <h1><i class="fas fa-sliders-h"></i> Update Adoption Preferences</h1>
            <p class="page-subtitle">Refine your preferences to improve child-adopter matching recommendations</p>
            <?php else: ?>
            <h1><i class="fas fa-user-edit"></i> Complete Your Profile</h1>
            <p class="page-subtitle">Fill in all required details to unlock adoption features</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($preferencesOnlyMode): ?>
    <!-- Verified Profile Banner -->
    <div class="verification-banner verification-info">
        <div class="verification-banner-icon"><i class="fas fa-shield-alt"></i></div>
        <div class="verification-banner-content">
            <strong>Profile Verified — Preferences Editable</strong>
            <p>Your verified personal data and documents are locked for data integrity. You can freely update your adoption preferences below to refine your matching recommendations.</p>
        </div>
    </div>
    <?php else: ?>
    <!-- Status Banner -->
    <?php if ($user['profile_status'] === 'pending'): ?>
    <div class="verification-banner verification-pending">
        <div class="verification-banner-icon"><i class="fas fa-clock"></i></div>
        <div class="verification-banner-content">
            <strong>Profile Under Review</strong>
            <p>Your profile has been submitted and is being reviewed by our team. You can still browse children and donate while waiting.</p>
        </div>
    </div>
    <?php elseif ($user['profile_status'] === 'rejected'): ?>
    <div class="verification-banner verification-rejected">
        <div class="verification-banner-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="verification-banner-content">
            <strong>Profile Verification Rejected</strong>
            <p><?php echo htmlspecialchars($user['profile_rejection_note'] ?? 'Please review and update your profile details.'); ?></p>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!$preferencesOnlyMode): ?>
    <!-- Progress Steps -->
    <div class="profile-stepper">
        <div class="step active" data-step="1">
            <div class="step-circle">1</div>
            <span class="step-label">Biodata</span>
        </div>
        <div class="step-line"></div>
        <div class="step" data-step="2">
            <div class="step-circle">2</div>
            <span class="step-label">Documents</span>
        </div>
        <div class="step-line"></div>
        <div class="step" data-step="3">
            <div class="step-circle">3</div>
            <span class="step-label">Health</span>
        </div>
        <div class="step-line"></div>
        <div class="step" data-step="4">
            <div class="step-circle">4</div>
            <span class="step-label">Preferences</span>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data" data-validate id="profileForm">
        <!-- Step 1: Biodata -->
        <?php if (!$preferencesOnlyMode): ?>
        <div class="profile-step active" id="step1">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-id-card text-primary"></i> Personal Biodata</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="dob">Date of Birth *</label>
                            <input type="date" id="dob" name="date_of_birth" class="form-control" value="<?php echo $user['date_of_birth'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="male" <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="maritalStatus">Marital Status *</label>
                            <select id="maritalStatus" name="marital_status" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="single" <?php echo ($user['marital_status'] ?? '') === 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="married" <?php echo ($user['marital_status'] ?? '') === 'married' ? 'selected' : ''; ?>>Married</option>
                                <option value="divorced" <?php echo ($user['marital_status'] ?? '') === 'divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="widowed" <?php echo ($user['marital_status'] ?? '') === 'widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location (City/District) *</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Full Address *</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" placeholder="Ward, Municipality, District" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="occupation">Occupation *</label>
                            <input type="text" id="occupation" name="occupation" class="form-control" value="<?php echo htmlspecialchars($user['occupation'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="annualIncome">Annual Income (NPR)</label>
                            <input type="number" id="annualIncome" name="annual_income" class="form-control" value="<?php echo $user['annual_income'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="familySize">Family Size</label>
                            <input type="number" id="familySize" name="family_size" class="form-control" min="1" value="<?php echo $user['family_size'] ?? 1; ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-actions">
                <span></span>
                <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                    Next: Documents <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Documents -->
        <div class="profile-step" id="step2">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt text-warning"></i> Identity & Employment Documents</h3>
                </div>
                <div class="card-body">
                    <div class="doc-section-header">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <p class="doc-section-title">Why do we need these documents?</p>
                            <p class="doc-section-subtitle">To ensure the safety and well-being of every child, we verify that all potential adopters have valid identity, legal citizenship, and stable employment.</p>
                        </div>
                    </div>

                    <!-- National ID -->
                    <div class="form-group">
                        <label for="nationalIdDoc"><i class="fas fa-id-card text-primary"></i> National ID / Passport *</label>
                        <div class="doc-upload-box <?php echo isset($existingDocs['national_id']) ? 'uploaded' : ''; ?>" id="nationalIdBox">
                            <input type="file" id="nationalIdDoc" name="national_id" accept=".pdf,.jpg,.jpeg,.png,.webp" class="doc-file-input" <?php echo !isset($existingDocs['national_id']) ? 'required' : ''; ?>>
                            <div class="doc-upload-placeholder" <?php echo isset($existingDocs['national_id']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload National ID</span>
                                <small>PDF, JPG, PNG or WebP • Max 10MB</small>
                            </div>
                            <div class="doc-upload-success" <?php echo !isset($existingDocs['national_id']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-check-circle"></i>
                                <span class="doc-file-name"><?php echo isset($existingDocs['national_id']) ? htmlspecialchars($existingDocs['national_id']['original_name']) : ''; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Citizenship Certificate -->
                    <div class="form-group">
                        <label for="citizenshipDoc"><i class="fas fa-passport text-secondary"></i> Citizenship Certificate *</label>
                        <div class="doc-upload-box <?php echo isset($existingDocs['citizenship']) ? 'uploaded' : ''; ?>" id="citizenshipBox">
                            <input type="file" id="citizenshipDoc" name="citizenship" accept=".pdf,.jpg,.jpeg,.png,.webp" class="doc-file-input" <?php echo !isset($existingDocs['citizenship']) ? 'required' : ''; ?>>
                            <div class="doc-upload-placeholder" <?php echo isset($existingDocs['citizenship']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload Citizenship Certificate</span>
                                <small>PDF, JPG, PNG or WebP • Max 10MB</small>
                            </div>
                            <div class="doc-upload-success" <?php echo !isset($existingDocs['citizenship']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-check-circle"></i>
                                <span class="doc-file-name"><?php echo isset($existingDocs['citizenship']) ? htmlspecialchars($existingDocs['citizenship']['original_name']) : ''; ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Occupation Proof -->
                    <div class="form-group">
                        <label for="occupationDoc"><i class="fas fa-briefcase text-warning"></i> Occupation / Employment Proof *</label>
                        <div class="doc-upload-box <?php echo isset($existingDocs['occupation_proof']) ? 'uploaded' : ''; ?>" id="occupationBox">
                            <input type="file" id="occupationDoc" name="occupation_proof" accept=".pdf,.jpg,.jpeg,.png,.webp" class="doc-file-input" <?php echo !isset($existingDocs['occupation_proof']) ? 'required' : ''; ?>>
                            <div class="doc-upload-placeholder" <?php echo isset($existingDocs['occupation_proof']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload Employment Proof</span>
                                <small>Salary slip, employment letter, or business registration • Max 10MB</small>
                            </div>
                            <div class="doc-upload-success" <?php echo !isset($existingDocs['occupation_proof']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-check-circle"></i>
                                <span class="doc-file-name"><?php echo isset($existingDocs['occupation_proof']) ? htmlspecialchars($existingDocs['occupation_proof']['original_name']) : ''; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-actions">
                <button type="button" class="btn btn-outline" onclick="prevStep(1)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                    Next: Health Records <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Step 3: Health Records -->
        <div class="profile-step" id="step3">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-heartbeat text-danger"></i> Health Records</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bloodGroup">Blood Group *</label>
                            <select id="bloodGroup" name="blood_group" class="form-control" required>
                                <option value="">-- Select --</option>
                                <?php
                                $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                foreach ($bloodGroups as $bg):
                                ?>
                                <option value="<?php echo $bg; ?>" <?php echo ($user['blood_group'] ?? '') === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="healthConditions">Health Conditions / Notes</label>
                        <textarea id="healthConditions" name="health_conditions" class="form-control" rows="4" placeholder="Mention any chronic illnesses, disabilities, allergies, or medications. Write 'None' if not applicable."><?php echo htmlspecialchars($user['health_conditions'] ?? ''); ?></textarea>
                    </div>

                    <!-- Health Certificate -->
                    <div class="form-group">
                        <label for="healthCertDoc"><i class="fas fa-file-medical text-success"></i> Health Certificate *</label>
                        <div class="doc-upload-box <?php echo isset($existingDocs['health_certificate']) ? 'uploaded' : ''; ?>" id="healthCertBox">
                            <input type="file" id="healthCertDoc" name="health_certificate" accept=".pdf,.jpg,.jpeg,.png,.webp" class="doc-file-input" <?php echo !isset($existingDocs['health_certificate']) ? 'required' : ''; ?>>
                            <div class="doc-upload-placeholder" <?php echo isset($existingDocs['health_certificate']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-cloud-upload-alt"></i>
                                <span>Click to upload Health Certificate</span>
                                <small>Medical fitness certificate from a registered hospital • Max 10MB</small>
                            </div>
                            <div class="doc-upload-success" <?php echo !isset($existingDocs['health_certificate']) ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-check-circle"></i>
                                <span class="doc-file-name"><?php echo isset($existingDocs['health_certificate']) ? htmlspecialchars($existingDocs['health_certificate']['original_name']) : ''; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="step-actions">
                <button type="button" class="btn btn-outline" onclick="prevStep(2)">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary" onclick="nextStep(4)">
                    Next: Preferences <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
        <?php endif; /* end !preferencesOnlyMode for steps 1-3 */ ?>

        <!-- Step 4: Adoption Preferences -->
        <div class="profile-step <?php echo $preferencesOnlyMode ? 'active' : ''; ?>" id="step4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sliders-h text-info"></i> Adoption Preferences</h3>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 20px;">
                        <i class="fas fa-info-circle"></i> These preferences help our <strong>Child-Adopter Matching Algorithm</strong> recommend the most compatible children for you.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ageMin">Preferred Child Age (Min)</label>
                            <input type="number" id="ageMin" name="age_preference_min" class="form-control" min="0" max="18" value="<?php echo $user['age_preference_min']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="ageMax">Preferred Child Age (Max)</label>
                            <input type="number" id="ageMax" name="age_preference_max" class="form-control" min="0" max="18" value="<?php echo $user['age_preference_max']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="genderPref">Gender Preference</label>
                            <select id="genderPref" name="gender_preference" class="form-control">
                                <option value="any" <?php echo $user['gender_preference'] === 'any' ? 'selected' : ''; ?>>Any</option>
                                <option value="male" <?php echo $user['gender_preference'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $user['gender_preference'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <hr style="border-color: var(--border-light); margin: 20px 0;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 16px;">
                        <i class="fas fa-sliders-h"></i> <strong>Advanced Preferences</strong> — These help the algorithm provide deeper, human-centered matching.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="healthPref"><i class="fas fa-heartbeat text-danger"></i> Health Condition Preference</label>
                            <select id="healthPref" name="health_preference" class="form-control">
                                <option value="any" <?php echo ($user['health_preference'] ?? 'any') === 'any' ? 'selected' : ''; ?>>Any (No Preference)</option>
                                <option value="healthy" <?php echo ($user['health_preference'] ?? '') === 'healthy' ? 'selected' : ''; ?>>Healthy Only</option>
                                <option value="minor_issues" <?php echo ($user['health_preference'] ?? '') === 'minor_issues' ? 'selected' : ''; ?>>Healthy or Minor Issues</option>
                                <option value="special_needs" <?php echo ($user['health_preference'] ?? '') === 'special_needs' ? 'selected' : ''; ?>>Special Needs (Willing)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="educationPref"><i class="fas fa-graduation-cap text-info"></i> Education Level Preference</label>
                            <input type="text" id="educationPref" name="education_preference" class="form-control" placeholder="e.g., Pre-school, Grade 1-3, Any" value="<?php echo htmlspecialchars($user['education_preference'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="adaptabilityPref"><i class="fas fa-sync-alt text-success"></i> Child Adaptability Preference</label>
                            <select id="adaptabilityPref" name="adaptability_preference" class="form-control">
                                <option value="any" <?php echo ($user['adaptability_preference'] ?? 'any') === 'any' ? 'selected' : ''; ?>>Any (No Preference)</option>
                                <option value="high" <?php echo ($user['adaptability_preference'] ?? '') === 'high' ? 'selected' : ''; ?>>High Adaptability</option>
                                <option value="moderate" <?php echo ($user['adaptability_preference'] ?? '') === 'moderate' ? 'selected' : ''; ?>>Moderate Adaptability</option>
                                <option value="low" <?php echo ($user['adaptability_preference'] ?? '') === 'low' ? 'selected' : ''; ?>>Low (Needs Extra Care)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="emotionalPref"><i class="fas fa-heart text-warning"></i> Emotional Environment You Offer</label>
                            <input type="text" id="emotionalPref" name="emotional_preference" class="form-control" placeholder="e.g., Calm and nurturing, Active and engaging" value="<?php echo htmlspecialchars($user['emotional_preference'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="behaviorPref"><i class="fas fa-child text-primary"></i> Behavior Preference</label>
                            <select id="behaviorPref" name="behavior_preference" class="form-control">
                                <option value="" <?php echo empty($user['behavior_preference'] ?? '') ? 'selected' : ''; ?>>Any (No Preference)</option>
                                <option value="calm" <?php echo ($user['behavior_preference'] ?? '') === 'calm' ? 'selected' : ''; ?>>Calm & Quiet</option>
                                <option value="active" <?php echo ($user['behavior_preference'] ?? '') === 'active' ? 'selected' : ''; ?>>Active & Energetic</option>
                                <option value="social" <?php echo ($user['behavior_preference'] ?? '') === 'social' ? 'selected' : ''; ?>>Social & Outgoing</option>
                                <option value="independent" <?php echo ($user['behavior_preference'] ?? '') === 'independent' ? 'selected' : ''; ?>>Independent & Self-reliant</option>
                                <option value="creative" <?php echo ($user['behavior_preference'] ?? '') === 'creative' ? 'selected' : ''; ?>>Creative & Artistic</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="familyBgPref"><i class="fas fa-home text-info"></i> Family Background Preference</label>
                            <select id="familyBgPref" name="family_background_preference" class="form-control">
                                <option value="" <?php echo empty($user['family_background_preference'] ?? '') ? 'selected' : ''; ?>>Any (No Preference)</option>
                                <option value="orphaned" <?php echo ($user['family_background_preference'] ?? '') === 'orphaned' ? 'selected' : ''; ?>>Fully Orphaned</option>
                                <option value="single_parent" <?php echo ($user['family_background_preference'] ?? '') === 'single_parent' ? 'selected' : ''; ?>>Single Parent Background</option>
                                <option value="abandoned" <?php echo ($user['family_background_preference'] ?? '') === 'abandoned' ? 'selected' : ''; ?>>Abandoned / Surrendered</option>
                                <option value="displaced" <?php echo ($user['family_background_preference'] ?? '') === 'displaced' ? 'selected' : ''; ?>>Displaced / Refugee</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Info -->
            <div class="card mt-2">
                <div class="card-body" style="text-align: center;">
                    <?php if ($preferencesOnlyMode): ?>
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-sliders-h" style="font-size: 2.5rem; color: var(--primary-light); margin-bottom: 12px;"></i>
                        <h3 style="font-size: 1.1rem; margin-bottom: 6px;">Update Your Preferences</h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); max-width: 500px; margin: 0 auto;">
                            Your preferences will be updated immediately. The matching algorithm will use your new preferences for future child recommendations.
                        </p>
                    </div>
                    <div class="step-actions" style="justify-content: center;">
                        <a href="profile.php" class="btn btn-outline">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                    <?php else: ?>
                    <div style="margin-bottom: 20px;">
                        <i class="fas fa-shield-alt" style="font-size: 2.5rem; color: var(--primary-light); margin-bottom: 12px;"></i>
                        <h3 style="font-size: 1.1rem; margin-bottom: 6px;">Ready to Submit?</h3>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); max-width: 500px; margin: 0 auto;">
                            After submission, our admin team will review your profile and documents. You will be notified once verified. You can continue browsing children and donating while you wait.
                        </p>
                    </div>
                    <div class="step-actions" style="justify-content: center;">
                        <button type="button" class="btn btn-outline" onclick="prevStep(3)">
                            <i class="fas fa-arrow-left"></i> Back
                        </button>
                        <button type="submit" class="btn btn-accent btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Profile for Verification
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Profile step navigation
const preferencesOnly = <?php echo $preferencesOnlyMode ? 'true' : 'false'; ?>;
let currentStep = preferencesOnly ? 4 : 1;
const totalSteps = 4;

function showStep(step) {
    document.querySelectorAll('.profile-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');

    if (!preferencesOnly) {
        document.querySelectorAll('.profile-stepper .step').forEach(s => s.classList.remove('active', 'completed'));
        for (let i = 1; i <= totalSteps; i++) {
            const stepEl = document.querySelector('.profile-stepper .step[data-step="' + i + '"]');
            if (stepEl) {
                if (i < step) stepEl.classList.add('completed');
                if (i <= step) stepEl.classList.add('active');
            }
        }
    }

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep(step) {
    showStep(step);
}

function prevStep(step) {
    showStep(step);
}

// Document upload visual feedback
document.querySelectorAll('.doc-file-input').forEach(function(input) {
    input.addEventListener('change', function() {
        const box = this.closest('.doc-upload-box');
        const placeholder = box.querySelector('.doc-upload-placeholder');
        const success = box.querySelector('.doc-upload-success');
        const fileName = box.querySelector('.doc-file-name');

        if (this.files.length > 0) {
            placeholder.style.display = 'none';
            success.style.display = 'flex';
            fileName.textContent = this.files[0].name;
            box.classList.add('uploaded');
        } else {
            placeholder.style.display = 'flex';
            success.style.display = 'none';
            box.classList.remove('uploaded');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
