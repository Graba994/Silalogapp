<?php
// Ustaw nagłówki, aby zapobiec buforowaniu przez przeglądarkę
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Społeczność - Tablica Aktywności';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// --- 1. PRZYGOTOWANIE DANYCH ---
$currentUserId = $_SESSION['user_id'];
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$socialData = get_social_data();
$allExercises = get_all_exercises();

$usersById = array_column($allUsers, null, 'id');
$exerciseMap = array_column($allExercises, 'name', 'id');

$friendIds = $socialData[$currentUserId]['friends'] ?? [];
$userIdsToDisplay = $friendIds;
$userIdsToDisplay[] = $currentUserId;
$userIdsToDisplay = array_unique($userIdsToDisplay);

$activityFeed = [];

// --- 2. ZBIERANIE I PRZETWARZANIE AKTYWNOŚCI ---
$oneWeekAgo = strtotime('-7 days');
$hotWorkouts = [];

foreach ($userIdsToDisplay as $userId) {
    if (isset($usersById[$userId])) {
        $userWorkouts = get_user_workouts($userId);
        foreach ($userWorkouts as $workout) {
            // Dodaj podstawowe informacje
            $workout['author'] = ['id' => $userId, 'name' => $usersById[$userId]['name'], 'icon' => $usersById[$userId]['icon']];
            
            // Oblicz sumę interakcji (dla "Gorących Treningów")
            $interactionScore = count($workout['interactions']['likes'] ?? []) + count($workout['interactions']['comments'] ?? []);
            $workout['interaction_score'] = $interactionScore;

            // Oblicz objętość i PR-y
            $workout['total_volume'] = 0;
            foreach($workout['exercises'] as $ex) {
                foreach($ex['sets'] as $set) {
                    $workout['total_volume'] += ($set['weight'] ?? 0) * ($set['reps'] ?? 0);
                }
            }
            $workout['new_prs'] = calculate_new_prs_for_workout($workout, $userId);

            $activityFeed[] = $workout;

            // Jeśli trening jest z ostatniego tygodnia i ma interakcje, dodaj go do "gorących"
            if (strtotime($workout['date']) >= $oneWeekAgo && $interactionScore > 0) {
                $hotWorkouts[] = $workout;
            }
        }
    }
}

// Sortuj "gorące" treningi po liczbie interakcji
if (!empty($hotWorkouts)) {
    usort($hotWorkouts, fn($a, $b) => $b['interaction_score'] <=> $a['interaction_score']);
}
$topHotWorkouts = array_slice($hotWorkouts, 0, 3); // Weź top 3

// --- 3. FILTROWANIE I SORTOWANIE GŁÓWNEJ TABLICY ---
$filterByUserId = $_GET['user_id'] ?? 'all';
if ($filterByUserId !== 'all') {
    $activityFeed = array_filter($activityFeed, fn($activity) => $activity['author']['id'] === $filterByUserId);
}

usort($activityFeed, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));

// --- 4. PAGINACJA ---
$perPage = 10;
$totalActivities = count($activityFeed);
$totalPages = ceil($totalActivities / $perPage);
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $perPage;
$paginatedFeed = array_slice($activityFeed, $offset, $perPage);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Tablica Aktywności</h1>
</div>

<!-- NOWY PANEL FILTRÓW -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div class="flex-grow-1">
                <label for="user_id_filter" class="form-label small">Pokaż aktywności:</label>
                <select name="user_id" id="user_id_filter" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $filterByUserId === 'all' ? 'selected' : '' ?>>Wszystkich (Ty i znajomi)</option>
                    <option value="<?= $currentUserId ?>" <?= $filterByUserId === $currentUserId ? 'selected' : '' ?>>Tylko moje</option>
                    <?php foreach($friendIds as $friendId): if(isset($usersById[$friendId])): ?>
                        <option value="<?= $friendId ?>" <?= $filterByUserId === $friendId ? 'selected' : '' ?>><?= htmlspecialchars($usersById[$friendId]['name']) ?></option>
                    <?php endif; endforeach; ?>
                </select>
            </div>
            <a href="community.php" class="btn btn-sm btn-outline-secondary">Wyczyść filtr</a>
            <a href="friends.php" class="btn btn-sm btn-secondary ms-auto">Zarządzaj Znajomymi</a>
        </form>
    </div>
</div>

