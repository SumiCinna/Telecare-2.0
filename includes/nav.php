<?php
// includes/nav.php
// $active_nav must be set before including: 'home' | 'visits' | 'meds' | 'profile'
$active_nav = $active_nav ?? 'home';

$nav_pages = [
    'home'    => ['href' => 'dashboard.php', 'label' => 'Home',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
    'visits'  => ['href' => 'visits.php',    'label' => 'Visits',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
    'meds'    => ['href' => 'meds.php',      'label' => 'Meds',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>'],
    'profile' => ['href' => 'profile.php',   'label' => 'Profile',
                  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
];
?>
<nav class="bottom-nav">
  <?php foreach ($nav_pages as $key => $item): ?>
  <a href="<?= $item['href'] ?>" class="nav-item <?= $active_nav === $key ? 'active' : '' ?>" data-tab="<?= $key ?>">
    <div class="nav-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
        <?= $item['icon'] ?>
      </svg>
    </div>
    <?= $item['label'] ?>
  </a>
  <?php endforeach; ?>
</nav>