<?php
$pageTitle = 'Trening Wspólny';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'];
$liveWorkoutId = $_SESSION['active_live_workout_id'] ?? null;

// Logika dołączania do istniejącego treningu (jeśli sesja wygasła)
if (!$liveWorkoutId) {
    $liveWorkoutFiles = glob('data/live_workouts/lw_*.json');
    foreach ($liveWorkoutFiles as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data['status'] === 'active' && in_array($userId, $data['participants'])) {
            $liveWorkoutId = $data['live_workout_id'];
            $_SESSION['active_live_workout_id'] = $liveWorkoutId;
            break;
        }
    }
}

// Jeśli po sprawdzeniu nadal nie ma ID, przekieruj na panel główny
if (!$liveWorkoutId) {
    header('Location: dashboard.php');
    exit();
}

$liveWorkoutPath = 'data/live_workouts/' . basename($liveWorkoutId) . '.json';
if (!file_exists($liveWorkoutPath)) {
    unset($_SESSION['active_live_workout_id']);
    header('Location: dashboard.php?status=workout_finished');
    exit();
}

$liveWorkout = json_decode(file_get_contents($liveWorkoutPath), true);
$isAdHoc = ($liveWorkout['base_plan']['plan_id'] === 'adhoc');
$allExercises = get_all_exercises();

require_once 'includes/header.php';
// NOWOŚĆ: Dodajemy style dla Tom Select
echo '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">';
?>
<style>
    /* Style dla dolnej nawigacji i płynnego przejścia treści */
    body { padding-bottom: 150px !important; } /* Zapewnia miejsce na dolny pasek i nawigację */
    .mobile-nav {
        position: fixed;
        bottom: 70px; /* Nad paskiem akcji */
        left: 0;
        right: 0;
        background: var(--bs-tertiary-bg);
        border-top: 1px solid var(--bs-border-color);
        display: flex;
        justify-content: space-around;
        padding: 0.5rem 0;
        z-index: 1025;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    .mobile-nav .nav-link {
        color: var(--bs-secondary-color);
        border-radius: 8px;
        flex-grow: 1;
        text-align: center;
        max-width: 120px;
        transition: all 0.2s ease;
    }
    .mobile-nav .nav-link.active {
        background-color: var(--bs-primary);
        color: var(--bs-white);
        transform: translateY(-3px);
    }
    .participant-view {
        display: none;
    }
    .participant-view.active {
        display: block;
    }
    .series-counter {
        min-width: 40px;
        text-align: center;
    }
</style>

<div id="live-workout-container" data-live-id="<?= htmlspecialchars($liveWorkoutId) ?>" data-user-id="<?= htmlspecialchars($userId) ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-0"><?= htmlspecialchars($liveWorkout['base_plan']['plan_name']) ?></h1>
            <span class="badge bg-danger">LIVE</span>
        </div>
        <div class="btn-group">
            <button id="cancel-workout-btn" type="button" class="btn btn-outline-secondary" title="Anuluj i usuń trening">
                <i class="bi bi-x-lg"></i> <span class="d-none d-sm-inline">Anuluj</span>
            </button>
            <button id="finish-workout-btn" type="button" class="btn btn-danger" title="Zakończ i zapisz trening wszystkim">
                <i class="bi bi-flag-fill"></i> <span class="d-none d-sm-inline">Zakończ</span>
            </button>
        </div>
    </div>
    
    <div id="main-content-area">
        <!-- Widoki dla poszczególnych uczestników będą renderowane tutaj przez JS -->
        <div class="text-center p-5">
             <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Ładowanie treningu...</span>
            </div>
            <p class="mt-3 text-muted">Ładowanie danych treningu...</p>
        </div>
    </div>

    <!-- DOLNA NAWIGACJA MOBILNA -->
    <nav class="mobile-nav" id="participant-nav">
        <!-- Linki nawigacyjne będą renderowane tutaj przez JS -->
    </nav>

    <!-- Pasek akcji na samym dole -->
    <div class="workout-actions-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <button id="add-live-exercise-btn" type="button" class="btn btn-secondary">
                <i class="bi bi-plus-lg me-1"></i> <span class="d-none d-sm-inline">Dodaj ćwiczenie</span>
            </button>
            <div id="progress-indicators" class="d-flex align-items-center gap-3">
                <!-- Wskaźniki progresu będą tutaj -->
            </div>
        </div>
    </div>
</div>

<!-- ================ SZABLONY JS ================ -->
<template id="participant-view-template">
    <div class="participant-view" id="view-{pId}">
        <div class="exercises-container vstack gap-4">
            <!-- Bloki ćwiczeń dla tego uczestnika -->
        </div>
    </div>
</template>

<template id="exercise-block-template">
    <div class="card exercise-block-v2" data-ex-index="{exIndex}" data-exercise-id="{exId}">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fs-5 text-truncate">{exName}</h5>
            <button type="button" class="btn btn-sm btn-outline-primary add-set-btn"><i class="bi bi-plus"></i> Dodaj Serię</button>
        </div>
        <div class="list-group list-group-flush sets-container">
            <!-- Wiersze serii -->
        </div>
    </div>
</template>

<template id="set-row-template">
    <div class="list-group-item set-row" data-p-id="{pId}" data-ex-index="{exIndex}" data-set-index="{setIndex}">
        <div class="d-flex align-items-center gap-2 gap-sm-3">
            <span class="series-counter fw-bold">S{setNum}</span>
            <div class="flex-grow-1 row gx-2">
                <div class="col"><input type="number" step="1" class="form-control form-control-sm reps-input" placeholder="Powt." {disabled} value="{reps}"></div>
                <div class="col"><input type="number" step="0.25" class="form-control form-control-sm weight-input" placeholder="Kg" {disabled} value="{weight}"></div>
            </div>
            <button class="btn btn-lg {btnClass} check-btn rounded-circle" style="width: 48px; height: 48px;" {disabled}><i class="bi {icon}"></i></button>
        </div>
    </div>
</template>

<template id="participant-nav-link-template">
    <a class="nav-link d-flex flex-column align-items-center justify-content-center" href="#" data-p-id="{pId}">
        <i class="bi {pIcon} fs-4"></i>
        <div class="small text-truncate" style="font-size: 0.7rem;">{pName}</div>
    </a>
</template>

<template id="progress-indicator-template">
    <div class="d-flex align-items-center gap-2 text-white-50" title="Postęp {pName}">
        <i class="bi {pIcon}"></i>
        <span class="fw-bold text-white">{completed}/{total}</span>
    </div>
</template>

<!-- ZMIANA: Ulepszony szablon do dodawania ćwiczeń ad-hoc -->
<template id="new-exercise-adhoc-template">
    <div class="card exercise-block-v2 new-exercise-prompt" data-exercise-index="{exIndex}">
        <div class="card-body">
            <label class="form-label">Wybierz ćwiczenie do dodania:</label>
            <select class="new-exercise-select">
                <!-- Opcje zostaną dodane przez JS -->
            </select>
        </div>
    </div>
</template>

<?php require_once 'includes/footer.php'; ?>
<!-- NOWOŚĆ: Skrypt Tom Select -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    // Przekazanie listy ćwiczeń do JS w bezpieczny sposób
    window.allExercisesData = <?= json_encode($allExercises); ?>;
</script>
<script src="assets/js/app.js" type="module"></script>