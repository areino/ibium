<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(defined('APP_NAME') ? APP_NAME : 'Band Manager') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body x-data="{ drawerOpen: false }" @keydown.escape.window="drawerOpen = false">

<!-- Mobile header -->
<header class="mobile-header">
    <button class="hamburger" @click="drawerOpen = true" aria-label="Open menu">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
    <span class="mobile-header-title"><?= e(defined('APP_NAME') ? APP_NAME : 'Band Manager') ?></span>
    <div style="width:34px;"></div><!-- spacer to center title -->
</header>

<!-- Mobile drawer overlay -->
<div class="mobile-drawer-overlay"
     x-show="drawerOpen"
     x-cloak
     @click="drawerOpen = false"
     style="display:none;">
</div>

<!-- Mobile drawer panel -->
<div class="mobile-drawer sidebar" :class="{ open: drawerOpen }" x-cloak style="display:flex;">
    <?php
    $user     = $user ?? [];
    $myBands  = $myBands ?? [];
    require ROOT . '/templates/nav.php';
    ?>
</div>

<!-- App layout -->
<div class="app-layout">
    <!-- Desktop sidebar -->
    <aside class="sidebar">
        <?php require ROOT . '/templates/nav.php'; ?>
    </aside>

    <!-- Main content -->
    <div class="main-content">
        <?php require ROOT . '/templates/' . $content_template . '.php'; ?>
    </div>
</div>

<!-- Alpine.js -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
