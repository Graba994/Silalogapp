<?php
$pageTitle = 'Podsumowanie Treningu';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// === 1. POBRANIE DANYCH (bez zmian) ===
$userId = $_SESSION['user_id'];
$workoutIdToShow = $_GET['id'] ?? null;

if (!$workoutIdToShow) {
    echo "<div class='alert alert-danger'>Nie podano ID treningu.</div>";
    require_once 'includes/footer.php';
    exit();
}

$allWorkouts = get_user_workouts($userId);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');

// === 2. ZNALEZIENIE BIEŻĄCEGO I POPRZEDNICH TRENINGÓW (bez zmian) ===
$currentWorkout = null;
foreach ($allWorkouts as $workout) {
    if ($workout['workout_id'] === $workoutIdToShow) {
        $currentWorkout = $workout;
        break;
    }
}

if (!$currentWorkout) {
    echo "<div class='alert alert-danger'>Nie znaleziono treningu o podanym ID.</div>";
    require_once 'includes/footer.php';
    exit();
}

$previousWorkouts = [];
foreach ($allWorkouts as $workout) {
    if ($workout['workout_id'] !== $currentWorkout['workout_id'] && strtotime($workout['date']) <= strtotime($currentWorkout['date'])) {
        $previousWorkouts[] = $workout;
    }
}

// === 3. OBLICZENIE STARYCH REKORDÓW (PRs) (bez zmian) ===
$oldPRs = [];
foreach ($previousWorkouts as $workout) {
    foreach ($workout['exercises'] as $exercise) {
        $exId = $exercise['exercise_id'];
        if (!isset($oldPRs[$exId])) {
            $oldPRs[$exId] = ['max_weight' => 0, 'max_reps' => 0, 'max_time' => 0, 'e1rm' => 0];
        }
        foreach ($exercise['sets'] as $set) {
            $weight = $set['weight'] ?? 0;
            $reps = $set['reps'] ?? 0;
            $time = $set['time'] ?? 0;
            if ($weight > $oldPRs[$exId]['max_weight']) $oldPRs[$exId]['max_weight'] = $weight;
            if ($reps > $oldPRs[$exId]['max_reps']) $oldPRs[$exId]['max_reps'] = $reps;
            if ($time > $oldPRs[$exId]['max_time']) $oldPRs[$exId]['max_time'] = $time;
            if ($reps > 1 && $reps < 11 && $weight > 0) {
                $e1rm = $weight / (1.0278 - (0.0278 * $reps));
                if ($e1rm > $oldPRs[$exId]['e1rm']) $oldPRs[$exId]['e1rm'] = round($e1rm, 1);
            } elseif ($reps === 1 && $weight > $oldPRs[$exId]['e1rm']) {
                $oldPRs[$exId]['e1rm'] = $weight;
            }
        }
    }
}

// === 4. PORÓWNANIE I ZNALEZIENIE NOWYCH REKORDÓW (ZINTEGROWANA NOWA LOGIKA) ===
$newPRs = [];
$totalVolume = 0;
$totalSets = 0;
foreach ($currentWorkout['exercises'] as $exercise) {
    $exId = $exercise['exercise_id'];
    $currentTotalRepsForExercise = 0; // Zmienna do zliczania powtórzeń w tym ćwiczeniu

    // **NOWOŚĆ**: Pobierz poprzedni rekord dzienny, WYKLUCZAJĄC bieżący trening
    $previousDailyRepPR = get_daily_rep_PR($userId, $exId, $currentWorkout['workout_id']);

    foreach ($exercise['sets'] as $set) {
        $totalSets++;
        $weight = $set['weight'] ?? 0;
        $reps = $set['reps'] ?? 0;
        $time = $set['time'] ?? 0;
        $totalVolume += $weight * $reps;
        $currentTotalRepsForExercise += $reps; // Sumujemy powtórzenia

        $oldPrForEx = $oldPRs[$exId] ?? ['max_weight' => 0, 'max_reps' => 0, 'max_time' => 0, 'e1rm' => 0];

        // Twoja istniejąca logika sprawdzania PRów (bez zmian)
        if ($weight > $oldPrForEx['max_weight']) {
            $newPRs[$exId]['max_weight'] = ['old' => $oldPrForEx['max_weight'], 'new' => $weight];
        }
        if ($reps > $oldPrForEx['max_reps']) {
            $newPRs[$exId]['max_reps'] = ['old' => $oldPrForEx['max_reps'], 'new' => $reps];
        }
        if ($time > $oldPrForEx['max_time']) {
            $newPRs[$exId]['max_time'] = ['old' => $oldPrForEx['max_time'], 'new' => $time];
        }
        if ($reps > 0 && $weight > 0) {
             $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1);
             if ($e1rm > $oldPrForEx['e1rm']) {
                $newPRs[$exId]['e1rm'] = ['old' => $oldPrForEx['e1rm'], 'new' => $e1rm];
             }
        }
    }
    
    // **NOWOŚĆ**: Sprawdź, czy padł nowy dzienny rekord powtórzeń po zsumowaniu wszystkich serii
    if ($currentTotalRepsForExercise > $previousDailyRepPR) {
        $newPRs[$exId]['daily_reps'] = ['old' => $previousDailyRepPR, 'new' => $currentTotalRepsForExercise];
    }
}
?>

<div class="container text-center py-5">
    <h1 class="display-4 fw-bold">Dobra robota, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
    <p class="lead text-muted">Trening z dnia <?= htmlspecialchars($currentWorkout['date']) ?> został zapisany.</p>
</div>

