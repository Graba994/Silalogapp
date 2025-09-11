<?php
// KROK 1: Logika formularza i sesji
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    
    // === POPRAWIONA I BARDZIEJ NIEZAWODNA LOGIKA ZBIERANIA UCZESTNIKÓW ===
    $selectedFriends = $_POST['participants'] ?? [];
    // Zawsze zaczynamy od dodania siebie, potem łączymy z resztą i usuwamy duplikaty.
    $participants = array_unique(array_merge([$userId], $selectedFriends));

    $planId = $_POST['plan_id'] ?? null;
    $isCoachMode = isset($_POST['coach_mode']);

    // Walidacja: Musi być plan i co najmniej 2 uczestników (Ty + 1 znajomy)
    if (!$planId || count($participants) < 2) {
        header('Location: start_shared_workout.php?error=validation_failed');
        exit();
    }

    $basePlan = null;
    if ($planId === 'adhoc') {
        $basePlan = ['plan_id' => 'adhoc', 'plan_name' => 'Wspólny Trening Ad-Hoc', 'exercises' => []];
    } else {
        $userPlans = get_user_plans($userId);
        foreach ($userPlans as $plan) {
            if ($plan['plan_id'] === $planId) {
                $basePlan = $plan;
                break;
            }
        }
    }

    if ($basePlan) {
        $liveWorkoutId = 'lw_' . date('YmdHis') . '_' . bin2hex(random_bytes(3));
        $liveWorkoutData = [
            'live_workout_id' => $liveWorkoutId,
            'owner_id' => $userId,
            'coach_mode' => $isCoachMode,
            'participants' => $participants,
            'status' => 'active',
            'start_time' => date('c'),
            'base_plan' => $basePlan,
            'live_data' => []
        ];
        
        // Inicjalizuj puste dane dla każdego uczestnika
        foreach ($participants as $pId) {
            $liveWorkoutData['live_data'][$pId] = [];
        }
        
        file_put_contents('data/live_workouts/' . $liveWorkoutId . '.json', json_encode($liveWorkoutData, JSON_PRETTY_PRINT));
        $_SESSION['active_live_workout_id'] = $liveWorkoutId;
        header('Location: shared_workout.php');
        exit();
    } else {
        header('Location: start_shared_workout.php?error=plan_not_found');
        exit();
    }
}

// KROK 2: Przygotowanie danych do wyświetlenia
$pageTitle = 'Nowy Trening Wspólny';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
$userId = $_SESSION['user_id'];
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$socialData = get_social_data();
$userPlans = get_user_plans($userId);
$friends = get_user_friends_details($userId, $allUsers, $socialData);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');

