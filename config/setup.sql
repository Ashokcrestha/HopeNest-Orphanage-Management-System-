-- =============================================
-- Orphanage Management System - Database Setup
-- =============================================

CREATE DATABASE IF NOT EXISTS orphanage_db;
USE orphanage_db;

-- =============================================
-- Table: admins
-- =============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: users (adopters / donors)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    marital_status ENUM('single','married','divorced','widowed') DEFAULT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    health_conditions TEXT DEFAULT NULL,
    address TEXT,
    location VARCHAR(100),
    age_preference_min INT DEFAULT 0,
    age_preference_max INT DEFAULT 18,
    gender_preference ENUM('male', 'female', 'any') DEFAULT 'any',
    health_preference ENUM('healthy','minor_issues','special_needs','any') DEFAULT 'any',
    education_preference VARCHAR(100) DEFAULT NULL,
    emotional_preference VARCHAR(255) DEFAULT NULL,
    adaptability_preference ENUM('high','moderate','low','any') DEFAULT 'any',
    occupation VARCHAR(100),
    annual_income DECIMAL(12,2),
    family_size INT DEFAULT 1,
    profile_status ENUM('incomplete','pending','verified','rejected') DEFAULT 'incomplete',
    profile_rejection_note TEXT DEFAULT NULL,
    profile_submitted_at TIMESTAMP NULL DEFAULT NULL,
    profile_verified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: orphans (children)
-- =============================================
CREATE TABLE IF NOT EXISTS orphans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
    health_status ENUM('healthy', 'minor_issues', 'special_needs') DEFAULT 'healthy',
    health_details TEXT,
    blood_group VARCHAR(5) DEFAULT NULL,
    behavioral_traits TEXT DEFAULT NULL,
    personality VARCHAR(255) DEFAULT NULL,
    emotional_needs VARCHAR(255) DEFAULT NULL,
    adaptability_level ENUM('high','moderate','low') DEFAULT 'moderate',
    background TEXT,
    location VARCHAR(100),
    education_level VARCHAR(100),
    photo VARCHAR(255) DEFAULT 'default.png',
    availability_status ENUM('available', 'adopted', 'pending') DEFAULT 'available',
    date_admitted DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: donations
-- =============================================
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    donation_type ENUM('monetary','clothing','food','toys','educational','supplies','other') DEFAULT 'monetary',
    amount DECIMAL(12,2) DEFAULT 0,
    item_description TEXT DEFAULT NULL,
    message TEXT,
    payment_method VARCHAR(50) DEFAULT 'online',
    transaction_id VARCHAR(100),
    donated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Table: adoption_requests
-- =============================================
CREATE TABLE IF NOT EXISTS adoption_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    orphan_id INT NOT NULL,
    reason TEXT,
    preferred_age_min INT,
    preferred_age_max INT,
    preferred_gender ENUM('male', 'female', 'any') DEFAULT 'any',
    income DECIMAL(12,2),
    family_size INT,
    status ENUM('pending', 'approved', 'meeting_scheduled', 'completed', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    national_id_doc VARCHAR(255) DEFAULT NULL,
    citizenship_doc VARCHAR(255) DEFAULT NULL,
    employment_doc VARCHAR(255) DEFAULT NULL,
    employer_name VARCHAR(100) DEFAULT NULL,
    employment_duration VARCHAR(50) DEFAULT NULL,
    matching_score DECIMAL(5,2),
    meeting_date DATE DEFAULT NULL,
    meeting_notes TEXT DEFAULT NULL,
    cancellation_reason TEXT DEFAULT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (orphan_id) REFERENCES orphans(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Table: matching_scores
-- =============================================
CREATE TABLE IF NOT EXISTS matching_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    orphan_id INT NOT NULL,
    age_score DECIMAL(5,2) DEFAULT 0,
    gender_score DECIMAL(5,2) DEFAULT 0,
    location_score DECIMAL(5,2) DEFAULT 0,
    health_score DECIMAL(5,2) DEFAULT 0,
    education_score DECIMAL(5,2) DEFAULT 0,
    behavioral_score DECIMAL(5,2) DEFAULT 0,
    emotional_score DECIMAL(5,2) DEFAULT 0,
    adaptability_score DECIMAL(5,2) DEFAULT 0,
    total_score DECIMAL(5,2) DEFAULT 0,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (orphan_id) REFERENCES orphans(id) ON DELETE CASCADE,
    UNIQUE KEY unique_match (user_id, orphan_id)
) ENGINE=InnoDB;

-- =============================================
-- Table: user_documents
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
-- Table: adoption_feedback
-- =============================================
CREATE TABLE IF NOT EXISTS adoption_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    adoption_request_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL,
    process_rating ENUM('excellent','good','average','poor') DEFAULT 'good',
    communication_rating ENUM('excellent','good','average','poor') DEFAULT 'good',
    support_rating ENUM('excellent','good','average','poor') DEFAULT 'good',
    feedback_text TEXT NOT NULL,
    suggestions TEXT DEFAULT NULL,
    is_anonymous TINYINT(1) DEFAULT 0,
    admin_response TEXT DEFAULT NULL,
    responded_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (adoption_request_id) REFERENCES adoption_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_feedback (adoption_request_id)
) ENGINE=InnoDB;

