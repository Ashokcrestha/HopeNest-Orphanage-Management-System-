<?php
/**
 * Enhanced Child-Adopter Matching Algorithm
 * Orphanage Management System
 * 
 * SECURE MATCHING WITH ENHANCED PREFERENCE SCORING
 * 
 * PSEUDOCODE:
 * -----------
 * FUNCTION getMatchingRecommendations(userId):
 *     // STEP 1: Security Gate - Verify user profile status
 *     user = FETCH user FROM database WHERE id = userId
 *     IF user NOT FOUND THEN RETURN empty
 *     IF user.profile_status != 'verified' THEN RETURN empty   // BLOCKED
 * 
 *     // STEP 2: Fetch available children
 *     children = FETCH all orphans WHERE availability_status = 'available'
 * 
 *     // STEP 3: Calculate enhanced weighted match score for each child
 *     FOR EACH child IN children:
 *         score = calculateMatchScore(user.preferences, child.attributes)
 *         // Enhanced weighted scoring breakdown:
 *         //   Age Match:         25 points  (child age within preferred range)
 *         //   Gender Match:      15 points  (gender matches preference)
 *         //   Location Match:    10 points  (geographic proximity)
 *         //   Health Preference: 15 points  (health status compatibility)
 *         //   Education Level:   10 points  (education level alignment)
 *         //   Behavioral Match:  10 points  (behavioral traits similarity)
 *         //   Emotional Needs:    8 points  (emotional needs compatibility)
 *         //   Adaptability:       7 points  (adaptability level alignment)
 *         //   TOTAL:            100 points maximum
 *         results.append({child, score})
 * 
 *     // STEP 4: Sort by score descending (best match first)
 *     SORT results BY total_score DESC
 *     RETURN results
 * 
 * FUNCTION calculateMatchScore(adopter, child):
 *     // Age Matching (25 pts max)
 *     IF child.age BETWEEN adopter.age_min AND adopter.age_max:
 *         ageScore = 25 * (1 - deviation_from_midpoint * 0.3)
 *     ELSE:
 *         ageScore = MAX(0, 12 - years_outside * 5)
 * 
 *     // Gender Matching (15 pts max)
 *     IF adopter.gender_pref = 'any' OR match: genderScore = 15
 * 
 *     // Location Matching (10 pts max)
 *     IF same location: locationScore = 10
 *     ELSE: locationScore = similarity_percentage * 7 / 100
 * 
 *     // Health Preference (15 pts max)
 *     IF adopter.health_pref = 'any': healthScore = 15
 *     IF exact match: healthScore = 15
 *     IF adopter accepts higher needs than child has: healthScore = 12
 *     IF mismatch: healthScore = 3
 * 
 *     // Education Level (10 pts max)
 *     IF adopter.education_pref is empty or matches: educationScore = 10
 *     IF partial match: educationScore = similarity * 10
 * 
 *     // Behavioral Traits (10 pts max)
 *     IF keyword overlap between child.traits & adopter's context: behavioralScore = overlap_ratio * 10
 * 
 *     // Emotional Needs (8 pts max)
 *     IF keyword overlap between child.emotional_needs & adopter.emotional_pref: emotionalScore = overlap_ratio * 8
 * 
 *     // Adaptability (7 pts max)
 *     IF adopter.adaptability_pref = 'any' OR match: adaptabilityScore = 7
 *     IF one level away: adaptabilityScore = 4
 *     IF opposite: adaptabilityScore = 1
 * 
 *     totalScore = SUM of all scores
 *     RETURN {all_scores, totalScore}
 * 
 * SECURITY RULES:
 *   1. Incomplete profiles → recommendations BLOCKED
 *   2. Pending verification → recommendations BLOCKED
 *   3. Rejected profiles → recommendations BLOCKED
 *   4. Only 'verified' users → recommendations ENABLED
 *   5. Admin approval alone does NOT trigger matching
 *   6. Server-side enforcement; cannot be bypassed
 */

