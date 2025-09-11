<?php
// Plik: koks/coach_panel.php
require_once 'includes/coach_guard.php'; // Zabezpieczenie na samej górze!
$pageTitle = 'Panel Trenera';
require_once 'includes/functions.php'; // Główne funkcje
require_once 'includes/header.php';

$coachId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// --- Logika Zarządzania Klientami ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $coachingData = get_coaching_data();
    $action = $_POST['action'] ?? null;
    $clientId = $_POST['client_id'] ?? null;

    if ($action === 'add' && $clientId) {
        if (!isset($coachingData[$coachId])) $coachingData[$coachId] = [];
        if (!in_array($clientId, $coachingData[$coachId])) {
            $coachingData[$coachId][] = $clientId;
            $successMessage = "Dodano nowego podopiecznego.";
        } else {
            $errorMessage = "Ten użytkownik jest już Twoim podopiecznym.";
        }
    }

    if ($action === 'remove' && $clientId) {
        if (isset($coachingData[$coachId])) {
            $coachingData[$coachId] = array_diff($coachingData[$coachId], [$clientId]);
            $successMessage = "Usunięto podopiecznego z Twojej listy.";
        }
    }

    if (!save_coaching_data($coachingData)) {
        $errorMessage = "Wystąpił błąd podczas zapisu danych.";
    }
}

// --- Przygotowanie Danych do Wyświetlenia ---
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$usersById = array_column($allUsers, null, 'id');
$coachingData = get_coaching_data();
$clientIds = $coachingData[$coachId] ?? [];

$clients = [];
foreach($clientIds as $clientId) {
    if (isset($usersById[$clientId])) $clients[] = $usersById[$clientId];
}

$potentialClients = array_filter($allUsers, function($user) use ($coachId, $clientIds) {
    $isNotCoachOrAdmin = !in_array($user['role'], ['coach', 'admin']);
    $isNotSelf = $user['id'] !== $coachId;
    $isNotAlreadyClient = !in_array($user['id'], $clientIds);
    return $isNotCoachOrAdmin && $isNotSelf && $isNotAlreadyClient;
});
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1 class="mb-0">Panel Trenera</h1>
        <p class="text-muted mb-0">Zarządzaj podopiecznymi i prowadź sesje treningowe.</p>
    </div>
    <a href="coach_start_session.php" class="btn btn-success btn-lg">
        <i class="bi bi-play-circle-fill me-2"></i>Rozpocznij Nową Sesję
    </a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<div class="row g-4">
    <!-- LEWA KOLUMNA: LISTA PODOPIECZNYCH -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Moi Podopieczni</h5>
                <span class="badge bg-light text-dark"><?= count($clients) ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($clients)): ?>
                    <div class="list-group-item text-center text-muted p-5">
                        <p class="mb-0">Nie masz jeszcze żadnych podopiecznych.</p>
                        <small>Dodaj ich z panelu po prawej stronie.</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                            <i class="bi <?= htmlspecialchars($client['icon']) ?> fs-4 me-2"></i>
                            <div class="flex-grow-1">
                                <strong class="d-block"><?= htmlspecialchars($client['name']) ?></strong>
                                <small class="text-muted"><?= htmlspecialchars($client['id']) ?></small>
                            </div>
                            <div class="ms-auto btn-group">
                                <!-- NOWY PRZYCISK KALENDARZA -->
                                <a href="coach_calendar.php?client_id=<?= urlencode($client['id']) ?>" class="btn btn-sm btn-outline-success" title="Zobacz kalendarz podopiecznego">
                                    <i class="bi bi-calendar-event"></i> Kalendarz
                                </a>
                                <a href="coach_view_history.php?client_id=<?= urlencode($client['id']) ?>" class="btn btn-sm btn-outline-primary" title="Zobacz historię i statystyki">
                                    <i class="bi bi-clock-history"></i> Historia
                                </a>
                                <a href="coach_assign_plan.php?client_id=<?= urlencode($client['id']) ?>" class="btn btn-sm btn-outline-info" title="Zarządzaj planami treningowymi">
                                    <i class="bi bi-journal-text"></i> Plany
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Czy na pewno chcesz usunąć tego podopiecznego ze swojej listy?');">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($client['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Usuń podopiecznego">
                                        <i class="bi bi-person-x-fill"></i> Usuń
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- PRAWA KOLUMNA: DODAJ PODOPIECZNEGO -->
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">Dodaj Podopiecznego</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="client_id" class="form-label">Wybierz użytkownika</label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="" disabled selected>-- Wybierz z listy --</option>
                            <?php foreach ($potentialClients as $user): ?>
                                <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-person-plus-fill me-2"></i>Dodaj do moich podopiecznych
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>