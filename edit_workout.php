<?php
// KROK 1: Logika formularza i sesji
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';

$userId = $_SESSION['user_id'];
$workoutId = $_GET['id'] ?? $_POST['workout_id'] ?? null;
$errorMessage = '';
$successMessage = '';
$allTrackableParams = get_all_trackable_params(); 

// Walidacja ID
if (!$workoutId) {
    header('Location: history.php?error=no_id');
    exit();
}

// Wczytanie wszystkich treningów i znalezienie tego do edycji
$allWorkouts = get_user_workouts($userId);
$workoutToEdit = null;
$workoutIndex = null;
foreach ($allWorkouts as $index => $workout) {
    if ($workout['workout_id'] === $workoutId) {
        $workoutToEdit = $workout;
        $workoutIndex = $index;
        break;
    }
}

// Jeśli nie znaleziono treningu, przekieruj
if ($workoutToEdit === null) {
    header('Location: history.php?error=not_found');
    exit();
}

// KROK 2: Logika zapisywania formularza po edycji
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $notes = $_POST['notes'] ?? '';
    $postedExercises = $_POST['exercises'] ?? [];
    
    $updatedWorkout = [
        'workout_id' => $workoutId, // Zachowaj ID!
        'date' => $date,
        'notes' => htmlspecialchars($notes),
        'exercises' => [],
        'interactions' => $workoutToEdit['interactions'] // Zachowaj istniejące polubienia i komentarze
    ];

    if (!empty($postedExercises)) {
        foreach ($postedExercises as $ex) {
            if (!empty($ex['exercise_id']) && isset($ex['sets']) && is_array($ex['sets'])) {
                $exerciseData = ['exercise_id' => (int)$ex['exercise_id'], 'sets' => []];
                foreach ($ex['sets'] as $set) {
                    $setData = [];
                    foreach ($allTrackableParams as $param) {
                        $paramId = $param['id'];
                        if (isset($set[$paramId]) && trim((string)$set[$paramId]) !== '') {
                            $setData[$paramId] = (float)$set[$paramId];
                        }
                    }
                    if (!empty($setData)) {
                        $exerciseData['sets'][] = $setData;
                    }
                }
                if (!empty($exerciseData['sets'])) {
                    $updatedWorkout['exercises'][] = $exerciseData;
                }
            }
        }
    }

    if (!empty($updatedWorkout['exercises'])) {
        $allWorkouts[$workoutIndex] = $updatedWorkout; // Zastąp stary trening nowym w tablicy
        if (save_user_workouts($userId, $allWorkouts)) {
            $successMessage = "Trening został pomyślnie zaktualizowany.";
            $workoutToEdit = $updatedWorkout; // Odśwież dane do ponownego wyświetlenia w formularzu
        } else {
            $errorMessage = "Wystąpił błąd podczas zapisywania treningu.";
        }
    } else {
        $errorMessage = "Trening musi zawierać co najmniej jedno ćwiczenie. Nie zapisano zmian.";
    }
}

// KROK 3: Przygotowanie danych dla widoku
$pageTitle = 'Edytuj Trening';
require_once 'includes/header.php';

echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">';

$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, null, 'id');

