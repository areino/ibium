<?php
/**
 * Sidebar nav partial.
 * Expects in scope: $user (array), $myBands (array)
 */
$currentUri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

if (!function_exists('navLinkActive')) {
    function navLinkActive(string $href, string $currentUri): string {
        return $href === $currentUri ? ' active' : '';
    }
}
?>
<div class="sidebar-logo">
    <span class="logo-icon">🎵</span>
    <?= e(defined('APP_NAME') ? APP_NAME : 'Band Manager') ?>
</div>

<nav class="sidebar-nav">
    <a href="/" class="nav-link<?= navLinkActive('/', $currentUri) ?>">
        <span class="nav-icon">📅</span>
        Calendar
    </a>

    <?php if (!empty($myBands)): ?>
        <div class="nav-section-label">Bands</div>
        <?php foreach ($myBands as $band): ?>
            <a href="/band/<?= e($band['id']) ?>"
               class="nav-link<?= navLinkActive('/band/' . $band['id'], $currentUri) ?>">
                <span class="nav-icon">🎸</span>
                <?= e($band['name']) ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($user['is_admin'])): ?>
        <div class="nav-section-label">System</div>
        <a href="/admin" class="nav-link<?= navLinkActive('/admin', $currentUri) ?>">
            <span class="nav-icon">⚙️</span>
            Admin
        </a>
    <?php endif; ?>
</nav>

<div class="sidebar-footer">
    <div class="sidebar-user-name"><?= e($user['name'] ?? 'Unknown') ?></div>
    <div class="sidebar-user-email"><?= e($user['email'] ?? '') ?></div>
    <a href="/logout" class="sidebar-signout">
        <span>↩</span> Sign out
    </a>
</div>