// KROK 3: Wyświetlanie strony
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
        <h1 class="display-5 fw-bold">Stwórz Trening Wspólny</h1>
        <p class="lead text-muted">Zaproś znajomych, wybierzcie plan i zacznijcie razem trenować.</p>
    </div>

    <form method="POST" id="start-shared-form" action="start_shared_workout.php">
        <!-- KROK 1: ZNAJOMI -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h2 class="h4 mb-0"><span class="badge bg-primary me-2">1</span> Zaproś znajomych</h2></div>
            <div class="card-body">
                <?php if (empty($friends)): ?>
                    <p class="text-muted">Nie masz znajomych do zaproszenia. <a href="friends.php">Dodaj ich</a>, aby móc trenować wspólnie.</p>
                <?php else: ?>
                    <div class="row g-3" id="friends-container">
                        <?php foreach ($friends as $friend): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card text-center choice-card" data-friend-id="<?= htmlspecialchars($friend['id']) ?>">
                                <div class="card-body"><i class="bi <?= htmlspecialchars($friend['icon']) ?> display-4"></i><h6 class="card-title mt-2 mb-0 text-truncate"><?= htmlspecialchars($friend['name']) ?></h6><i class="bi bi-check-circle-fill selected-indicator"></i></div>
                                <input type="checkbox" name="participants[]" value="<?= htmlspecialchars($friend['id']) ?>" class="d-none">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- KROK 2: PLAN -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white"><h2 class="h4 mb-0"><span class="badge bg-primary me-2">2</span> Wybierz plan</h2></div>
            <div class="card-body">
                <input type="hidden" name="plan_id" id="selected_plan_id">
                <div class="mb-3"><input type="search" id="plan-search-input" class="form-control" placeholder="Filtruj plany po nazwie..."></div>
                <div class="row g-3" id="plans-container-selectable">
                    <div class="col-md-6 col-lg-4 plan-item" data-plan-name="trening ad-hoc">
                        <div class="card h-100 choice-card" data-plan-id="adhoc"><div class="card-body text-center d-flex flex-column justify-content-center p-4"><i class="bi bi-stars display-1 text-primary"></i><h5 class="card-title mt-3">Trening Ad-Hoc</h5><p class="card-text small text-muted">Zacznijcie od zera i dodawajcie ćwiczenia na bieżąco.</p><i class="bi bi-check-circle-fill selected-indicator"></i></div></div>
                    </div>
                    <?php foreach ($userPlans as $plan): ?>
                    <div class="col-md-6 col-lg-4 plan-item" data-plan-name="<?= strtolower(htmlspecialchars($plan['plan_name'])) ?>">
                        <div class="card h-100 choice-card" data-plan-id="<?= htmlspecialchars($plan['plan_id']) ?>">
                            <div class="card-body d-flex flex-column">
                                <div><h5 class="card-title text-truncate"><?= htmlspecialchars($plan['plan_name']) ?></h5><p class="small text-muted mb-2"><?= htmlspecialchars($plan['plan_description']) ?: 'Brak opisu.' ?></p></div>
                                <ul class="list-group list-group-flush small flex-grow-1">
                                    <?php foreach (array_slice($plan['exercises'], 0, 3) as $ex): ?>
                                    <li class="list-group-item px-0 py-1 d-flex justify-content-between"><span class="text-truncate"><?= htmlspecialchars($exerciseMap[$ex['exercise_id']] ?? '?') ?></span><span class="badge bg-secondary rounded-pill flex-shrink-0 ms-2"><?= count($ex['target_sets']) ?> serii</span></li>
                                    <?php endforeach; ?>
                                    <?php if (count($plan['exercises']) > 3): ?><li class="list-group-item px-0 py-1 text-muted">...i <?= count($plan['exercises']) - 3 ?> więcej.</li><?php endif; ?>
                                </ul><i class="bi bi-check-circle-fill selected-indicator"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- KROK 3: OPCJE -->
        <div class="card shadow-sm"><div class="card-header bg-dark text-white"><h2 class="h4 mb-0"><span class="badge bg-primary me-2">3</span> Opcje</h2></div><div class="card-body"><div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" role="switch" id="coach_mode" name="coach_mode" checked><label class="form-check-label" for="coach_mode">Włącz "Tryb Trenera"</label><div class="form-text mt-1">Jako założyciel treningu będziesz mógł edytować serie wszystkich uczestników.</div></div></div></div>
        
        <!-- Pasek Akcji -->
        <div class="workout-actions-bar"><div class="container d-flex justify-content-end"><button type="submit" class="btn btn-lg btn-success" id="submit-btn"><i class="bi bi-rocket-launch-fill me-2"></i> Rozpocznij Trening!</button></div></div>
    </form>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="validationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
      <strong class="me-auto">Błąd walidacji</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body">
      <!-- Wiadomość będzie wstawiana przez JS -->
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('start-shared-form');
    const friendsContainer = document.getElementById('friends-container');
    const plansContainer = document.getElementById('plans-container-selectable');
    const hiddenPlanInput = document.getElementById('selected_plan_id');
    const submitBtn = document.getElementById('submit-btn');
    const validationToastEl = document.getElementById('validationToast');
    const validationToast = new bootstrap.Toast(validationToastEl);

    // Walidacja po stronie klienta
    form.addEventListener('submit', (e) => {
        const selectedFriendsCount = form.querySelectorAll('input[name="participants[]"]:checked').length;
        if (selectedFriendsCount === 0) {
            e.preventDefault(); // Zatrzymaj wysyłanie formularza
            validationToastEl.querySelector('.toast-body').textContent = 'Musisz zaprosić co najmniej jednego znajomego!';
            validationToast.show();
            return;
        }
        if (hiddenPlanInput.value === '') {
            e.preventDefault();
            validationToastEl.querySelector('.toast-body').textContent = 'Musisz wybrać plan treningowy lub opcję Ad-Hoc!';
            validationToast.show();
            return;
        }
    });

    // Logika wyboru znajomych
    if (friendsContainer) {
        friendsContainer.addEventListener('click', e => {
            const card = e.target.closest('.choice-card');
            if (!card) return;
            card.classList.toggle('selected');
            const checkbox = card.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
        });
    }

    // Logika wyboru planu
    plansContainer.addEventListener('click', e => {
        const card = e.target.closest('.choice-card');
        if (!card) return;
        document.querySelectorAll('#plans-container-selectable .choice-card').forEach(c => c.classList.remove('selected'));
        card.classList.add('selected');
        hiddenPlanInput.value = card.dataset.planId;
    });

    // Logika filtrowania planów
    const searchInput = document.getElementById('plan-search-input');
    searchInput.addEventListener('input', () => {
        const searchTerm = searchInput.value.toLowerCase();
        document.querySelectorAll('.plan-item').forEach(item => {
            const planName = item.dataset.planName || '';
            item.style.display = planName.includes(searchTerm) ? '' : 'none';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>