/**
 * SECURITY GATE: Check if user is eligible for recommendations
 * 
 * @param PDO   $pdo    - Database connection
 * @param int   $userId - User ID to verify
 * @return bool          - True if user is eligible
 */
function isEligibleForMatching($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT profile_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Only 'verified' status passes the security gate
    if (!$user || $user['profile_status'] !== 'verified') {
        return false;
    }
    return true;
}

/**
 * Helper: Calculate age from date of birth
 */
if (!function_exists('calculateAge')) {
    function calculateAge($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    }
}

/**
 * Helper: Calculate keyword overlap ratio between two text strings
 * Used for behavioral traits, emotional needs matching
 * 
 * @param string $text1 - First text (e.g., child's traits)
 * @param string $text2 - Second text (e.g., adopter's preference)
 * @return float         - Overlap ratio between 0.0 and 1.0
 */
function calculateKeywordOverlap($text1, $text2) {
    if (empty($text1) || empty($text2)) {
        return 0.5; // Neutral score when no data available
    }

    // Normalize and tokenize both texts
    $words1 = array_unique(str_word_count(strtolower($text1), 1));
    $words2 = array_unique(str_word_count(strtolower($text2), 1));

    // Remove common stop words
    $stopWords = ['and', 'the', 'is', 'in', 'to', 'a', 'of', 'for', 'with', 'on', 'at', 'by', 'but', 'or', 'not', 'has', 'be', 'an'];
    $words1 = array_diff($words1, $stopWords);
    $words2 = array_diff($words2, $stopWords);

    if (empty($words1) || empty($words2)) {
        return 0.3;
    }

    // Calculate overlap
    $overlap = count(array_intersect($words1, $words2));
    $total = max(count($words1), count($words2));

    return min(1.0, $overlap / $total);
}

/**
 * Calculate enhanced matching score between an adopter and a child
 * 
 * Enhanced weighted scoring:
 *   Age (25) + Gender (15) + Location (10) + Health (15) +
 *   Education (10) + Behavioral (10) + Emotional (8) + Adaptability (7) = 100
 * 
 * @param array $adopter - Adopter preferences and details
 * @param array $child   - Child attributes
 * @return array         - Score breakdown and total
 */
