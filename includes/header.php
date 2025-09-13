<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) && !isset($loginPage)) {
    header("Location: login.php");
    exit;
}

require_once 'functions.php';
require_once 'theme_functions.php';

$themeConfig = get_theme_config();
$selectedTheme = $themeConfig['selectedTheme'] ?? 'dark.css'; 
$themePath = 'assets/css/themes/' . $selectedTheme;

$currentUser = $_SESSION['user'] ?? null;
$appName = $themeConfig['appName'] ?? 'WorkoutLogger';
$pageTitle = $pageTitle ?? 'Dashboard';
$footerText = $themeConfig['footerText'] ?? '© {rok} WorkoutLogger';

$logoPath = $themeConfig['logoPath'] ?? null;
if ($logoPath && !file_exists($logoPath)) $logoPath = null;

$faviconPath = $themeConfig['faviconPath'] ?? null;
if ($faviconPath && !file_exists($faviconPath)) $faviconPath = null;

?>
<!DOCTYPE html>
<html lang="pl" data-bs-theme="<?= str_replace('.css', '', $selectedTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($appName) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <?php if (file_exists($themePath)): ?>
    <link rel="stylesheet" href="<?= $themePath ?>?v=<?= time() ?>">
    <?php endif; ?>

    <?php if ($faviconPath): ?>
    <link rel="icon" href="<?= htmlspecialchars($faviconPath) ?>?v=<?= time() ?>" type="image/png">
    <?php endif; ?>

    <style>
        :root {
            <?php foreach ($themeConfig['colors'] as $key => $value): ?>
            <?= $key ?>: <?= htmlspecialchars($value) ?>;
            <?php endforeach; ?>
        }
        <?php if (!empty($themeConfig['navbarBg']) || !empty($themeConfig['navbarText'])): ?>
        .navbar { 
            --bs-navbar-color: <?= htmlspecialchars($themeConfig['navbarText']) ?>;
            --bs-navbar-hover-color: <?= htmlspecialchars($themeConfig['navbarText']) ?>;
            --bs-navbar-disabled-color: <?= htmlspecialchars($theme-Config['navbarText']) ?>;
            --bs-navbar-active-color: <?= htmlspecialchars($themeConfig['navbarText']) ?>;
            background-color: <?= htmlspecialchars($themeConfig['navbarBg']) ?> !important;
        }
        .navbar .navbar-brand, .navbar .nav-link { color: <?= htmlspecialchars($themeConfig['navbarText']) ?>; }
        .navbar .navbar-toggler-icon { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='<?= urlencode($themeConfig['navbarText']) ?>' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e"); }
        <?php endif; ?>
    </style>
</head>
<body>
<div class="d-flex" id="wrapper">
    <?php if (isset($_SESSION['user'])): ?>
    <div class="bg-dark border-right" id="sidebar-wrapper">
        <div class="sidebar-heading text-center py-4 fs-4">
             <?php if ($logoPath): ?>
                <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($appName) ?> Logo" style="max-height: 40px;">
            <?php else: ?>
                <?= htmlspecialchars($appName) ?>
            <?php endif; ?>
        </div>
        <div class="list-group list-group-flush my-3">
            <a href="dashboard.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-house-door-fill me-2"></i>Panel</a>
            <a href="log_workout.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-plus-circle-fill me-2"></i>Rejestruj Trening</a>
            <a href="history.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-calendar-event-fill me-2"></i>Historia</a>
            <a href="plans.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-journal-album me-2"></i>Plany Treningowe</a>
            <a href="manage_exercises.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-person-arms-up me-2"></i>Zarządzaj Ćwiczeniami</a>
            <a href="goals.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-trophy-fill me-2"></i>Cele</a>
            <a href="progress.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-graph-up-arrow me-2"></i>Postępy</a>
            <a href="stats.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-bar-chart-line-fill me-2"></i>Statystyki</a>
            <a href="community.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-people-fill me-2"></i>Społeczność</a>
             <?php if ($currentUser['role'] === 'coach' || $currentUser['role'] === 'admin'): ?>
            <a href="coach_panel.php" class="list-group-item list-group-item-action bg-transparent border-0"><i class="bi bi-clipboard2-pulse-fill me-2"></i>Panel Trenera</a>
            <?php endif; ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
            <a href="admin.php" class="list-group-item list-group-item-action bg-transparent border-0 text-danger"><i class="bi bi-shield-lock-fill me-2"></i>Panel Admina</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="page-content-wrapper">
        <?php if (isset($_SESSION['user'])): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom">
            <div class="container-fluid">
                <button class="btn btn-primary" id="sidebarToggle"><i class="bi bi-list"></i></button>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mt-2 mt-lg-0">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                Witaj, <?= htmlspecialchars($currentUser['username']) ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                <a class="dropdown-item" href="profile.php">Profil</a>
                                <a class="dropdown-item" href="friends.php">Znajomi</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">Wyloguj</a>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <main class="container-fluid p-4">
