<?php
// Plik: koks/import_plan.php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$currentUserId = $_SESSION['user_id'];
$sourceUserId = $_POST['source_user_id'] ?? null;
$planIdToImport = $_POST['plan_id'] ?? null;

if (!$sourceUserId || !$planIdToImport) {
    // Błąd, wróć do listy planów
    header('Location: plans.php?import=error');
    exit();
}

// 1. Pobierz plany źródłowe i docelowe
$sourcePlans = get_user_plans($sourceUserId);
$currentUserPlans = get_user_plans($currentUserId);

// 2. Znajdź plan do zaimportowania
$planToImport = null;
foreach ($sourcePlans as $plan) {
    if ($plan['plan_id'] === $planIdToImport) {
        $planToImport = $plan;
        break;
    }
}

if ($planToImport) {
    // 3. Przygotuj skopiowany plan
    $importedPlan = $planToImport;

    // Nadaj nowe, unikalne ID, aby uniknąć konfliktów
    $importedPlan['plan_id'] = 'p_' . date('YmdHis') . '_' . bin2hex(random_bytes(2));

    // Oznacz plan jako importowany, dodając imię autora
    $allUsers = json_decode(file_get_contents('data/users.json'), true);
    $sourceUserName = 'Gymbro'; // Domyślna nazwa
    foreach ($allUsers as $user) {
        if ($user['id'] === $sourceUserId) {
            $sourceUserName = $user['name'];
            break;
        }
    }
    $importedPlan['plan_name'] = "[{$sourceUserName}] " . $planToImport['plan_name'];
    $importedPlan['plan_description'] = "Zaimportowano od {$sourceUserName}. " . ($planToImport['plan_description'] ?? '');

    // 4. Dodaj skopiowany plan do planów aktualnego użytkownika
    $currentUserPlans[] = $importedPlan;

    // 5. Zapisz zaktualizowaną listę planów
    save_user_plans($currentUserId, $currentUserPlans);

    // Przekieruj z komunikatem o sukcesie
    header('Location: plans.php?import=success');
    exit();
}

// Jeśli nie znaleziono planu
header('Location: plans.php?import=notfound');
exit();