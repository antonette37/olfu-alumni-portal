<?php
/**
 * Course → expected industry categories for tracer / ISO alignment (OLFU-oriented).
 * Keys: normalize with cps_normalize_course_key(); values: broad industry buckets.
 * Extend this list to match Registrar program strings exactly.
 *
 * Tracer analytics: use cps_tracer_industry_bucket() so every row has a category —
 * unknown or non-matching industries become "Other / Uncategorized" (100% coverage).
 */
declare(strict_types=1);

/** Label used when industry is missing, unknown, or not mappable to the course mapping. */
function cps_industry_bucket_other_label(): string
{
    return 'Other / Uncategorized';
}

/** @return array<string, list<string>> */
function cps_course_industry_map(): array
{
    return [
        // Short codes
        'BSIT' => ['Technology', 'Software', 'Information Technology', 'IT', 'Telecommunications', 'Computer'],
        'BSCS' => ['Technology', 'Software', 'Information Technology', 'IT', 'Computer'],
        'BSIS' => ['Technology', 'Software', 'Information Technology', 'IT', 'Computer'],
        'BSCPE' => ['Technology', 'Engineering', 'Computer', 'Electronics'],
        'BSCE' => ['Engineering', 'Construction', 'Civil Engineering'],
        'BSEE' => ['Engineering', 'Electrical', 'Electronics'],
        'BSME' => ['Engineering', 'Mechanical', 'Manufacturing'],
        'BSBA' => ['Business', 'Finance', 'Marketing', 'Management', 'Retail'],
        'BSA' => ['Accounting', 'Finance', 'Business', 'Audit'],
        'BSE' => ['Business', 'Entrepreneurship', 'Retail'],
        'BSN' => ['Healthcare', 'Nursing', 'Medical', 'Hospital'],
        'BSMT' => ['Healthcare', 'Medical Technology', 'Laboratory'],
        'BSPT' => ['Healthcare', 'Physical Therapy', 'Medical'],
        'BSPSY' => ['Healthcare', 'Psychology', 'Human Services', 'Education'],
        'BSED' => ['Education', 'Teaching', 'Academe'],
        'BEED' => ['Education', 'Teaching', 'Academe'],
        'BPED' => ['Education', 'Sports', 'Teaching'],
        'BACOMM' => ['Media', 'Communication', 'Marketing', 'Public Relations'],
        'BSBIO' => ['Science', 'Research', 'Healthcare', 'Laboratory', 'Education'],
        'MD' => ['Healthcare', 'Medical', 'Hospital'],
        // Long names (match registration degree dropdown)
        'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY' => ['Technology', 'Software', 'Information Technology', 'IT', 'Telecommunications', 'Computer'],
        'BACHELOR OF SCIENCE IN COMPUTER SCIENCE' => ['Technology', 'Software', 'Information Technology', 'IT', 'Computer'],
        'BACHELOR OF SCIENCE IN INFORMATION SYSTEMS' => ['Technology', 'Software', 'Information Technology', 'IT', 'Computer'],
        'BACHELOR OF SCIENCE IN COMPUTER ENGINEERING' => ['Technology', 'Engineering', 'Computer', 'Electronics'],
        'BACHELOR OF SCIENCE IN CIVIL ENGINEERING' => ['Engineering', 'Construction', 'Civil Engineering'],
        'BACHELOR OF SCIENCE IN ELECTRICAL ENGINEERING' => ['Engineering', 'Electrical', 'Electronics'],
        'BACHELOR OF SCIENCE IN MECHANICAL ENGINEERING' => ['Engineering', 'Mechanical', 'Manufacturing'],
        'BACHELOR OF SCIENCE IN BUSINESS ADMINISTRATION' => ['Business', 'Finance', 'Marketing', 'Management', 'Retail'],
        'BACHELOR OF SCIENCE IN ACCOUNTANCY' => ['Accounting', 'Finance', 'Business', 'Audit'],
        'BACHELOR OF SCIENCE IN ENTREPRENEURSHIP' => ['Business', 'Entrepreneurship', 'Retail'],
        'BACHELOR OF ARTS IN COMMUNICATION' => ['Media', 'Communication', 'Marketing', 'Public Relations'],
        'BACHELOR OF SCIENCE IN PSYCHOLOGY' => ['Healthcare', 'Psychology', 'Human Services', 'Education'],
        'BACHELOR OF SCIENCE IN BIOLOGY' => ['Science', 'Research', 'Healthcare', 'Laboratory', 'Education'],
        'BACHELOR OF ELEMENTARY EDUCATION' => ['Education', 'Teaching', 'Academe'],
        'BACHELOR OF SECONDARY EDUCATION' => ['Education', 'Teaching', 'Academe'],
        'BACHELOR OF PHYSICAL EDUCATION' => ['Education', 'Sports', 'Teaching'],
        'BACHELOR OF SCIENCE IN NURSING' => ['Healthcare', 'Nursing', 'Medical', 'Hospital'],
        'DOCTOR OF MEDICINE' => ['Healthcare', 'Medical', 'Hospital'],
    ];
}

function cps_normalize_course_key(string $program): string
{
    $p = strtoupper(trim($program));
    $p = preg_replace('/\s+/', ' ', $p) ?? $p;
    return $p;
}

/** @return list<string> */
function cps_expected_industries_for_course(string $program): array
{
    $map = cps_course_industry_map();
    $key = cps_normalize_course_key($program);
    if (isset($map[$key])) {
        return $map[$key];
    }
    foreach ($map as $k => $industries) {
        if ($k !== '' && str_contains($key, $k)) {
            return $industries;
        }
    }
    return [];
}

function cps_industry_matches_expected(string $industryType, array $expected): bool
{
    $ind = strtoupper(trim($industryType));
    if ($ind === '' || empty($expected)) {
        return false;
    }
    foreach ($expected as $exp) {
        $e = strtoupper(trim($exp));
        if ($e !== '' && (str_contains($ind, $e) || str_contains($e, $ind))) {
            return true;
        }
    }
    return false;
}

function cps_is_job_aligned_with_course(string $program, string $industryType): bool
{
    $expected = cps_expected_industries_for_course($program);
    return cps_industry_matches_expected($industryType, $expected);
}

/**
 * First matching expected bucket label for an industry string (canonical form from the map).
 */
function cps_best_matching_expected_label(string $industryType, array $expected): ?string
{
    $ind = strtoupper(trim($industryType));
    if ($ind === '') {
        return null;
    }
    foreach ($expected as $exp) {
        $e = strtoupper(trim((string)$exp));
        if ($e !== '' && (str_contains($ind, $e) || str_contains($e, $ind))) {
            return trim((string)$exp);
        }
    }
    return null;
}

/**
 * ISO / tracer category for reporting: always returns a label (catch-all for gaps).
 * — Matched to course mapping → one of the expected bucket names.
 * — Empty industry, unknown program, or no substring match → Other / Uncategorized.
 */
function cps_tracer_industry_bucket(string $program, string $industryType): string
{
    $ind = trim($industryType);
    if ($ind === '') {
        return cps_industry_bucket_other_label();
    }
    $expected = cps_expected_industries_for_course($program);
    if (empty($expected)) {
        return cps_industry_bucket_other_label();
    }
    $label = cps_best_matching_expected_label($ind, $expected);
    if ($label !== null) {
        return $label;
    }
    return cps_industry_bucket_other_label();
}