-- =============================================
-- Insert default admin account
-- Password: password
-- =============================================
INSERT INTO admins (username, password, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@orphanage.com');

-- =============================================
-- Insert sample orphan data
-- =============================================
INSERT INTO orphans (full_name, date_of_birth, gender, health_status, health_details, blood_group, behavioral_traits, personality, emotional_needs, adaptability_level, background, location, education_level, date_admitted) VALUES
('Aarav Sharma', '2018-03-15', 'male', 'healthy', 'Regular checkups - all normal', 'A+', 'Cheerful, curious, enjoys playing with blocks and drawing', 'Curious and joyful', 'Needs nurturing attention', 'high', 'Found abandoned at a local hospital. Has been in our care since infancy.', 'Kathmandu', 'Nursery', '2018-04-01'),
('Sita Thapa', '2016-07-22', 'female', 'healthy', 'Good overall health', 'B+', 'Shy but kind, loves reading stories, good with younger children', 'Gentle and thoughtful', 'Thrives in calm environments', 'moderate', 'Parents passed away in a natural disaster. No known relatives.', 'Pokhara', 'Grade 2', '2017-01-15'),
('Ram Gurung', '2019-11-10', 'male', 'minor_issues', 'Mild asthma, under treatment', 'O+', 'Energetic, playful, enjoys outdoor activities and singing', 'Energetic and adventurous', 'Benefits from active engagement', 'high', 'Mother unable to care for child due to severe illness.', 'Chitwan', 'Nursery', '2020-02-20'),
('Priya Magar', '2014-05-08', 'female', 'healthy', 'Excellent health condition', 'A-', 'Confident, studious, actively participates in school programs', 'Determined and focused', 'Responds well to encouragement', 'high', 'Both parents deceased. Was living with elderly grandmother who could no longer provide care.', 'Kathmandu', 'Grade 4', '2016-08-10'),
('Bikash KC', '2017-01-30', 'male', 'special_needs', 'Hearing impairment in left ear, uses hearing aid', 'AB+', 'Resilient, creative, expresses through art and sign language', 'Creative and resilient', 'Requires patient communication', 'moderate', 'Abandoned at birth. Has been receiving special education support.', 'Lalitpur', 'Grade 1', '2017-03-01'),
('Anita Rai', '2015-09-14', 'female', 'healthy', 'No health issues', 'B-', 'Artistic, empathetic, loves painting and helping others', 'Compassionate and artistic', 'Values emotional stability', 'high', 'Orphaned due to family tragedy. Bright student with interest in arts.', 'Bhaktapur', 'Grade 3', '2016-11-05'),
('Dipesh Tamang', '2020-06-25', 'male', 'healthy', 'Healthy and active', 'O+', 'Active, friendly, adapts well to new environments', 'Friendly and adaptable', 'Adjusts easily with support', 'high', 'Single parent passed away. No other family members found.', 'Pokhara', 'Pre-school', '2021-01-10'),
('Maya Lama', '2013-12-03', 'female', 'minor_issues', 'Mild vision impairment, wears glasses', 'A+', 'Responsible, caring, shows leadership qualities among peers', 'Responsible and empathetic', 'Seeks meaningful connections', 'moderate', 'Parents involved in accident. Has been a responsible and caring child.', 'Kathmandu', 'Grade 5', '2015-06-20');

-- =============================================
-- Insert sample user data
-- Password: password
-- =============================================
INSERT INTO users (full_name, email, password, phone, address, location, age_preference_min, age_preference_max, gender_preference, occupation, annual_income, family_size) VALUES
('Rajesh Adhikari', 'rajesh@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9841234567', 'Thamel, Kathmandu', 'Kathmandu', 3, 8, 'any', 'Business Owner', 1200000.00, 3),
('Sunita Basnet', 'sunita@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9851234567', 'Lakeside, Pokhara', 'Pokhara', 5, 12, 'female', 'Teacher', 600000.00, 2);

-- =============================================
-- Insert sample donations
-- =============================================
INSERT INTO donations (user_id, amount, message, payment_method) VALUES
(1, 25000.00, 'For the betterment of children', 'online'),
(2, 15000.00, 'Monthly contribution for education', 'online'),
(1, 50000.00, 'Special donation for medical needs', 'bank_transfer');

-- =============================================
-- Insert sample adoption request
-- =============================================
INSERT INTO adoption_requests (user_id, orphan_id, reason, preferred_age_min, preferred_age_max, preferred_gender, income, family_size, status) VALUES
(1, 1, 'We want to provide a loving home to a child. We have the resources and willingness to support their growth.', 3, 8, 'any', 1200000.00, 3, 'pending');
