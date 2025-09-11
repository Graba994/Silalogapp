<?php
require_once 'includes/admin_guard.php';
require_once 'includes/functions.php';

$userIdToDelete = $_GET['id'] ?? null;

if ($userIdToDelete && $userIdToDelete !== $_SESSION['user_id']) {
    $allUsers = json_decode(file_get_contents('data/users.json'), true);
    
    // Usuń użytkownika z głównej listy
    $updatedUsers = array_filter($allUsers, fn($user) => $user['id'] !== $userIdToDelete);
    file_put_contents('data/users.json', json_encode(array_values($updatedUsers), JSON_PRETTY_PRINT));

    // Usuń powiązane pliki danych
    $filesToDelete = [
        "data/workouts_{$userIdToDelete}.json",
        "data/plans_{$userIdToDelete}.json",
        "data/goals_{$userIdToDelete}.json",
        "data/measurements_{$userIdToDelete}.json"
    ];
    foreach ($filesToDelete as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    // Usuń użytkownika z pliku social.json
    $socialData = get_social_data();
    if (isset($socialData[$userIdToDelete])) {
        unset($socialData[$userIdToDelete]);
        foreach ($socialData as &$userData) {
            $userData['friends'] = array_values(array_diff($userData['friends'], [$userIdToDelete]));
            $userData['pending_sent'] = array_values(array_diff($userData['pending_sent'], [$userIdToDelete]));
            $userData['pending_received'] = array_values(array_diff($userData['pending_received'], [$userIdToDelete]));
        }
        save_social_data($socialData);
    }
}

header('Location: admin.php');
exit();