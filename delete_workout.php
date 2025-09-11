<?php
session_start();
require_once 'includes/functions.php';

// Zabezpieczenie: tylko metoda POST i zalogowany użytkownik
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$workoutIdToDelete = $_POST['workout_id'] ?? null;

if (!$workoutIdToDelete) {
    header('Location: history.php?error=missingid');
    exit();
}

$userWorkouts = get_user_workouts($userId);

// Przefiltruj tablicę, aby usunąć trening o podanym ID
$updatedWorkouts = array_filter($userWorkouts, function($workout) use ($workoutIdToDelete) {
    return $workout['workout_id'] !== $workoutIdToDelete;
});

// Zapisz zaktualizowaną tablicę, resetując indeksy
if (save_user_workouts($userId, array_values($updatedWorkouts))) {
    header('Location: history.php?status=deleted');
} else {
    header('Location: history.php?error=save');
}
exit();