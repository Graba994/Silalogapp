<?php
$pageTitle = 'Analiza Progresu';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$userId = $_SESSION['user_id'];
$userWorkouts = get_user_workouts($userId);

// --- LOGIKA FILTROWANIA I GRUPOWANIA DANYCH ---

// Domyślne wartości
$groupBy = $_GET['group_by'] ?? 'week'; // 'week' lub 'month'
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-3 months'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Filtruj treningi po dacie
$filteredWorkouts = array_filter($userWorkouts, fn($w) => (strtotime($w['date']) >= strtotime($startDate) && strtotime($w['date']) <= strtotime($endDate)));

$data = [];

if (!empty($filteredWorkouts)) {
    // Agregacja danych
    foreach ($filteredWorkouts as $workout) {
        $key = '';
        if ($groupBy === 'week') {
            // Klucz to rok i numer tygodnia, np. "2023-W34"
            $key = date('o-\WW', strtotime($workout['date'])); 
        } else { // month
            // Klucz to rok i miesiąc, np. "2023-08"
            $key = date('Y-m', strtotime($workout['date']));
        }

        if (!isset($data[$key])) {
            $data[$key] = ['total_volume' => 0, 'total_reps' => 0, 'workout_count' => 0, 'total_weight_lifted' => 0];
        }

        $data[$key]['workout_count']++;
        foreach ($workout['exercises'] as $exercise) {
            foreach ($exercise['sets'] as $set) {
                $weight = $set['weight'] ?? 0;
                $reps = $set['reps'] ?? 0;
                $data[$key]['total_volume'] += $weight * $reps;
                $data[$key]['total_reps'] += $reps;
                $data[$key]['total_weight_lifted'] += $weight * $reps; // Używane do obliczenia intensywności
            }
        }
    }
}

// Sortowanie kluczy (okresów) chronologicznie
ksort($data);

// Przygotowanie danych do wykresów
$chartLabels = [];
$chartVolumeData = [];
$chartIntensityData = [];

foreach ($data as $period => $stats) {
    $chartLabels[] = $period;
    $chartVolumeData[] = round($stats['total_volume'] / 1000, 2); // w tonach
    // Średnia intensywność = Całkowity podniesiony ciężar / Całkowita liczba powtórzeń
    $chartIntensityData[] = ($stats['total_reps'] > 0) ? round($stats['total_weight_lifted'] / $stats['total_reps'], 2) : 0;
}

// Obliczanie statystyk ogólnych
$totalOverallVolume = array_sum(array_column($data, 'total_volume'));
$bestPeriodVolume = !empty($data) ? max(array_column($data, 'total_volume')) : 0;
$bestPeriodLabel = !empty($data) ? array_search($bestPeriodVolume, array_column($data, 'total_volume')) : 'Brak';
if($bestPeriodLabel !== 'Brak') $bestPeriodLabel = array_keys($data)[$bestPeriodLabel];

$chartData = [
    'labels' => $chartLabels,
    'volume' => $chartVolumeData,
    'intensity' => $chartIntensityData,
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Analiza Progresu: Objętość i Intensywność</h1>
</div>

<!-- PANEL FILTRÓW -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="progress.php">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3">
                    <label for="start_date" class="form-label">Od:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-lg-3">
                    <label for="end_date" class="form-label">Do:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-lg-3">
                    <label for="group_by" class="form-label">Grupuj wg:</label>
                    <select name="group_by" id="group_by" class="form-select">
                        <option value="week" <?= $groupBy === 'week' ? 'selected' : '' ?>>Tygodnia</option>
                        <option value="month" <?= $groupBy === 'month' ? 'selected' : '' ?>>Miesiąca</option>
                    </select>
                </div>
                <div class="col-lg-3 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart-line-fill me-2"></i>Analizuj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($data)): ?>
    <div class="alert alert-warning text-center">Brak danych treningowych w wybranym okresie. Zmień filtry lub <a href="log_workout.php">dodaj trening</a>.</div>
<?php else: ?>
    <!-- KARTY Z PODSUMOWANIEM -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Całkowita Objętość</h6>
                    <p class="card-text display-5 fw-bold"><?= number_format($totalOverallVolume / 1000, 2) ?> t</p>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="card text-center h-100 bg-success-subtle border-success">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Najlepszy Okres (Objętość)</h6>
                    <p class="card-text display-5 fw-bold"><?= number_format($bestPeriodVolume / 1000, 2) ?> t</p>
                    <div class="small text-success fw-bold"><?= htmlspecialchars($bestPeriodLabel) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-12 col-lg-4">
             <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">Średnia Intensywność</h6>
                    <p class="card-text display-5 fw-bold"><?= !empty($chartIntensityData) ? end($chartIntensityData) : 0 ?> kg</p>
                    <div class="small text-muted">Średni ciężar na powtórzenie w ostatnim okresie</div>
                </div>
            </div>
        </div>
    </div>


    <!-- WYKRESY -->
    <div class="row g-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Całkowita objętość (w tonach)</h5>
                </div>
                <div class="card-body">
                    <canvas id="volumeChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Średnia intensywność (średni ciężar na powtórzenie)</h5>
                </div>
                <div class="card-body">
                    <canvas id="intensityChart"></canvas>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chartData = <?= json_encode($chartData) ?>;

    // Wykres Objętości
    const volumeCtx = document.getElementById('volumeChart').getContext('2d');
    new Chart(volumeCtx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Objętość (w tonach)',
                data: chartData.volume,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'Tony' }
                }
            }
        }
    });

    // Wykres Intensywności
    const intensityCtx = document.getElementById('intensityChart').getContext('2d');
    new Chart(intensityCtx, {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Średnia intensywność (kg)',
                data: chartData.intensity,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: false,
                    title: { display: true, text: 'kg' }
                }
            }
        }
    });
});
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>