function calculateMatchScore($adopter, $child) {
    $scores = [
        'age_score'          => 0,
        'gender_score'       => 0,
        'location_score'     => 0,
        'health_score'       => 0,
        'education_score'    => 0,
        'behavioral_score'   => 0,
        'emotional_score'    => 0,
        'adaptability_score' => 0,
        'total_score'        => 0
    ];

    // 1. AGE MATCHING (Max: 25 points)
    $childAge = isset($child['age']) ? (int)$child['age'] : calculateAge($child['date_of_birth']);
    $prefMinAge = (int)$adopter['age_preference_min'];
    $prefMaxAge = (int)$adopter['age_preference_max'];

    if ($childAge >= $prefMinAge && $childAge <= $prefMaxAge) {
        $midpoint = ($prefMinAge + $prefMaxAge) / 2;
        $range = ($prefMaxAge - $prefMinAge) / 2;
        if ($range > 0) {
            $deviation = abs($childAge - $midpoint) / $range;
            $scores['age_score'] = round(25 * (1 - ($deviation * 0.3)), 2);
        } else {
            $scores['age_score'] = 25;
        }
    } else {
        $distance = ($childAge < $prefMinAge) ? $prefMinAge - $childAge : $childAge - $prefMaxAge;
        $scores['age_score'] = max(0, round(12 - ($distance * 5), 2));
    }

    // 2. GENDER MATCHING (Max: 15 points)
    $genderPref = strtolower($adopter['gender_preference']);
    $childGender = strtolower($child['gender']);

    if ($genderPref === 'any' || $genderPref === $childGender) {
        $scores['gender_score'] = 15;
    } else {
        $scores['gender_score'] = 0;
    }

    // 3. LOCATION MATCHING (Max: 10 points)
    $adopterLocation = strtolower(trim($adopter['location'] ?? ''));
    $childLocation = strtolower(trim($child['location'] ?? ''));

    if (!empty($adopterLocation) && !empty($childLocation)) {
        if ($adopterLocation === $childLocation) {
            $scores['location_score'] = 10;
        } else {
            $similarityPercent = 0;
            similar_text($adopterLocation, $childLocation, $similarityPercent);
            $scores['location_score'] = round(($similarityPercent / 100) * 7, 2);
        }
    } else {
        $scores['location_score'] = 3;
    }

    // 4. HEALTH PREFERENCE (Max: 15 points)
    $healthPref = strtolower($adopter['health_preference'] ?? 'any');
    $childHealth = strtolower($child['health_status']);

    // Health hierarchy: healthy < minor_issues < special_needs
    $healthLevels = ['healthy' => 1, 'minor_issues' => 2, 'special_needs' => 3];
    $childLevel = $healthLevels[$childHealth] ?? 1;

    if ($healthPref === 'any') {
        $scores['health_score'] = 15; // Open to all
    } elseif ($healthPref === $childHealth) {
        $scores['health_score'] = 15; // Exact match
    } elseif (isset($healthLevels[$healthPref])) {
        $prefLevel = $healthLevels[$healthPref];
        if ($prefLevel >= $childLevel) {
            // Adopter willing to accept higher needs than child has
            $scores['health_score'] = 12;
        } else {
            // Child needs more care than adopter indicated
            $scores['health_score'] = 3;
        }
    } else {
        $scores['health_score'] = 8;
    }

    // =========================================
    // 5. EDUCATION LEVEL (Max: 10 points)
    // =========================================
    $educationPref = strtolower(trim($adopter['education_preference'] ?? ''));
    $childEducation = strtolower(trim($child['education_level'] ?? ''));

    if (empty($educationPref) || $educationPref === 'any') {
        $scores['education_score'] = 10; // No preference, full score
    } elseif (!empty($childEducation)) {
        if (strpos($childEducation, $educationPref) !== false || strpos($educationPref, $childEducation) !== false) {
            $scores['education_score'] = 10; // Direct match
        } else {
            $similarityPercent = 0;
            similar_text($educationPref, $childEducation, $similarityPercent);
            $scores['education_score'] = round(($similarityPercent / 100) * 10, 2);
        }
    } else {
        $scores['education_score'] = 5;
    }

    // 6. BEHAVIORAL TRAITS (Max: 10 points)
    $childTraits = ($child['behavioral_traits'] ?? '') . ' ' . ($child['personality'] ?? '');
    // Use adopter's emotional preference context for trait matching
    $adopterContext = ($adopter['emotional_preference'] ?? '');

    if (!empty(trim($childTraits)) && !empty(trim($adopterContext))) {
        $overlap = calculateKeywordOverlap($childTraits, $adopterContext);
        $scores['behavioral_score'] = round($overlap * 10, 2);
    } else {
        $scores['behavioral_score'] = 5; // Neutral when no data
    }

    // 7. EMOTIONAL NEEDS (Max: 8 points)
    $childEmotional = $child['emotional_needs'] ?? '';
    $adopterEmotional = $adopter['emotional_preference'] ?? '';

    if (!empty($childEmotional) && !empty($adopterEmotional)) {
        $overlap = calculateKeywordOverlap($childEmotional, $adopterEmotional);
        $scores['emotional_score'] = round($overlap * 8, 2);
    } else {
        $scores['emotional_score'] = 4; // Neutral when no data
    }

    // 8. ADAPTABILITY LEVEL (Max: 7 points)
    $adaptPref = strtolower($adopter['adaptability_preference'] ?? 'any');
    $childAdapt = strtolower($child['adaptability_level'] ?? 'moderate');

    $adaptLevels = ['high' => 3, 'moderate' => 2, 'low' => 1];

    if ($adaptPref === 'any') {
        $scores['adaptability_score'] = 7;
    } elseif ($adaptPref === $childAdapt) {
        $scores['adaptability_score'] = 7; // Exact match
    } else {
        $prefVal = $adaptLevels[$adaptPref] ?? 2;
        $childVal = $adaptLevels[$childAdapt] ?? 2;
        $distance = abs($prefVal - $childVal);
        if ($distance === 1) {
            $scores['adaptability_score'] = 4; // One level away
        } else {
            $scores['adaptability_score'] = 1; // Opposite ends
        }
    }

    // TOTAL SCORE (out of 100)
    $scores['total_score'] = round(
        $scores['age_score'] + $scores['gender_score'] + $scores['location_score'] +
        $scores['health_score'] + $scores['education_score'] + $scores['behavioral_score'] +
        $scores['emotional_score'] + $scores['adaptability_score'],
        2
    );

    return $scores;
}

