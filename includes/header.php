<?php
// Rozpocznij sesję, jeśli jeszcze nie jest aktywna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Dołącz funkcje i wczytaj konfigurację skórki
require_once 'includes/theme_functions.php';
require_once 'includes/functions.php';
$themeConfig = get_theme_config();

// Pobierz nazwę aktualnie otwartego pliku, aby podświetlić aktywny link
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['user_role'] ?? 'user'; // Pobierz rolę użytkownika

// Zdefiniuj grupy stron dla łatwiejszego podświetlania menu
$trainingPages = ['log_workout.php', 'plans.php', 'history.php', 'start_shared_workout.php', 'shared_workout.php'];
$analysisPages = ['stats.php', 'progress.php', 'goals.php'];
$communityPages = ['community.php', 'friends.php'];
$coachPages = ['coach_panel.php', 'coach_view_history.php', 'coach_assign_plan.php']; // NOWA GRUPA

// Sprawdzanie aktywnego treningu wspólnego
$is_in_live_workout = false;
$liveWorkoutFiles = glob('data/live_workouts/lw_*.json');
foreach ($liveWorkoutFiles as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (isset($data['status']) && $data['status'] === 'active' && in_array($_SESSION['user_id'], $data['participants'] ?? [])) {
        $is_in_live_workout = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="pl"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' . htmlspecialchars($themeConfig['appName']) : htmlspecialchars($themeConfig['appName']) ?></title>
    
    <?php if (!empty($themeConfig['faviconPath']) && file_exists($themeConfig['faviconPath'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($themeConfig['faviconPath']) ?>?v=<?= filemtime($themeConfig['faviconPath']) ?>">
    <?php endif; ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- NOWA BIBLIOTEKA: FullCalendar CSS -->
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

    <!-- DYNAMICZNE STYLE DLA SKÓRKI -->
    <style>
        :root {
            <?php foreach ($themeConfig['colors'] as $variable => $value): ?>
                <?php if (preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $value)): ?>
                    <?= htmlspecialchars($variable) ?>: <?= htmlspecialchars($value) ?>;
                <?php endif; ?>
            <?php endforeach; ?>
        }
        /* Style dla paska nawigacji */
        .navbar.bg-custom-theme {
            background-color: <?= htmlspecialchars($themeConfig['navbarBg']) ?> !important;
        }
        .navbar.bg-custom-theme .navbar-brand,
        .navbar.bg-custom-theme .nav-link,
        .navbar.bg-custom-theme .navbar-text a {
            color: <?= htmlspecialchars($themeConfig['navbarText']) ?> !important;
        }
        .navbar.bg-custom-theme .navbar-toggler-icon {
            filter: invert(1) grayscale(100%) brightness(200%); /* Prosty sposób na białą ikonę hamburgera */
        }
    </style>

    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Skrypt do motywu (przed renderowaniem body) -->
    <script>
        const getPreferredTheme = () => {
            const storedTheme = localStorage.getItem('theme');
            if (storedTheme) return storedTheme;
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        };
        document.documentElement.setAttribute('data-bs-theme', getPreferredTheme());
    </script>
</head>
<body> 
    <nav class="navbar navbar-expand-lg navbar-dark bg-custom-theme sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <?php if (!empty($themeConfig['logoPath']) && file_exists($themeConfig['logoPath'])): ?>
                    <img src="<?= htmlspecialchars($themeConfig['logoPath']) ?>?v=<?= filemtime($themeConfig['logoPath']) ?>" alt="<?= htmlspecialchars($themeConfig['appName']) ?> Logo" style="height: 30px; width: auto;">
                <?php else: ?>
                    <i class="bi bi-barbell me-1"></i> <?= htmlspecialchars($themeConfig['appName']) ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav" aria-controls="main-nav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main-nav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php"><i class="bi bi-house-door-fill me-2"></i>Panel</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($currentPage, $trainingPages) ? 'active' : '' ?>" href="#" id="trainingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-activity me-2"></i>Trening
                            <?php if ($is_in_live_workout): ?>
                                <span class="badge bg-danger rounded-pill pulsating-dot ms-1">LIVE</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="trainingDropdown">
                            <li><h6 class="dropdown-header">Trening Solo</h6></li>
                            <li><a class="dropdown-item" href="start_workout.php?plan_id=adhoc"><i class="bi bi-joystick me-2"></i>Rozpocznij pusty trening</a></li>
                            <li><a class="dropdown-item" href="plans.php"><i class="bi bi-journal-text me-2"></i>Wybierz z planu</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Trening Wspólny</h6></li>
                            <li>
                                <a class="dropdown-item" href="<?= $is_in_live_workout ? 'shared_workout.php' : 'start_shared_workout.php' ?>">
                                    <i class="bi bi-people-fill me-2"></i>
                                    <?= $is_in_live_workout ? 'Wróć do sesji LIVE' : 'Stwórz nową sesję' ?>
                                    <?php if ($is_in_live_workout): ?>
                                        <span class="badge bg-danger rounded-pill pulsating-dot ms-1">LIVE</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="history.php"><i class="bi bi-clock-history me-2"></i>Historia Treningów</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($currentPage, $analysisPages) ? 'active' : '' ?>" href="#" id="analysisDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-pie-chart-fill me-2"></i>Analiza
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="analysisDropdown">
                            <li><a class="dropdown-item" href="stats.php"><i class="bi bi-graph-up-arrow me-2"></i>Statystyki</a></li>
                            <li><a class="dropdown-item" href="progress.php"><i class="bi bi-reception-4 me-2"></i>Progres</a></li>
                            <li><a class="dropdown-item" href="goals.php"><i class="bi bi-bullseye me-2"></i>Moje Cele</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= in_array($currentPage, $communityPages) ? 'active' : '' ?>" href="#" id="communityDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-globe me-2"></i>Społeczność
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="communityDropdown">
                            <li><a class="dropdown-item" href="community.php"><i class="bi bi-newspaper me-2"></i>Tablica Aktywności</a></li>
                            <li><a class="dropdown-item" href="friends.php"><i class="bi bi-person-plus-fill me-2"></i>Zarządzaj Znajomymi</a></li>
                        </ul>
                    </li>

                    <!-- NOWY LINK DO PANELU TRENERA -->
                    <?php if ($userRole === 'coach' || $userRole === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($currentPage, $coachPages) ? 'active' : '' ?>" href="coach_panel.php"><i class="bi bi-clipboard-heart-fill me-2"></i>Panel Trenera</a>
                    </li>
                    <?php endif; ?>

                </ul>

                <!-- Menu użytkownika (po prawej) -->
                <div class="navbar-text dropdown">
                    <a href="#" class="d-block link-light text-decoration-none dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark text-small" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-lines-fill me-2"></i>Mój Profil</a></li>
                        <?php if ($userRole === 'admin'): ?>
                        <li><a class="dropdown-item" href="admin.php"><i class="bi bi-shield-lock-fill me-2"></i>Panel Admina</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item d-flex align-items-center theme-toggle" type="button">
                                <i class="bi bi-moon-stars-fill me-2"></i> 
                                <span>Tryb ciemny</span>
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Wyloguj</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <main class="container mt-4">