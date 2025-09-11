<?php
$pageTitle = 'Panel Główny - SiłaLog';
require_once 'includes/functions.php';
require_once 'includes/header.php'; 

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userWorkouts = get_user_workouts($userId);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');
$userPlans = get_user_plans($userId);

$widgets = $themeConfig['dashboardWidgets'];

// Logika dla aktywnych treningów
$activeLiveWorkoutId = null;
$liveWorkoutFiles = glob('data/live_workouts/lw_*.json');
foreach ($liveWorkoutFiles as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (isset($data['status']) && $data['status'] === 'active' && in_array($userId, $data['participants'] ?? [])) {
        $activeLiveWorkoutId = $data['live_workout_id'];
        break;
    }
}
$activeSoloWorkout = null;
$soloSessionFile = 'data/solo_sessions/solo_' . $userId . '.json';
if (file_exists($soloSessionFile)) {
    $activeSoloWorkout = json_decode(file_get_contents($soloSessionFile), true);
}

// Logika dla widżetów
if ($widgets['weeklySummary']['enabled'] ?? false) {
    $weekSummary = ['workouts' => 0, 'sets' => 0, 'volume' => 0];
    $oneWeekAgo = strtotime('-7 days');
    foreach ($userWorkouts as $workout) {
        if (strtotime($workout['date']) >= $oneWeekAgo) {
            $weekSummary['workouts']++;
            foreach ($workout['exercises'] as $exercise) {
                $weekSummary['sets'] += count($exercise['sets']);
                foreach ($exercise['sets'] as $set) {
                    $weekSummary['volume'] += ($set['weight'] ?? 0) * ($set['reps'] ?? 0);
                }
            }
        }
    }
}
if ($widgets['recentPRs']['enabled'] ?? false) {
    $lastPRs = []; $allTimePRs = [];
    foreach ($userWorkouts as $workout) {
        foreach ($workout['exercises'] as $exercise) {
            $exId = $exercise['exercise_id'];
            if (!isset($allTimePRs[$exId])) $allTimePRs[$exId] = ['max_weight' => 0, 'e1rm' => 0];
            foreach ($exercise['sets'] as $set) {
                $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0;
                if ($weight > $allTimePRs[$exId]['max_weight']) {
                    $allTimePRs[$exId]['max_weight'] = $weight;
                    $lastPRs[] = ['type' => 'Max Ciężar', 'value' => "$weight kg", 'date' => $workout['date'], 'exName' => $exerciseMap[$exId] ?? 'Nieznane'];
                }
                if ($reps > 0 && $weight > 0) {
                    $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1);
                    if ($e1rm > $allTimePRs[$exId]['e1rm']) {
                        $allTimePRs[$exId]['e1rm'] = $e1rm;
                        $lastPRs[] = ['type' => 'Szac. 1RM', 'value' => "$e1rm kg", 'date' => $workout['date'], 'exName' => $exerciseMap[$exId] ?? 'Nieznane'];
                    }
                }
            }
        }
    }
    usort($lastPRs, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
    $lastPRs = array_slice($lastPRs, 0, 5);
}
if ($widgets['strengthRankings']['enabled'] ?? false) {
    $socialData = get_social_data();
    $friendIds = $socialData[$userId]['friends'] ?? [];
    $userIdsToRank = array_merge([$userId], $friendIds);
    $exercisesToRankIds = [1, 2, 3];
    $strengthRankings = get_global_rankings_for_users($exercisesToRankIds, $userIdsToRank);
}
if ($widgets['quote']['enabled'] ?? false) {
    $quotesFile = 'data/quotes.json';
    $randomQuote = null;
    if (file_exists($quotesFile)) {
        $quotesData = json_decode(file_get_contents($quotesFile), true);
        if (!empty($quotesData) && is_array($quotesData)) {
            $randomQuote = $quotesData[array_rand($quotesData)];
        }
    }
}

// Logika dla Kalendarza
if ($widgets['activityHeatmap']['enabled'] ?? false) {
    $completedEvents = [];
    foreach ($userWorkouts as $workout) {
        $completedEvents[] = [
            'title' => '✔ Wykonany Trening',
            'start' => $workout['date'],
            'url' => 'history.php?page=1&start_date=' . $workout['date'] . '&end_date=' . $workout['date'],
            'classNames' => ['event-completed'],
            'isCompleted' => true,
            'color' => 'var(--bs-success)'
        ];
    }
    $scheduledEvents = [];
    $userSchedule = get_user_schedule($userId);
    foreach ($userSchedule as $event) {
        $isDone = false;
        foreach($userWorkouts as $w) { if($w['date'] === $event['start']) { $isDone = true; break; } }
        if ($isDone) continue;
        $scheduledEvents[] = [
            'id' => $event['id'],
            'title' => ($event['type'] === 'coach' ? '★ ' : '') . $event['title'],
            'start' => $event['start'],
            'color' => $event['color'],
            'extendedProps' => [
                'planId' => $event['planId'],
                'type' => $event['type'],
                'isCoachSession' => $event['isCoachSession'] ?? false,
                'createdBy' => $event['createdBy'] ?? null
            ]
        ];
    }
    $allCalendarEvents = array_merge($completedEvents, $scheduledEvents);
    $allCalendarEventsJson = json_encode($allCalendarEvents);
    $userPlansJson = json_encode($userPlans);
}
?>

