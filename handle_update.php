<?php
require_once 'includes/admin_guard.php';
require_once 'includes/functions.php';

set_time_limit(300);

// === KROK 1: Walidacja przesłanego pliku ===
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['update_message'] = ['type' => 'danger', 'text' => 'Błąd przesyłania pliku. Spróbuj ponownie.'];
    header('Location: admin_update.php');
    exit();
}

$file = $_FILES['update_zip'];
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);

if (strtolower($fileExtension) !== 'zip') {
    $_SESSION['update_message'] = ['type' => 'danger', 'text' => 'Nieprawidłowy format pliku. Akceptowane są tylko pliki .zip.'];
    header('Location: admin_update.php');
    exit();
}

// === KROK 2: Automatyczna kopia zapasowa PRZED aktualizacją ===
$backupResult = create_backup();
if (!$backupResult['success']) {
    $_SESSION['update_message'] = ['type' => 'danger', 'text' => 'KRYTYCZNY BŁĄD: Nie udało się utworzyć kopii zapasowej. Proces aktualizacji został przerwany. Błąd: ' . $backupResult['message']];
    header('Location: admin_update.php');
    exit();
}

// === KROK 3: Przetwarzanie pliku ZIP ===
$zip = new ZipArchive();
$tempDir = rtrim(sys_get_temp_dir(), '/') . '/silalog_update_' . time();
$appRoot = realpath(__DIR__ . '/../koks'); // Upewnij się, że to jest poprawna ścieżka do głównego folderu aplikacji 'koks'

if ($zip->open($file['tmp_name']) === TRUE) {
    if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
        $_SESSION['update_message'] = ['type' => 'danger', 'text' => 'Nie można utworzyć tymczasowego katalogu do rozpakowania aktualizacji.'];
        header('Location: admin_update.php');
        exit();
    }
    
    $zip->extractTo($tempDir);
    $zip->close();

    // === KROK 4: Inteligentne nadpisywanie plików ===
    $sourceIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS));
    
    $foldersToIgnore = ['data', 'AutoBackup'];
    $filesUpdated = 0;

    foreach ($sourceIterator as $sourceFile) {
        $sourcePath = $sourceFile->getRealPath();
        $relativePath = substr($sourcePath, strlen($tempDir) + 1);

        // Sprawdź, czy plik nie jest w ignorowanym folderze
        $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
        if (in_array($pathParts[0], $foldersToIgnore)) {
            continue; // Pomiń ten plik/folder
        }
        
        $destinationPath = $appRoot . '/' . $relativePath;
        
        if ($sourceFile->isDir()) {
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0775, true);
            }
        } else {
            // Upewnij się, że folder docelowy istnieje
            $destinationDir = dirname($destinationPath);
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0775, true);
            }
            // Kopiuj plik
            if (copy($sourcePath, $destinationPath)) {
                $filesUpdated++;
            }
        }
    }

    // === KROK 5: Sprzątanie ===
    $cleanupIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($cleanupIterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($tempDir);

    $_SESSION['update_message'] = ['type' => 'success', 'text' => "Aktualizacja zakończona pomyślnie! Zaktualizowano {$filesUpdated} plików. Stworzono kopię zapasową przed operacją."];

} else {
    $_SESSION['update_message'] = ['type' => 'danger', 'text' => 'Nie udało się otworzyć archiwum ZIP.'];
}

header('Location: admin_update.php');
exit();