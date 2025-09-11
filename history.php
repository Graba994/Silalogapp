<?php
$pageTitle = 'Historia Treningów';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
$allWorkouts = get_user_workouts($userId);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');

// --- FILTROWANIE I WYSZUKIWANIE ---
$filteredWorkouts = $allWorkouts;
$filterExerciseId = $_GET['exercise_id'] ?? '';
$filterStartDate = $_GET['start_date'] ?? '';
$filterEndDate = $_GET['end_date'] ?? '';

if ($filterExerciseId) {
    $filteredWorkouts = array_filter($filteredWorkouts, function($workout) use ($filterExerciseId) {
        foreach ($workout['exercises'] as $exercise) {
            if ($exercise['exercise_id'] == $filterExerciseId) {
                return true;
            }
        }
        return false;
    });
}
if ($filterStartDate) {
    $filteredWorkouts = array_filter($filteredWorkouts, fn($w) => strtotime($w['date']) >= strtotime($filterStartDate));
}
if ($filterEndDate) {
    $filteredWorkouts = array_filter($filteredWorkouts, fn($w) => strtotime($w['date']) <= strtotime($filterEndDate));
}

// Sortujemy treningi od najnowszego do najstarszego
usort($filteredWorkouts, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

// --- PAGINACJA ---
$perPage = 10;
$totalWorkouts = count($filteredWorkouts);
$totalPages = ceil($totalWorkouts / $perPage);
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages)); // Upewnij się, że strona jest w zakresie
$offset = ($currentPage - 1) * $perPage;
$paginatedWorkouts = array_slice($filteredWorkouts, $offset, $perPage);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Historia Treningów</h1>
    <a href="log_workout.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Dodaj Nowy Trening</a>
</div>

