<?php
// Plik: koks/log_workout.php

// === KROK 1: Logika formularza i sesji (NOWA WERSJA) ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';

$userId = $_SESSION['user_id'];
$errorMessage = '';
$allTrackableParams = get_all_trackable_params(); 
$sessionFilePath = 'data/solo_sessions/solo_' . $userId . '.json';
$activePlan = null;
$workoutDate = null;
$workoutNotes = null;

// Sprawdź, czy istnieje aktywna sesja treningu solo
if (file_exists($sessionFilePath)) {
    $activePlanData = json_decode(file_get_contents($sessionFilePath), true);
    if ($activePlanData) {
        // Wczytujemy dane z pliku sesji, a nie z sesji PHP
        $activePlan = $activePlanData['plan'];
        $workoutDate = $activePlanData['date'];
        $workoutNotes = $activePlanData['notes'];
    }
}

// === KROK 2: Logika zapisywania formularza (WERSJA Z OSTATECZNĄ POPRAWKĄ) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $notes = $_POST['notes'] ?? '';
    
    $postedExercises = isset($_POST['exercises']) ? array_values($_POST['exercises']) : [];
    
    $newWorkout = [
        'workout_id' => 'w_' . date('YmdHis') . '_' . bin2hex(random_bytes(2)),
        'date' => $date,
        'notes' => htmlspecialchars($notes),
        'exercises' => [],
        'interactions' => ['likes' => [], 'comments' => []]
    ];

    if (!empty($postedExercises)) {
        foreach ($postedExercises as $ex) {
            $sets = isset($ex['sets']) ? array_values($ex['sets']) : [];

            if (!empty($ex['exercise_id']) && !empty($sets)) {
                $exerciseData = ['exercise_id' => (int)$ex['exercise_id'], 'sets' => []];
                
                foreach ($sets as $set) {
                    $isPerformedFromPlan = isset($set['performed']) && $set['performed'] === '1';
                    $isAdHocSet = !isset($set['performed']);

                    if ($isPerformedFromPlan || $isAdHocSet) {
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
                }
                if (!empty($exerciseData['sets'])) {
                    $newWorkout['exercises'][] = $exerciseData;
                }
            }
        }
    }

    if (!empty($newWorkout['exercises'])) {
        $allWorkouts = get_user_workouts($userId);
        $allWorkouts[] = $newWorkout;
        if (save_user_workouts($userId, $allWorkouts)) {
            // Po pomyślnym zapisie, usuń plik sesji
            if (file_exists($sessionFilePath)) {
                unlink($sessionFilePath);
            }
            header('Location: workout_summary.php?id=' . $newWorkout['workout_id']);
            exit();
        } else {
            $errorMessage = "Wystąpił błąd podczas zapisywania treningu.";
        }
    } else {
        $errorMessage = "Nie zapisano żadnych wykonanych serii. Trening nie został dodany.";
    }
}

// KROK 3: Przygotowanie danych dla widoku
$pageTitle = $activePlan ? 'Kontynuuj Trening' : 'Nowy Trening';
require_once 'includes/header.php';

echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">';

$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, null, 'id');

$allTagGroups = get_all_tags();
$tagMap = [];
$groupMap = [];
foreach ($allTagGroups as $group) {
    $groupMap[$group['group_id']] = ['name' => $group['group_name'], 'color' => $group['color']];
    foreach ($group['tags'] as $tag) {
        $tagMap[$tag['id']] = [ 'name' => $tag['name'], 'color' => $group['color'] ?? 'secondary', 'group_id' => $group['group_id'] ];
    }
}
$groupMap['inne'] = ['name' => 'Inne', 'color' => 'secondary']; 

