<?php
$pageTitle = 'Zarządzaj Ćwiczeniami - SiłaLog';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$successMessage = '';
$errorMessage = '';

// --- LOGIKA ZARZĄDZANIA OPCJAMI (Kategorie i Parametry) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $newCategory = trim($_POST['category_name'] ?? '');
        if (empty($newCategory)) {
            $errorMessage = "Nazwa kategorii nie może być pusta.";
        } else {
            $categories = get_all_categories();
            if (in_array(strtolower($newCategory), array_map('strtolower', $categories))) {
                $errorMessage = "Kategoria o tej nazwie już istnieje.";
            } else {
                $categories[] = $newCategory;
                if (save_categories($categories)) $successMessage = "Dodano nową kategorię: '{$newCategory}'.";
                else $errorMessage = "Błąd zapisu pliku kategorii.";
            }
        }
    }

    if (isset($_POST['add_trackable_param'])) {
        $paramName = trim($_POST['param_name'] ?? '');
        if (empty($paramName)) {
            $errorMessage = "Nazwa parametru nie może być pusta.";
        } else {
            $params = get_all_trackable_params();
            $paramId = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $paramName));
            if (in_array($paramId, array_column($params, 'id'))) {
                $errorMessage = "Parametr o takim ID ('{$paramId}') już istnieje.";
            } else {
                $params[] = ['id' => $paramId, 'name' => $paramName];
                if (save_trackable_params($params)) $successMessage = "Dodano nowy parametr: '{$paramName}'.";
                else $errorMessage = "Błąd zapisu pliku parametrów.";
            }
        }
    }
}

// Usuwanie opcji
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'delete_category' && isset($_GET['name'])) {
        $categories = get_all_categories();
        $categories = array_filter($categories, fn($c) => strcasecmp($c, $_GET['name']) !== 0);
        if (save_categories($categories)) $successMessage = "Usunięto kategorię.";
        else $errorMessage = "Błąd przy usuwaniu kategorii.";
    }
    if ($_GET['action'] === 'delete_trackable_param' && isset($_GET['id'])) {
        $params = get_all_trackable_params();
        $params = array_filter($params, fn($p) => $p['id'] !== $_GET['id']);
        if (save_trackable_params($params)) $successMessage = "Usunięto parametr.";
        else $errorMessage = "Błąd przy usuwaniu parametru.";
    }
}


// ===================================================================
// === POPRAWIONA I W PEŁNI FUNKCJONALNA LOGIKA DODAWANIA ĆWICZEŃ ===
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exercise'])) {
    $name = trim($_POST['name'] ?? '');

    // Walidacja 1: Sprawdzenie, czy nazwa nie jest pusta
    if (empty($name)) {
        $errorMessage = "Nazwa ćwiczenia jest wymagana.";
    } else {
        $allExercises = get_all_exercises();

        // Walidacja 2: Sprawdzenie, czy ćwiczenie o tej nazwie już istnieje
        $exists = false;
        foreach ($allExercises as $exercise) {
            if (strcasecmp($exercise['name'], $name) === 0) { // Porównanie bez względu na wielkość liter
                $exists = true;
                break;
            }
        }

        if ($exists) {
            $errorMessage = "Ćwiczenie o nazwie '<strong>" . htmlspecialchars($name) . "</strong>' już istnieje w bazie.";
        } else {
            // Jeśli walidacja przeszła pomyślnie, kontynuujemy
            $newId = !empty($allExercises) ? max(array_column($allExercises, 'id')) + 1 : 1;
            
            $newExercise = [
                'id' => $newId,
                'name' => htmlspecialchars($name),
                'description' => $_POST['description'] ?? '',
                'howto' => $_POST['howto'] ?? '',
                'category' => $_POST['category'] ?? 'inne',
                'track_by' => $_POST['track_by'] ?? [],
                'tags' => $_POST['tags'] ?? [],
                'image' => null
            ];

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'assets/img/exercises/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $newFileName = $newId . '_' . uniqid() . '.' . $fileExtension;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFileName)) {
                    $newExercise['image'] = $newFileName;
                }
            }
            
            $allExercises[] = $newExercise;

            if (save_exercises($allExercises)) {
                $successMessage = "Nowe ćwiczenie '<strong>" . htmlspecialchars($newExercise['name']) . "</strong>' zostało pomyślnie dodane!";
            } else {
                $errorMessage = "Wystąpił błąd podczas zapisywania pliku. Sprawdź uprawnienia do zapisu katalogu 'data'.";
            }
        }
    }
}

// Logika usuwania
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $exerciseIdToDelete = (int)$_GET['id'];
    $allExercises = get_all_exercises();
    $imageToDelete = null;

    $updatedExercises = array_filter($allExercises, function($exercise) use ($exerciseIdToDelete, &$imageToDelete) {
        if ($exercise['id'] === $exerciseIdToDelete) {
            if (isset($exercise['image'])) $imageToDelete = $exercise['image'];
            return false;
        }
        return true;
    });

    if (save_exercises(array_values($updatedExercises))) {
        if ($imageToDelete && $imageToDelete !== 'default.png' && file_exists('assets/img/exercises/' . $imageToDelete)) {
            unlink('assets/img/exercises/' . $imageToDelete);
        }
        $successMessage = "Ćwiczenie zostało pomyślnie usunięte.";
    } else {
        $errorMessage = "Wystąpił błąd podczas usuwania ćwiczenia.";
    }
}


