<?php
// ============================================================
// STEP 1 — Add to your database/config.php or run this SQL
// ============================================================
/*
ALTER TABLE patients
  ADD COLUMN google_id VARCHAR(50) NULL DEFAULT NULL AFTER preferred_language,
  ADD COLUMN auth_provider ENUM('manual','google') NOT NULL DEFAULT 'manual' AFTER google_id,
  ADD UNIQUE INDEX idx_google_id (google_id);
*/


// ============================================================
// STEP 2 — Google OAuth URLs
// Replace YOUR_GOOGLE_CLIENT_ID below with your real Client ID
// ============================================================

$google_client_id = 'REMOVED';
$google_base      = 'https://accounts.google.com/o/oauth2/v2/auth';
$google_scope     = 'openid email profile';

// URL for REGISTRATION (goes to google-register-callback.php)
$google_register_url = $google_base . '?' . http_build_query([
    'client_id'     => $google_client_id,
    'redirect_uri'  => 'https://telecareai.site/auth/google-register-callback.php',
    'response_type' => 'code',
    'scope'         => $google_scope,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

// URL for LOGIN (goes to google-callback.php)
$google_login_url = $google_base . '?' . http_build_query([
    'client_id'     => $google_client_id,
    'redirect_uri'  => 'https://telecareai.site/auth/google-callback.php',
    'response_type' => 'code',
    'scope'         => $google_scope,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);
?>


<!-- ============================================================
     STEP 3 — Paste this button into register.php
     Place it just BEFORE the <form> tag in your step-1 section
     ============================================================ -->

<!-- Google Register Button (paste into register.php) -->
<a href="<?= $google_register_url ?>" style="
    display:flex;align-items:center;justify-content:center;gap:.75rem;
    width:100%;padding:.85rem 1rem;
    border:1.5px solid rgba(36,68,65,.18);
    border-radius:50px;
    background:#fff;
    color:#244441;
    font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:600;
    text-decoration:none;
    transition:all .25s;
    box-shadow:0 2px 8px rgba(0,0,0,.07);
    margin-bottom:1.25rem;
">
  <svg width="18" height="18" viewBox="0 0 48 48">
    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.35-8.16 2.35-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
  </svg>
  Sign up with Google
</a>

<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem">
  <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
  <span style="font-size:.78rem;color:#9ab0ae;white-space:nowrap">or register manually</span>
  <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
</div>


<!-- ============================================================
     STEP 4 — Paste this button into login.php
     Place it at the top of your login form
     ============================================================ -->

<!-- Google Login Button (paste into login.php) -->
<a href="<?= $google_login_url ?>" style="
    display:flex;align-items:center;justify-content:center;gap:.75rem;
    width:100%;padding:.85rem 1rem;
    border:1.5px solid rgba(36,68,65,.18);
    border-radius:50px;
    background:#fff;
    color:#244441;
    font-family:'DM Sans',sans-serif;font-size:.92rem;font-weight:600;
    text-decoration:none;
    transition:all .25s;
    box-shadow:0 2px 8px rgba(0,0,0,.07);
    margin-bottom:1.25rem;
">
  <svg width="18" height="18" viewBox="0 0 48 48">
    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.35-8.16 2.35-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
  </svg>
  Continue with Google
</a>

<div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem">
  <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
  <span style="font-size:.78rem;color:#9ab0ae;white-space:nowrap">or log in manually</span>
  <div style="flex:1;height:1px;background:rgba(36,68,65,.1)"></div>
</div>


<!-- ============================================================
     STEP 5 — Update your Google Cloud Console redirect URIs
     ============================================================
     Authorized JavaScript origins:
       https://telecareai.site

     Authorized redirect URIs:
       https://telecareai.site/auth/google-callback.php
       https://telecareai.site/auth/google-register-callback.php
     ============================================================ -->