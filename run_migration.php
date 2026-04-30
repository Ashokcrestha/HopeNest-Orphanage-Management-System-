<?php
/**
 * Run database migration for profile verification system
 */
require_once __DIR__ . '/config/database.php';

echo "Starting migration...\n\n";

$queries = [
    // 1. Add profile verification columns to users
    "ALTER TABLE users ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER phone",
    "ALTER TABLE users ADD COLUMN gender ENUM('male','female','other') DEFAULT NULL AFTER date_of_birth",
    "ALTER TABLE users ADD COLUMN marital_status ENUM('single','married','divorced','widowed') DEFAULT NULL AFTER gender",
    "ALTER TABLE users ADD COLUMN blood_group VARCHAR(5) DEFAULT NULL AFTER marital_status",
    "ALTER TABLE users ADD COLUMN health_conditions TEXT DEFAULT NULL AFTER blood_group",
    "ALTER TABLE users ADD COLUMN profile_status ENUM('incomplete','pending','verified','rejected') DEFAULT 'incomplete' AFTER family_size",
    "ALTER TABLE users ADD COLUMN profile_rejection_note TEXT DEFAULT NULL AFTER profile_status",
    "ALTER TABLE users ADD COLUMN profile_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER profile_rejection_note",
    "ALTER TABLE users ADD COLUMN profile_verified_at TIMESTAMP NULL DEFAULT NULL AFTER profile_submitted_at",

    // 2. Create user_documents table
    "CREATE TABLE IF NOT EXISTS user_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        doc_type ENUM('national_id','citizenship','occupation_proof','health_certificate') NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_doc (user_id, doc_type)
    ) ENGINE=InnoDB",

    // 3. Add blood_group to orphans table
    "ALTER TABLE orphans ADD COLUMN blood_group VARCHAR(5) DEFAULT NULL AFTER health_details",

    // 4. Update sample orphan data with blood groups
    "UPDATE orphans SET blood_group = 'A+' WHERE full_name = 'Aarav Sharma'",
    "UPDATE orphans SET blood_group = 'B+' WHERE full_name = 'Sita Thapa'",
    "UPDATE orphans SET blood_group = 'O+' WHERE full_name = 'Ram Gurung'",
    "UPDATE orphans SET blood_group = 'A-' WHERE full_name = 'Priya Magar'",
    "UPDATE orphans SET blood_group = 'AB+' WHERE full_name = 'Bikash KC'",
    "UPDATE orphans SET blood_group = 'B-' WHERE full_name = 'Anita Rai'",
    "UPDATE orphans SET blood_group = 'O+' WHERE full_name = 'Dipesh Tamang'",
    "UPDATE orphans SET blood_group = 'A+' WHERE full_name = 'Maya Lama'",

    // 5. Add behavioral_traits to orphans
    "ALTER TABLE orphans ADD COLUMN behavioral_traits TEXT DEFAULT NULL AFTER blood_group",

    // 6. Add meeting & cancellation columns to adoption_requests
    "ALTER TABLE adoption_requests MODIFY COLUMN status ENUM('pending','approved','meeting_scheduled','completed','rejected','cancelled') DEFAULT 'pending'",
    "ALTER TABLE adoption_requests ADD COLUMN meeting_date DATE DEFAULT NULL AFTER matching_score",
    "ALTER TABLE adoption_requests ADD COLUMN meeting_notes TEXT DEFAULT NULL AFTER meeting_date",
    "ALTER TABLE adoption_requests ADD COLUMN cancellation_reason TEXT DEFAULT NULL AFTER meeting_notes",

    // 7. Seed behavioral traits for existing orphans
    "UPDATE orphans SET behavioral_traits = 'Cheerful, curious, enjoys playing with blocks and drawing' WHERE full_name = 'Aarav Sharma' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Shy but kind, loves reading stories, good with younger children' WHERE full_name = 'Sita Thapa' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Energetic, playful, enjoys outdoor activities and singing' WHERE full_name = 'Ram Gurung' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Confident, studious, actively participates in school programs' WHERE full_name = 'Priya Magar' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Resilient, creative, expresses through art and sign language' WHERE full_name = 'Bikash KC' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Artistic, empathetic, loves painting and helping others' WHERE full_name = 'Anita Rai' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Active, friendly, adapts well to new environments' WHERE full_name = 'Dipesh Tamang' AND behavioral_traits IS NULL",
    "UPDATE orphans SET behavioral_traits = 'Responsible, caring, shows leadership qualities among peers' WHERE full_name = 'Maya Lama' AND behavioral_traits IS NULL",

    // 8. Add enhanced child attributes to orphans
    "ALTER TABLE orphans ADD COLUMN personality VARCHAR(255) DEFAULT NULL AFTER behavioral_traits",
    "ALTER TABLE orphans ADD COLUMN emotional_needs VARCHAR(255) DEFAULT NULL AFTER personality",
    "ALTER TABLE orphans ADD COLUMN adaptability_level ENUM('high','moderate','low') DEFAULT 'moderate' AFTER emotional_needs",

    // 9. Add enhanced adopter preferences to users
    "ALTER TABLE users ADD COLUMN health_preference ENUM('healthy','minor_issues','special_needs','any') DEFAULT 'any' AFTER gender_preference",
    "ALTER TABLE users ADD COLUMN education_preference VARCHAR(100) DEFAULT NULL AFTER health_preference",
    "ALTER TABLE users ADD COLUMN emotional_preference VARCHAR(255) DEFAULT NULL AFTER education_preference",
    "ALTER TABLE users ADD COLUMN adaptability_preference ENUM('high','moderate','low','any') DEFAULT 'any' AFTER emotional_preference",

    // 10b. Add behavior and family background preference columns to users
    "ALTER TABLE users ADD COLUMN behavior_preference VARCHAR(50) DEFAULT NULL AFTER adaptability_preference",
    "ALTER TABLE users ADD COLUMN family_background_preference VARCHAR(50) DEFAULT NULL AFTER behavior_preference",

    // 10. Seed enhanced attributes for existing orphans
    "UPDATE orphans SET personality = 'Curious and joyful', emotional_needs = 'Needs nurturing attention', adaptability_level = 'high' WHERE full_name = 'Aarav Sharma' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Gentle and thoughtful', emotional_needs = 'Thrives in calm environments', adaptability_level = 'moderate' WHERE full_name = 'Sita Thapa' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Energetic and adventurous', emotional_needs = 'Benefits from active engagement', adaptability_level = 'high' WHERE full_name = 'Ram Gurung' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Determined and focused', emotional_needs = 'Responds well to encouragement', adaptability_level = 'high' WHERE full_name = 'Priya Magar' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Creative and resilient', emotional_needs = 'Requires patient communication', adaptability_level = 'moderate' WHERE full_name = 'Bikash KC' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Compassionate and artistic', emotional_needs = 'Values emotional stability', adaptability_level = 'high' WHERE full_name = 'Anita Rai' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Friendly and adaptable', emotional_needs = 'Adjusts easily with support', adaptability_level = 'high' WHERE full_name = 'Dipesh Tamang' AND personality IS NULL",
    "UPDATE orphans SET personality = 'Responsible and empathetic', emotional_needs = 'Seeks meaningful connections', adaptability_level = 'moderate' WHERE full_name = 'Maya Lama' AND personality IS NULL",

    // 11. Add multi-category donation support
    "ALTER TABLE donations ADD COLUMN donation_type ENUM('monetary','clothing','food','toys','educational','supplies','other') DEFAULT 'monetary' AFTER user_id",
    "ALTER TABLE donations ADD COLUMN item_description TEXT DEFAULT NULL AFTER donation_type",
    "ALTER TABLE donations MODIFY COLUMN amount DECIMAL(12,2) DEFAULT 0",

    // 12. Add missing score columns to matching_scores table (8-dimension support)
    "ALTER TABLE matching_scores ADD COLUMN education_score DECIMAL(5,2) DEFAULT 0 AFTER health_score",
    "ALTER TABLE matching_scores ADD COLUMN behavioral_score DECIMAL(5,2) DEFAULT 0 AFTER education_score",
    "ALTER TABLE matching_scores ADD COLUMN emotional_score DECIMAL(5,2) DEFAULT 0 AFTER behavioral_score",
    "ALTER TABLE matching_scores ADD COLUMN adaptability_score DECIMAL(5,2) DEFAULT 0 AFTER emotional_score",
];

