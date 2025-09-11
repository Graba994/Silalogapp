<?php
$pageTitle = 'Statystyki - SiłaLog';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// === 1. WCZYTANIE WSZYSTKICH POTRZEBNYCH DANYCH ===
$userId = $_SESSION['user_id'];
$userBodyWeight = $_SESSION['user_body_params']['weight'] ?? 0;
$allUsers = json_decode(file_get_contents('data/users.json'), true);
$currentUser = null;
foreach($allUsers as $u) { if($u['id'] === $userId) { $currentUser = $u; break; } }

$allExercises = get_all_exercises();
$exerciseMap = array_column($allExercises, 'name', 'id');
$exerciseDetailsMap = array_column($allExercises, null, 'id');
$userWorkouts = get_user_workouts($userId);
$userGoals = get_user_goals($userId);
$userMeasurements = get_user_measurements($userId); // NOWOŚĆ: Pobierz historię pomiarów

// === 2. FUNKCJA POMOCNICZA DO OBLICZANIA STATYSTYK DLA ĆWICZENIA ===
function calculate_exercise_stats($exerciseId, $workoutsToAnalyze, $allUserWorkouts, $userBodyWeight, $exerciseDetails) {
    $stats = [
        'prMaxWeight' => 0, 'prMaxReps' => 0, 'prMaxTime' => 0, 'prEstimated1RM' => 0, 'prRelativeStrength' => 0,
        'chartData' => ['labels' => [], 'e1rm' => [], 'maxWeight' => [], 'maxReps' => [], 'maxTime' => [], 'volume' => [], 'avgIntensity' => []]
    ];
    $tracksWeight = in_array('weight', $exerciseDetails['track_by']);
    $tracksReps = in_array('reps', $exerciseDetails['track_by']);
    $tracksTime = in_array('time', $exerciseDetails['track_by']);

    // Oblicz rekordy wszech czasów na podstawie wszystkich treningów
    foreach ($allUserWorkouts as $workout) {
        foreach ($workout['exercises'] as $ex) {
            if ($ex['exercise_id'] === $exerciseId) {
                foreach ($ex['sets'] as $set) {
                    $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; $time = $set['time'] ?? 0;
                    if ($tracksWeight && $weight > $stats['prMaxWeight']) $stats['prMaxWeight'] = $weight;
                    if ($tracksReps && $reps > $stats['prMaxReps']) $stats['prMaxReps'] = $reps;
                    if ($tracksTime && $time > $stats['prMaxTime']) $stats['prMaxTime'] = $time;
                    if ($tracksWeight && $tracksReps) {
                        if ($reps > 1 && $reps < 11 && $weight > 0) {
                            $e1rm = $weight / (1.0278 - (0.0278 * $reps));
                            if ($e1rm > $stats['prEstimated1RM']) $stats['prEstimated1RM'] = round($e1rm, 1);
                        } elseif ($reps === 1 && $weight > $stats['prEstimated1RM']) {
                            $stats['prEstimated1RM'] = round($weight, 1);
                        }
                    }
                }
            }
        }
    }
    if ($userBodyWeight > 0 && $stats['prEstimated1RM'] > 0) {
        $stats['prRelativeStrength'] = round($stats['prEstimated1RM'] / $userBodyWeight, 2);
    }
    
    // Przygotuj dane do wykresu na podstawie przefiltrowanych treningów
    usort($workoutsToAnalyze, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));
    foreach ($workoutsToAnalyze as $workout) {
        $stats['chartData']['labels'][] = $workout['date'];
        $sessionE1RM = 0; $sessionMaxWeight = 0; $sessionMaxReps = 0; $sessionMaxTime = 0; $sessionVolume = 0; $totalReps = 0;
        $foundInWorkout = false;
        foreach ($workout['exercises'] as $ex) {
            if ($ex['exercise_id'] === $exerciseId) {
                $foundInWorkout = true;
                foreach ($ex['sets'] as $set) {
                    $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; $time = $set['time'] ?? 0;
                    $sessionVolume += $weight * $reps; $totalReps += $reps;
                    if ($tracksWeight && $weight > $sessionMaxWeight) $sessionMaxWeight = $weight;
                    if ($tracksReps && $reps > $sessionMaxReps) $sessionMaxReps = $reps;
                    if ($tracksTime && $time > $sessionMaxTime) $sessionMaxTime = $time;
                    if ($tracksWeight && $tracksReps) {
                        if ($reps > 1 && $reps < 11 && $weight > 0) {
                            $e1rm = $weight / (1.0278 - (0.0278 * $reps));
                            if ($e1rm > $sessionE1RM) $sessionE1RM = round($e1rm, 1);
                        } elseif ($reps === 1 && $weight > $sessionE1RM) {
                            $sessionE1RM = round($weight, 1);
                        }
                    }
                }
            }
        }
        // Jeśli ćwiczenie wystąpiło, zapisz staty, jeśli nie, zapisz null, aby przerwać linię na wykresie
        $stats['chartData']['e1rm'][] = $foundInWorkout ? $sessionE1RM : null;
        $stats['chartData']['maxWeight'][] = $foundInWorkout ? $sessionMaxWeight : null;
        $stats['chartData']['maxReps'][] = $foundInWorkout ? $sessionMaxReps : null;
        $stats['chartData']['maxTime'][] = $foundInWorkout ? $sessionMaxTime : null;
        $stats['chartData']['volume'][] = $foundInWorkout ? round($sessionVolume / 1000, 2) : null;
        $stats['chartData']['avgIntensity'][] = ($foundInWorkout && $totalReps > 0) ? round($sessionVolume / $totalReps, 2) : null;
    }
    return $stats;
}