<!-- NOWA SEKCJA: GORĄCE TRENINGI TYGODNIA -->
<?php if (!empty($topHotWorkouts)): ?>
<div class="mb-4">
    <h4 class="mb-3"><i class="bi bi-fire text-danger me-2"></i>Gorące w tym tygodniu</h4>
    <div class="row g-3">
        <?php foreach($topHotWorkouts as $hot): ?>
        <div class="col-lg-4">
            <div class="card card-body text-center h-100 shadow-sm border-2 border-warning">
                <div class="small text-muted"><?= htmlspecialchars($hot['author']['name']) ?> - <?= htmlspecialchars($hot['date']) ?></div>
                <h6 class="mb-1 mt-2">
                    <?= htmlspecialchars($exerciseMap[$hot['exercises'][0]['exercise_id']] ?? 'Główny bój') ?>
                </h6>
                <p class="mb-0 small text-success fw-bold">
                    <i class="bi bi-hand-thumbs-up-fill"></i> <?= count($hot['interactions']['likes'] ?? []) ?>
                    <i class="bi bi-chat-dots-fill ms-2"></i> <?= count($hot['interactions']['comments'] ?? []) ?>
                </p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <hr class="my-4">
</div>
<?php endif; ?>


<?php if (empty($activityFeed)): ?>
    <div class="card text-center p-5">
        <div class="card-body">
            <h3 class="text-muted">Brak aktywności do wyświetlenia.</h3>
            <p class="lead text-muted">Zmień filtry lub dodaj nowy trening, aby zobaczyć go tutaj.</p>
            <a href="start_workout.php?plan_id=adhoc" class="btn btn-lg btn-primary mt-3"><i class="bi bi-plus-circle-dotted me-2"></i>Dodaj trening</a>
        </div>
    </div>
