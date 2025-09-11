<?php
$pageTitle = 'Stwórz Nowy Plan Treningowy';
require_once 'includes/functions.php';

// --- LOGIKA ZAPISU (PRZENIESIONA NA GÓRĘ) ---
session_start();
$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planName = trim($_POST['plan_name'] ?? '');
    $planDescription = trim($_POST['plan_description'] ?? '');
    $postedExercises = $_POST['exercises'] ?? [];

    if (empty($planName) || empty($postedExercises)) {
        $errorMessage = "Nazwa planu oraz co najmniej jedno ćwiczenie są wymagane.";
    } else {
        $newPlan = [
            'plan_id' => 'p_' . date('YmdHis') . '_' . bin2hex(random_bytes(2)),
            'plan_name' => htmlspecialchars($planName),
            'plan_description' => htmlspecialchars($planDescription),
            'exercises' => []
        ];

        foreach ($postedExercises as $ex) {
            if (!empty($ex['exercise_id']) && !empty($ex['sets'])) {
                $exerciseData = [
                    'exercise_id' => (int)$ex['exercise_id'],
                    'target_sets' => []
                ];
                foreach ($ex['sets'] as $set) {
                    $setData = [];
                    // ZMIANA: Dynamiczne pobieranie danych
                    foreach (get_all_trackable_params() as $param) {
                        $paramId = $param['id'];
                        if (isset($set[$paramId]) && $set[$paramId] !== '') {
                            $setData[$paramId] = $set[$paramId];
                        }
                    }
                    if (!empty($setData)) {
                        $exerciseData['target_sets'][] = $setData;
                    }
                }
                if (!empty($exerciseData['target_sets'])) {
                    $newPlan['exercises'][] = $exerciseData;
                }
            }
        }

        if (!empty($newPlan['exercises'])) {
            $userPlans = get_user_plans($userId);
            $userPlans[] = $newPlan;
            if (save_user_plans($userId, $userPlans)) {
                $successMessage = "Nowy plan treningowy '{$newPlan['plan_name']}' został pomyślnie utworzony!";
            } else {
                $errorMessage = "Wystąpił błąd podczas zapisywania planu.";
            }
        } else {
            $errorMessage = "Plan musi zawierać co najmniej jedną serię w przynajmniej jednym ćwiczeniu.";
        }
    }
}

// --- WYŚWIETLANIE STRONY ---
require_once 'includes/header.php';
echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">';

$allExercises = get_all_exercises();
$allTrackableParams = get_all_trackable_params(); // NOWOŚĆ
?>

<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $successMessage ?> <a href="plans.php" class="alert-link">Wróć do listy planów</a>.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= $errorMessage ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h1 class="h3 mb-0">Kreator Planu Treningowego</h1>
    </div>
    <div class="card-body">
        <form method="POST" action="create_plan.php" id="plan-creator-form">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="plan_name" class="form-label">Nazwa Planu</label>
                    <input type="text" id="plan_name" name="plan_name" class="form-control form-control-lg" placeholder="np. Push Day A, Nogi Siła" required>
                </div>
                <div class="col-md-6">
                    <label for="plan_description" class="form-label">Krótki opis</label>
                    <input type="text" id="plan_description" name="plan_description" class="form-control" placeholder="np. Skupienie na sile, długie przerwy">
                </div>
            </div>

            <h4 class="mt-4 pt-2 border-top">Ćwiczenia w Planie</h4>
            <div id="exercises-container" class="vstack gap-4">
                <!-- Dynamicznie dodawane ćwiczenia pojawią się tutaj -->
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between">
                <button type="button" id="add-exercise-btn" class="btn btn-secondary">
                    <i class="bi bi-plus-lg me-1"></i> Dodaj ćwiczenie do planu
                </button>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save me-2"></i> Zapisz Plan Treningowy
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SZABLONY HTML DLA JAVASCRIPTU -->
<template id="exercise-template">
    <div class="card exercise-block p-3" data-exercise-index="{exercise_index}">
        <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
        <div class="mb-3">
            <label class="form-label fw-bold">Wybierz ćwiczenie</label>
            <select name="exercises[{exercise_index}][exercise_id]" class="exercise-select" required>
                <option value="" disabled selected>-- Wybierz z listy --</option>
                <?php foreach ($allExercises as $exercise): ?>
                    <option value="<?= $exercise['id'] ?>" data-track-by='<?= json_encode($exercise['track_by']) ?>' data-tags='[]' data-desc='' data-howto=''>
                        <?= htmlspecialchars($exercise['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="info-button-container"></div>
        <div class="tags-display-container"></div>
        <hr>
        <div class="sets-container vstack gap-2"></div>
        <button type="button" class="btn btn-sm btn-outline-primary add-set mt-3"><i class="bi bi-plus me-1"></i>Dodaj docelową serię</button>
    </div>
</template>

<template id="set-template">
    <div class="set-row input-group">
        <span class="input-group-text series-counter" style="min-width: 80px;">Seria X</span>
        <?php foreach ($allTrackableParams as $param):
            $paramId = htmlspecialchars($param['id']);
            $paramName = htmlspecialchars($param['name']);
            // W kreatorze planów używamy input[type=text] dla większej elastyczności (np. "8-12" powtórzeń)
        ?>
            <input type="text" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[{exercise_index}][sets][{set_index}][<?= $paramId ?>]" placeholder="<?= $paramName ?>" style="display: none;">
        <?php endforeach; ?>
        <button class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    // Przekazujemy listę parametrów do JS (nawet jeśli nie wszystkie są używane, to dla spójności)
    window.APP_DATA = { 
        trackableParams: <?= json_encode($allTrackableParams) ?>
    };
</script>
<script src="assets/js/app.js" type="module"></script>