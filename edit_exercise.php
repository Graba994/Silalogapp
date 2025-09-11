<?php
$pageTitle = 'Edytuj Ćwiczenie';
require_once 'includes/functions.php';

// --- KROK 1: LOGIKA ZAPISU (PRZENIESIONA NA GÓRĘ) ---
session_start();
$exerciseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exercise'])) {
    if (!$exerciseId) {
        header('Location: manage_exercises.php');
        exit();
    }
    
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $errorMessage = 'Nazwa ćwiczenia jest wymagana.';
    } else {
        $allExercises = get_all_exercises();
        $exerciseIndex = null;
        foreach ($allExercises as $index => $ex) {
            if ($ex['id'] === $exerciseId) {
                $exerciseIndex = $index;
                break;
            }
        }

        if ($exerciseIndex !== null) {
            $allExercises[$exerciseIndex]['name'] = htmlspecialchars($name);
            $allExercises[$exerciseIndex]['description'] = $_POST['description_content'] ?? '';
            $allExercises[$exerciseIndex]['howto'] = $_POST['howto_content'] ?? '';
            $allExercises[$exerciseIndex]['category'] = $_POST['category'] ?? 'inne';
            $allExercises[$exerciseIndex]['track_by'] = $_POST['track_by'] ?? [];
            $allExercises[$exerciseIndex]['tags'] = $_POST['tags'] ?? [];

            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'assets/img/exercises/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                if (!empty($allExercises[$exerciseIndex]['image']) && $allExercises[$exerciseIndex]['image'] !== 'default.png' && file_exists($uploadDir . $allExercises[$exerciseIndex]['image'])) {
                    unlink($uploadDir . $allExercises[$exerciseIndex]['image']);
                }
                $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $newFileName = $exerciseId . '_' . uniqid() . '.' . $fileExtension;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFileName)) {
                    $allExercises[$exerciseIndex]['image'] = $newFileName;
                }
            }
            
            if (save_exercises($allExercises)) {
                $successMessage = "Ćwiczenie '{$name}' zostało zaktualizowane. <a href='manage_exercises.php' class='alert-link'>Wróć do listy</a>.";
            } else {
                $errorMessage = "Błąd zapisu pliku exercises.json.";
            }
        } else {
            $errorMessage = "Nie znaleziono ćwiczenia do aktualizacji.";
        }
    }
}

// --- KROK 2: WYŚWIETLANIE STRONY ---
require_once 'includes/header.php';

// Dołączamy style i skrypt dla edytora Quill
echo '<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>';

if (!$exerciseId) {
    header('Location: manage_exercises.php');
    exit();
}
$allExercises = get_all_exercises();
$exerciseToEdit = null;
foreach ($allExercises as $exercise) {
    if ($exercise['id'] === $exerciseId) {
        $exerciseToEdit = $exercise;
        break;
    }
}
if (!$exerciseToEdit) {
    echo "<div class='alert alert-danger'>Nie znaleziono ćwiczenia o podanym ID.</div>";
    require_once 'includes/footer.php';
    exit();
}
$allTagGroups = get_all_tags();
// NOWE DANE
$allCategories = get_all_categories();
$allTrackableParams = get_all_trackable_params();
?>

<?php if ($successMessage): ?><div class="alert alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= $errorMessage ?></div><?php endif; ?>

<div class="card mx-auto" style="max-width: 900px;">
    <div class="card-header bg-dark text-white"><h1 class="h3 mb-0">Edytujesz: <?= htmlspecialchars($exerciseToEdit['name']) ?></h1></div>
    <div class="card-body">
        <form method="POST" action="edit_exercise.php?id=<?= $exerciseId ?>" enctype="multipart/form-data" id="edit-exercise-form">
            
            <div class="text-center mb-3">
                <img src="assets/img/exercises/<?= !empty($exerciseToEdit['image']) ? htmlspecialchars($exerciseToEdit['image']) : 'default.png' ?>" alt="Podgląd" class="img-thumbnail" style="max-height: 200px; object-fit: cover;" onerror="this.onerror=null; this.src='assets/img/exercises/default.png';">
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6"><label for="name" class="form-label">Nazwa ćwiczenia</label><input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($exerciseToEdit['name']) ?>" required></div>
                <div class="col-md-6"><label for="image" class="form-label">Zmień/Dodaj obrazek (opcjonalnie)</label><input class="form-control" type="file" id="image" name="image" accept="image/jpeg, image/png, image/gif"></div>
            </div>

            <div class="mb-3">
                <label class="form-label">Opis</label>
                <div id="editor-description" style="min-height: 150px;"><?= $exerciseToEdit['description'] ?? '' ?></div>
                <input type="hidden" name="description_content" id="description_content">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Jak wykonać?</label>
                <div id="editor-howto" style="min-height: 200px;"><?= $exerciseToEdit['howto'] ?? '' ?></div>
                <input type="hidden" name="howto_content" id="howto_content">
            </div>
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="category" class="form-label">Kategoria</label>
                    <select id="category" name="category" class="form-select" required>
                        <?php foreach($allCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= ($exerciseToEdit['category'] === $cat) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                     <label class="form-label d-block">Co śledzić?</label>
                    <?php foreach($allTrackableParams as $param): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="track_<?= htmlspecialchars($param['id']) ?>" name="track_by[]" value="<?= htmlspecialchars($param['id']) ?>" <?= in_array($param['id'], $exerciseToEdit['track_by'] ?? []) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="track_<?= htmlspecialchars($param['id']) ?>"><?= htmlspecialchars($param['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

             <div class="mb-4">
                <label class="form-label">Tagi (grupy mięśniowe)</label>
                <div class="accordion" id="edit-tags-accordion">
                    <?php foreach($allTagGroups as $group): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading-edit-<?= $group['group_id'] ?>">
                            <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-edit-<?= $group['group_id'] ?>">
                                <?= htmlspecialchars($group['group_name']) ?>
                            </button>
                        </h2>
                        <div id="collapse-edit-<?= $group['group_id'] ?>" class="accordion-collapse collapse" data-bs-parent="#edit-tags-accordion">
                            <div class="accordion-body">
                                <?php foreach($group['tags'] as $tag): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="<?= htmlspecialchars($tag['id']) ?>" id="edit_tag_<?= htmlspecialchars($tag['id']) ?>" name="tags[]" <?= in_array($tag['id'], $exerciseToEdit['tags'] ?? []) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_tag_<?= htmlspecialchars($tag['id']) ?>"><?= htmlspecialchars($tag['name']) ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="manage_exercises.php" class="btn btn-secondary">Anuluj</a>
                <button type="submit" name="update_exercise" class="btn btn-primary"><i class="bi bi-save me-2"></i>Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toolbarOptions = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        ['link', 'image', 'video'],
        ['clean']
    ];

    const quillDesc = new Quill('#editor-description', {
        modules: { toolbar: toolbarOptions },
        theme: 'snow'
    });

    const quillHowto = new Quill('#editor-howto', {
        modules: { toolbar: toolbarOptions },
        theme: 'snow'
    });

    const form = document.getElementById('edit-exercise-form');
    const descInput = document.getElementById('description_content');
    const howtoInput = document.getElementById('howto_content');

    form.addEventListener('submit', function(e) {
        // Usuń puste paragrafy, które Quill lubi dodawać
        const cleanHtml = (html) => html === '<p><br></p>' ? '' : html;
        descInput.value = cleanHtml(quillDesc.root.innerHTML);
        howtoInput.value = cleanHtml(quillHowto.root.innerHTML);
    });
});
</script>
<script src="assets/js/app.js" type="module"></script>