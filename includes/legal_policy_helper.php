<?php
// includes/legal_policy_helper.php
//
// Shared helper so any page can pull the current PUBLISHED legal policy
// content straight from the database (managed in super_admin/legal_policies.php
// and super_admin/edit_policy.php), instead of hardcoding the text on every
// page. Include this once, then call legal_policy_content($conn, 'slug').
//
// Known slugs seeded by database/add_legal_policies.sql:
//   'data-privacy-notice'   'terms-and-conditions'
//   'privacy-policy'        'payment-policy'

if (!function_exists('get_legal_policy')) {
    /**
     * Fetch one published policy by slug.
     * @return array{title:string,content:string,version:string,updated_at:string}|null
     */
    function get_legal_policy(mysqli $conn, string $slug): ?array
    {
        $stmt = $conn->prepare(
            "SELECT title, content, version, updated_at
             FROM legal_policies
             WHERE slug = ? AND status = 'Published'
             LIMIT 1"
        );
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('legal_policy_content')) {
    /**
     * Convenience: just the HTML body of a published policy, ready to echo.
     * Falls back to a friendly notice if the policy isn't published/found.
     */
    function legal_policy_content(mysqli $conn, string $slug, ?string $fallback = null): string
    {
        $policy = get_legal_policy($conn, $slug);
        if ($policy) {
            return $policy['content'];
        }
        return $fallback ?? '<p><em>This document is not available yet. Please check back later.</em></p>';
    }
}