<div class="row g-4 justify-content-center">
    <!-- === KARTA Z NOWYMI REKORDAMI (jeśli są) === -->
    <?php if (!empty($newPRs)): ?>
    <div class="col-lg-8">
        <div class="card border-success shadow-lg">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Nowe Rekordy! Gratulacje!</h4>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($newPRs as $exId => $prs): ?>
                    <li class="list-group-item">
                        <h6 class="mb-2"><?= htmlspecialchars($exerciseMap[$exId] ?? 'Nieznane ćwiczenie') ?></h6>
                        <ul class="list-unstyled">
                            <!-- **NOWY BLOK**: Wyświetlanie rekordu dziennych powtórzeń -->
                            <?php if (isset($prs['daily_reps'])): ?>
                                <li class="d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-award-fill text-muted me-2"></i>Rekord Dzienny Powtórzeń</span>
                                    <span>
                                        <span class="text-muted me-2"><s><?= $prs['daily_reps']['old'] ?></s></span>
                                        <strong class="text-success"><?= $prs['daily_reps']['new'] ?></strong>
                                        <i class="bi bi-arrow-up-circle-fill text-success ms-1"></i>
                                    </span>
                                </li>
                            <?php endif; ?>

                            <!-- Twoje istniejące bloki wyświetlania PRów (bez zmian) -->
                            <?php if (isset($prs['max_weight'])): ?>
                                <li class="d-flex justify-content-between align-items-center mt-1">
                                    <span><i class="bi bi-barbell text-muted me-2"></i>Maksymalny ciężar</span>
                                    <span><span class="text-muted me-2"><s><?= $prs['max_weight']['old'] ?> kg</s></span><strong class="text-success"><?= $prs['max_weight']['new'] ?> kg</strong><i class="bi bi-arrow-up-circle-fill text-success ms-1"></i></span>
                                </li>
                            <?php endif; ?>
                            <?php if (isset($prs['max_reps'])): ?>
                                <li class="d-flex justify-content-between align-items-center mt-1">
                                    <span><i class="bi bi-arrow-repeat text-muted me-2"></i>Maksymalnie powtórzeń</span>
                                    <span><span class="text-muted me-2"><s><?= $prs['max_reps']['old'] ?></s></span><strong class="text-success"><?= $prs['max_reps']['new'] ?></strong><i class="bi bi-arrow-up-circle-fill text-success ms-1"></i></span>
                                </li>
                            <?php endif; ?>
                            <?php if (isset($prs['max_time'])): ?>
                                <li class="d-flex justify-content-between align-items-center mt-1">
                                    <span><i class="bi bi-stopwatch-fill text-muted me-2"></i>Maksymalny czas</span>
                                    <span><span class="text-muted me-2"><s><?= $prs['max_time']['old'] ?> s</s></span><strong class="text-success"><?= $prs['max_time']['new'] ?> s</strong><i class="bi bi-arrow-up-circle-fill text-success ms-1"></i></span>
                                </li>
                            <?php endif; ?>
                            <?php if (isset($prs['e1rm'])): ?>
                                <li class="d-flex justify-content-between align-items-center mt-1">
                                    <span><i class="bi bi-calculator-fill text-muted me-2"></i>Szacowany 1RM</span>
                                    <span><span class="text-muted me-2"><s><?= $prs['e1rm']['old'] ?> kg</s></span><strong class="text-success"><?= $prs['e1rm']['new'] ?> kg</strong><i class="bi bi-arrow-up-circle-fill text-success ms-1"></i></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <!-- === KARTA Z PODSUMOWANIEM TRENINGU (ZAKTUALIZOWANA) === -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-dark text-white"><h4 class="mb-0"><i class="bi bi-clipboard-data-fill me-2"></i>Podsumowanie Treningu</h4></div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4"><div class="h5"><?= count($currentWorkout['exercises']) ?></div><div class="text-muted small">Ćwiczeń</div></div>
                    <div class="col-4"><div class="h5"><?= $totalSets ?></div><div class="text-muted small">Serii</div></div>
                    <div class="col-4"><div class="h5"><?= number_format($totalVolume, 0, ',', ' ') ?> kg</div><div class="text-muted small">Objętości</div></div>
                </div>
                <hr>
                 <ul class="list-group list-group-flush">
                    <?php foreach ($currentWorkout['exercises'] as $exercise): ?>
                        <li class="list-group-item">
                            <!-- **NOWOŚĆ**: Suma powtórzeń dodana obok nazwy ćwiczenia -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold"><?= htmlspecialchars($exerciseMap[$exercise['exercise_id']] ?? 'Nieznane ćwiczenie') ?></span>
                                <?php $totalRepsForExercise = array_sum(array_column($exercise['sets'], 'reps')); ?>
                                <span class="badge bg-primary rounded-pill">Łącznie: <?= $totalRepsForExercise ?> powt.</span>
                            </div>
                            <ul class="list-unstyled mt-2 mb-0 ps-3">
                                <?php foreach ($exercise['sets'] as $index => $set): ?>
                                    <li class="text-muted">
                                        <small>Seria <?= $index + 1 ?>:
                                        <?php
                                            $details = [];
                                            if (isset($set['reps'])) $details[] = "<strong>{$set['reps']}</strong> powt.";
                                            if (isset($set['weight'])) $details[] = "<strong>{$set['weight']}</strong> kg";
                                            if (isset($set['time'])) $details[] = "<strong>{$set['time']}</strong> sek.";
                                            echo implode(' <span class="text-black-50 mx-1">×</span> ', $details);
                                        ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
             <div class="card-footer text-center">
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                    <a href="dashboard.php" class="btn btn-primary px-4">Wróć do Panelu</a>
                    <a href="stats.php" class="btn btn-outline-secondary px-4">Zobacz Statystyki</a>
                    <a href="log_workout.php" class="btn btn-success px-4">Zapisz kolejny trening</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>