<?php else: ?>
    <div class="vstack gap-4 activity-feed">
        <?php foreach ($paginatedFeed as $activity):
            $likes = $activity['interactions']['likes'] ?? [];
            $comments = $activity['interactions']['comments'] ?? [];
            $userHasLiked = in_array($currentUserId, $likes);
            $isOwnActivity = ($activity['author']['id'] === $currentUserId);
            $uniqueCollapseId = 'details-' . $activity['workout_id']; // Unikalne ID dla każdego treningu
        ?>
            <div class="card shadow-sm <?= $isOwnActivity ? 'border-info' : '' ?>">
                <div class="card-header <?= $isOwnActivity ? 'bg-info-subtle' : 'bg-body-tertiary' ?>">
                    <div class="d-flex align-items-center">
                        <i class="bi <?= htmlspecialchars($activity['author']['icon']) ?> fs-4 me-3 text-primary"></i>
                        <div>
                            <h5 class="mb-0 fs-6">
                                <strong><?= $isOwnActivity ? 'Ty' : htmlspecialchars($activity['author']['name']) ?></strong>
                                <?= $isOwnActivity ? 'ukończyłeś(aś)' : 'ukończył(a)' ?> trening
                            </h5>
                            <small class="text-muted"><?= htmlspecialchars($activity['date']) ?></small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($activity['notes'])): ?>
                        <blockquote class="blockquote small border-start border-2 ps-3 mb-3">
                            <p class="mb-0 fst-italic">"<?= htmlspecialchars($activity['notes']) ?>"</p>
                        </blockquote>
                    <?php endif; ?>
                    
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($activity['exercises'], 0, 3) as $exercise): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?= htmlspecialchars($exerciseMap[$exercise['exercise_id']] ?? 'Nieznane ćwiczenie') ?></span>
                                    <?php $totalRepsForExercise = array_sum(array_column($exercise['sets'], 'reps')); ?>
                                    <span class="badge bg-primary rounded-pill fw-normal">Suma: <?= $totalRepsForExercise ?> powt.</span>
                                </div>
                                <div class="text-muted small">
                                    <?php
                                        $setSummaries = [];
                                        foreach ($exercise['sets'] as $set) {
                                            $summary = [];
                                            if (isset($set['reps'])) $summary[] = "{$set['reps']}p";
                                            if (isset($set['weight'])) $summary[] = "{$set['weight']}kg";
                                            if (isset($set['time'])) $summary[] = "{$set['time']}s";
                                            $setSummaries[] = implode(' x ', $summary);
                                        }
                                        echo implode(' | ', $setSummaries);
                                    ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- === NOWA SEKCJA: Rozwijanie reszty ćwiczeń === -->
                    <?php if (count($activity['exercises']) > 3): ?>
                        <div class="collapse" id="<?= $uniqueCollapseId ?>">
                             <ul class="list-group list-group-flush">
                                <?php foreach (array_slice($activity['exercises'], 3) as $exercise): ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold"><?= htmlspecialchars($exerciseMap[$exercise['exercise_id']] ?? 'Nieznane ćwiczenie') ?></span>
                                            <?php $totalRepsForExercise = array_sum(array_column($exercise['sets'], 'reps')); ?>
                                            <span class="badge bg-primary rounded-pill fw-normal">Suma: <?= $totalRepsForExercise ?> powt.</span>
                                        </div>
                                        <div class="text-muted small">
                                            <?php
                                                $setSummaries = [];
                                                foreach ($exercise['sets'] as $set) {
                                                    $summary = [];
                                                    if (isset($set['reps'])) $summary[] = "{$set['reps']}p";
                                                    if (isset($set['weight'])) $summary[] = "{$set['weight']}kg";
                                                    if (isset($set['time'])) $summary[] = "{$set['time']}s";
                                                    $setSummaries[] = implode(' x ', $summary);
                                                }
                                                echo implode(' | ', $setSummaries);
                                            ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <a class="btn btn-link btn-sm p-0 mt-2" data-bs-toggle="collapse" href="#<?= $uniqueCollapseId ?>" role="button" aria-expanded="false" aria-controls="<?= $uniqueCollapseId ?>">
                           Pokaż więcej ćwiczeń...
                        </a>
                    <?php endif; ?>
                    <!-- === KONIEC NOWEJ SEKCJI === -->

                </div>
                <div class="card-footer bg-body-tertiary">
                    <div class="d-flex justify-content-between align-items-center small text-muted mb-2 border-bottom pb-2">
                        <div>
                            <i class="bi bi-truck me-1"></i>
                            Objętość: <strong><?= number_format($activity['total_volume'], 0, ',', ' ') ?> kg</strong>
                        </div>
                        <?php if(!empty($activity['new_prs'])): ?>
                        <div class="text-success fw-bold">
                            <i class="bi bi-trophy-fill me-1"></i>
                            Nowy PR!
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-start gap-3 align-items-center mb-3">
                        <button class="btn btn-sm btn-outline-primary like-btn <?= $userHasLiked ? 'active' : '' ?>" 
                                data-owner-id="<?= htmlspecialchars($activity['author']['id']) ?>" 
                                data-workout-id="<?= htmlspecialchars($activity['workout_id']) ?>">
                            <i class="bi <?= $userHasLiked ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up' ?>"></i> 
                            Daj piątkę (<span class="like-counter"><?= count($likes) ?></span>)
                        </button>
                        <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#comments-<?= htmlspecialchars($activity['workout_id']) ?>" role="button" aria-expanded="false" aria-controls="comments-<?= htmlspecialchars($activity['workout_id']) ?>">
                            <i class="bi bi-chat-dots"></i> Komentarze (<span class="comment-counter"><?= count($comments) ?></span>)
                        </a>
                    </div>

                    <div class="collapse" id="comments-<?= htmlspecialchars($activity['workout_id']) ?>">
                        <div class="comments-container border-top pt-3">
                            <?php if(empty($comments)): ?>
                                <p class="text-muted small text-center">Brak komentarzy. Bądź pierwszy!</p>
                            <?php else: ?>
                                <?php foreach(array_slice($comments, -5) as $comment): ?>
                                <div class="d-flex small gap-2 mb-2">
                                    <i class="bi bi-person-circle mt-1"></i>
                                    <div class="flex-grow-1">
                                        <strong><?= htmlspecialchars($comment['user_name']) ?></strong>
                                        <p class="mb-0"><?= htmlspecialchars($comment['text']) ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form class="comment-form d-flex gap-2 mt-3" 
                              data-owner-id="<?= htmlspecialchars($activity['author']['id']) ?>" 
                              data-workout-id="<?= htmlspecialchars($activity['workout_id']) ?>">
                            <input type="text" class="form-control form-control-sm comment-input" placeholder="Napisz komentarz...">
                            <button type="submit" class="btn btn-sm btn-primary">Wyślij</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Nawigacja po aktywnościach">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage - 1 ?>&user_id=<?= urlencode($filterByUserId) ?>">Poprzednia</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&user_id=<?= urlencode($filterByUserId) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $currentPage + 1 ?>&user_id=<?= urlencode($filterByUserId) ?>">Następna</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>