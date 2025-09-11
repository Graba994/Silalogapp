<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$pageTitle = 'Zarządzaj Znajomymi';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$currentUserId = $_SESSION['user_id'];
$allUsers = json_decode(file_get_contents('data/users.json'), true);

// === KLUCZOWA ZMIANA: Używamy nowej funkcji, która gwarantuje, że profil istnieje ===
$socialData = ensure_social_profile_exists($currentUserId);

// Bezpieczne pobieranie danych użytkownika, z domyślnymi pustymi tablicami
$currentUserSocial = $socialData[$currentUserId] ?? [
    'friends' => [],
    'pending_sent' => [],
    'pending_received' => []
];
$usersById = array_column($allUsers, null, 'id');

// Przygotuj listy użytkowników do wyświetlenia
$friends = [];
foreach ($currentUserSocial['friends'] as $id) {
    if (isset($usersById[$id])) $friends[$id] = $usersById[$id];
}

$pendingReceived = [];
foreach ($currentUserSocial['pending_received'] as $id) {
    if (isset($usersById[$id])) $pendingReceived[$id] = $usersById[$id];
}

$pendingSent = [];
foreach ($currentUserSocial['pending_sent'] as $id) {
    if (isset($usersById[$id])) $pendingSent[$id] = $usersById[$id];
}

// Użytkownicy, których można zaprosić (nie są znajomymi i nie ma z nimi interakcji)
$canInvite = array_filter($allUsers, function($user) use ($currentUserId, $friends, $pendingReceived, $pendingSent) {
    $userId = $user['id'];
    return $userId !== $currentUserId && !isset($friends[$userId]) && !isset($pendingReceived[$userId]) && !isset($pendingSent[$userId]);
});

?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Zarządzaj Znajomymi</h1>
</div>

<div class="row g-4">
    <!-- Kolumna z istniejącymi i oczekującymi znajomymi -->
    <div class="col-lg-7">
        <!-- Otrzymane zaproszenia -->
        <?php if (!empty($pendingReceived)): ?>
        <div class="card mb-4 border-primary">
            <div class="card-header bg-primary-subtle"><h5 class="mb-0">Oczekujące zaproszenia</h5></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($pendingReceived as $user): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi <?= htmlspecialchars($user['icon']) ?> me-2"></i><?= htmlspecialchars($user['name']) ?></span>
                    <div class="d-flex gap-2">
                        <form action="manage_friend_request.php" method="POST" class="d-inline">
                            <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="accept">
                            <button type="submit" class="btn btn-sm btn-success">Akceptuj</button>
                        </form>
                        <form action="manage_friend_request.php" method="POST" class="d-inline">
                            <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Odrzuć</button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Twoi znajomi -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Twoi znajomi</h5></div>
            <?php if (empty($friends)): ?>
                <div class="card-body text-center text-muted">Nie masz jeszcze żadnych znajomych.</div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($friends as $user): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi <?= htmlspecialchars($user['icon']) ?> me-2"></i><?= htmlspecialchars($user['name']) ?></span>
                    <form action="manage_friend_request.php" method="POST" class="d-inline">
                        <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="remove">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Usuń</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Wysłane zaproszenia -->
        <?php if (!empty($pendingSent)): ?>
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Wysłane zaproszenia</h5></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($pendingSent as $user): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi <?= htmlspecialchars($user['icon']) ?> me-2"></i><?= htmlspecialchars($user['name']) ?></span>
                    <form action="manage_friend_request.php" method="POST" class="d-inline">
                        <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Anuluj</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- Kolumna z użytkownikami do zaproszenia -->
    <div class="col-lg-5">
        <div class="card sticky-top" style="top: 80px;">
            <div class="card-header bg-dark text-white"><h5 class="mb-0">Znajdź Gymbro</h5></div>
             <?php if (empty($canInvite)): ?>
                <div class="card-body text-center text-muted">Wszyscy użytkownicy są już Twoimi znajomymi lub otrzymali zaproszenie.</div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($canInvite as $user): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi <?= htmlspecialchars($user['icon']) ?> me-2"></i><?= htmlspecialchars($user['name']) ?></span>
                    <form action="manage_friend_request.php" method="POST" class="d-inline">
                        <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="btn btn-sm btn-info">Zaproś</button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>