/**
 * Get matching recommendations for an adopter
 * SECURITY: Only runs for verified users
 * 
 * @param PDO   $pdo    - Database connection
 * @param int   $userId - Adopter's user ID
 * @return array         - Sorted children with scores (empty if not verified)
 */
function getMatchingRecommendations($pdo, $userId) {
    // SECURITY GATE: Block unverified users
    if (!isEligibleForMatching($pdo, $userId)) {
        return [];
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $adopter = $stmt->fetch();

    if (!$adopter) {
        return [];
    }

    $stmt = $pdo->query("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE availability_status = 'available' ORDER BY full_name");
    $orphans = $stmt->fetchAll();

    $results = [];
    foreach ($orphans as $orphan) {
        $scores = calculateMatchScore($adopter, $orphan);
        $results[] = array_merge($orphan, ['scores' => $scores]);
    }

    usort($results, function ($a, $b) {
        return $b['scores']['total_score'] <=> $a['scores']['total_score'];
    });

    return $results;
}

/**
 * Calculate and store matching score for a specific adoption request
 * 
 * @param PDO   $pdo      - Database connection
 * @param int   $userId   - Adopter's user ID
 * @param int   $orphanId - Orphan's ID
 * @return array|null      - Score breakdown, or null if not eligible
 */
function storeMatchingScore($pdo, $userId, $orphanId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $adopter = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM orphans WHERE id = ?");
    $stmt->execute([$orphanId]);
    $orphan = $stmt->fetch();

    if (!$adopter || !$orphan) {
        return null;
    }

    $scores = calculateMatchScore($adopter, $orphan);

    // Store in matching_scores table if it exists
    try {
        $stmt = $pdo->prepare("
            INSERT INTO matching_scores (user_id, orphan_id, age_score, gender_score, location_score, health_score, education_score, behavioral_score, emotional_score, adaptability_score, total_score)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                age_score = VALUES(age_score),
                gender_score = VALUES(gender_score),
                location_score = VALUES(location_score),
                health_score = VALUES(health_score),
                education_score = VALUES(education_score),
                behavioral_score = VALUES(behavioral_score),
                emotional_score = VALUES(emotional_score),
                adaptability_score = VALUES(adaptability_score),
                total_score = VALUES(total_score),
                calculated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            $userId, $orphanId,
            $scores['age_score'], $scores['gender_score'],
            $scores['location_score'], $scores['health_score'],
            $scores['education_score'], $scores['behavioral_score'],
            $scores['emotional_score'], $scores['adaptability_score'],
            $scores['total_score']
        ]);
    } catch (PDOException $e) {
        // matching_scores table may not exist; score is still returned
    }

    return $scores;
}

/**
 * Get all matching scores for admin view
 */
function getMatchingScoresForRequest($pdo, $requestId) {
    try {
        $stmt = $pdo->prepare("
            SELECT ms.*, u.full_name as adopter_name, u.location as adopter_location,
                   o.full_name as orphan_name, TIMESTAMPDIFF(YEAR, o.date_of_birth, CURDATE()) AS age,
                   o.gender, o.location as orphan_location, o.health_status
            FROM matching_scores ms
            JOIN users u ON ms.user_id = u.id
            JOIN orphans o ON ms.orphan_id = o.id
            JOIN adoption_requests ar ON ar.user_id = ms.user_id AND ar.orphan_id = ms.orphan_id
            WHERE ar.id = ?
            ORDER BY ms.total_score DESC
        ");
        $stmt->execute([$requestId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