$allTagGroups = get_all_tags();
$tagMap = [];
foreach ($allTagGroups as $group) {
    foreach ($group['tags'] as $tag) {
        $tagMap[$tag['id']] = [ 'name' => $tag['name'], 'color' => $group['color'] ?? 'secondary' ];
    }
}
?>
<!-- KROK 4: Wyświetlanie HTML -->
<?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<form method="POST" action="edit_workout.php" id="workout-form">
    <input type="hidden" name="workout_id" value="<?= htmlspecialchars($workoutId) ?>">
    
    <div class="card mb-4">
        <div class="card-header bg-body-secondary">
            <h1 class="h4 mb-0">Edycja treningu z dnia: <?= htmlspecialchars($workoutToEdit['date']) ?></h1>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label for="date" class="form-label">Data</label><input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($workoutToEdit['date']) ?>" required></div>
                <div class="col-md-6"><label for="notes" class="form-label">Notatki</label><input type="text" id="notes" name="notes" class="form-control" value="<?= htmlspecialchars($workoutToEdit['notes']) ?>" placeholder="np. Dobra energia, progres w przysiadzie"></div>
            </div>
        </div>
    </div>
    
    <div id="exercises-container" class="vstack gap-4">
        <?php foreach ($workoutToEdit['exercises'] as $exIndex => $exercise): ?>
            <?php
                $exerciseDetails = $exerciseMap[$exercise['exercise_id']] ?? null;
                if (!$exerciseDetails) continue;
                $prs = get_exercise_prs($exercise['exercise_id'], $userId);
            ?>
            <div class="card exercise-block-v2" data-exercise-index="<?= $exIndex ?>">
                <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
                <div class="card-header d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <select name="exercises[<?= $exIndex ?>][exercise_id]" class="exercise-select" required>
                            <?php foreach ($allExercises as $exOption): ?>
                                <option value="<?= $exOption['id'] ?>" <?= ($exOption['id'] == $exercise['exercise_id']) ? 'selected' : '' ?>
                                    data-track-by='<?= json_encode($exOption['track_by']) ?>'
                                    data-tags='<?= json_encode($exOption['tags'] ?? []) ?>'
                                    data-desc="<?= htmlspecialchars(auto_embed_youtube_videos($exOption['description'] ?? '')) ?>"
                                    data-howto="<?= htmlspecialchars(auto_embed_youtube_videos($exOption['howto'] ?? '')) ?>"
                                    data-pr-weight="<?= get_exercise_prs($exOption['id'], $userId)['max_weight'] ?>"
                                    data-pr-e1rm="<?= get_exercise_prs($exOption['id'], $userId)['e1rm'] ?>">
                                    <?= htmlspecialchars($exOption['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pr-display-container small text-muted mt-2"></div>
                        <div class="tags-display-container d-flex flex-wrap gap-1 mt-2"></div>
                    </div>
                    <div class="info-button-container ps-2 pt-4"></div>
                </div>
                <div class="sets-container vstack gap-2 p-3">
                    <?php foreach ($exercise['sets'] as $setIndex => $set): ?>
                        <div class="set-row input-group">
                            <span class="input-group-text series-counter" style="min-width: 80px;">Seria <?= $setIndex + 1 ?></span>
                            <?php foreach ($allTrackableParams as $param):
                                $paramId = htmlspecialchars($param['id']);
                                $paramName = htmlspecialchars($param['name']);
                                $step = ($paramId === 'weight') ? '0.25' : '1';
                                $value = $set[$paramId] ?? '';
                                $displayStyle = in_array($paramId, $exerciseDetails['track_by']) ? 'display:block;' : 'display:none;';
                            ?>
                                <input type="number" step="<?= $step ?>" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[<?= $exIndex ?>][sets][<?= $setIndex ?>][<?= $paramId ?>]" value="<?= $value ?>" placeholder="<?= $paramName ?>" style="<?= $displayStyle ?>">
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-body-tertiary">
                    <button type="button" class="btn btn-sm btn-outline-primary add-set"><i class="bi bi-plus me-1"></i>Dodaj serię</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="workout-actions-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <button type="button" id="add-exercise-btn" class="btn btn-secondary"><i class="bi bi-plus-lg me-1"></i> Dodaj ćwiczenie</button>
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save me-2"></i> Zapisz Zmiany</button>
        </div>
    </div>
</form>

<!-- Szablony dla JS - identyczne jak w log_workout.php -->
<template id="exercise-template">
    <div class="card exercise-block-v2" data-exercise-index="{exercise_index}">
         <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
        <div class="card-header d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <select name="exercises[{exercise_index}][exercise_id]" class="exercise-select" required>
                    <option value="" disabled selected>-- Zacznij pisać, aby wyszukać --</option>
                    <?php foreach ($allExercises as $exercise): ?>
                        <option value="<?= $exercise['id'] ?>" 
                                data-track-by='<?= json_encode($exercise['track_by']) ?>'
                                data-tags='<?= json_encode($exercise['tags'] ?? []) ?>'
                                data-desc="<?= htmlspecialchars(auto_embed_youtube_videos($exercise['description'] ?? '')) ?>"
                                data-howto="<?= htmlspecialchars(auto_embed_youtube_videos($exercise['howto'] ?? '')) ?>"
                                data-pr-weight="<?= get_exercise_prs($exercise['id'], $userId)['max_weight'] ?>"
                                data-pr-e1rm="<?= get_exercise_prs($exercise['id'], $userId)['e1rm'] ?>">
                            <?= htmlspecialchars($exercise['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pr-display-container small text-muted mt-2"></div>
                <div class="tags-display-container d-flex flex-wrap gap-1 mt-2"></div>
            </div>
            <div class="info-button-container ps-2 pt-4"></div>
        </div>
        <div class="sets-container vstack gap-2 p-3"></div>
        <div class="card-footer bg-body-tertiary">
            <button type="button" class="btn btn-sm btn-outline-primary add-set"><i class="bi bi-plus me-1"></i>Dodaj serię</button>
        </div>
    </div>
</template>

<template id="set-template">
    <div class="set-row input-group">
        <span class="input-group-text series-counter" style="min-width: 80px;">Seria X</span>
        <?php foreach ($allTrackableParams as $param):
            $paramId = htmlspecialchars($param['id']);
            $paramName = htmlspecialchars($param['name']);
            $step = ($paramId === 'weight') ? '0.25' : '1';
        ?>
            <input type="number" step="<?= $step ?>" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[{exercise_index}][sets][{set_index}][<?= $paramId ?>]" placeholder="<?= $paramName ?>" style="display: none;">
        <?php endforeach; ?>
        <button type="button" class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
    </div>
</template>

<script>
    // Przekazanie danych do JS, tak jak w log_workout.php
    window.APP_DATA = { 
        tagMap: <?= json_encode($tagMap) ?>,
        trackableParams: <?= json_encode($allTrackableParams) ?>
    };
    // Skrypt do inicjalizacji TomSelect na już istniejących elementach
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.exercise-select').forEach(function(select) {
            if (select.tomselect) return; // Już zainicjowany
            new TomSelect(select, { create: false, sortField: { field: "text", direction: "asc" } });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="assets/js/app.js" type="module"></script>