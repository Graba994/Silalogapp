<?php
$pageTitle = 'Mój Profil - SiłaLog';
require_once 'includes/functions.php';

// --- KROK 1: LOGIKA FORMULARZY (Zawsze na samej górze) ---
session_start();
$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';
$activeTab = $_GET['tab'] ?? 'measurements'; // Domyślna zakładka

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allUsers = json_decode(file_get_contents('data/users.json'), true);
    $currentUserIndex = null;
    foreach ($allUsers as $index => $user) {
        if ($user['id'] === $userId) {
            $currentUserIndex = $index;
            break;
        }
    }

    // Obsługa dodawania/aktualizacji pomiarów
    if (isset($_POST['save_measurements'])) {
        $activeTab = 'measurements';
        $date = $_POST['date'] ?? null;
        if (empty($date)) {
            $errorMessage = "Data jest wymagana do zapisu pomiaru.";
        } else {
            $newMeasurement = ['date' => $date];
            $params = ['weight', 'body_fat', 'neck', 'chest', 'biceps', 'waist', 'hips', 'thigh', 'calf'];
            foreach ($params as $param) {
                if (isset($_POST[$param]) && $_POST[$param] !== '') $newMeasurement[$param] = (float)$_POST[$param];
            }

            $allMeasurements = get_user_measurements($userId);
            $entryIndex = null;
            foreach ($allMeasurements as $index => $entry) {
                if ($entry['date'] === $date) {
                    $entryIndex = $index;
                    break;
                }
            }

            if ($entryIndex !== null) { // Aktualizuj istniejący wpis
                $allMeasurements[$entryIndex] = array_merge($allMeasurements[$entryIndex], $newMeasurement);
            } else { // Dodaj nowy wpis
                $allMeasurements[] = $newMeasurement;
            }

            if (save_user_measurements($userId, $allMeasurements)) {
                $successMessage = "Pomiary dla daty {$date} zostały zapisane.";
                $allMeasurements = get_user_measurements($userId); // Wczytaj posortowane
                $latest_entry = end($allMeasurements);
                if (isset($newMeasurement['weight']) && $currentUserIndex !== null && $latest_entry['date'] === $date) {
                    $allUsers[$currentUserIndex]['body_params']['weight'] = $newMeasurement['weight'];
                    file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT));
                    $_SESSION['user_body_params']['weight'] = $newMeasurement['weight'];
                }
            } else {
                $errorMessage = 'Błąd zapisu danych pomiarów.';
            }
        }
    }

    // Obsługa zmiany ikony
    if (isset($_POST['change_icon'])) {
        $activeTab = 'settings';
        $newIcon = $_POST['user_icon'] ?? 'bi-person';
        if ($currentUserIndex !== null) {
            $allUsers[$currentUserIndex]['icon'] = htmlspecialchars($newIcon);
            if (file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT))) {
                $successMessage = 'Ikona profilu została zmieniona.';
            } else {
                $errorMessage = 'Błąd zapisu ikony.';
            }
        }
    }
    
    // Obsługa zmiany hasła
    if (isset($_POST['change_password'])) {
        $activeTab = 'security';
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        if (empty($newPassword) || $newPassword !== $confirmPassword) {
            $errorMessage = 'Hasła są puste lub się nie zgadzają.';
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = 'Hasło musi mieć co najmniej 8 znaków.';
        } else {
            $allUsers[$currentUserIndex]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            if (file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT))) {
                $successMessage = 'Hasło zostało zmienione pomyślnie.';
            } else {
                $errorMessage = 'Błąd zapisu danych.';
            }
        }
    }

    // Obsługa kluczowych ćwiczeń
    if (isset($_POST['update_key_exercises'])) {
        $activeTab = 'settings';
        $keyExercises = isset($_POST['key_exercises']) ? array_filter(array_map('intval', $_POST['key_exercises'])) : [];
        $allUsers[$currentUserIndex]['key_exercises'] = array_slice($keyExercises, 0, 4);
        if (file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT))) {
            $successMessage = 'Kluczowe ćwiczenia zostały zaktualizowane.';
        } else {
            $errorMessage = 'Błąd zapisu danych.';
        }
    }

    // === NOWA LOGIKA: Zapis ustawień sesji ===
    if (isset($_POST['save_session_settings'])) {
        $activeTab = 'security';
        $sessionTimeout = (int)($_POST['session_timeout'] ?? 1440); // Domyślnie 24 min
        if ($currentUserIndex !== null) {
            $allUsers[$currentUserIndex]['settings']['session_timeout'] = $sessionTimeout;
            if (file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT))) {
                $_SESSION['user_settings']['session_timeout'] = $sessionTimeout; // Zaktualizuj też w bieżącej sesji
                $successMessage = 'Ustawienia sesji zostały zapisane.';
            } else {
                $errorMessage = 'Błąd zapisu ustawień sesji.';
            }
        }
    }
}

