<?php
require_once 'includes/admin_guard.php';
$pageTitle = 'Ustawienia Wyglądu';
require_once 'includes/functions.php';
require_once 'includes/theme_functions.php';

$configPath = 'data/theme.json';
$successMessage = '';
$errorMessage = '';
$activeTab = $_GET['tab'] ?? 'general';

$themes = [];
$themeFiles = glob('assets/css/themes/*.css');
foreach ($themeFiles as $file) {
    $fileName = basename($file);
    $themeName = ucfirst(str_replace(['.css', '_'], ['', ' '], $fileName));
    $themes[$fileName] = $themeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentConfig = get_theme_config();
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'remove_logo') {
            if (!empty($currentConfig['logoPath']) && file_exists($currentConfig['logoPath'])) @unlink($currentConfig['logoPath']);
            $currentConfig['logoPath'] = null;
        }
        if ($_POST['action'] === 'remove_favicon') {
            if (!empty($currentConfig['faviconPath']) && file_exists($currentConfig['faviconPath'])) @unlink($currentConfig['faviconPath']);
            $currentConfig['faviconPath'] = null;
        }
        if ($_POST['action'] === 'remove_login_bg') {
             if (!empty($currentConfig['loginPage']['backgroundImage']) && file_exists($currentConfig['loginPage']['backgroundImage'])) @unlink($currentConfig['loginPage']['backgroundImage']);
            $currentConfig['loginPage']['backgroundImage'] = null;
            $currentConfig['loginPage']['backgroundType'] = 'color';
        }
    }

    if (isset($_POST['save_general'])) {
        $activeTab = 'general';
        $currentConfig['appName'] = trim($_POST['appName']);
        $currentConfig['footerText'] = trim($_POST['footerText']);
        
        if (isset($_POST['selectedTheme']) && array_key_exists($_POST['selectedTheme'], $themes)) {
            $currentConfig['selectedTheme'] = $_POST['selectedTheme'];
        }

        $colorFields = ['navbarBg', 'navbarText', '--bs-primary', '--bs-secondary', '--bs-success', '--bs-danger', '--bs-info', '--bs-dark'];
        foreach ($colorFields as $field) {
            $postKey = str_replace('--bs-', '', $field);
            if (isset($_POST[$postKey]) && preg_match('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $_POST[$postKey])) {
                if (strpos($field, '--bs-') === 0) $currentConfig['colors'][$field] = $_POST[$postKey];
                else $currentConfig[$field] = $_POST[$postKey];
            }
        }

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $fileName = 'custom_logo.' . $fileExtension;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName)) {
                $currentConfig['logoPath'] = $uploadDir . $fileName;
            } else { $errorMessage = "Błąd podczas przesyłania logo."; }
        }

        if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileExtension = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
            $fileName = 'custom_favicon.' . $fileExtension;
            if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $fileName)) {
                $currentConfig['faviconPath'] = $uploadDir . $fileName;
            } else { $errorMessage = "Błąd podczas przesyłania favicony."; }
        }
    }

    if (isset($_POST['save_login'])) {
        $activeTab = 'login';
        $loginConfig = &$currentConfig['loginPage'];
        $loginConfig['backgroundType'] = $_POST['backgroundType'];
        $loginConfig['backgroundColor'] = $_POST['backgroundColor'];
        $loginConfig['boxColor'] = $_POST['boxColor'];
        $loginConfig['textColor'] = $_POST['textColor'];
        
        if (isset($_FILES['backgroundImage']) && $_FILES['backgroundImage']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'assets/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = 'login_bg.' . pathinfo($_FILES['backgroundImage']['name'], PATHINFO_EXTENSION);
            if (move_uploaded_file($_FILES['backgroundImage']['tmp_name'], $uploadDir . $fileName)) {
                $loginConfig['backgroundImage'] = $uploadDir . $fileName;
                $loginConfig['backgroundType'] = 'image';
            } else { $errorMessage = "Błąd podczas przesyłania tła."; }
        }

        foreach ($loginConfig['welcomeWidgets'] as $index => &$widget) {
            $widget['enabled'] = isset($_POST['widget_enabled'][$index]);
            $widget['icon'] = $_POST['widget_icon'][$index];
            $widget['title'] = $_POST['widget_title'][$index];
            $widget['text'] = $_POST['widget_text'][$index];
        }
    }
    
    if (isset($_POST['save_widgets'])) {
        $activeTab = 'widgets';
        if (isset($currentConfig['dashboardWidgets']) && is_array($currentConfig['dashboardWidgets'])) {
            foreach ($currentConfig['dashboardWidgets'] as $key => &$widget) {
                $widget['enabled'] = isset($_POST['widgets'][$key]);
            }
        }
    }

    if (empty($errorMessage)) {
        if (file_put_contents($configPath, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $successMessage = "Ustawienia zostały zaktualizowane.";
        } else { $errorMessage = "Nie udało się zapisać pliku konfiguracyjnego."; }
    }
}

