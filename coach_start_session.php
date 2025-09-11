<?php
// Plik: koks/coach_start_session.php
require_once 'includes/coach_guard.php';
$pageTitle = 'Nowa Sesja Trenerska';
require_once 'includes/functions.php';

$coachId = $_SESSION['user_id'];
$coachName = $_SESSION['user_name'];

// --- Logika Tworzenia Sesji ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = $_POST['plan_id'] ?? null;
    $clientIds = $_POST['clients'] ?? [];
    $includeCoach = isset($_POST['include_coach']);

    if (empty($planId) || empty($clientIds)) {
        header('Location: coach_start_session.php?error=validation');
        exit();
    }

    $basePlan = null;
    if ($planId === 'adhoc') {
        $basePlan = ['plan_id' => 'adhoc', 'plan_name' => 'Grupowa Sesja Trenerska', 'exercises' => []];
    } else {
        $coachPlans = get_user_plans($coachId);
        foreach ($coachPlans as $plan) {
            if ($plan['plan_id'] === $planId) {
                $basePlan = $plan;
                break;
            }
        }
    }

    if ($basePlan) {
        $participants = $clientIds;
        if ($includeCoach) {
            $participants[] = $coachId;
        }
        $participants = array_unique($participants);

        $liveWorkoutId = 'lw_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        $liveWorkoutData = [
            'live_workout_id' => $liveWorkoutId,
            'owner_id' => $coachId,
            'coach_mode' => true,
            'participants' => $participants,
            'is_coaching_session' => true,
            'client_ids' => $clientIds, // Zapisujemy listę podopiecznych
            'status' => 'active',
            'start_time' => date('c'),
            'base_plan' => $basePlan,
            'live_data' => []
        ];
        
        foreach($participants as $pId) {
            $liveWorkoutData['live_data'][$pId] = [];
        }
        
        file_put_contents('data/live_workouts/' . $liveWorkoutId . '.json', json_encode($liveWorkoutData, JSON_PRETTY_PRINT));
        $_SESSION['active_live_workout_id'] = $liveWorkoutId;
        header('Location: shared_workout.php');
        exit();
    }
}

// --- Przygotowanie Danych do Wyświetlenia ---
$coachingData = get_coaching_data();
$myClientIds = $coachingData[$coachId] ?? [];
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$clients = array_filter($allUsers, fn($user) => in_array($user['id'], $myClientIds));
$coachPlans = get_user_plans($coachId);

require_once 'includes/header.php';
?>
<style>
    .choice-card { cursor: pointer; border: 2px solid var(--bs-border-color-translucent); transition: all 0.2s ease-in-out; position: relative; overflow: hidden; }
    .choice-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); border-color: var(--bs-primary); }
    .choice-card.selected { border-color: var(--bs-primary); box-shadow: 0 0 0 4px rgba(var(--bs-primary-rgb), 0.25); }
    .choice-card .selected-indicator { display: none; position: absolute; top: 10px; right: 10px; font-size: 1.5rem; color: var(--bs-primary); }
    .choice-card.selected .selected-indicator { display: block; }
</style>

<div class="container py-4">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold">Skonfiguruj Sesję Treningową</h1>
        <p class="lead text-muted">Wybierz uczestników i plan, a następnie rozpocznij trening.</p>
    </div>

    <form method="POST" id="start-session-form">
        <!-- KROK 1: UCZESTNICY -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h2 class="h4 mb-0"><span class="badge bg-primary me-2">1</span> Wybierz Uczestników</h2></div>
            <div class="card-body">
                <?php if (empty($clients)): ?>
                    <p class="text-muted">Nie masz podopiecznych do zaproszenia. <a href="coach_panel.php">Dodaj ich w panelu trenera</a>.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($clients as $client): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card text-center choice-card" data-user-id="<?= htmlspecialchars($client['id']) ?>">
                                <div class="card-body"><i class="bi <?= htmlspecialchars($client['icon']) ?> display-4"></i><h6 class="card-title mt-2 mb-0 text-truncate"><?= htmlspecialchars($client['name']) ?></h6><i class="bi bi-check-circle-fill selected-indicator"></i></div>
                                <input type="checkbox" name="clients[]" value="<?= htmlspecialchars($client['id']) ?>" class="d-none">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <hr class="my-4">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="include_coach" name="include_coach">
                        <label class="form-check-label" for="include_coach">Ja też biorę udział (<?= htmlspecialchars($coachName) ?>)</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- KROK 2: PLAN -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h2 class="h4 mb-0"><span class="badge bg-primary me-2">2</span> Wybierz Plan</h2></div>
            <div class="list-group list-group-flush">
                <label class="list-group-item list-group-item-action"><div class="d-flex w-100 justify-content-between"><h5 class="mb-1">Trening Ad-Hoc</h5><input type="radio" class="form-check-input" name="plan_id" value="adhoc" required></div><p class="mb-1 text-muted">Rozpocznij pustą sesję i dodawaj ćwiczenia na bieżąco.</p></label>
                <?php if (!empty($coachPlans)): ?>
                    <?php foreach ($coachPlans as $plan): ?>
                    <label class="list-group-item list-group-item-action"><div class="d-flex w-100 justify-content-between"><h5 class="mb-1"><?= htmlspecialchars($plan['plan_name']) ?></h5><input type="radio" class="form-check-input" name="plan_id" value="<?= htmlspecialchars($plan['plan_id']) ?>"></div><p class="mb-1 text-muted"><?= htmlspecialchars($plan['plan_description']) ?: 'Brak opisu.' ?></p><small><?= count($plan['exercises']) ?> ćwiczeń</small></label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="coach_panel.php" class="btn btn-secondary btn-lg">Anuluj</a>
            <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-rocket-launch-fill me-2"></i>Rozpocznij Sesję</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Logika wyboru uczestników
    document.querySelectorAll('.choice-card').forEach(card => {
        card.addEventListener('click', () => {
            card.classList.toggle('selected');
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>