// --- KROK 2: WYŚWIETLANIE STRONY ---
require_once 'includes/header.php';

$allUsers = json_decode(file_get_contents('data/users.json'), true);
$currentUser = null;
foreach($allUsers as $u) { if($u['id'] === $userId) { $currentUser = $u; break; } }
$allExercises = get_all_exercises();
$userMeasurements = get_user_measurements($userId);
$currentSessionTimeout = $currentUser['settings']['session_timeout'] ?? 1440; // Domyślnie 24 min

$chartLabels = []; $chartWeightData = []; $chartBodyFatData = [];
if (!empty($userMeasurements)) {
    $chartLabels = array_column($userMeasurements, 'date');
    $tempWeight = array_column($userMeasurements, 'weight', 'date');
    $tempBodyFat = array_column($userMeasurements, 'body_fat', 'date');
    foreach($chartLabels as $label) {
        $chartWeightData[] = $tempWeight[$label] ?? null;
        $chartBodyFatData[] = $tempBodyFat[$label] ?? null;
    }
}

$iconList = ['person-arms-up', 'person-biking', 'person-running', 'person-walking', 'universal-access', 'person-raised-hand', 'person-standing', 'person-video', 'person-video2', 'person-video3', 'person-wheelchair', 'person-workspace', 'person-badge', 'person-bounding-box', 'person-check', 'person-circle', 'person-fill', 'person-heart', 'person-lines-fill', 'person-plus'];
sort($iconList);
?>

<?php if ($successMessage): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $errorMessage ?></div><?php endif; ?>

<h1 class="mb-4">Mój Profil</h1>

<ul class="nav nav-tabs mb-4" id="profileTab" role="tablist">
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'measurements' ? 'active' : '' ?>" href="profile.php?tab=measurements">Pomiary Ciała</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'settings' ? 'active' : '' ?>" href="profile.php?tab=settings">Ustawienia Konta</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link <?= $activeTab === 'security' ? 'active' : '' ?>" href="profile.php?tab=security">Bezpieczeństwo</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link" href="manage_exercises.php">Zarządzaj Ćwiczeniami</a></li>
    <li class="nav-item" role="presentation"><a class="nav-link" href="manage_tags.php">Zarządzaj Tagami</a></li>
</ul>

