<?php
// Plik: koks/coach_view_history.php
require_once 'includes/coach_guard.php'; // Zabezpieczenie na samej górze!
$pageTitle = 'Historia Podopiecznego';
require_once 'includes/functions.php';

// --- Weryfikacja Dostępu ---
$clientId = $_GET['client_id'] ?? null;
if (!$clientId) {
    header('Location: coach_panel.php?error=no_client_id');
    exit();
}

$coachingData = get_coaching_data();
$coachId = $_SESSION['user_id'];
$clientIds = $coachingData[$coachId] ?? [];

// Sprawdź, czy trener faktycznie ma tego klienta na liście
if (!in_array($clientId, $clientIds) && $_SESSION['user_role'] !== 'admin') {
    header('Location: coach_panel.php?error=access_denied');
    exit();
}

// --- Pobieranie Danych ---
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$clientUser = null;
foreach ($allUsers as $user) {
    if ($user['id'] === $clientId) {
        $clientUser = $user;
        break;
    }
}

if (!$clientUser) {
    header('Location: coach_panel.php?error=client_not_found');
    exit();
}

$allWorkouts = get_user_workouts($clientId); // <-- Kluczowa zmiana: pobieramy treningi klienta
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');

// Sortujemy treningi od najnowszego do najstarszego
usort($allWorkouts, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

// Paginacja
$perPage = 10;
$totalWorkouts = count($allWorkouts);
$totalPages = ceil($totalWorkouts / $perPage);
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $perPage;
$paginatedWorkouts = array_slice($allWorkouts, $offset, $perPage);

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Historia Treningów</h1>
        <p class="lead text-muted mb-0">Podopieczny: <strong><?= htmlspecialchars($clientUser['name']) ?></strong></p>
    </div>
    <a href="coach_panel.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left-circle me-2"></i>Wróć do panelu trenera
    </a>
</div>

<?php if (empty($paginatedWorkouts)): ?>
    <div class="card text-center p-5">
        <div class="card-body">
            <h3 class="text-muted">Ten użytkownik nie ma jeszcze żadnych zapisanych treningów.</h3>
        </div>
    </div>
<?php else: ?>
    <div class="accordion" id="workoutHistoryAccordion">
        <?php foreach ($paginatedWorkouts as $index => $workout): ?>
            <?php
                // Obliczanie statystyk sesji
                $sessionVolume = 0; $sessionSets = 0;
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
                            <span class="fw-bold"><i class="bi bi-calendar3 me-2 text-primary"></i>Trening z dnia: <?= htmlspecialchars($workout['date']) ?></span>
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
                                <h6 class="border-bottom pb-2 mb-2"><?= htmlspecialchars($exerciseMap[$exercise['exercise_id']] ?? 'Nieznane ćwiczenie') ?></h6>
                                <div class="sets-grid">
                                    <?php foreach ($exercise['sets'] as $setIndex => $set): ?>
                                    <div class="row py-1">
                                        <div class="col-sm-3 fw-bold">Seria <?= $setIndex + 1 ?></div>
                                        <div class="col-9 col-sm-9">
                                        <?php
                                            $details = [];
                                            if (isset($set['reps'])) $details[] = "{$set['reps']} powt.";
                                            if (isset($set['weight'])) $details[] = "{$set['weight']} kg";
                                            if (isset($set['time'])) $details[] = "{$set['time']} s";
                                            echo implode(' <span class="text-muted mx-1">/</span> ', $details);
                                        ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginacja -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Nawigacja po historii">
        <ul class="pagination justify-content-center mt-4">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?client_id=<?= urlencode($clientId) ?>&page=<?= $currentPage - 1 ?>">Poprzednia</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="?client_id=<?= urlencode($clientId) ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?client_id=<?= urlencode($clientId) ?>&page=<?= $currentPage + 1 ?>">Następna</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>