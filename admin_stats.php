<?php
require_once 'includes/admin_guard.php'; // Zabezpieczenie na początku
$pageTitle = 'Globalne Statystyki';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// --- 1. WCZYTANIE PODSTAWOWYCH DANYCH ---
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');
$oneMonthAgo = strtotime('-30 days');

// --- 2. INICJALIZACJA ZMIENNYCH STATYSTYCZNYCH ---
$totalUsers = count($allUsers);
$totalWorkouts = 0;
$totalVolume = 0;
$exerciseUsage = [];
$userActivity = [];

// Zainicjuj aktywność dla każdego użytkownika
foreach ($allUsers as $user) {
    $userActivity[$user['id']] = [
        'name' => $user['name'],
        'workout_count' => 0
    ];
}

// --- 3. GŁÓWNA PĘTLA ANALITYCZNA ---
// Iterujemy po wszystkich plikach workout w katalogu data/
$workoutFiles = glob('data/workouts_*.json');
foreach ($workoutFiles as $file) {
    $workouts = json_decode(file_get_contents($file), true);
    if (is_array($workouts)) {
        $totalWorkouts += count($workouts);
        
        // Wyciągnij ID użytkownika z nazwy pliku
        preg_match('/workouts_(\w+)\.json/', $file, $matches);
        $fileUserId = $matches[1] ?? null;

        foreach ($workouts as $workout) {
            // Zliczaj aktywność użytkownika w ostatnim miesiącu
            if ($fileUserId && strtotime($workout['date']) >= $oneMonthAgo) {
                if(isset($userActivity[$fileUserId])) {
                    $userActivity[$fileUserId]['workout_count']++;
                }
            }

            // Zliczaj objętość i użycie ćwiczeń
            foreach ($workout['exercises'] as $exercise) {
                $exId = $exercise['exercise_id'];
                // Zliczanie użycia ćwiczeń
                if (!isset($exerciseUsage[$exId])) {
                    $exerciseUsage[$exId] = 0;
                }
                $exerciseUsage[$exId]++;
                
                // Zliczanie objętości
                foreach ($exercise['sets'] as $set) {
                    $totalVolume += ($set['weight'] ?? 0) * ($set['reps'] ?? 0);
                }
            }
        }
    }
}

// --- 4. PRZYGOTOWANIE RANKINGÓW DO WYŚWIETLENIA ---

// Najpopularniejsze ćwiczenia
arsort($exerciseUsage); // Sortuj malejąco, zachowując klucze (ID ćwiczeń)
$topExercises = array_slice($exerciseUsage, 0, 10, true);

// Najbardziej aktywni użytkownicy
uasort($userActivity, fn($a, $b) => $b['workout_count'] <=> $a['workout_count']);
$topUsers = array_slice($userActivity, 0, 10, true);

?>

<h1 class="mb-4">Globalne Statystyki Aplikacji</h1>

<!-- GŁÓWNE KARTY ZE STATYSTYKAMI -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Łączna liczba użytkowników</h6>
                <p class="card-text display-5 fw-bold"><?= $totalUsers ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card text-center h-100">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-muted">Łączna liczba treningów</h6>
                <p class="card-text display-5 fw-bold"><?= $totalWorkouts ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-12 col-lg-6">
        <div class="card text-center h-100 bg-dark text-white">
            <div class="card-body">
                <h6 class="card-subtitle mb-2 text-white-50">Całkowita objętość</h6>
                <p class="card-text display-5 fw-bold"><?= number_format($totalVolume / 1000, 2) ?> t</p>
                <small class="text-white-50">(Tyle ton podnieśli wszyscy użytkownicy łącznie)</small>
            </div>
        </div>
    </div>
</div>

<!-- RANKINGI -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-star-fill text-warning me-2"></i>Najpopularniejsze ćwiczenia</h5></div>
            <ul class="list-group list-group-flush">
                <?php if(empty($topExercises)): ?>
                    <li class="list-group-item text-muted">Brak danych.</li>
                <?php else: ?>
                    <?php $rank = 1; foreach ($topExercises as $exId => $count): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><strong><?= $rank++ ?>.</strong> <?= htmlspecialchars($exerciseMap[$exId] ?? 'Nieznane ćwiczenie') ?></span>
                        <span class="badge bg-primary rounded-pill"><?= $count ?> razy</span>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-arms-up text-success me-2"></i>Najaktywniejsi użytkownicy (ostatnie 30 dni)</h5></div>
            <ul class="list-group list-group-flush">
                <?php if(empty($topUsers) || max(array_column($topUsers, 'workout_count')) == 0): ?>
                    <li class="list-group-item text-muted">Brak aktywności w ostatnim miesiącu.</li>
                <?php else: ?>
                    <?php $rank = 1; foreach ($topUsers as $userId => $data): if($data['workout_count'] > 0): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><strong><?= $rank++ ?>.</strong> <?= htmlspecialchars($data['name']) ?></span>
                        <span class="badge bg-success rounded-pill"><?= $data['workout_count'] ?> treningów</span>
                    </li>
                    <?php endif; endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>