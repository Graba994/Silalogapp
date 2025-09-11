<?php
session_start();

// Jeśli użytkownik jest już zalogowany, przekieruj go do panelu
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Dołącz i wczytaj konfigurację skórki
require_once 'includes/theme_functions.php';
$themeConfig = get_theme_config();
$loginConfig = $themeConfig['loginPage'];

$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Wyświetl błąd tylko raz
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zaloguj się - <?= htmlspecialchars($themeConfig['appName']) ?></title>
    
    <?php if (!empty($themeConfig['faviconPath']) && file_exists($themeConfig['faviconPath'])): ?>
        <link rel="icon" href="<?= htmlspecialchars($themeConfig['faviconPath']) ?>">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Dynamiczne style dla strony logowania -->
    <style>
        body {
            <?php if ($loginConfig['backgroundType'] === 'image' && !empty($loginConfig['backgroundImage']) && file_exists($loginConfig['backgroundImage'])): ?>
            background-image: url('<?= htmlspecialchars($loginConfig['backgroundImage']) ?>?v=<?= time() ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            <?php else: ?>
            background-color: <?= htmlspecialchars($loginConfig['backgroundColor']) ?>;
            <?php endif; ?>
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-card {
            background-color: <?= htmlspecialchars($loginConfig['boxColor']) ?>;
            color: <?= htmlspecialchars($loginConfig['textColor']) ?>;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,0.175);
            overflow: hidden;
        }
        .login-card .form-control {
            background-color: rgba(0,0,0,0.05);
            border: none;
            color: inherit; /* Dziedzicz kolor tekstu */
        }
        .login-card .form-control:focus {
             background-color: rgba(0,0,0,0.1);
             box-shadow: none;
        }
        .login-card .form-floating > label {
            color: rgba(<?= implode(',', sscanf(htmlspecialchars($loginConfig['textColor']), "#%02x%02x%02x")) ?>, 0.6);
        }
        .welcome-widgets {
            padding: 3rem;
            background: rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="card login-card col-xl-10 mx-auto">
                <div class="row g-0">
                    <!-- Kolumna z widżetami -->
                    <div class="col-lg-7 d-none d-lg-block welcome-widgets">
                        <h2 class="mb-4">Witaj w <?= htmlspecialchars($themeConfig['appName']) ?>!</h2>
                        <p class="lead mb-5" style="color: rgba(<?= implode(',', sscanf(htmlspecialchars($loginConfig['textColor']), "#%02x%02x%02x")) ?>, 0.8);">Twoje centrum zarządzania treningiem.</p>
                        
                        <div class="row g-4">
                            <?php foreach ($loginConfig['welcomeWidgets'] as $widget): ?>
                                <?php if ($widget['enabled']): ?>
                                <div class="col-12 d-flex">
                                    <div class="flex-shrink-0 me-3">
                                        <i class="bi <?= htmlspecialchars($widget['icon']) ?> fs-2 text-primary"></i>
                                    </div>
                                    <div>
                                        <h5><?= htmlspecialchars($widget['title']) ?></h5>
                                        <p class="mb-0 small" style="color: rgba(<?= implode(',', sscanf(htmlspecialchars($loginConfig['textColor']), "#%02x%02x%02x")) ?>, 0.7);"><?= htmlspecialchars($widget['text']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Kolumna z formularzem logowania -->
                    <div class="col-lg-5">
                        <div class="card-body p-4 p-sm-5">
                            <div class="text-center">
                                <?php if (!empty($themeConfig['logoPath']) && file_exists($themeConfig['logoPath'])): ?>
                                    <img src="<?= htmlspecialchars($themeConfig['logoPath']) ?>" alt="Logo" class="mb-4" style="max-height: 50px;">
                                <?php else: ?>
                                    <h1 class="h3 mb-4 fw-bold"><?= htmlspecialchars($themeConfig['appName']) ?></h1>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-center mb-4" style="color: rgba(<?= implode(',', sscanf(htmlspecialchars($loginConfig['textColor']), "#%02x%02x%02x")) ?>, 0.6);">Zaloguj się, aby kontynuować</p>
                            
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                            <?php endif; ?>

                            <form action="login.php" method="POST">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="user_id" name="user_id" placeholder="Nazwa użytkownika" required>
                                    <label for="user_id">Nazwa użytkownika</label>
                                </div>
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Hasło" required>
                                    <label for="password">Hasło</label>
                                </div>
                                
                                <div class="form-check text-start my-3">
                                    <input class="form-check-input" type="checkbox" name="remember_me" id="remember_me">
                                    <label class="form-check-label" for="remember_me">
                                        Zapamiętaj mnie na tym urządzeniu
                                    </label>
                                </div>
                                
                                <button class="w-100 btn btn-lg btn-primary" type="submit">Zaloguj się</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>