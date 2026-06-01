<?php
require_once __DIR__ . '/config.php';

/**
 * Map textual salary ranges to a numeric midpoint (for aggregation only).
 */
function mapSalaryRangeToMidpoint(?string $range): ?int {
    if ($range === null || $range === '') return null;
    $map = [
        'Below 20k' => 15000,
        '20k-30k' => 25000,
        '30k-50k' => 40000,
        '50k-100k' => 75000,
        'Above 100k' => 120000,
    ];
    return $map[$range] ?? null;
}

/**
 * Load privacy settings for a user. Returns defaults if missing.
 */
function getPrivacySettings(mysqli $conn, int $userId): array {
    $defaults = [
        'salary_visibility' => 'Private',
        'salary_aggregated_consent' => 0,
        'contact_visibility' => 'Private',
        'employment_visibility' => 'Admin Only',
        'photo_visibility' => 'Admin Only',
    ];
    $stmt = $conn->prepare("SELECT salary_visibility, salary_aggregated_consent, contact_visibility, employment_visibility, photo_visibility FROM privacy_settings WHERE user_id = ?");
    if (!$stmt) return $defaults;
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $stmt->bind_result($sVis, $agg, $cVis, $eVis, $pVis);
        if ($stmt->fetch()) {
            $defaults['salary_visibility'] = (string)$sVis;
            $defaults['salary_aggregated_consent'] = (int)$agg;
            $defaults['contact_visibility'] = (string)$cVis;
            $defaults['employment_visibility'] = (string)$eVis;
            $defaults['photo_visibility'] = (string)$pVis;
        }
    }
    $stmt->close();
    return $defaults;
}

/**
 * Whether admin can see an individual salary range value based on the user's preference.
 */
function canAdminSeeSalary(array $privacy): bool {
    $level = $privacy['salary_visibility'] ?? 'Private';
    return in_array($level, ['Admin Only', 'Public'], true);
}

/**
 * Whether a user's salary data can be included in aggregated statistics.
 */
function includeInAggregates(array $privacy): bool {
    return !empty($privacy['salary_aggregated_consent']);
}

?>


