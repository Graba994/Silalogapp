<?php
$pageTitle = 'Plany Treningowe - SiłaLog';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
$userPlans = get_user_plans($userId);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');

// Pobierz listę innych użytkowników do importu
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$otherUsers = array_filter($allUsers, fn($user) => $user['id'] !== $userId);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0">Moje Plany Treningowe</h1>
    <div class="d-flex gap-2">
        <button id="toggle-edit-mode" type="button" class="btn btn-outline-secondary" title="Zarządzaj planami">
            <i class="bi bi-gear"></i> <span class="d-none d-sm-inline">Zarządzaj</span>
        </button>
<button type="button" class="btn btn-info" id="open-import-modal-btn">
    <i class="bi bi-download"></i> <span class="d-none d-sm-inline">Importuj</span>
</button>
        <a href="create_plan.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Stwórz nowy</span></a>
    </div>
</div>

<!-- Komunikaty zwrotne z importu -->
<?php if(isset($_GET['import'])): ?>
    <!-- ... (ten blok bez zmian) ... -->
<?php endif; ?>

<!-- ================== NOWY PANEL FILTRÓW ================== -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-center">
            <div class="col-lg-5">
                <input type="search" id="plan-search-input" class="form-control" placeholder="Szukaj po nazwie lub opisie...">
            </div>
            <div class="col-lg-3">
                <select id="plan-filter-exercise" class="form-select">
                    <option value="">Filtruj po ćwiczeniu...</option>
                    <?php foreach ($allExercises as $exercise): ?>
                        <option value="<?= $exercise['id'] ?>"><?= htmlspecialchars($exercise['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <select id="plan-sort-select" class="form-select">
                    <option value="name-asc">Sortuj A-Z</option>
                    <option value="name-desc">Sortuj Z-A</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                 <div class="btn-group w-100" role="group" aria-label="Przełącznik widoku">
                    <button type="button" class="btn btn-outline-secondary active" id="view-toggle-grid" title="Widok siatki"><i class="bi bi-grid-3x3-gap-fill"></i></button>
                    <button type="button" class="btn btn-outline-secondary" id="view-toggle-list" title="Widok listy"><i class="bi bi-list-ul"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- ======================================================== -->


<div class="row g-4" id="plans-container">
    <?php if (empty($userPlans)): ?>
        <div class="col-12">
            <div class="card text-center p-5">
                <div class="card-body">
                    <h3 class="text-muted">Nie masz jeszcze żadnych planów.</h3>
                    <p class="lead text-muted">Stwórz swój pierwszy plan lub zaimportuj gotowy od znajomego!</p>
                    <a href="create_plan.php" class="btn btn-lg btn-success mt-3"><i class="bi bi-joystick me-2"></i>Zaprojektuj swój plan teraz</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($userPlans as $plan): ?>
            <?php 
                // Zbieramy ID ćwiczeń w planie do atrybutu data-*
                $exerciseIdsInPlan = array_column($plan['exercises'], 'exercise_id');
            ?>
            <!-- WAŻNA ZMIANA: Dodajemy DIV-wrapper dla każdego planu, aby ułatwić sortowanie i przełączanie widoku -->
            <div class="col-md-6 col-lg-4 plan-card-wrapper" 
                 data-plan-name="<?= strtolower(htmlspecialchars($plan['plan_name'])) ?>" 
                 data-plan-desc="<?= strtolower(htmlspecialchars($plan['plan_description'])) ?>"
                 data-plan-exercises='<?= json_encode($exerciseIdsInPlan) ?>'>
                <div class="card h-100 d-flex flex-column plan-card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-truncate"><?= htmlspecialchars($plan['plan_name']) ?></h5>
                        <a href="start_workout.php?plan_id=<?= $plan['plan_id'] ?>" class="btn btn-sm btn-success flex-shrink-0 ms-2" title="Uruchom trening"><i class="bi bi-play-fill"></i></a>
                    </div>
                    <div class="card-body flex-grow-1">
                        <p class="card-text text-muted fst-italic">
                            <?= !empty($plan['plan_description']) ? htmlspecialchars($plan['plan_description']) : 'Brak opisu.' ?>
                        </p>
                        <h6 class="mt-3">Ćwiczenia w planie:</h6>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($plan['exercises'] as $ex): ?>
                                <li class="list-group-item px-0 py-1 d-flex justify-content-between align-items-center">
                                    <small class="text-truncate"><i class="bi bi-dot"></i> <?= $exerciseMap[$ex['exercise_id']] ?? 'Nieznane ćwiczenie' ?></small>
                                    <span class="badge bg-secondary rounded-pill"><?= count($ex['target_sets']) ?> serii</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 p-3">
                         <!-- Przyciski edycji i usuwania teraz mają klasę 'plan-actions' dla JS -->
                        <div class="plan-actions d-flex gap-2">
                            <a href="edit_plan.php?plan_id=<?= $plan['plan_id'] ?>" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Edytuj"><i class="bi bi-pencil me-1"></i> Edytuj</a>
                            <a href="delete_plan.php?plan_id=<?= $plan['plan_id'] ?>" class="btn btn-sm btn-outline-danger delete-plan-btn flex-grow-1" title="Usuń"><i class="bi bi-trash3 me-1"></i> Usuń</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Komunikat, gdy filtry nic nie znajdą -->
    <div id="no-plans-found" class="col-12 text-center py-5" style="display: none;">
        <h3 class="text-muted">Nie znaleziono planów pasujących do kryteriów.</h3>
        <p class="lead text-muted">Spróbuj zmienić filtry lub wyczyścić wyszukiwanie.</p>
    </div>
</div>

<!-- === MODAL DO IMPORTOWANIA PLANÓW - WERSJA OSTATECZNA === -->
<div class="modal fade" id="importPlanModal" tabindex="-1" aria-labelledby="importPlanModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importPlanModalLabel">Importuj Plan Treningowy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="import_plan.php" method="POST">
        <div class="modal-body">
            
            <div class="mb-3">
                <label for="import-user-select" class="form-label">Wybierz Gymbro:</label>
                <select id="import-user-select" name="source_user_id" class="form-select" required>
                    <option value="" selected disabled>-- Wybierz użytkownika --</option>
                    <?php foreach ($otherUsers as $user): ?>
                        <option value="<?= htmlspecialchars($user['id']) ?>"><?= htmlspecialchars($user['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="import-plan-select" class="form-label">Wybierz plan do zaimportowania:</label>
                <div class="input-group">
                    <select id="import-plan-select" name="plan_id" class="form-select" required disabled>
                        <option value="">-- Najpierw wybierz użytkownika --</option>
                    </select>
                    <span class="input-group-text d-none" id="import-loader">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </span>
                </div>
            </div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
          <button type="submit" id="confirm-import-btn" class="btn btn-primary" disabled>
            <i class="bi bi-check-lg me-1"></i> Zaimportuj ten plan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>

<?php require_once 'includes/footer.php'; ?>