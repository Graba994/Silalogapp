<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['plan_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$planIdToDelete = $_GET['plan_id'];

$userPlans = get_user_plans($userId);

// Przefiltruj tablicę, aby usunąć plan o podanym ID
$updatedPlans = array_filter($userPlans, function($plan) use ($planIdToDelete) {
    return $plan['plan_id'] !== $planIdToDelete;
});

// Zapisz zaktualizowaną tablicę planów
// array_values jest używane, aby zresetować indeksy tablicy po usunięciu
if (save_user_plans($userId, array_values($updatedPlans))) {
    // Opcjonalnie: można dodać komunikat o sukcesie do sesji, aby wyświetlić go na plans.php
} else {
    // Opcjonalnie: obsługa błędu
}

header('Location: plans.php');
exit();