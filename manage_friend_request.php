<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];
$targetUserId = $_POST['target_user_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$targetUserId || !$action) {
    header('Location: friends.php?error=missing_data');
    exit();
}

$socialData = get_social_data();

// Upewnij się, że obaj użytkownicy istnieją w pliku social.json
if (!isset($socialData[$currentUserId]) || !isset($socialData[$targetUserId])) {
    header('Location: friends.php?error=user_not_found');
    exit();
}

switch ($action) {
    case 'add':
        // Dodaj ID celu do wysłanych zaproszeń aktualnego usera
        $socialData[$currentUserId]['pending_sent'][] = $targetUserId;
        // Dodaj ID aktualnego usera do otrzymanych zaproszeń celu
        $socialData[$targetUserId]['pending_received'][] = $currentUserId;
        break;

    case 'accept':
        // Usuń zaproszenie z obu stron
        $socialData[$currentUserId]['pending_received'] = array_diff($socialData[$currentUserId]['pending_received'], [$targetUserId]);
        $socialData[$targetUserId]['pending_sent'] = array_diff($socialData[$targetUserId]['pending_sent'], [$currentUserId]);
        // Dodajcie się nawzajem do znajomych
        $socialData[$currentUserId]['friends'][] = $targetUserId;
        $socialData[$targetUserId]['friends'][] = $currentUserId;
        break;

    case 'decline':
    case 'cancel': // Anulowanie wysłanego zaproszenia to to samo co odrzucenie otrzymanego
        $socialData[$currentUserId]['pending_received'] = array_diff($socialData[$currentUserId]['pending_received'], [$targetUserId]);
        $socialData[$currentUserId]['pending_sent'] = array_diff($socialData[$currentUserId]['pending_sent'], [$targetUserId]);
        $socialData[$targetUserId]['pending_received'] = array_diff($socialData[$targetUserId]['pending_received'], [$currentUserId]);
        $socialData[$targetUserId]['pending_sent'] = array_diff($socialData[$targetUserId]['pending_sent'], [$currentUserId]);
        break;

    case 'remove':
        // Usuńcie się nawzajem ze znajomych
        $socialData[$currentUserId]['friends'] = array_diff($socialData[$currentUserId]['friends'], [$targetUserId]);
        $socialData[$targetUserId]['friends'] = array_diff($socialData[$targetUserId]['friends'], [$currentUserId]);
        break;
}

// Oczyść tablice z duplikatów i zresetuj indeksy
foreach ($socialData as &$userSocial) {
    foreach ($userSocial as &$list) {
        $list = array_values(array_unique($list));
    }
}

save_social_data($socialData);
header('Location: friends.php?status=success');
exit();