$groupedPlanExercises = [];
if ($activePlan && !empty($activePlan['exercises'])) {
    foreach ($activePlan['exercises'] as $exerciseInPlan) {
        $exDetails = $exerciseMap[$exerciseInPlan['exercise_id']] ?? null;
        if (!$exDetails) continue;
        $firstTagId = $exDetails['tags'][0] ?? null;
        $groupId = $tagMap[$firstTagId]['group_id'] ?? 'inne';
        if (!isset($groupedPlanExercises[$groupId])) {
            $groupedPlanExercises[$groupId] = [];
        }
        $groupedPlanExercises[$groupId][] = $exerciseInPlan;
    }
}
?>
<!-- KROK 4: Wyświetlanie HTML -->
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<form method="POST" action="log_workout.php" id="workout-form">
    <div class="card mb-4">
        <div class="card-header bg-body-secondary d-flex justify-content-between align-items-center">
            <h1 class="h4 mb-0"><?= $activePlan ? htmlspecialchars($activePlan['plan_name']) : 'Nowy Trening (Ad-hoc)' ?></h1>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6"><label for="date" class="form-label">Data</label><input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($workoutDate ?? date('Y-m-d')) ?>" required></div>
                <div class="col-md-6"><label for="notes" class="form-label">Notatki</label><input type="text" id="notes" name="notes" class="form-control" value="<?= htmlspecialchars($workoutNotes ?? '') ?>" placeholder="np. Dobra energia, progres w przysiadzie"></div>
            </div>
        </div>
    </div>
    
    <div id="exercises-container" class="vstack gap-4">
    <?php if (!empty($groupedPlanExercises)): ?>
        <?php foreach ($groupedPlanExercises as $groupId => $exercisesInGroup): ?>
            <div class="exercise-group border-start border-4 rounded p-3 ps-4" style="--bs-border-color: var(--bs-<?= $groupMap[$groupId]['color'] ?? 'secondary' ?>);">
                <h3 class="h5 mb-3 text-<?= $groupMap[$groupId]['color'] ?? 'secondary' ?>"><?= htmlspecialchars($groupMap[$groupId]['name'] ?? 'Inne') ?></h3>
                <div class="vstack gap-4">
                <?php foreach ($exercisesInGroup as $exIndex => $exerciseInPlan): ?>
                    <?php 
                        $exerciseDetails = $exerciseMap[$exerciseInPlan['exercise_id']] ?? null;
                        if (!$exerciseDetails) continue;
                        $lastPerformance = get_last_performance($exerciseInPlan['exercise_id'], $userId);
                        $exercisePRs = get_exercise_prs($exerciseInPlan['exercise_id'], $userId);
                    ?>
                    <div class="card exercise-block-v2" data-exercise-name="<?= strtolower(htmlspecialchars($exerciseDetails['name'])) ?>" data-group-id="<?= htmlspecialchars($groupId) ?>">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1 fs-5"><?= htmlspecialchars($exerciseDetails['name']) ?></h5>
                                <div class="small text-muted">
                                    <i class="bi bi-trophy-fill text-warning me-1"></i>PR: <strong><?= $exercisePRs['max_weight'] > 0 ? $exercisePRs['max_weight'] . ' kg' : '-' ?></strong><span class="mx-1">·</span>e1RM: <strong><?= $exercisePRs['e1rm'] > 0 ? $exercisePRs['e1rm'] . ' kg' : '-' ?></strong>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary info-btn" data-bs-toggle="modal" data-bs-target="#exerciseInfoModal" data-bs-name="<?= htmlspecialchars($exerciseDetails['name']) ?>" data-bs-desc="<?= htmlspecialchars(auto_embed_youtube_videos($exerciseDetails['description'] ?? '')) ?>" data-bs-howto="<?= htmlspecialchars(auto_embed_youtube_videos($exerciseDetails['howto'] ?? '')) ?>"><i class="bi bi-info-circle"></i></button>
                        </div>
                        <input type="hidden" name="exercises[<?= $exIndex ?>][exercise_id]" value="<?= $exerciseInPlan['exercise_id'] ?>">
                        <ul class="list-group list-group-flush sets-container">
                            <?php 
                                $paramMap = array_column($allTrackableParams, 'name', 'id');
                                $units = ['weight' => 'kg', 'time' => 's', 'reps' => 'powt.', 'distance' => 'km']; 
                            ?>
                            <?php foreach ($exerciseInPlan['target_sets'] as $setIndex => $targetSet): ?>
                                <li class="list-group-item set-row">
                                    <div class="d-flex align-items-center gap-3">
                                        <button type="button" class="btn btn-outline-secondary check-btn flex-shrink-0"><i class="bi bi-circle"></i></button>
                                        <div class="flex-grow-1">
                                            <span class="fw-bold set-display"><?php $details = []; foreach ($targetSet as $paramId => $value) { if (trim((string)$value) !== '' && isset($paramMap[$paramId])) { $unit = $units[$paramId] ?? ''; $details[] = "{$value} {$unit}"; } } echo implode(' <span class="text-muted">·</span> ', $details); ?></span>
                                            <?php if (isset($lastPerformance[$setIndex])): $lastSet = $lastPerformance[$setIndex]; $lastDetails = []; foreach ($lastSet as $paramId => $value) { if (trim((string)$value) !== '' && isset($paramMap[$paramId])) { $unit = $units[$paramId] ?? ''; $lastDetails[] = "{$value} {$unit}"; } } ?>
                                                <div class="small text-muted fst-italic mt-1"><i class="bi bi-clock-history me-1"></i>Ostatnio: <?= implode(' · ', $lastDetails) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="set-actions d-flex gap-2">
                                            <button type="button" class="btn btn-outline-primary edit-set-btn flex-shrink-0"><i class="bi bi-pencil"></i></button>
                                            <?php if (in_array('time', $exerciseDetails['track_by'])): ?>
                                            <button type="button" class="btn btn-outline-secondary start-timer-btn flex-shrink-0" data-state="idle" data-target-time="<?= htmlspecialchars($targetSet['time'] ?? '0') ?>" title="Uruchom stoper"><i class="bi bi-stopwatch"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="set-edit-form">
                                        <div class="input-group">
                                            <?php $trackBy = $exerciseDetails['track_by'] ?? []; foreach ($allTrackableParams as $param): $paramId = htmlspecialchars($param['id']); $paramName = htmlspecialchars($param['name']); $step = ($paramId === 'weight') ? '0.25' : '1'; $value = htmlspecialchars($targetSet[$paramId] ?? ''); $displayStyle = in_array($paramId, $trackBy) ? 'display: block;' : 'display: none;'; ?>
                                                <input type="number" step="<?= $step ?>" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[<?= $exIndex ?>][sets][<?= $setIndex ?>][<?= $paramId ?>]" value="<?= $value ?>" placeholder="<?= $paramName ?>" style="<?= $displayStyle ?>">
                                            <?php endforeach; ?>
                                            <button type="button" class="btn btn-success save-set-btn" title="Zatwierdź zmiany"><i class="bi bi-check-lg"></i></button><button type="button" class="btn btn-secondary cancel-edit-btn" title="Anuluj edycję"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    </div>
                                    <input type="hidden" class="performed-flag" name="exercises[<?= $exIndex ?>][sets][<?= $setIndex ?>][performed]" value="0">
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif (!$activePlan): ?>
       <div id="initial-prompt" class="text-center py-5 border bg-body-tertiary rounded-3"><h3 class="text-muted">Twój trening jest pusty</h3><p class="lead text-muted">Zacznij od dodania swojego pierwszego ćwiczenia.</p><button type="button" id="add-first-exercise-btn" class="btn btn-primary btn-lg"><i class="bi bi-plus-circle me-2"></i>Dodaj pierwsze ćwiczenie</button></div>
    <?php endif; ?>
    </div>

    <div class="workout-actions-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <button type="button" id="add-exercise-btn" class="btn btn-lg <?= $activePlan ? 'btn-outline-secondary' : 'btn-secondary' ?>">
                <i class="bi bi-plus-lg me-1"></i> Dodaj ćwiczenie
            </button>
            <!-- === NOWY ELEMENT: Wskaźnik statusu Autosave === -->
            <div id="autosave-status" class="small text-muted"></div>
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-2"></i> Zakończ i Zapisz Trening</button>
        </div>
    </div>
