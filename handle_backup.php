<?php
require_once 'includes/admin_guard.php';
require_once 'includes/functions.php';

// Ustawienie dłuższego czasu wykonywania skryptu, na wypadek dużych plików
set_time_limit(300); 

$result = create_backup();

if ($result['success']) {
    $_SESSION['update_message'] = [
        'type' => 'success',
        'text' => $result['message']
    ];
} else {
    $_SESSION['update_message'] = [
        'type' => 'danger',
        'text' => 'Błąd: ' . $result['message']
    ];
}

header('Location: admin_update.php');
exit();