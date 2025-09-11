<?php
$pageTitle = 'Edytuj Plan Treningowy';
require_once 'includes/functions.php';

// --- KROK 1: Logika formularza (na samej górze) ---
session_start();
$userId = $_SESSION['user_id'];
$planId = $_GET['plan_id'] ?? null;
$successMessage = '';
$errorMessage = '';

if (!$planId) {
    header('Location: plans.php');
    exit();
}

// Pobranie planu do edycji przed ewentualną aktualizacją
$userPlans = get_user_plans($userId);
$planToEdit = null;
$planIndex = null;
foreach ($userPlans as $index => $plan) {
    if ($plan['plan_id'] === $planId) {
        $planToEdit = $plan;
        $planIndex = $index;
        break;
    }
}

if (!$planToEdit) {
    // Jeśli nie znaleziono planu, nie ma sensu kontynuować
    require_once 'includes/header.php';
    echo "<div class='alert alert-danger'>Nie znaleziono planu o podanym ID.</div>";
    require_once 'includes/footer.php';
    exit();
}


// --- LOGIKA AKTUALIZACJI PLANU ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planName = trim($_POST['plan_name'] ?? '');
    $planDescription = trim($_POST['plan_description'] ?? '');
    $postedExercises = $_POST['exercises'] ?? [];

    if (empty($planName) || empty($postedExercises)) {
        $errorMessage = "Nazwa planu oraz co najmniej jedno ćwiczenie są wymagane.";
    } else {
        $updatedPlan = [
            'plan_id' => $planId, // Zachowaj stare ID
            'plan_name' => htmlspecialchars($planName),
            'plan_description' => htmlspecialchars($planDescription),
            'exercises' => []
        ];

        // === POPRAWIONA, DYNAMICZNA LOGIKA ZAPISU SERII ===
        foreach ($postedExercises as $ex) {
            if (!empty($ex['exercise_id']) && !empty($ex['sets'])) {
                $exerciseData = ['exercise_id' => (int)$ex['exercise_id'], 'target_sets' => []];
                foreach ($ex['sets'] as $set) {
                    $setData = [];
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
                    $updatedPlan['exercises'][] = $exerciseData;
                }
            }
        }
        
        // Zaktualizuj plan w tablicy
        if (!empty($updatedPlan['exercises'])) {
            $userPlans[$planIndex] = $updatedPlan;
            if (save_user_plans($userId, $userPlans)) {
                $successMessage = "Plan '{$updatedPlan['plan_name']}' został zaktualizowany. <a href='plans.php' class='alert-link'>Wróć do listy planów</a>.";
                $planToEdit = $updatedPlan; // Odśwież dane do wyświetlenia w formularzu
            } else {
                $errorMessage = "Wystąpił błąd podczas zapisywania planu.";
            }
        } else {
            $errorMessage = "Plan musi zawierać co najmniej jedną serię w przynajmniej jednym ćwiczeniu.";
        }
    }
}


// --- KROK 2: Wyświetlanie strony ---
require_once 'includes/header.php';
echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">';

$allExercises = get_all_exercises();
$allTrackableParams = get_all_trackable_params();
?>

<?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<div class="card">
    <div class="card-header bg-dark text-white">
        <h1 class="h3 mb-0">Edytujesz: <?= htmlspecialchars($planToEdit['plan_name']) ?></h1>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_plan.php?plan_id=<?= $planId ?>" id="plan-creator-form">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="plan_name" class="form-label">Nazwa Planu</label>
                    <input type="text" id="plan_name" name="plan_name" class="form-control form-control-lg" value="<?= htmlspecialchars($planToEdit['plan_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="plan_description" class="form-label">Krótki opis</label>
                    <input type="text" id="plan_description" name="plan_description" class="form-control" value="<?= htmlspecialchars($planToEdit['plan_description']) ?>">
                </div>
            </div>

            <h4 class="mt-4 pt-2 border-top">Ćwiczenia w Planie</h4>
            <div id="exercises-container" class="vstack gap-4">
                <?php foreach ($planToEdit['exercises'] as $exIndex => $exercise): ?>
                    <div class="card exercise-block p-3" data-exercise-index="<?= $exIndex ?>">
                        <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Wybierz ćwiczenie</label>
                            <select name="exercises[<?= $exIndex ?>][exercise_id]" class="exercise-select" required>
                                <?php foreach ($allExercises as $exDef): ?>
                                    <option value="<?= $exDef['id'] ?>" <?= ($exDef['id'] == $exercise['exercise_id']) ? 'selected' : '' ?> data-track-by='<?= json_encode($exDef['track_by']) ?>'>
                                        <?= htmlspecialchars($exDef['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr>
                        <div class="sets-container vstack gap-2">
                            <?php foreach ($exercise['target_sets'] as $setIndex => $set): ?>
                            <div class="set-row input-group">
                                <span class="input-group-text series-counter" style="min-width: 80px;">Seria <?= $setIndex + 1 ?></span>
                                <?php foreach ($allTrackableParams as $param): 
                                    $paramId = htmlspecialchars($param['id']);
                                    $paramName = htmlspecialchars($param['name']);
                                    $value = htmlspecialchars($set[$paramId] ?? '');
                                    $trackByForThisExercise = array_column(array_filter($allExercises, fn($ex) => $ex['id'] == $exercise['exercise_id']), 'track_by')[0] ?? [];
                                ?>
                                    <input type="text" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[<?= $exIndex ?>][sets][<?= $setIndex ?>][<?= $paramId ?>]" value="<?= $value ?>" placeholder="<?= $paramName ?>" style="<?= in_array($paramId, $trackByForThisExercise) ? 'display:block;' : 'display:none;' ?>">
                                <?php endforeach; ?>
                                <button class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary add-set mt-3"><i class="bi bi-plus me-1"></i>Dodaj docelową serię</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-between">
                <button type="button" id="add-exercise-btn" class="btn btn-secondary"><i class="bi bi-plus-lg me-1"></i> Dodaj kolejne ćwiczenie</button>
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i> Zaktualizuj Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Szablony HTML (takie same jak w create_plan.php) -->
<template id="exercise-template">
    <div class="card exercise-block p-3" data-exercise-index="{exercise_index}">
        <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
        <div class="mb-3">
            <label class="form-label fw-bold">Wybierz ćwiczenie</label>
            <select name="exercises[{exercise_index}][exercise_id]" class="exercise-select" required>
                <option value="" disabled selected>-- Wybierz z listy --</option>
                <?php foreach ($allExercises as $exercise): ?>
                    <option value="<?= $exercise['id'] ?>" data-track-by='<?= json_encode($exercise['track_by']) ?>'>
                        <?= htmlspecialchars($exercise['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
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
        ?>
            <input type="text" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[{exercise_index}][sets][{set_index}][<?= $paramId ?>]" placeholder="<?= $paramName ?>" style="display: none;">
        <?php endforeach; ?>
        <button class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    window.APP_DATA = { 
        trackableParams: <?= json_encode($allTrackableParams) ?>
    };
</script>
<script src="assets/js/app.js" type="module"></script>