// === 3. GŁÓWNA LOGIKA STRONY ===
$selectedExerciseId = isset($_GET['exercise_id']) && !empty($_GET['exercise_id']) ? (int)$_GET['exercise_id'] : null;
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if (!$selectedExerciseId) {
    // WIDOK GŁÓWNY Z SIATKĄ 2x2
    $keyExerciseIds = $currentUser['key_exercises'] ?? [];
    $keyExercisesData = [];
    foreach($keyExerciseIds as $exId) {
        if(isset($exerciseDetailsMap[$exId])) {
            $data = calculate_exercise_stats($exId, $userWorkouts, $userWorkouts, $userBodyWeight, $exerciseDetailsMap[$exId]);
            $data['name'] = $exerciseDetailsMap[$exId]['name'];
            $data['track_by'] = $exerciseDetailsMap[$exId]['track_by'];
            $goal = $userGoals[$exId][0] ?? null;
            $data['goal'] = $goal ? $goal['targets'] : null;
            $keyExercisesData[$exId] = $data;
        }
    }
} else {
    // WIDOK SZCZEGÓŁOWY
    $filteredWorkoutsForDetail = $userWorkouts;
    if ($startDate || $endDate) {
        $filterStart = $startDate ? strtotime($startDate) : 0;
        $filterEnd = $endDate ? strtotime($endDate . ' 23:59:59') : time();
        $filteredWorkoutsForDetail = array_filter($userWorkouts, fn($w) => (strtotime($w['date']) >= $filterStart && strtotime($w['date']) <= $filterEnd));
    }
    $selectedExercise = $exerciseDetailsMap[$selectedExerciseId];
    $detailedStats = calculate_exercise_stats($selectedExerciseId, $filteredWorkoutsForDetail, $userWorkouts, $userBodyWeight, $selectedExercise);
    
    // Obliczanie statystyk dla wybranego okresu
    $periodStats = ['detailedHistory' => []];
    foreach($filteredWorkoutsForDetail as $w) {
        foreach($w['exercises'] as $ex) {
            if ($ex['exercise_id'] === $selectedExerciseId) {
                foreach($ex['sets'] as $s) {
                    $periodStats['detailedHistory'][] = ['date' => $w['date'], 'set' => $s];
                }
            }
        }
    }
    
    // Obliczanie progresu do celów
    $goalProgress = [];
    if (!empty($userGoals[$selectedExerciseId])) {
        foreach ($userGoals[$selectedExerciseId] as $goal) {
            $totalPercentage = 0; $paramCount = 0; $progressDetails = [];
            if (isset($goal['targets']['weight'])) {
                $paramCount++; 
                $percentage = ($goal['targets']['weight'] > 0) ? min(100, ($detailedStats['prMaxWeight'] / $goal['targets']['weight']) * 100) : 0;
                $totalPercentage += $percentage;
                $progressDetails[] = ['label' => 'Ciężar', 'current' => $detailedStats['prMaxWeight'], 'target' => $goal['targets']['weight'], 'percent' => round($percentage), 'unit' => 'kg'];
            }
            if (isset($goal['targets']['reps'])) {
                $paramCount++; 
                $percentage = ($goal['targets']['reps'] > 0) ? min(100, ($detailedStats['prMaxReps'] / $goal['targets']['reps']) * 100) : 0;
                $totalPercentage += $percentage;
                $progressDetails[] = ['label' => 'Powtórzenia', 'current' => $detailedStats['prMaxReps'], 'target' => $goal['targets']['reps'], 'percent' => round($percentage), 'unit' => ''];
            }
            if (isset($goal['targets']['time'])) {
                $paramCount++; 
                $percentage = ($goal['targets']['time'] > 0) ? min(100, ($detailedStats['prMaxTime'] / $goal['targets']['time']) * 100) : 0;
                $totalPercentage += $percentage;
                $progressDetails[] = ['label' => 'Czas', 'current' => $detailedStats['prMaxTime'], 'target' => $goal['targets']['time'], 'percent' => round($percentage), 'unit' => 's'];
            }
            $averagePercentage = $paramCount > 0 ? round($totalPercentage / $paramCount) : 0;
            $goalProgress[] = ['name' => $goal['goal_name'], 'avg_percent' => $averagePercentage, 'details' => $progressDetails];
        }
    }
}
?>