<!-- NOWY PANEL FILTRÓW -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="history.php">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label for="exercise_id" class="form-label">Pokaż treningi z ćwiczeniem:</label>
                    <select id="exercise_id" name="exercise_id" class="form-select">
                        <option value="">-- Wszystkie ćwiczenia --</option>
                        <?php foreach ($allExercises as $exercise): ?>
                            <option value="<?= $exercise['id'] ?>" <?= ($filterExerciseId == $exercise['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($exercise['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="start_date" class="form-label">Od:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($filterStartDate) ?>">
                </div>
                <div class="col-md-3 col-lg-2">
                    <label for="end_date" class="form-label">Do:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($filterEndDate) ?>">
                </div>
                <div class="col-md-3 col-lg-2 d-grid">
                    <button type="submit" class="btn btn-info"><i class="bi bi-filter"></i> Filtruj</button>
                </div>
                 <div class="col-md-3 col-lg-2 d-grid">
                    <a href="history.php" class="btn btn-outline-secondary">Wyczyść</a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($paginatedWorkouts)): ?>
    <div class="card text-center p-5">
        <div class="card-body">
            <h3 class="text-muted">Nie znaleziono treningów pasujących do kryteriów.</h3>
            <p class="lead text-muted">Spróbuj zmienić filtry lub <a href="log_workout.php">dodaj nowy trening</a>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="accordion" id="workoutHistoryAccordion">
        <?php foreach ($paginatedWorkouts as $index => $workout): ?>
            <?php
                // Obliczanie statystyk sesji
                $sessionVolume = 0;
                $sessionSets = 0;
                foreach ($workout['exercises'] as $exercise) {
                    foreach ($exercise['sets'] as $set) {
                        $sessionSets++;
                        $sessionVolume += ($set['weight'] ?? 0) * ($set['reps'] ?? 0);
                    }
                }
            ?>
            <div class="accordion-item shadow-sm mb-2">
                <h2 class="accordion-header" id="heading-<?= $workout['workout_id'] ?>">
                    <button class="accordion-button fs-5 <?= $index > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $workout['workout_id'] ?>">
                        <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                            <span class="fw-bold">
                                <i class="bi bi-calendar3 me-2 text-primary"></i>
                                Trening z dnia: <?= htmlspecialchars($workout['date']) ?>
                            </span>
                            <div class="d-none d-md-flex gap-4">
                                <span class="badge bg-secondary rounded-pill p-2"><i class="bi bi-layers-fill me-1"></i> <?= $sessionSets ?> serii</span>
                                <span class="badge bg-dark rounded-pill p-2"><i class="bi bi-truck me-1"></i> <?= number_format($sessionVolume, 0, ',', ' ') ?> kg</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="collapse-<?= $workout['workout_id'] ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#workoutHistoryAccordion">
                    <div class="accordion-body">
                        
                        <?php if (!empty($workout['notes'])): ?>
                            <blockquote class="blockquote alert alert-light border-start border-4 border-info">
                                <p class="mb-0 fst-italic"> <?= htmlspecialchars($workout['notes']) ?></p>
                            </blockquote>
                        <?php endif; ?>

                        <?php foreach ($workout['exercises'] as $exercise): ?>
                            <div class="mb-3">
                                <h6 class="border-bottom pb-2 mb-2"><?= htmlspecialchars($exerciseMap[$exercise['exercise_id']] ?? 'Nieznane ćwiczenie') ?> <?php $totalReps = array_sum(array_column($exercise['sets'], 'reps')); ?>
            <span class="badge bg-info fw-normal ms-2"><?= $totalReps ?> powt. łącznie</span></h6>
                                
                                <!-- ================== POCZĄTEK POPRAWIONEGO BLOKU ================== -->
                                <div class="sets-grid">
                                    <!-- Nagłówek siatki (widoczny tylko dla screen readerów, ale pomaga w strukturze) -->
                                    <div class="row fw-bold text-muted small border-bottom mb-1 d-none d-sm-flex">
                                        <div class="col-3">Seria</div>
                                        <div class="col-3 text-center">Powtórzenia</div>
                                        <div class="col-3 text-center">Ciężar</div>
                                        <div class="col-3 text-center">Czas</div>
                                    </div>
                                    
                                    <?php foreach ($exercise['sets'] as $setIndex => $set): ?>
                                        <div class="row py-2 border-bottom border-light align-items-center">
                                            <div class="col-sm-3 fw-bold">
                                                Seria <?= $setIndex + 1 ?>
                                            </div>
                                            <div class="col-4 col-sm-3 text-sm-center">
                                                <?php if (isset($set['reps'])): ?>
                                                    <i class="bi bi-arrow-repeat text-primary me-1 d-inline d-sm-none"></i>
                                                    <span><?= htmlspecialchars($set['reps']) ?></span>
                                                    <span class="text-muted small d-none d-sm-inline"> powt.</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-4 col-sm-3 text-sm-center">
                                                <?php if (isset($set['weight'])): ?>
                                                    <i class="bi bi-barbell text-danger me-1 d-inline d-sm-none"></i>
                                                    <span><?= htmlspecialchars($set['weight']) ?></span>
                                                    <span class="text-muted small d-none d-sm-inline"> kg</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-4 col-sm-3 text-sm-center">
                                                <?php if (isset($set['time'])): ?>
                                                    <i class="bi bi-stopwatch text-success me-1 d-inline d-sm-none"></i>
                                                    <span><?= htmlspecialchars($set['time']) ?></span>
                                                    <span class="text-muted small d-none d-sm-inline"> sek.</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <!-- =================== KONIEC POPRAWIONEGO BLOKU =================== -->

                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="start_workout.php?repeat_workout_id=<?= $workout['workout_id'] ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-play-circle-fill me-1"></i> Uruchom ponownie
                            </a>
                            <a href="workout_summary.php?id=<?= $workout['workout_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye-fill me-1"></i> Zobacz podsumowanie
                            </a>
                                <a href="edit_workout.php?id=<?= $workout['workout_id'] ?>" class="btn btn-sm btn-outline-warning"> <!-- NOWY PRZYCISK -->
        <i class="bi bi-pencil-square me-1"></i> Edytuj
    </a>
    <button type="button" class="btn btn-sm btn-outline-danger delete-workout-btn"
            data-action="delete_workout.php"
            data-workout-id="<?= htmlspecialchars($workout['workout_id']) ?>">
        <i class="bi bi-trash3-fill me-1"></i> Usuń
    </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginacja -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Nawigacja po historii">
        <ul class="pagination justify-content-center mt-4">
            <?php
            // Zachowaj parametry filtra w linkach paginacji
            $queryParams = http_build_query(['exercise_id' => $filterExerciseId, 'start_date' => $filterStartDate, 'end_date' => $filterEndDate]);
            ?>
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage - 1 ?>&<?= $queryParams ?>">Poprzednia</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&<?= $queryParams ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage + 1 ?>&<?= $queryParams ?>">Następna</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>