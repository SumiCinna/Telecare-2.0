<?php
// includes/legal_policy_helper.php


if (!function_exists('get_legal_policy')) {
    /**
     * Fetch one published policy by slug.
     * @return array{title:string,content:string,version:string,updated_at:string}|null
     */
    function get_legal_policy(mysqli $conn, string $slug): ?array
    {
        try {
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
        } catch (\Throwable $e) {
            // Never let a missing table / DB hiccup take down the whole page
            // (e.g. database/add_legal_policies.sql hasn't been run yet).
            error_log('legal_policy_helper: ' . $e->getMessage());
            return null;
        }
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
        return $fallback ?? '<p><em>This document isn\'t available yet. (If you\'re the admin: run database/add_legal_policies.sql and publish this policy in Legal Policies.)</em></p>';
    }
}