-- =============================================
-- Profile Verification & Gated Adoption System
-- Database Migration Script
-- =============================================

USE orphanage_db;

-- =============================================
-- 1. Add profile verification columns to users
-- =============================================
ALTER TABLE users
    ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER phone,
    ADD COLUMN gender ENUM('male','female','other') DEFAULT NULL AFTER date_of_birth,
    ADD COLUMN marital_status ENUM('single','married','divorced','widowed') DEFAULT NULL AFTER gender,
    ADD COLUMN blood_group VARCHAR(5) DEFAULT NULL AFTER marital_status,
    ADD COLUMN health_conditions TEXT DEFAULT NULL AFTER blood_group,
    ADD COLUMN profile_status ENUM('incomplete','pending','verified','rejected') DEFAULT 'incomplete' AFTER family_size,
    ADD COLUMN profile_rejection_note TEXT DEFAULT NULL AFTER profile_status,
    ADD COLUMN profile_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER profile_rejection_note,
    ADD COLUMN profile_verified_at TIMESTAMP NULL DEFAULT NULL AFTER profile_submitted_at;

-- =============================================
-- 2. Create user_documents table
-- =============================================
CREATE TABLE IF NOT EXISTS user_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type ENUM('national_id','citizenship','occupation_proof','health_certificate') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_doc (user_id, doc_type)
) ENGINE=InnoDB;

-- =============================================
-- 3. Add blood_group to orphans table
-- =============================================
ALTER TABLE orphans
    ADD COLUMN blood_group VARCHAR(5) DEFAULT NULL AFTER health_details;

-- =============================================
-- 4. Update sample orphan data with blood groups
-- =============================================
UPDATE orphans SET blood_group = 'A+' WHERE full_name = 'Aarav Sharma';
UPDATE orphans SET blood_group = 'B+' WHERE full_name = 'Sita Thapa';
UPDATE orphans SET blood_group = 'O+' WHERE full_name = 'Ram Gurung';
UPDATE orphans SET blood_group = 'A-' WHERE full_name = 'Priya Magar';
UPDATE orphans SET blood_group = 'AB+' WHERE full_name = 'Bikash KC';
UPDATE orphans SET blood_group = 'B-' WHERE full_name = 'Anita Rai';
UPDATE orphans SET blood_group = 'O+' WHERE full_name = 'Dipesh Tamang';
UPDATE orphans SET blood_group = 'A+' WHERE full_name = 'Maya Lama';