<style>
.event-completed { background-color: var(--bs-success-bg-subtle) !important; border-color: var(--bs-success-border-subtle) !important; }
.event-completed .fc-event-title { text-decoration: line-through; color: var(--bs-secondary-color); }
.fc-event[href]:hover, .fc-event.fc-event-draggable:hover { cursor: pointer; }
.fc-event-title .bi-star-fill { color: var(--bs-warning); }
</style>

<!-- === SEKCJA HTML === -->
<?php if ($activeLiveWorkoutId): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert"><h4 class="alert-heading"><i class="bi bi-broadcast"></i> Jesteś w trakcie treningu wspólnego!</h4><p>Trwa aktywna sesja. Możesz do niej dołączyć w każdej chwili.</p><hr><a href="shared_workout.php" class="btn btn-success mb-0 fw-bold"><i class="bi bi-arrow-right-circle-fill me-2"></i>Przejdź do treningu na żywo</a></div>
<?php endif; ?>

<?php if ($activeSoloWorkout): ?>
    <div class="alert alert-info shadow-sm" role="alert"><div class="d-flex flex-wrap align-items-center"><div class="flex-grow-1"><h4 class="alert-heading"><i class="bi bi-play-circle-fill"></i> Masz niedokończony trening!</h4><p class="mb-0">Wygląda na to, że ostatnio nie zapisałeś(aś) swojej sesji. Co chcesz zrobić?</p></div><div class="mt-3 mt-md-0 ms-md-auto"><a href="log_workout.php" class="btn btn-info"><i class="bi bi-arrow-right-circle-fill me-2"></i>Kontynuuj trening</a><a href="cancel_solo_workout.php" class="btn btn-outline-secondary" onclick="return confirm('Czy na pewno chcesz anulować ten trening? Wszystkie niezapisane postępy zostaną utracone.')"><i class="bi bi-x-circle me-2"></i>Anuluj</a></div></div></div>
<?php endif; ?>

