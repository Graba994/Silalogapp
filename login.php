<?php
session_start();
require_once 'includes/functions.php'; // Potrzebujemy funkcji do wczytania użytkowników

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['password'])) {
    $userId = strtolower(trim($_POST['user_id']));
    $password = $_POST['password'];

    $usersData = file_get_contents('data/users.json');
    $users = json_decode($usersData, true);
    
     $foundUser = null;
    $users = json_decode(file_get_contents('data/users.json'), true);
    foreach ($users as $user) {
        if ($user['id'] === $_POST['user_id']) {
            $foundUser = $user;
            break;
        }
    }

    if ($foundUser && password_verify($_POST['password'], $foundUser['password_hash'])) {
        // Logowanie poprawne
        $_SESSION['user_id'] = $foundUser['id'];
        $_SESSION['user_name'] = $foundUser['name'];
        $_SESSION['user_body_params'] = $foundUser['body_params']; 
        $_SESSION['user_role'] = $foundUser['role'] ?? 'user'; // <-- DODAJ TĘ LINIĘ
        
        header('Location: dashboard.php');
        exit();
    } else {
        // Błędne dane
        $_SESSION['login_error'] = 'Nieprawidłowa nazwa użytkownika lub hasło.';
        header('Location: index.php');
        exit();
    }
}

// Jeśli ktoś wejdzie na stronę bez POST
header('Location: index.php');
exit();