<div class="tab-content" id="profileTabContent">
    <!-- ZAKŁADKA: POMIARY CIAŁA -->
    <div class="tab-pane fade <?= $activeTab === 'measurements' ? 'show active' : '' ?>" id="measurements-panel" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-rulers me-2"></i>Edytuj / Dodaj Pomiar</h5></div>
                    <div class="card-body">
                        <form method="POST" action="profile.php?tab=measurements" id="measurements-form">
                            <div class="mb-3"><label for="date" class="form-label">Data pomiaru</label><input type="date" class="form-control" id="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
                            <div class="row g-2"><?php $fields = ['weight' => ['label' => 'Waga (kg)', 'step' => '0.1'], 'body_fat' => ['label' => 'Tk. tł. (%)', 'step' => '0.1'], 'neck' => ['label' => 'Kark (cm)', 'step' => '0.5'], 'chest' => ['label' => 'Klatka (cm)', 'step' => '0.5'], 'biceps' => ['label' => 'Biceps (cm)', 'step' => '0.5'], 'waist' => ['label' => 'Talia (cm)', 'step' => '0.5'], 'hips' => ['label' => 'Biodra (cm)', 'step' => '0.5'], 'thigh' => ['label' => 'Udo (cm)', 'step' => '0.5'], 'calf' => ['label' => 'Łydka (cm)', 'step' => '0.5']]; ?><?php foreach ($fields as $key => $details): ?><div class="col-6 mb-2"><label for="<?= $key ?>" class="form-label small"><?= $details['label'] ?></label><input type="number" step="<?= $details['step'] ?>" class="form-control form-control-sm" id="<?= $key ?>" name="<?= $key ?>"></div><?php endforeach; ?></div>
                            <button type="submit" name="save_measurements" class="btn btn-primary w-100 mt-3"><i class="bi bi-save me-2"></i>Zapisz pomiar</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-header"><h5 class="mb-0">Historia Wagi i Tkanki Tłuszczowej</h5></div><div class="card-body"><?php if(!empty($userMeasurements)): ?><canvas id="weightChart" style="max-height: 300px;"></canvas><?php else: ?><p class="text-center text-muted p-5">Brak danych do wyświetlenia. Dodaj swój pierwszy pomiar.</p><?php endif; ?></div></div>
                <div class="card"><div class="card-header"><h5 class="mb-0">Historia Pomiarów (kliknij wiersz, aby edytować)</h5></div><div class="table-responsive" style="max-height: 400px;"><table class="table table-striped table-hover mb-0" id="measurements-table"><thead><tr><th>Data</th><?php foreach ($fields as $key => $details) echo "<th>{$details['label']}</th>"; ?></tr></thead><tbody><?php foreach (array_reverse($userMeasurements) as $entry): ?><tr style="cursor: pointer;" data-measurements='<?= htmlspecialchars(json_encode($entry)) ?>'><td><strong><?= $entry['date'] ?></strong></td><?php foreach ($fields as $key => $details) echo "<td>" . ($entry[$key] ?? '-') . "</td>"; ?></tr><?php endforeach; ?></tbody></table></div></div>
            </div>
        </div>
    </div>
    
    <!-- ZAKŁADKA: USTAWIENIA KONTA -->
    <div class="tab-pane fade <?= $activeTab === 'settings' ? 'show active' : '' ?>" id="settings-panel" role="tabpanel">
         <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white"><h2 class="h5 mb-0">Personalizacja</h2></div>
                    <div class="card-body">
                        <form method="POST" action="profile.php?tab=settings">
                            <div class="mb-3"><label class="form-label">Twoja ikona profilu</label><div class="d-flex align-items-center gap-3"><i id="icon-preview" class="bi <?= htmlspecialchars($currentUser['icon']) ?> fs-1 text-primary p-2 bg-light rounded"></i><input type="hidden" name="user_icon" id="user_icon_input" value="<?= htmlspecialchars($currentUser['icon']) ?>"><div class="flex-grow-1"><button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#iconPickerModal">Zmień ikonę</button><button type="submit" name="change_icon" class="btn btn-primary">Zapisz ikonę</button></div></div></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white"><h2 class="h5 mb-0">Twoje Kluczowe Ćwiczenia</h2></div>
                    <div class="card-body">
                        <p class="text-muted small">Wybierz do 4 ćwiczeń, których skrócone statystyki chcesz widzieć na stronie "Statystyki".</p>
                        <form method="POST" action="profile.php?tab=settings">
                            <div class="row"><?php for ($i = 0; $i < 4; $i++): ?><div class="col-md-6 mb-3"><label for="key_exercise_<?= $i ?>" class="form-label small">Ćwiczenie #<?= $i + 1 ?></label><select class="form-select form-select-sm" id="key_exercise_<?= $i ?>" name="key_exercises[]"><option value="">-- Brak --</option><?php $selectedId = $currentUser['key_exercises'][$i] ?? null; foreach($allExercises as $exercise): ?><option value="<?= $exercise['id'] ?>" <?= ($selectedId == $exercise['id']) ? 'selected' : '' ?>><?= htmlspecialchars($exercise['name']) ?></option><?php endforeach; ?></select></div><?php endfor; ?></div>
                            <button type="submit" name="update_key_exercises" class="btn btn-info">Zapisz wybrane ćwiczenia</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- NOWA ZAKŁADKA: BEZPIECZEŃSTWO -->
    <div class="tab-pane fade <?= $activeTab === 'security' ? 'show active' : '' ?>" id="security-panel" role="tabpanel">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white"><h2 class="h5 mb-0">Zmień Hasło</h2></div>
                    <div class="card-body">
                        <form method="POST" action="profile.php?tab=security">
                            <div class="mb-3"><label for="new_password" class="form-label">Nowe hasło</label><input type="password" class="form-control" id="new_password" name="new_password" required minlength="8"></div>
                            <div class="mb-3"><label for="confirm_password" class="form-label">Potwierdź nowe hasło</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8"></div>
                            <button type="submit" name="change_password" class="btn btn-warning">Zmień hasło</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                 <div class="card h-100">
                    <div class="card-header bg-dark text-white"><h2 class="h5 mb-0">Ustawienia Sesji</h2></div>
                    <div class="card-body">
                        <form method="POST" action="profile.php?tab=security">
                            <div class="mb-3">
                                <label for="session_timeout" class="form-label">Automatyczne wylogowanie</label>
                                <select class="form-select" id="session_timeout" name="session_timeout">
                                    <option value="1440" <?= $currentSessionTimeout == 1440 ? 'selected' : '' ?>>po 24 minutach bezczynności</option>
                                    <option value="3600" <?= $currentSessionTimeout == 3600 ? 'selected' : '' ?>>po 1 godzinie bezczynności</option>
                                    <option value="14400" <?= $currentSessionTimeout == 14400 ? 'selected' : '' ?>>po 4 godzinach bezczynności</option>
                                    <option value="86400" <?= $currentSessionTimeout == 86400 ? 'selected' : '' ?>>po 1 dniu bezczynności</option>
                                </select>
                                <div class="form-text">To ustawienie nie ma wpływu na opcję "Zapamiętaj mnie" przy logowaniu.</div>
                            </div>
                             <button type="submit" name="save_session_settings" class="btn btn-info">Zapisz ustawienia sesji</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DO WYBORU IKONY -->
