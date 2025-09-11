<?php
// KROK 1: CAŁA LOGIKA PRZETWARZANIA NA SAMYM POCZĄTKU
session_start();
require_once 'includes/functions.php';

$groupId = $_GET['id'] ?? null;
if (!$groupId) {
    header('Location: manage_tags.php');
    exit();
}

// Logika aktualizacji - uruchamiana ZANIM cokolwiek zostanie wyświetlone
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newGroupName = trim($_POST['group_name'] ?? '');
    $newGroupColor = $_POST['group_color'] ?? 'secondary';
    
    if (empty($newGroupName)) {
        // W przyszłości można tu ustawić komunikat błędu w sesji
        // Na razie po prostu nie robimy nic i pozwalamy formularzowi się załadować ponownie
    } else {
        $allGroups = get_all_tags();
        $groupIndex = null;
        foreach ($allGroups as $index => $group) {
            if ($group['group_id'] === $groupId) {
                $groupIndex = $index;
                break;
            }
        }
        
        if ($groupIndex !== null) {
            $allGroups[$groupIndex]['group_name'] = htmlspecialchars($newGroupName);
            $allGroups[$groupIndex]['color'] = htmlspecialchars($newGroupColor);
            
            if (save_tags($allGroups)) {
                // Przekierowanie - TERAZ ZADZIAŁA POPRAWNIE
                header('Location: manage_tags.php?status=group_updated');
                exit();
            }
        }
    }
}


// KROK 2: PRZYGOTOWANIE DANYCH DO WYŚWIETLENIA FORMULARZA
// Ta część kodu wykona się tylko wtedy, gdy żądanie NIE jest POSTem lub gdy POST miał błędy
$pageTitle = 'Edytuj Grupę Tagów';
require_once 'includes/header.php'; // HEADER JEST TERAZ TUTAJ

$allGroups = get_all_tags();
$groupToEdit = null;

foreach ($allGroups as $group) {
    if ($group['group_id'] === $groupId) {
        $groupToEdit = $group;
        break;
    }
}

if (!$groupToEdit) {
    echo "<div class='alert alert-danger'>Nie znaleziono grupy o podanym ID.</div>";
    require_once 'includes/footer.php';
    exit();
}

// Lista kolorów dostępnych w Bootstrap + nasz fioletowy
$bootstrapColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'purple'];
?>

<!-- KROK 3: WYŚWIETLANIE FORMULARZA HTML -->
<div class="card mx-auto" style="max-width: 500px;">
    <div class="card-header bg-dark text-white">
        <h1 class="h4 mb-0">Edytujesz grupę: 
            <span class="badge bg-<?= htmlspecialchars($groupToEdit['color']) ?>"><?= htmlspecialchars($groupToEdit['group_name']) ?></span>
        </h1>
    </div>
    <div class="card-body">
        <form method="POST" action="edit_group.php?id=<?= urlencode($groupId) ?>">
            <div class="mb-3">
                <label for="group_name" class="form-label">Nowa nazwa grupy</label>
                <input type="text" class="form-control" id="group_name" name="group_name" value="<?= htmlspecialchars($groupToEdit['group_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="group_color" class="form-label">Kolor grupy</label>
                <select id="group_color" name="group_color" class="form-select">
                    <?php foreach($bootstrapColors as $color): ?>
                    <option value="<?= $color ?>" <?= ($groupToEdit['color'] === $color) ? 'selected' : '' ?>>
                        <span class="badge bg-<?= $color ?>">●</span> <?= ucfirst($color) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="manage_tags.php" class="btn btn-secondary">Anuluj</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>