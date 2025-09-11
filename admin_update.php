<?php
require_once 'includes/admin_guard.php';
$pageTitle = 'Aktualizacja i Kopie Zapasowe';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Funkcja pomocnicza do formatowania rozmiaru pliku
function format_bytes($size, $precision = 2) {
    if ($size == 0) return "0 B";
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// === POPRAWIONA ŚCIEŻKA ===
// Używamy __DIR__ dla pewności, że ścieżka jest zawsze poprawna, niezależnie od tego, skąd uruchamiany jest skrypt.
$backupDir = __DIR__ . '/../AutoBackup/'; 
$backupFiles = glob($backupDir . '*.zip');
// Sortuj od najnowszej do najstarszej
if ($backupFiles) {
    usort($backupFiles, fn($a, $b) => filemtime($b) <=> filemtime($a));
}

// Sprawdź komunikaty z sesji
$message = $_SESSION['update_message'] ?? null;
unset($_SESSION['update_message']);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Aktualizacja i Kopie Zapasowe</h1>
    <a href="admin.php" class="btn btn-secondary">Wróć do panelu admina</a>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message['text']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- SEKCJA AKTUALIZACJI -->
    <div class="col-lg-6">
        <div class="card border-warning shadow-sm h-100">
            <div class="card-header bg-warning-subtle">
                <h5 class="mb-0"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Aktualizuj program</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Uwaga!</strong> Ta operacja nadpisze istniejące pliki programu nową wersją. Twoje dane w folderze `/data` pozostaną nienaruszone. Przed aktualizacją automatycznie zostanie wykonana pełna kopia zapasowa.</div>
                <form action="handle_update.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="update_zip" class="form-label">Wybierz plik aktualizacyjny (.zip)</label>
                        <input class="form-control" type="file" id="update_zip" name="update_zip" accept=".zip" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Czy na pewno chcesz rozpocząć proces aktualizacji?');">
                        <i class="bi bi-upload me-2"></i>Aktualizuj
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SEKCJA KOPII ZAPASOWYCH -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-archive-fill me-2"></i>Kopie Zapasowe</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Program przechowuje do 20 najnowszych kopii. Najstarsze są automatycznie usuwane.</p>
                <div class="d-grid mb-3">
                    <a href="handle_backup.php" class="btn btn-info">
                        <i class="bi bi-plus-circle-dotted me-2"></i>Utwórz kopię zapasową teraz
                    </a>
                </div>

                <h6 class="mt-4">Istniejące kopie:</h6>
                <?php if (empty($backupFiles)): ?>
                    <p class="text-muted text-center">Brak utworzonych kopii zapasowych.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($backupFiles as $file): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-zip me-2"></i>
                                <strong><?= htmlspecialchars(basename($file)) ?></strong>
                                <small class="d-block text-muted">
                                    <?= date('Y-m-d H:i:s', filemtime($file)) ?> | <?= format_bytes(filesize($file)) ?>
                                </small>
                            </div>
                            <div class="btn-group">
                                <a href="AutoBackup/<?= htmlspecialchars(basename($file)) ?>" class="btn btn-sm btn-outline-secondary" title="Pobierz" download>
                                    <i class="bi bi-download"></i>
                                </a>
                                <a href="handle_backup_delete.php?file=<?= urlencode(basename($file)) ?>" class="btn btn-sm btn-outline-danger" title="Usuń" onclick="return confirm('Czy na pewno chcesz usunąć tę kopię zapasową?');">
                                    <i class="bi bi-trash3"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>