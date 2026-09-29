<?php
declare(strict_types=1);

$navBase = ai_base_path();
$activeNav = $activeNav ?? 'institutes';
?>
<header class="top-nav">
    <div class="top-nav-inner">
        <div class="brand">
            <a href="<?= htmlspecialchars($navBase) ?>/admin/institutes.php">
                <span class="brand-badge">GETMORE</span>
                <strong>AI Platform</strong>
            </a>
        </div>
        <nav class="nav-menu">
            <a href="<?= htmlspecialchars($navBase) ?>/admin/institutes.php" class="nav-link <?= ($activeNav === 'dashboard' || $activeNav === 'institutes') ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                Institutes
            </a>
            <a href="<?= htmlspecialchars($navBase) ?>/admin/institute-create.php" class="nav-link <?= ($activeNav === 'institute-create') ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add Institute
            </a>
            <a href="<?= htmlspecialchars($navBase) ?>/admin/index.php" class="nav-link <?= ($activeNav === 'assistants') ? 'active' : '' ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                AI Assistants
            </a>
        </nav>
        <div class="user-menu">
            <a href="<?= htmlspecialchars($navBase) ?>/admin/logout.php" class="logout-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                Logout
            </a>
        </div>
    </div>
</header>