$themeConfig = get_theme_config();
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Dostosuj Wygląd Aplikacji</h1>
    <a href="admin.php" class="btn btn-secondary">Wróć do panelu admina</a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= $successMessage ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $errorMessage ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<ul class="nav nav-tabs mb-4" id="themeTab" role="tablist">
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'general' ? 'active' : '' ?>" href="?tab=general">Ustawienia Ogólne</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'login' ? 'active' : '' ?>" href="?tab=login">Strona Logowania</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'widgets' ? 'active' : '' ?>" href="?tab=widgets">Widżety Panelu</a></li>
</ul>

<div class="tab-content" id="themeTabContent">
    <div class="tab-pane fade <?= $activeTab === 'general' ? 'show active' : '' ?>" role="tabpanel">
        <form method="POST" enctype="multipart/form-data" action="?tab=general">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white"><h4 class="mb-0">Ustawienia Główne</h4></div>
                        <div class="card-body">
                            <div class="mb-3"><label for="appName" class="form-label">Nazwa Aplikacji</label><input type="text" class="form-control" id="appName" name="appName" value="<?= htmlspecialchars($themeConfig['appName']) ?>"></div>
                            <div class="mb-3"><label for="footerText" class="form-label">Tekst w stopce</label><input type="text" class="form-control" id="footerText" name="footerText" value="<?= htmlspecialchars($themeConfig['footerText']) ?>"><div class="form-text">Użyj <code>{rok}</code>, aby wstawić aktualny rok.</div></div>
                            <div class="mb-3">
                                <label for="selectedTheme" class="form-label">Motyw Aplikacji</label>
                                <select class="form-select" id="selectedTheme" name="selectedTheme">
                                    <?php foreach ($themes as $file => $name): ?>
                                    <option value="<?= $file ?>" <?= ($themeConfig['selectedTheme'] ?? 'dark.css') === $file ? 'selected' : '' ?>><?= $name ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                     <div class="card shadow-sm">
                         <div class="card-header bg-dark text-white"><h4 class="mb-0">Branding</h4></div>
                         <div class="card-body">
                            <div class="mb-3"><label for="logo" class="form-label">Logo Aplikacji</label><input class="form-control" type="file" id="logo" name="logo" accept="image/*">
                            <?php if (!empty($themeConfig['logoPath']) && file_exists($themeConfig['logoPath'])): ?>
                                <div class="mt-2 d-flex align-items-center gap-2"><img src="<?= htmlspecialchars($themeConfig['logoPath']) ?>?v=<?= time() ?>" height="30" class="bg-dark p-1 rounded"><button type="submit" name="action" value="remove_logo" class="btn btn-sm btn-outline-danger">Usuń logo</button></div>
                            <?php endif; ?>
                            </div>
                            <div class="mb-3"><label for="favicon" class="form-label">Favicona</label><input class="form-control" type="file" id="favicon" name="favicon" accept="image/x-icon, image/png">
                             <?php if (!empty($themeConfig['faviconPath']) && file_exists($themeConfig['faviconPath'])): ?>
                                <div class="mt-2 d-flex align-items-center gap-2"><img src="<?= htmlspecialchars($themeConfig['faviconPath']) ?>?v=<?= time() ?>" height="16"><button type="submit" name="action" value="remove_favicon" class="btn btn-sm btn-outline-danger">Usuń favicon</button></div>
                            <?php endif; ?>
                            </div>
                         </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white"><h4 class="mb-0">Paleta Kolorów</h4></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label for="navbarBg" class="form-label">Tło nawigacji</label><input type="color" class="form-control form-control-color" name="navbarBg" value="<?= htmlspecialchars($themeConfig['navbarBg']) ?>"></div>
                                <div class="col-md-6"><label for="navbarText" class="form-label">Tekst nawigacji</label><input type="color" class="form-control form-control-color" name="navbarText" value="<?= htmlspecialchars($themeConfig['navbarText']) ?>"></div>
                                <hr class="my-3">
                                <?php $colors = ['primary'=>'Główny', 'secondary'=>'Drugorzędny', 'success'=>'Sukces', 'danger'=>'Zagrożenie', 'info'=>'Informacja', 'dark'=>'Ciemny']; foreach ($colors as $key => $label): $varName = '--bs-' . $key; $value = $themeConfig['colors'][$varName] ?? '#000000'; ?>
                                <div class="col-md-6 d-flex align-items-center gap-2"><input type="color" class="form-control form-control-color" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>"><label class="form-label mb-0 w-100"><?= $label ?></label></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-4 pt-4 border-top"><button type="submit" name="save_general" class="btn btn-primary btn-lg">Zapisz Ustawienia Ogólne</button></div>
        </form>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'login' ? 'show active' : '' ?>" role="tabpanel">
        <form method="POST" enctype="multipart/form-data" action="?tab=login">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-dark text-white"><h5 class="mb-0">Tło i Kolory</h5></div>
                        <div class="card-body">
                            <div class="mb-3"><label class="form-label">Typ tła</label><div class="form-check"><input class="form-check-input" type="radio" name="backgroundType" value="color" <?= $themeConfig['loginPage']['backgroundType'] === 'color' ? 'checked' : '' ?>><label class="form-check-label">Jednolity kolor</label></div><div class="form-check"><input class="form-check-input" type="radio" name="backgroundType" value="image" <?= $themeConfig['loginPage']['backgroundType'] === 'image' ? 'checked' : '' ?>><label class="form-check-label">Obrazek</label></div></div>
                            <div class="mb-3"><label class="form-label">Kolor tła</label><input type="color" class="form-control form-control-color" name="backgroundColor" value="<?= htmlspecialchars($themeConfig['loginPage']['backgroundColor']) ?>"></div>
                            <div class="mb-3"><label class="form-label">Obrazek tła</label><input class="form-control" type="file" name="backgroundImage" accept="image/*">
                            <?php if (!empty($themeConfig['loginPage']['backgroundImage']) && file_exists($themeConfig['loginPage']['backgroundImage'])): ?>
                                <div class="mt-2"><img src="<?= htmlspecialchars($themeConfig['loginPage']['backgroundImage']) ?>?v=<?= time() ?>" class="img-thumbnail" width="100"><button type="submit" name="action" value="remove_login_bg" class="btn btn-sm btn-outline-danger ms-2">Usuń obrazek</button></div>
                            <?php endif; ?>
                            </div>
                            <hr>
                            <div class="mb-3"><label class="form-label">Kolor panelu logowania</label><input type="color" class="form-control form-control-color" name="boxColor" value="<?= htmlspecialchars($themeConfig['loginPage']['boxColor']) ?>"></div>
                            <div class="mb-3"><label class="form-label">Kolor tekstu</label><input type="color" class="form-control form-control-color" name="textColor" value="<?= htmlspecialchars($themeConfig['loginPage']['textColor']) ?>"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white"><h5 class="mb-0">Widżety Powitalne</h5></div>
                        <div class="card-body">
                            <?php foreach($themeConfig['loginPage']['welcomeWidgets'] as $index => $widget): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="widget_enabled[<?= $index ?>]" <?= $widget['enabled'] ? 'checked' : '' ?>><label class="form-check-label">Widżet #<?= $index+1 ?> włączony</label></div>
                                <div class="mb-2"><label class="form-label small">Ikona (np. bi-trophy-fill)</label><input type="text" class="form-control form-control-sm" name="widget_icon[<?= $index ?>]" value="<?= htmlspecialchars($widget['icon']) ?>"></div>
                                <div class="mb-2"><label class="form-label small">Tytuł</label><input type="text" class="form-control form-control-sm" name="widget_title[<?= $index ?>]" value="<?= htmlspecialchars($widget['title']) ?>"></div>
                                <div class="mb-2"><label class="form-label small">Tekst</label><textarea class="form-control form-control-sm" name="widget_text[<?= $index ?>]" rows="2"><?= htmlspecialchars($widget['text']) ?></textarea></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="text-end mt-4 pt-4 border-top"><button type="submit" name="save_login" class="btn btn-primary btn-lg">Zapisz Ustawienia Strony Logowania</button></div>
        </form>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'widgets' ? 'show active' : '' ?>" role="tabpanel">
        <form method="POST" action="?tab=widgets">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white"><h4 class="mb-0">Widżety na Panelu Głównym</h4></div>
                <div class="card-body">
                    <p class="text-muted">Wybierz, które widżety mają być widoczne dla wszystkich użytkowników na ich panelu głównym (dashboard).</p>
                    <div class="row">
                        <?php foreach ($themeConfig['dashboardWidgets'] as $key => $widget): ?>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2 fs-5">
                                <input class="form-check-input" type="checkbox" role="switch" id="widget_<?= $key ?>" name="widgets[<?= $key ?>]" <?= $widget['enabled'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="widget_<?= $key ?>"><?= htmlspecialchars($widget['title']) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="text-end mt-4 pt-4 border-top"><button type="submit" name="save_widgets" class="btn btn-primary btn-lg">Zapisz Ustawienia Widżetów</button></div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>