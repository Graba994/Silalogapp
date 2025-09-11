<?php
$pageTitle = 'Zarządzaj Tagami i Grupami';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$successMessage = '';
$errorMessage = '';
$allTagGroups = get_all_tags();

// --- LOGIKA DODAWANIA NOWEJ GRUPY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group'])) {
    $groupName = trim($_POST['group_name'] ?? '');
    $groupColor = $_POST['group_color'] ?? 'secondary';

    if (empty($groupName)) {
        $errorMessage = "Nazwa grupy nie może być pusta.";
    } else {
        $groupId = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $groupName));
        $exists = false;
        foreach ($allTagGroups as $group) {
            if ($group['group_id'] === $groupId || strcasecmp($group['group_name'], $groupName) === 0) {
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $errorMessage = "Grupa o takiej nazwie lub ID już istnieje.";
        } else {
            $allTagGroups[] = [
                'group_id' => $groupId,
                'group_name' => htmlspecialchars($groupName),
                'color' => htmlspecialchars($groupColor),
                'tags' => [] // Nowa grupa jest na początku pusta
            ];
            if (save_tags($allTagGroups)) {
                $successMessage = "Nowa grupa '{$groupName}' została dodana.";
            } else {
                $errorMessage = "Wystąpił błąd podczas zapisywania grup.";
            }
        }
    }
}

// --- LOGIKA DODAWANIA TAGU DO GRUPY ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag_to_group'])) {
    $tagName = trim($_POST['tag_name'] ?? '');
    $groupId = $_POST['group_id'] ?? '';

    if (empty($tagName) || empty($groupId)) {
        $errorMessage = "Nazwa taga i ID grupy są wymagane.";
    } else {
        $tagId = $groupId . '_' . strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $tagName));
        $tagExists = false;
        $groupFound = false;

        foreach ($allTagGroups as &$group) { // Zwróć uwagę na & - modyfikujemy tablicę
            if ($group['group_id'] === $groupId) {
                $groupFound = true;
                foreach ($group['tags'] as $tag) {
                    if ($tag['id'] === $tagId || strcasecmp($tag['name'], $tagName) === 0) {
                        $tagExists = true;
                        break;
                    }
                }
                if (!$tagExists) {
                    $group['tags'][] = ['id' => $tagId, 'name' => htmlspecialchars($tagName)];
                }
                break;
            }
        }

        if (!$groupFound) {
            $errorMessage = "Nie znaleziono grupy o podanym ID.";
        } elseif ($tagExists) {
            $errorMessage = "Tag o tej nazwie lub ID już istnieje w tej grupie.";
        } else {
            if (save_tags($allTagGroups)) {
                $successMessage = "Nowy tag '{$tagName}' został dodany.";
            } else {
                $errorMessage = "Wystąpił błąd podczas zapisywania tagu.";
            }
        }
    }
}

// Pobierz świeże dane po ewentualnych zmianach
$allTagGroups = get_all_tags();
$bootstrapColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'dark', 'purple'];
?>

<!-- Komunikaty -->
<?php if ($successMessage): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $successMessage ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= $errorMessage ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Zarządzaj Grupami i Tagami</h1>
    <a href="manage_exercises.php" class="btn btn-secondary">Wróć do zarządzania ćwiczeniami</a>
</div>

<div class="row g-4">
    <!-- Kolumna: Dodaj nową grupę -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 80px;">
            <div class="card-header bg-dark text-white">
                <h2 class="h5 mb-0"><i class="bi bi-plus-circle me-2"></i>Dodaj nową grupę</h2>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_tags.php">
                    <div class="mb-3">
                        <label for="group_name" class="form-label">Nazwa grupy</label>
                        <input type="text" id="group_name" name="group_name" class="form-control" placeholder="np. Kardio" required>
                    </div>
                    <div class="mb-3">
                        <label for="group_color" class="form-label">Kolor grupy</label>
                        <select id="group_color" name="group_color" class="form-select">
                            <?php foreach($bootstrapColors as $color): ?>
                            <option value="<?= $color ?>" class="text-<?= $color ?> fw-bold"><?= ucfirst($color) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_group" class="btn btn-primary w-100">Dodaj grupę</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolumna: Lista istniejących grup i tagów -->
    <div class="col-lg-8">
        <div class="vstack gap-4">
            <?php if (!empty($allTagGroups)): ?>
                <?php foreach ($allTagGroups as $group): ?>
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0 d-flex align-items-center gap-2">
                            <span class="badge bg-<?= htmlspecialchars($group['color']) ?>"><?= htmlspecialchars($group['group_name']) ?></span>
                            <small class="text-muted fw-normal">(ID: <?= htmlspecialchars($group['group_id']) ?>)</small>
                        </h3>
                        <div class="btn-group">
                            <a href="edit_group.php?id=<?= urlencode($group['group_id']) ?>" class="btn btn-sm btn-outline-secondary" title="Edytuj grupę"><i class="bi bi-pencil-fill"></i></a>
                            <a href="delete_group.php?id=<?= urlencode($group['group_id']) ?>" class="btn btn-sm btn-outline-danger" title="Usuń grupę" onclick="return confirm('Czy na pewno chcesz usunąć całą grupę \'<?= htmlspecialchars($group['group_name']) ?>\' i wszystkie jej tagi?');"><i class="bi bi-trash3-fill"></i></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($group['tags'])): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($group['tags'] as $tag): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?= htmlspecialchars($tag['name']) ?> <small class="text-muted">(ID: <?= htmlspecialchars($tag['id']) ?>)</small></span>
                                <div class="btn-group">
                                    <a href="edit_tag.php?group_id=<?= urlencode($group['group_id']) ?>&tag_id=<?= urlencode($tag['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Edytuj tag"><i class="bi bi-pencil"></i></a>
                                    <a href="delete_tag.php?group_id=<?= urlencode($group['group_id']) ?>&tag_id=<?= urlencode($tag['id']) ?>" class="btn btn-sm btn-outline-danger" title="Usuń tag" onclick="return confirm('Czy na pewno chcesz usunąć tag \'<?= htmlspecialchars($tag['name']) ?>\'?');"><i class="bi bi-trash3"></i></a>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                        <p class="text-muted text-center small mb-0">Brak tagów w tej grupie.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="group_id" value="<?= htmlspecialchars($group['group_id']) ?>">
                            <input type="text" name="tag_name" class="form-control form-control-sm" placeholder="Dodaj nowy tag do tej grupy..." required>
                            <button type="submit" name="add_tag_to_group" class="btn btn-sm btn-success flex-shrink-0">Dodaj</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card-body text-center text-muted">
                    <p class="mb-0">Brak zdefiniowanych grup. Dodaj pierwszą w formularzu obok.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>