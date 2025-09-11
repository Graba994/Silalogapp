<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$sessionFilePath = 'data/solo_sessions/solo_' . $userId . '.json';

// Sprawdź, czy plik sesji istnieje i usuń go
if (file_exists($sessionFilePath)) {
    unlink($sessionFilePath);
}

// Przekieruj z powrotem na dashboard
header('Location: dashboard.php');
exit();