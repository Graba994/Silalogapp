<?php
// Plik: koks/coach_calendar.php
require_once 'includes/coach_guard.php';
$pageTitle = 'Kalendarz Podopiecznego';

$clientId = $_GET['client_id'] ?? null;
if (!$clientId) { header('Location: coach_panel.php?error=no_client_id'); exit(); }
$coachingData = get_coaching_data();
$coachId = $_SESSION['user_id'];
$clientIds = $coachingData[$coachId] ?? [];
if (!in_array($clientId, $clientIds) && $_SESSION['user_role'] !== 'admin') { header('Location: coach_panel.php?error=access_denied'); exit(); }
require_once 'includes/functions.php';

$allUsers = json_decode(file_get_contents('data/users.json'), true);
// UŻYCIE NOWEJ FUNKCJI POMOCNICZEJ
$clientUser = find_item_in_array($allUsers, $clientId);
if (!$clientUser) { header('Location: coach_panel.php?error=client_not_found'); exit(); }

// --- Przygotowanie Danych dla Kalendarza ---
$clientWorkouts = get_user_workouts($clientId);
$completedEvents = [];
foreach ($clientWorkouts as $workout) {
    $completedEvents[] = [
        'title' => '✔ Wykonany Trening', 'start' => $workout['date'],
        'url' => 'history.php?page=1&start_date=' . $workout['date'] . '&end_date=' . $workout['date'],
        'classNames' => ['event-completed'], 'isCompleted' => true, 'color' => 'var(--bs-success)'
    ];
}

$clientSchedule = get_user_schedule($clientId);
$scheduledEvents = [];
foreach ($clientSchedule as $event) {
    $isDone = false;
    foreach($clientWorkouts as $w) { if($w['date'] === $event['start']) { $isDone = true; break; } }
    if ($isDone) continue;

    $scheduledEvents[] = [
        'id' => $event['id'], 'title' => ($event['type'] === 'coach' ? '★ ' : '') . $event['title'], 'start' => $event['start'],
        'color' => $event['color'],
        'extendedProps' => [
            'planId' => $event['planId'], 'type' => $event['type'],
            'isCoachSession' => $event['isCoachSession'] ?? false, 'createdBy' => $event['createdBy'] ?? null
        ]
    ];
}

$allCalendarEvents = array_merge($completedEvents, $scheduledEvents);
$allCalendarEventsJson = json_encode($allCalendarEvents);
$coachPlans = get_user_plans($coachId);
$coachPlansJson = json_encode($coachPlans);

require_once 'includes/header.php';
?>
<style>
.event-completed { background-color: var(--bs-success-bg-subtle) !important; border-color: var(--bs-success-border-subtle) !important; }
.event-completed .fc-event-title { text-decoration: line-through; color: var(--bs-secondary-color); }
.fc-event[href]:hover { cursor: pointer; }
★ { color: var(--bs-warning); }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Kalendarz Aktywności</h1>
        <p class="lead text-muted mb-0">Podopieczny: <strong><?= htmlspecialchars($clientUser['name']) ?></strong></p>
    </div>
    <a href="coach_panel.php" class="btn btn-secondary"><i class="bi bi-arrow-left-circle me-2"></i>Wróć do panelu trenera</a>
</div>

<div class="card">
    <div class="card-body"><div id="activity-calendar"></div></div>
    <div class="card-footer text-muted small"><i class="bi bi-info-circle-fill me-2"></i>Kliknij pusty dzień, aby zaplanować nowy trening dla podopiecznego.</div>
</div>

<script>
    window.allCalendarEvents = <?= $allCalendarEventsJson ?? '[]' ?>;
    window.userPlansForCalendar = <?= $coachPlansJson ?? '[]' ?>;
    window.calendarUserId = '<?= htmlspecialchars($clientId) ?>';
    window.currentUserId = '<?= htmlspecialchars($coachId) ?>';
    window.currentUserRole = '<?= htmlspecialchars($_SESSION['user_role'] ?? 'user') ?>';
    window.isCoachView = true;
</script>

<?php require_once 'includes/footer.php'; ?>