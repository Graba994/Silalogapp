<?php
require_once 'includes/admin_guard.php';

$filename = $_GET['file'] ?? null;

if (!$filename) {
    $_SESSION['update_message'] = [
        'type' => 'danger',
        'text' => 'Nie podano nazwy pliku do usunięcia.'
    ];
    header('Location: admin_update.php');
    exit();
}

$backupDir = '../AutoBackup/';
// Używamy realpath, aby uzyskać absolutną ścieżkę i zapobiec atakom typu "directory traversal"
$filePath = realpath($backupDir . $filename);
$realBackupDir = realpath($backupDir);

// Sprawdź, czy plik istnieje i czy na pewno znajduje się w folderze AutoBackup
if ($filePath && strpos($filePath, $realBackupDir) === 0 && file_exists($filePath)) {
    if (unlink($filePath)) {
        $_SESSION['update_message'] = [
            'type' => 'success',
            'text' => 'Kopia zapasowa "' . htmlspecialchars($filename) . '" została usunięta.'
        ];
    } else {
        $_SESSION['update_message'] = [
            'type' => 'danger',
            'text' => 'Nie udało się usunąć pliku. Sprawdź uprawnienia serwera.'
        ];
    }
} else {
    $_SESSION['update_message'] = [
        'type' => 'danger',
        'text' => 'Plik nie istnieje lub próbowano usunąć plik spoza dozwolonego katalogu.'
    ];
}

header('Location: admin_update.php');
exit();