<!-- PANEL FILTRÓW -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="stats.php" id="stats-form">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label for="exercise_id" class="form-label">Wybierz ćwiczenie do szczegółowej analizy:</label><select id="exercise_id" name="exercise_id" class="form-select" onchange="this.form.submit()"><option value="">-- Panel główny --</option><?php foreach ($allExercises as $exercise): ?><option value="<?= $exercise['id'] ?>" <?= ($selectedExerciseId === $exercise['id']) ? 'selected' : '' ?>><?= htmlspecialchars($exercise['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 col-lg-2"><label for="start_date" class="form-label">Od:</label><input type="date" name="start_date" id="start_date" class="form-control" value="<?= htmlspecialchars($startDate ?? '') ?>"></div>
                <div class="col-md-3 col-lg-2"><label for="end_date" class="form-label">Do:</label><input type="date" name="end_date" id="end_date" class="form-control" value="<?= htmlspecialchars($endDate ?? '') ?>"></div>
                <div class="col-md-6 col-lg-2 d-grid"><button type="submit" class="btn btn-primary" <?= !$selectedExerciseId ? 'disabled' : '' ?>>Filtruj</button></div>
                <div class="col-md-6 col-lg-2 d-grid"><a href="stats.php" class="btn btn-outline-secondary">Panel Główny</a></div>
            </div>
            <?php if($selectedExerciseId): ?>
            <div class="d-flex flex-wrap gap-2 mt-2"><button type="button" class="btn btn-sm btn-outline-secondary date-preset" data-preset="month">Ostatni miesiąc</button><button type="button" class="btn btn-sm btn-outline-secondary date-preset" data-preset="quarter">Ostatni kwartał</button><button type="button" class="btn btn-sm btn-outline-secondary date-preset" data-preset="year">Ostatni rok</button></div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!$selectedExerciseId): ?>
    <!-- WIDOK SIATKI 2x2 -->
    <div class="row g-4">
        <?php if(empty($keyExercisesData)): ?>
            <div class="col-12"><div class="alert alert-info">Nie zdefiniowałeś jeszcze swoich kluczowych ćwiczeń. <a href="profile.php" class="alert-link">Zrób to w swoim profilu</a>, aby zobaczyć tu szybki podgląd progresu.</div></div>
        <?php else: ?>
            <?php foreach($keyExercisesData as $exId => $data): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= htmlspecialchars($data['name']) ?></h5>
                        <a href="stats.php?exercise_id=<?= $exId ?>" class="btn btn-sm btn-outline-light">Szczegóły <i class="bi bi-arrow-right-short"></i></a>
                    </div>
                    <div class="card-body">
                        <div style="height: 200px;"><canvas id="chart-<?= $exId ?>"></canvas></div>
                        <hr>
                        <div class="row text-center mt-3">
                            <?php if(in_array('weight', $data['track_by'])): ?><div class="col"><strong><?= $data['prMaxWeight'] ?> kg</strong><div class="small text-muted">Max Ciężar</div></div><?php endif; ?>
                            <?php if(in_array('reps', $data['track_by'])): ?><div class="col"><strong><?= $data['prMaxReps'] ?></strong><div class="small text-muted">Max Powt.</div></div><?php endif; ?>
                            <?php if(in_array('time', $data['track_by'])): ?><div class="col"><strong><?= $data['prMaxTime'] ?> s</strong><div class="small text-muted">Max Czas</div></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if(!empty($keyExercisesData)) foreach($keyExercisesData as $exId => $data): ?>
            new Chart(document.getElementById('chart-<?= $exId ?>'), {
                type: 'line',
                data: { labels: <?= json_encode($data['chartData']['labels']) ?>, datasets: [{ label: 'Progres e1RM (kg)', data: <?= json_encode($data['chartData']['e1rm']) ?>, borderColor: '#0d6efd', tension: 0.2, pointRadius: 2, spanGaps: true }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: false } } }
            });
        <?php endforeach; ?>
    });
    </script>
