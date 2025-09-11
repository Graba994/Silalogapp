<?php
// Plik: koks/coach_assign_plan.php
require_once 'includes/coach_guard.php';
$pageTitle = 'Zarządzaj Planami Podopiecznego';
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
if (!in_array($clientId, $clientIds) && $_SESSION['user_role'] !== 'admin') {
    header('Location: coach_panel.php?error=access_denied');
    exit();
}

// --- Pobieranie Danych ---
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$clientUser = null; foreach ($allUsers as $user) { if ($user['id'] === $clientId) { $clientUser = $user; break; } }
if (!$clientUser) {
    header('Location: coach_panel.php?error=client_not_found');
    exit();
}

$coachPlans = get_user_plans($coachId); // Plany trenera
$clientPlans = get_user_plans($clientId); // Plany klienta
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');
$successMessage = '';
$errorMessage = '';

// --- Logika Formularzy (Przypisywanie i Usuwanie) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'assign' && isset($_POST['plan_id'])) {
        $planIdToAssign = $_POST['plan_id'];
        $planToAssign = null;
        // Znajdź plan w planach trenera
        foreach ($coachPlans as $plan) {
            if ($plan['plan_id'] === $planIdToAssign) {
                $planToAssign = $plan;
                break;
            }
        }

        if ($planToAssign) {
            $newPlanForClient = $planToAssign;
            // Nadaj nowe, unikalne ID i oznacz, że pochodzi od trenera
            $newPlanForClient['plan_id'] = 'p_' . date('YmdHis') . '_' . bin2hex(random_bytes(2));
            $newPlanForClient['plan_description'] = "Przypisano przez trenera: {$_SESSION['user_name']}. " . ($planToAssign['plan_description'] ?? '');
            
            $clientPlans[] = $newPlanForClient;
            if (save_user_plans($clientId, $clientPlans)) {
                $successMessage = "Pomyślnie przypisano plan '{$planToAssign['plan_name']}' do podopiecznego.";
            } else {
                $errorMessage = "Błąd zapisu planu podopiecznego.";
            }
        }
    }

    if ($action === 'unassign' && isset($_POST['plan_id'])) {
        $planIdToUnassign = $_POST['plan_id'];
        // Przefiltruj plany klienta, usuwając ten o podanym ID
        $clientPlans = array_filter($clientPlans, fn($p) => $p['plan_id'] !== $planIdToUnassign);
        
        if (save_user_plans($clientId, array_values($clientPlans))) {
            $successMessage = "Pomyślnie usunięto plan z konta podopiecznego.";
        } else {
            $errorMessage = "Błąd podczas usuwania planu podopiecznego.";
        }
    }
}

// Odśwież plany klienta po operacji
$clientPlans = get_user_plans($clientId);
require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Zarządzaj Planami</h1>
        <p class="lead text-muted mb-0">Podopieczny: <strong><?= htmlspecialchars($clientUser['name']) ?></strong></p>
    </div>
    <a href="coach_panel.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle me-2"></i>Wróć do panelu trenera</a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<div class="row g-4">
    <!-- LEWA KOLUMNA: PLANY PODOPIECZNEGO -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white"><h5 class="mb-0">Plany Przypisane Podopiecznemu</h5></div>
            <div class="list-group list-group-flush">
                <?php if (empty($clientPlans)): ?>
                    <div class="list-group-item text-center text-muted p-4">Ten użytkownik nie ma jeszcze żadnych planów.</div>
                <?php else: ?>
                    <?php foreach ($clientPlans as $plan): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($plan['plan_name']) ?></h6>
                                <form method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć ten plan z konta podopiecznego?');">
                                    <input type="hidden" name="action" value="unassign">
                                    <input type="hidden" name="plan_id" value="<?= htmlspecialchars($plan['plan_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Usuń plan"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                            <p class="mb-1 small text-muted"><?= htmlspecialchars($plan['plan_description']) ?></p>
                            <small><?= count($plan['exercises']) ?> ćwiczeń</small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PRAWA KOLUMNA: TWOJE PLANY DO PRZYPISANIA -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white"><h5 class="mb-0">Twoje Plany (do przypisania)</h5></div>
            <div class="list-group list-group-flush">
                <?php if (empty($coachPlans)): ?>
                    <div class="list-group-item text-center text-muted p-4">Nie masz żadnych własnych planów, które mógłbyś przypisać. <a href="create_plan.php">Stwórz plan</a>.</div>
                <?php else: ?>
                    <?php foreach ($coachPlans as $plan): ?>
                        <div class="list-group-item">
                             <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($plan['plan_name']) ?></h6>
                                <form method="POST">
                                    <input type="hidden" name="action" value="assign">
                                    <input type="hidden" name="plan_id" value="<?= htmlspecialchars($plan['plan_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-success" title="Przypisz ten plan">
                                        <i class="bi bi-check-lg"></i> Przypisz
                                    </button>
                                </form>
                            </div>
                            <p class="mb-1 small text-muted"><?= htmlspecialchars($plan['plan_description']) ?></p>
                            <small><?= count($plan['exercises']) ?> ćwiczeń</small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>