foreach ($queries as $i => $sql) {
    try {
        $pdo->exec($sql);
        $shortSql = substr(trim($sql), 0, 80);
        echo "[OK] Query " . ($i + 1) . ": $shortSql...\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column') !== false || strpos($msg, 'already exists') !== false) {
            echo "[SKIP] Query " . ($i + 1) . ": Already applied.\n";
        } else {
            echo "[ERROR] Query " . ($i + 1) . ": $msg\n";
        }
    }
}

// Remove duplicate users (keep the earliest record per email)
echo "\n--- Cleaning up duplicates ---\n";
try {
    $dupes = $pdo->query("
        SELECT email, COUNT(*) as cnt, MIN(id) as keep_id 
        FROM users 
        GROUP BY email 
        HAVING COUNT(*) > 1
    ")->fetchAll();
    
    if (!empty($dupes)) {
        foreach ($dupes as $dupe) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$dupe['email'], $dupe['keep_id']]);
            echo "[FIXED] Removed " . ($dupe['cnt'] - 1) . " duplicate(s) for: " . $dupe['email'] . "\n";
        }
    } else {
        echo "[OK] No duplicate users found.\n";
    }
} catch (PDOException $e) {
    echo "[ERROR] Duplicate cleanup: " . $e->getMessage() . "\n";
}

// Create uploads directory
$uploadDir = __DIR__ . '/uploads/profile_docs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "\n[OK] Created uploads/profile_docs/ directory.\n";
} else {
    echo "\n[SKIP] uploads/profile_docs/ already exists.\n";
}

echo "\nMigration complete!\n";