<?php else: ?>
    <!-- WIDOK SZCZEGÓŁOWY -->
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white"><h5 class="mb-0"><i class="bi bi-bullseye me-2"></i>Postęp do Celów</h5></div>
                <?php if (!empty($goalProgress)): ?>
                    <div class="accordion accordion-flush" id="goalsProgressAccordion">
                        <?php foreach($goalProgress as $index => $progress): ?>
                            <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-goal-<?= $index ?>"><div class="w-100 d-flex justify-content-between pe-3"><strong><?= htmlspecialchars($progress['name']) ?></strong><span class="badge bg-success"><?= $progress['avg_percent'] ?>%</span></div></button></h2><div id="collapse-goal-<?= $index ?>" class="accordion-collapse collapse" data-bs-parent="#goalsProgressAccordion"><div class="accordion-body pt-2 pb-3"><?php foreach ($progress['details'] as $detail): ?><div class="mb-2"><div class="d-flex justify-content-between small"><span><?= $detail['label'] ?></span><span><?= $detail['current'] ?> / <?= $detail['target'] ?> <?= $detail['unit'] ?></span></div><div class="progress" style="height: 5px;"><div class="progress-bar" role="progressbar" style="width: <?= $detail['percent'] ?>%;" ></div></div></div><?php endforeach; ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card-body text-center"><p class="text-muted mb-2">Brak celów dla tego ćwiczenia.</p><a href="goals.php" class="btn btn-sm btn-primary">Ustaw cele</a></div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-header bg-dark text-white"><h5 class="mb-0">Rekordy Życiowe</h5></div>
                <ul class="list-group list-group-flush">
                    <?php if (in_array('weight', $selectedExercise['track_by'])): ?><li class="list-group-item d-flex justify-content-between"><span>Max Ciężar:</span> <strong><?= $detailedStats['prMaxWeight'] ?> kg</strong></li><?php endif; ?>
                    <?php if (in_array('reps', $selectedExercise['track_by'])): ?><li class="list-group-item d-flex justify-content-between"><span>Max Powtórzeń:</span> <strong><?= $detailedStats['prMaxReps'] ?></strong></li><?php endif; ?>
                    <?php if (in_array('time', $selectedExercise['track_by'])): ?><li class="list-group-item d-flex justify-content-between"><span>Max Czas:</span> <strong><?= $detailedStats['prMaxTime'] ?> s</strong></li><?php endif; ?>
                    <?php if (in_array('weight', $selectedExercise['track_by']) && in_array('reps', $selectedExercise['track_by'])): ?><li class="list-group-item d-flex justify-content-between"><span>Szacowany 1RM:</span> <strong><?= $detailedStats['prEstimated1RM'] ?> kg</strong></li><li class="list-group-item d-flex justify-content-between"><span>Siła Względna:</span> <strong><?= $detailedStats['prRelativeStrength'] ?> x BW</strong></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white"><h5 class="mb-0">Interaktywny Wykres Progresu</h5></div>
                <div class="card-body"><canvas id="detailedChart"></canvas></div>
                <!-- ZMIANA: Dodajemy klasę .bg-body-tertiary do stopki karty -->
                <div class="card-footer bg-body-tertiary"><div id="chart-mode-selector" class="btn-group w-100" role="group"></div></div>
            </div>
            <div class="card">
                <div class="card-header bg-dark text-white"><h5 class="mb-0">Historia (w wybranym okresie)</h5></div>
                <div class="table-responsive" style="max-height: 400px;"><table class="table table-striped table-hover mb-0"><thead><tr><th>Data</th><th>Najlepsza seria</th></tr></thead><tbody><?php if (!empty($periodStats['detailedHistory'])): ?><?php foreach (array_reverse($periodStats['detailedHistory']) as $entry): ?><tr><td><?= htmlspecialchars($entry['date']) ?></td><td><?php $details = []; if (isset($entry['set']['reps'])) $details[] = "<strong>{$entry['set']['reps']}</strong>p"; if (isset($entry['set']['weight'])) $details[] = "<strong>{$entry['set']['weight']}</strong>kg"; if (isset($entry['set']['time'])) $details[] = "<strong>{$entry['set']['time']}</strong>s"; echo implode(' <span class="text-muted mx-1">x</span> ', $details); ?></td></tr><?php endforeach; ?><?php else: ?><tr><td colspan="2" class="text-center p-4 text-muted">Brak zapisanych serii w wybranym okresie.</td></tr><?php endif; ?></tbody></table></div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const detailedCtx = document.getElementById('detailedChart').getContext('2d');
        const chartData = <?= json_encode($detailedStats['chartData']) ?>;
        const weightHistory = <?= json_encode(array_map(fn($m) => ['x' => $m['date'], 'y' => $m['weight']], $userMeasurements)) ?>;
        let detailedChart;

        const weightDataset = { type: 'line', label: 'Waga ciała (kg)', data: weightHistory, borderColor: 'rgba(255, 159, 64, 0.8)', backgroundColor: 'rgba(255, 159, 64, 0.2)', borderDash: [5, 5], yAxisID: 'yWeight', hidden: true };
        const weightScale = { yWeight: { type: 'linear', display: false, position: 'right', title: { display: true, text: 'Waga (kg)' }, grid: { drawOnChartArea: false } } };

        const chartModes = {};
        <?php if (in_array('weight', $selectedExercise['track_by']) && in_array('reps', $selectedExercise['track_by'])): ?>
            chartModes.strength_volume = { name: 'Siła vs Objętość', datasets: [{ type: 'line', label: 'Progres e1RM (kg)', data: chartData.e1rm, borderColor: '#0d6efd', yAxisID: 'yPrimary', tension: 0.2, spanGaps: true }, { type: 'bar', label: 'Objętość (tony)', data: chartData.volume, backgroundColor: 'rgba(13, 202, 240, 0.2)', yAxisID: 'ySecondary' }, weightDataset ], scales: { yPrimary: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'kg (e1RM)' } }, ySecondary: { type: 'linear', display: true, position: 'right', title: { display: true, text: 'Tony' }, grid: { drawOnChartArea: false } }, ...weightScale } };
            chartModes.strength_analysis = { name: 'Analiza Siły', datasets: [{ type: 'line', label: 'Progres e1RM (kg)', data: chartData.e1rm, borderColor: '#0d6efd', tension: 0.2, spanGaps: true }, { type: 'line', label: 'Max Ciężar (kg)', data: chartData.maxWeight, borderColor: '#dc3545', tension: 0.2, borderDash: [5, 5], spanGaps: true }, weightDataset ], scales: { y: { beginAtZero: false, title: { display: true, text: 'kg' } }, ...weightScale } };
        <?php endif; ?>
        <?php if (in_array('reps', $selectedExercise['track_by'])): ?>
            chartModes.reps_analysis = { name: 'Analiza Powtórzeń', datasets: [{ type: 'line', label: 'Max Powtórzeń', data: chartData.maxReps, borderColor: '#198754', tension: 0.2, spanGaps: true }, weightDataset], scales: { y: { beginAtZero: false, title: { display: true, text: 'Powtórzenia' } }, ...weightScale } };
        <?php endif; ?>
        <?php if (in_array('time', $selectedExercise['track_by'])): ?>
            chartModes.time_analysis = { name: 'Analiza Czasu', datasets: [{ type: 'line', label: 'Max Czas (s)', data: chartData.maxTime, borderColor: '#fd7e14', tension: 0.2, spanGaps: true }, weightDataset], scales: { y: { beginAtZero: false, title: { display: true, text: 'Sekundy' } }, ...weightScale } };
        <?php endif; ?>
        
        const selectorContainer = document.getElementById('chart-mode-selector');
        const firstModeKey = Object.keys(chartModes)[0];
        Object.keys(chartModes).forEach(key => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-primary' + (key === firstModeKey ? ' active' : '');
            button.dataset.mode = key;
            button.textContent = chartModes[key].name;
            selectorContainer.appendChild(button);
        });

        function drawChart(modeKey) {
            if (detailedChart) { detailedChart.destroy(); }
            const config = chartModes[modeKey];
            if (!config) return;

            detailedChart = new Chart(detailedCtx, {
                type: 'bar',
                data: { labels: chartData.labels, datasets: config.datasets },
                options: { responsive: true, maintainAspectRatio: true, scales: config.scales, plugins: { legend: { onClick: (e, legendItem, legend) => { Chart.defaults.plugins.legend.onClick(e, legendItem, legend); const weightAxis = legend.chart.scales.yWeight; if (weightAxis) { weightAxis.options.display = legend.chart.isDatasetVisible(legend.chart.data.datasets.findIndex(ds => ds.yAxisID === 'yWeight')); legend.chart.update(); } } } } }
            });
        }
        
        selectorContainer.addEventListener('click', e => {
            if (e.target.tagName === 'BUTTON') {
                selectorContainer.querySelector('.active')?.classList.remove('active');
                e.target.classList.add('active');
                drawChart(e.target.dataset.mode);
            }
        });
        
        if (firstModeKey) drawChart(firstModeKey);
    });
    </script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>