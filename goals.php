<?php
$pageTitle = 'Moje Cele Treningowe';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (logika zapisywania bez zmian) ...
}

$allExercises = get_all_exercises();
// Sortujemy ćwiczenia alfabetycznie już na poziomie PHP
usort($allExercises, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$userGoals = get_user_goals($userId);
?>

<?php if ($successMessage): ?>
    <div class="alert alert-success"><?= $successMessage ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Ustaw Swoje Cele</h1>
</div>

<!-- ================== NOWY PANEL FILTRÓW I SORTOWANIA ================== -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-md-8">
                <input type="search" id="goal-search-input" class="form-control" placeholder="Znajdź ćwiczenie po nazwie...">
            </div>
            <div class="col-md-4">
                <select id="goal-sort-select" class="form-select">
                    <option value="name-asc">Sortuj A-Z</option>
                    <option value="name-desc">Sortuj Z-A</option>
                </select>
            </div>
        </div>
    </div>
</div>
<!-- ==================================================================== -->


<form method="POST" id="goals-form">
    <div class="accordion" id="goalsAccordion">
        <?php foreach ($allExercises as $exercise): ?>
            <!-- Dodajemy atrybut data-exercise-name do filtrowania -->
            <div class="accordion-item" data-exercise-name="<?= strtolower(htmlspecialchars($exercise['name'])) ?>">
                <h2 class="accordion-header" id="heading-<?= $exercise['id'] ?>">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $exercise['id'] ?>">
                        <?= htmlspecialchars($exercise['name']) ?>
                    </button>
                </h2>
                <div id="collapse-<?= $exercise['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#goalsAccordion">
                    <div class="accordion-body">
                        <div class="goals-for-exercise-container" data-exercise-id="<?= $exercise['id'] ?>">
                            <?php if (isset($userGoals[$exercise['id']])): ?>
                                <?php foreach ($userGoals[$exercise['id']] as $index => $goal): ?>
                                    <!-- Istniejący cel (bez zmian) -->
                                    <div class="card mb-3 goal-card">
                                        <!-- ... (zawartość karty celu bez zmian) ... -->
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm add-goal-btn" data-track-by='<?= json_encode($exercise['track_by']) ?>'>Dodaj nowy cel dla tego ćwiczenia</button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Komunikat, gdy filtry nic nie znajdą -->
    <div id="no-goals-found" class="col-12 text-center py-5" style="display: none;">
        <h3 class="text-muted">Nie znaleziono ćwiczeń pasujących do kryteriów.</h3>
    </div>

    <div class="workout-actions-bar">
        <div class="container d-flex justify-content-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-bullseye me-2"></i>Zapisz wszystkie cele</button>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>

<template id="goal-card-template">
    <div class="card mb-3 goal-card">
        <div class="card-body">
            <input type="hidden" name="goals[{exercise_id}][{goal_index}][goal_id]" value="">
            <div class="mb-3"><label class="form-label">Nazwa celu</label><input type="text" class="form-control" name="goals[{exercise_id}][{goal_index}][goal_name]" placeholder="np. Cel na siłę"></div>
            <div class="row g-2 target-fields-container"></div>
            <button type="button" class="btn btn-sm btn-outline-danger mt-2 remove-goal-btn">Usuń cel</button>
        </div>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>