// --- PRZYGOTOWANIE DANYCH DO WYŚWIETLENIA ---
$allExercises = get_all_exercises();
$allTagGroups = get_all_tags();
$allCategories = get_all_categories();
$allTrackableParams = get_all_trackable_params();

$flatTagMap = [];
foreach ($allTagGroups as $group) {
    foreach ($group['tags'] as $tag) {
        $flatTagMap[$tag['id']] = $tag['name'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Zarządzaj Ćwiczeniami</h1>
    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#optionsManagementModal">
        <i class="bi bi-gear-wide-connected me-2"></i>Zarządzaj Opcjami
    </button>
</div>


<!-- Komunikaty -->
<?php if (!empty($successMessage)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $successMessage ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if (!empty($errorMessage)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $errorMessage ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEWA KOLUMNA: FILTRY I FORMULARZ DODAWANIA -->
    <div class="col-lg-4">
        <!-- Panel filtrowania (zaktualizowany) -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-filter me-2"></i>Filtruj ćwiczenia</h5></div>
            <div class="card-body">
                <form id="filter-form">
                    <div class="mb-3">
                        <label for="filter-search" class="form-label">Wyszukaj po nazwie</label>
                        <input type="search" id="filter-search" class="form-control" placeholder="np. Wyciskanie...">
                    </div>
                    <div class="mb-3">
                        <label for="filter-category" class="form-label">Kategoria</label>
                        <select id="filter-category" class="form-select">
                            <option value="">Wszystkie</option>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="filter-tags" class="form-label">Tagi</label>
                        <select id="filter-tags" class="form-select">
                            <option value="">Wszystkie</option>
                            <?php foreach ($flatTagMap as $tagId => $tagName): ?>
                            <option value="<?= htmlspecialchars($tagId) ?>"><?= htmlspecialchars($tagName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="reset" id="filter-reset-btn" class="btn btn-outline-secondary w-100">Wyczyść filtry</button>
                </form>
            </div>
        </div>

        <!-- Formularz dodawania ćwiczenia (zaktualizowany) -->
        <div class="card">
            <div class="card-header bg-dark text-white"><h2 class="h5 mb-0">Dodaj nowe ćwiczenie</h2></div>
            <div class="card-body">
                <form method="POST" action="manage_exercises.php" enctype="multipart/form-data">
                     <div class="mb-3"><label for="name" class="form-label">Nazwa ćwiczenia</label><input type="text" id="name" name="name" class="form-control" required></div>
                     <div class="mb-3"><label for="add-description" class="form-label">Opis</label><textarea id="add-description" name="description" class="form-control" rows="2"></textarea></div>
                     <div class="mb-3"><label for="image" class="form-label">Obrazek</label><input class="form-control" type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif"></div>
                     <div class="mb-3">
                        <label for="category" class="form-label">Kategoria</label>
                        <select id="category" name="category" class="form-select" required>
                            <?php foreach ($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars(ucfirst($cat)) ?></option>
                            <?php endforeach; ?>
                        </select>
                     </div>
                     <div class="mb-3">
                        <label class="form-label d-block">Co śledzić?</label>
                        <?php foreach ($allTrackableParams as $param): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="track_<?= htmlspecialchars($param['id']) ?>" name="track_by[]" value="<?= htmlspecialchars($param['id']) ?>">
                            <label class="form-check-label" for="track_<?= htmlspecialchars($param['id']) ?>"><?= htmlspecialchars($param['name']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                     
                     <div class="mb-3"><label class="form-label">Tagi</label>
                        <div class="accordion" id="add-tags-accordion">
                            <?php foreach($allTagGroups as $group): ?>
                            <div class="accordion-item"><h2 class="accordion-header" id="heading-add-<?= htmlspecialchars($group['group_id']) ?>"><button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-add-<?= htmlspecialchars($group['group_id']) ?>"><?= htmlspecialchars($group['group_name']) ?></button></h2><div id="collapse-add-<?= htmlspecialchars($group['group_id']) ?>" class="accordion-collapse collapse" data-bs-parent="#add-tags-accordion"><div class="accordion-body"><?php foreach($group['tags'] as $tag): ?><div class="form-check"><input class="form-check-input" type="checkbox" value="<?= htmlspecialchars($tag['id']) ?>" id="add_tag_<?= htmlspecialchars($tag['id']) ?>" name="tags[]"><label class="form-check-label" for="add_tag_<?= htmlspecialchars($tag['id']) ?>"><?= htmlspecialchars($tag['name']) ?></label></div><?php endforeach; ?></div></div></div>
                            <?php endforeach; ?>
                        </div>
                     </div>
                     <button type="submit" name="add_exercise" class="btn btn-primary w-100">Dodaj ćwiczenie</button>
                </form>
            </div>
        </div>
    </div>

    <!-- PRAWA KOLUMNA: LISTA ISTNIEJĄCYCH ĆWICZEŃ -->
    <div class="col-lg-8">
        <div id="exercise-list-container">
            <?php if (!empty($allExercises)): ?>
                <?php foreach ($allExercises as $exercise): ?>
                    <div class="card mb-3 exercise-card" data-name="<?= strtolower(htmlspecialchars($exercise['name'])) ?>" data-category="<?= htmlspecialchars($exercise['category']) ?>" data-tags='<?= json_encode($exercise['tags'] ?? []) ?>'><div class="row g-0"><div class="col-md-3 d-flex align-items-center justify-content-center p-2"><img src="assets/img/exercises/<?= !empty($exercise['image']) ? htmlspecialchars($exercise['image']) : 'default.png' ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($exercise['name']) ?>" style="max-height: 120px; object-fit: cover;" onerror="this.onerror=null; this.src='assets/img/exercises/default.png';"></div><div class="col-md-9"><div class="card-body p-3"><div class="d-flex justify-content-between align-items-start"><h5 class="card-title mb-1"><?= htmlspecialchars($exercise['name']) ?></h5><div class="d-flex gap-1 flex-shrink-0 ms-2"><a href="edit_exercise.php?id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edytuj"><i class="bi bi-pencil"></i></a><a href="manage_exercises.php?action=delete&id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline-danger delete-exercise-btn" title="Usuń"><i class="bi bi-trash3"></i></a></div></div><p class="card-text text-muted small mb-2 text-truncate"><?= strip_tags($exercise['description'] ?? 'Brak opisu.') ?></p><div class="d-flex flex-wrap gap-1"><span class="badge text-bg-info"><?= htmlspecialchars(ucfirst($exercise['category'])) ?></span><?php if (!empty($exercise['tags'])): ?><?php foreach ($exercise['tags'] as $tagId): ?><?php if (isset($flatTagMap[$tagId])): ?><span class="badge text-bg-secondary"><?= htmlspecialchars($flatTagMap[$tagId]) ?></span><?php endif; ?><?php endforeach; ?><?php endif; ?></div></div></div></div></div>
                <?php endforeach; ?>
                <div id="no-results-message" class="alert alert-warning text-center" style="display: none;">Brak ćwiczeń pasujących do wybranych filtrów.</div>
            <?php else: ?>
                <div class="alert alert-info">Baza ćwiczeń jest pusta.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL DO ZARZĄDZANIA OPCJAMI -->
<div class="modal fade" id="optionsManagementModal" tabindex="-1" aria-labelledby="optionsManagementModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="optionsManagementModalLabel">Zarządzaj Opcjami Ćwiczeń</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul class="nav nav-tabs" id="optionsTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories-tab-pane" type="button" role="tab">Kategorie</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="params-tab" data-bs-toggle="tab" data-bs-target="#params-tab-pane" type="button" role="tab">Parametry do śledzenia</button>
          </li>
        </ul>
        <div class="tab-content" id="optionsTabContent">
          <!-- Zakładka Kategorie -->
          <div class="tab-pane fade show active" id="categories-tab-pane" role="tabpanel">
            <div class="p-3">
              <h6>Istniejące Kategorie</h6>
              <ul class="list-group mb-3">
                <?php foreach ($allCategories as $cat): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= htmlspecialchars(ucfirst($cat)) ?>
                  <a href="?action=delete_category&name=<?= urlencode($cat) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz usunąć tę kategorię?')"><i class="bi bi-trash3"></i></a>
                </li>
                <?php endforeach; ?>
              </ul>
              <h6>Dodaj nową kategorię</h6>
              <form method="POST" class="d-flex gap-2">
                <input type="text" name="category_name" class="form-control" placeholder="np. Stretching" required>
                <button type="submit" name="add_category" class="btn btn-primary">Dodaj</button>
              </form>
            </div>
          </div>
          <!-- Zakładka Parametry -->
          <div class="tab-pane fade" id="params-tab-pane" role="tabpanel">
            <div class="p-3">
              <h6>Istniejące Parametry</h6>
              <ul class="list-group mb-3">
                <?php foreach ($allTrackableParams as $param): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= htmlspecialchars($param['name']) ?> <small class="text-muted">(ID: <?= htmlspecialchars($param['id']) ?>)</small>
                  <a href="?action=delete_trackable_param&id=<?= urlencode($param['id']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Czy na pewno chcesz usunąć ten parametr?')"><i class="bi bi-trash3"></i></a>
                </li>
                <?php endforeach; ?>
              </ul>
              <h6>Dodaj nowy parametr</h6>
              <form method="POST" class="d-flex gap-2">
                <input type="text" name="param_name" class="form-control" placeholder="np. Dystans (km)" required>
                <button type="submit" name="add_trackable_param" class="btn btn-primary">Dodaj</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>