<div class="row g-4">
    <?php if ($widgets['welcome']['enabled'] ?? false): ?>
    <div class="col-12">
        <div class="card text-center p-4 shadow-sm">
            <div class="card-body">
                <h1 class="card-title">Witaj z powrotem, <?= htmlspecialchars($userName) ?>!</h1>
                <p class="lead text-muted">Co dzisiaj robimy?</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mt-4">
                    <div class="btn-group"><a href="start_workout.php?plan_id=adhoc" class="btn btn-primary btn-lg px-4"><i class="bi bi-plus-circle-dotted"></i> Nowy Trening Solo</a><button type="button" class="btn btn-primary btn-lg dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button><ul class="dropdown-menu"><li><a class="dropdown-item" href="start_workout.php?plan_id=adhoc"><i class="bi bi-joystick me-2"></i>Ad-hoc</a></li><li><a class="dropdown-item" href="plans.php"><i class="bi bi-journal-text me-2"></i>Z planu</a></li></ul></div>
                    <a href="start_shared_workout.php" class="btn btn-info btn-lg px-4"><i class="bi bi-people-fill"></i> Trening Wspólny</a>
                    <a href="history.php" class="btn btn-outline-secondary btn-lg px-4"><i class="bi bi-clock-history"></i> Zobacz Historię</a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (($widgets['quote']['enabled'] ?? false) && isset($randomQuote)): ?>
    <div class="col-12">
        <div class="card border-primary-subtle bg-primary-subtle"><div class="card-body text-center p-4"><blockquote class="blockquote mb-0"><p class="fs-5 fst-italic"><i class="bi bi-quote"></i><?= htmlspecialchars($randomQuote['quote']) ?></p><footer class="blockquote-footer mt-2 text-primary-emphasis"><cite><?= htmlspecialchars($randomQuote['source']) ?></cite></footer></blockquote></div></div>
    </div>
    <?php endif; ?>

    <?php if (($widgets['quickStart']['enabled'] ?? false) && !empty($userPlans)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-rocket-takeoff-fill me-2"></i>Szybki Start - Twoje Plany</h5></div>
            <div class="card-body"><div class="row g-3"><?php foreach (array_slice($userPlans, 0, 4) as $plan): ?><div class="col-md-6 col-lg-3"><a href="start_workout.php?plan_id=<?= $plan['plan_id'] ?>" class="btn btn-outline-primary w-100 h-100 p-3 text-start"><h6 class="mb-1"><?= htmlspecialchars($plan['plan_name']) ?></h6><small class="text-muted"><?= count($plan['exercises']) ?> ćwiczeń</small></a></div><?php endforeach; ?></div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($widgets['weeklySummary']['enabled'] ?? false): ?>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-calendar-week-fill me-2"></i>Twój tydzień w liczbach</h5></div><div class="card-body d-flex align-items-center justify-content-around text-center"><div><div class="display-6 fw-bold"><?= $weekSummary['workouts'] ?></div><div class="text-muted small">Treningów</div></div><div><div class="display-6 fw-bold"><?= $weekSummary['sets'] ?></div><div class="text-muted small">Serii</div></div><div><div class="display-6 fw-bold"><?= number_format($weekSummary['volume'], 0, ',', ' ') ?></div><div class="text-muted small">kg objętości</div></div></div></div>
    </div>
    <?php endif; ?>

    <?php if ($widgets['recentPRs']['enabled'] ?? false): ?>
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-trophy-fill me-2"></i>Twoje ostatnie osiągnięcia</h5></div><?php if (empty($lastPRs)): ?><div class="card-body d-flex align-items-center justify-content-center"><p class="text-muted">Brak nowych rekordów.</p></div><?php else: ?><ul class="list-group list-group-flush"><?php foreach($lastPRs as $pr): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div><strong class="text-success"><?= htmlspecialchars($pr['type']) ?></strong> w: <span class="fw-bold"><?= htmlspecialchars($pr['exName']) ?></span><small class="d-block text-muted"><?= htmlspecialchars($pr['date']) ?></small></div><span class="badge bg-success-subtle text-success-emphasis rounded-pill fs-6"><?= htmlspecialchars($pr['value']) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
    </div>
    <?php endif; ?>
    
    <?php if ($widgets['activityHeatmap']['enabled'] ?? false): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-calendar-event-fill me-2"></i>Kalendarz Aktywności</h5></div>
            <div class="card-body">
                <div id="activity-calendar"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($widgets['strengthRankings']['enabled'] ?? false): ?>
    <div class="col-12">
        <div class="card"><div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Rankingi Siły (Ty vs Znajomi)</h5></div><div class="card-body"><div class="row g-4"><?php foreach ($strengthRankings as $exId => $ranking): ?><div class="col-lg-4"><h6 class="text-center mb-3 fw-bold"><?= htmlspecialchars($exerciseMap[$exId] ?? 'Nieznane') ?></h6><?php if (empty($ranking)): ?><p class="text-center text-muted small">Brak danych.</p><?php else: ?><ul class="list-group"><?php $userRank = null; foreach ($ranking as $index => $entry) { if ($entry['user_id'] === $userId) $userRank = $index + 1; if ($index >= 3) continue; $place_class = 'podium-place-' . ($index + 1); ?><li class="list-group-item d-flex align-items-center gap-3 ranking-podium-item <?=($entry['user_id'] === $userId) ? 'is-you' : '' ?>"><div class="podium-place fw-bold <?= $place_class ?>"><i class="bi bi-award-fill"></i></div><i class="bi <?= htmlspecialchars($entry['user_icon']) ?> fs-4"></i><div class="flex-grow-1"><div class="fw-bold text-truncate"><?= htmlspecialchars($entry['user_name']) ?></div><small class="text-muted">Rekord z: <?= htmlspecialchars($entry['date']) ?></small></div><span class="badge bg-secondary rounded-pill fs-6"><?= htmlspecialchars($entry['value']) ?> kg</span></li><?php } ?></ul><?php if ($userRank !== null && $userRank > 3): ?><div class="text-center small text-muted mt-2">Twoja pozycja: <strong>#<?= $userRank ?></strong></div><?php endif; ?><?php endif; ?></div><?php endforeach; ?></div></div><div class="card-footer text-center text-muted small">Rankingi oparte o e1RM.</div></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($widgets['activityHeatmap']['enabled'] ?? false): ?>
<script>
    window.allCalendarEvents = <?= $allCalendarEventsJson ?? '[]' ?>;
    window.userPlansForCalendar = <?= $userPlansJson ?? '[]' ?>;
    window.calendarUserId = '<?= htmlspecialchars($userId) ?>';
    window.currentUserId = '<?= htmlspecialchars($userId) ?>';
    window.currentUserRole = '<?= htmlspecialchars($_SESSION['user_role'] ?? 'user') ?>';
    window.isCoachView = false;
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>