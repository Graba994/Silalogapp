<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$planId = $_GET['plan_id'] ?? null;
$repeatWorkoutId = $_GET['repeat_workout_id'] ?? null;
$activeWorkoutPlan = null;

// === OSTATECZNA POPRAWKA: Prawidłowa obsługa wszystkich scenariuszy ===

if ($planId === 'adhoc') {
    // SCENARIUSZ 1: Użytkownik kliknął "Dodaj Trening Solo" (Ad-hoc)
    $activeWorkoutPlan = [
        'plan_name' => 'Nowy Trening (Ad-hoc)',
        'plan_description' => '',
        'exercises' => []
    ];
} elseif ($planId) {
    // SCENARIUSZ 2: Użytkownik wybrał konkretny plan
    $userPlans = get_user_plans($userId);
    foreach ($userPlans as $plan) {
        if ($plan['plan_id'] === $planId) {
            $activeWorkoutPlan = $plan;
            break;
        }
    }
} elseif ($repeatWorkoutId) {
    // SCENARIUSZ 3: Użytkownik chce powtórzyć stary trening
    $userWorkouts = get_user_workouts($userId);
    foreach ($userWorkouts as $workout) {
        if ($workout['workout_id'] === $repeatWorkoutId) {
            $activeWorkoutPlan = [
                'plan_name' => 'Powtórzony trening z ' . $workout['date'],
                'exercises' => array_map(function($ex) {
                    return ['exercise_id' => $ex['exercise_id'], 'target_sets' => $ex['sets']];
                }, $workout['exercises'])
            ];
            break;
        }
    }
}

// === Logika tworzenia pliku sesji (teraz działa dla wszystkich scenariuszy) ===
if ($activeWorkoutPlan) {
    $soloSessionDir = __DIR__ . '/data/solo_sessions/';
    if (!is_dir($soloSessionDir)) {
        if (!mkdir($soloSessionDir, 0777, true)) {
            die('BŁĄD KRYTYCZNY: Nie można utworzyć katalogu /data/solo_sessions/. Sprawdź uprawnienia!');
        }
    }
    
    $sessionFilePath = $soloSessionDir . 'solo_' . $userId . '.json';
    
    $existingData = [];
    if (file_exists($sessionFilePath)) {
        $existingData = json_decode(file_get_contents($sessionFilePath), true) ?? [];
    }

    $sessionData = [
        'user_id' => $userId,
        'start_time' => date('c'),
        'date' => $existingData['date'] ?? date('Y-m-d'),
        'notes' => $existingData['notes'] ?? ($activeWorkoutPlan['plan_description'] ?? ''),
        'plan' => $activeWorkoutPlan
    ];

    if (file_put_contents($sessionFilePath, json_encode($sessionData, JSON_PRETTY_PRINT)) === false) {
        die('BŁĄD KRYTYCZNY: Nie można zapisać pliku sesji w ' . $sessionFilePath . '. Sprawdź uprawnienia!');
    }
    
    unset($_SESSION['active_workout_plan']);
    
    header('Location: log_workout.php');
    exit();
}

// Jeśli nic nie pasuje, wróć do listy planów
header('Location: plans.php?error=plan_not_found');
exit();