<div class="modal fade" id="iconPickerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Wybierz ikonę</h5><input type="search" id="icon-search" class="form-control ms-3" placeholder="Szukaj ikony..."><button type="button" class="btn-close ms-2" data-bs-dismiss="modal"></button></div><div class="modal-body"><div id="icon-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px;"><?php foreach($iconList as $icon): ?><div class="icon-picker-item text-center p-2 rounded" style="cursor: pointer;" data-icon-class="bi-<?= $icon ?>" data-icon-name="<?= $icon ?>"><i class="bi bi-<?= $icon ?> fs-2"></i><div class="small text-muted" style="font-size: 10px;"><?= $icon ?></div></div><?php endforeach; ?></div></div></div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... reszta skryptu (dla pomiarów, wykresu i ikony) pozostaje bez zmian ...
    const measurementsData = <?= json_encode($userMeasurements) ?>;
    const form = document.getElementById('measurements-form');
    const dateInput = document.getElementById('date');
    const tableRows = document.querySelectorAll('#measurements-table tbody tr');
    const formFields = ['weight', 'body_fat', 'neck', 'chest', 'biceps', 'waist', 'hips', 'thigh', 'calf'];
    function populateForm(date) { const entry = measurementsData.find(m => m.date === date); formFields.forEach(key => form.elements[key].value = ''); if (entry) { formFields.forEach(key => { if (entry[key] !== undefined) form.elements[key].value = entry[key]; }); } }
    if(dateInput) { dateInput.addEventListener('change', (e) => populateForm(e.target.value)); tableRows.forEach(row => { row.addEventListener('click', () => { const data = JSON.parse(row.dataset.measurements); dateInput.value = data.date; populateForm(data.date); }); }); populateForm(dateInput.value); }
    const ctx = document.getElementById('weightChart');
    if (ctx && measurementsData.length > 0) { new Chart(ctx, { type: 'line', data: { labels: <?= json_encode($chartLabels) ?>, datasets: [ { label: 'Waga (kg)', data: <?= json_encode($chartWeightData) ?>, borderColor: 'rgb(54, 162, 235)', yAxisID: 'yWeight', spanGaps: true, }, { label: 'Tk. tł. (%)', data: <?= json_encode($chartBodyFatData) ?>, borderColor: 'rgb(255, 99, 132)', yAxisID: 'yFat', hidden: true, spanGaps: true, } ] }, options: { responsive: true, maintainAspectRatio: false, scales: { yWeight: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Waga (kg)' } }, yFat: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Tk. tł. (%)' }, grid: { drawOnChartArea: false } } } } }); }
    const iconPickerModalEl = document.getElementById('iconPickerModal');
    if (iconPickerModalEl) { const iconModal = new bootstrap.Modal(iconPickerModalEl); const iconPreview = document.getElementById('icon-preview'); const hiddenInput = document.getElementById('user_icon_input'); const iconGrid = document.getElementById('icon-grid'); const iconSearch = document.getElementById('icon-search'); iconGrid.addEventListener('click', (e) => { const item = e.target.closest('.icon-picker-item'); if (item) { const newIconClass = item.dataset.iconClass; iconPreview.className = `${newIconClass} fs-1 text-primary p-2 bg-light rounded`; hiddenInput.value = newIconClass; iconModal.hide(); } }); iconSearch.addEventListener('input', () => { const searchTerm = iconSearch.value.toLowerCase(); document.querySelectorAll('.icon-picker-item').forEach(item => { const shouldShow = item.dataset.iconName.toLowerCase().includes(searchTerm); item.style.display = shouldShow ? 'block' : 'none'; }); }); }
});
</script>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>