</form>

<template id="exercise-template">
    <div class="card exercise-block-v2" data-exercise-index="{exercise_index}">
        <button type="button" class="btn-close remove-exercise" aria-label="Usuń ćwiczenie" style="position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;"></button>
        <div class="card-header d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <select name="exercises[{exercise_index}][exercise_id]" class="exercise-select" required>
                    <option value="" disabled selected>-- Zacznij pisać, aby wyszukać --</option>
                    <?php foreach ($allExercises as $exercise): $prs = get_exercise_prs($exercise['id'], $userId); ?>
                        <option value="<?= $exercise['id'] ?>" 
                                data-track-by='<?= json_encode($exercise['track_by']) ?>'
                                data-tags='<?= json_encode($exercise['tags'] ?? []) ?>'
                                data-desc="<?= htmlspecialchars(auto_embed_youtube_videos($exercise['description'] ?? '')) ?>"
                                data-howto="<?= htmlspecialchars(auto_embed_youtube_videos($exercise['howto'] ?? '')) ?>"
                                data-pr-weight="<?= $prs['max_weight'] ?>"
                                data-pr-e1rm="<?= $prs['e1rm'] ?>">
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
            <?php if ($paramId === 'time'): ?>
                <div class="set-field-wrapper set-field-time" style="display: none; flex-grow: 1;">
                     <input type="number" class="form-control" name="exercises[{exercise_index}][sets][{set_index}][<?= $paramId ?>]" placeholder="<?= $paramName ?>" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                     <button type="button" class="btn btn-outline-secondary start-timer-btn" data-state="idle" data-target-time="0" title="Uruchom stoper"><i class="bi bi-stopwatch"></i></button>
                </div>
            <?php else: ?>
                <input type="number" step="<?= $step ?>" class="form-control set-field-wrapper set-field-<?= $paramId ?>" name="exercises[{exercise_index}][sets][{set_index}][<?= $paramId ?>]" placeholder="<?= $paramName ?>" style="display: none;">
            <?php endif; ?>
        <?php endforeach; ?>
        <button type="button" class="btn btn-outline-danger remove-set" type="button"><i class="bi bi-trash3"></i></button>
    </div>
</template>

<script>
    window.APP_DATA = { 
        tagMap: <?= json_encode($tagMap) ?>,
        trackableParams: <?= json_encode($allTrackableParams) ?>
    };
</script>

<?php require_once 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script src="assets/js/app.js" type="module"></script>