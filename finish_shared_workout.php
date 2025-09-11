<?php
session_start();
require_once 'includes/functions.php'; // Tutaj potrzebujemy funkcji save_user_workouts

// Ustawienie nagłówka na JSON dla spójności odpowiedzi
header("Content-Type: application/json");

// --- KROK 1: ZABEZPIECZENIA ---
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Brak dostępu.']));
}

$currentUserId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$liveWorkoutId = $data['live_workout_id'] ?? null;

if (!$liveWorkoutId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Brak ID treningu.']));
}

// --- KROK 2: BEZPIECZNE ODCZYTANIE I ZABLOKOWANIE PLIKU ---
$safeLiveWorkoutId = basename($liveWorkoutId);
$liveWorkoutPath = 'data/live_workouts/' . $safeLiveWorkoutId . '.json';

if (!file_exists($liveWorkoutPath)) {
    unset($_SESSION['active_live_workout_id']);
    http_response_code(200);
    exit(json_encode(['success' => true, 'message' => 'Trening już nie istnieje.']));
}

$fileHandle = fopen($liveWorkoutPath, 'r+');
if (!$fileHandle || !flock($fileHandle, LOCK_EX)) {
    http_response_code(503);
    exit(json_encode(['success' => false, 'message' => 'Nie można uzyskać dostępu do pliku treningu. Spróbuj ponownie.']));
}

$liveWorkout = json_decode(stream_get_contents($fileHandle), true);

// Tylko właściciel (trener) może zakończyć trening
if ($liveWorkout['owner_id'] !== $currentUserId) {
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Tylko założyciel może zakończyć trening.']));
}

// Jeśli trening już jest zakończony, nie rób nic
if ($liveWorkout['status'] !== 'active') {
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    unset($_SESSION['active_live_workout_id']);
    exit(json_encode(['success' => true, 'message' => 'Trening został już zakończony.']));
}

// --- KROK 3: GŁÓWNA LOGIKA - ZAPISYWANIE WYNIKÓW ---

$isCoachingSession = $liveWorkout['is_coaching_session'] ?? false;
$usersToSaveFor = [];
$notes = "";

if ($isCoachingSession) {
    // Sesja trenerska: zapisz dla wszystkich podopiecznych i opcjonalnie dla trenera
    $usersToSaveFor = $liveWorkout['client_ids'] ?? [];
    if (in_array($currentUserId, $liveWorkout['participants'])) {
        $usersToSaveFor[] = $currentUserId; // Dodaj trenera, jeśli brał udział
    }
    $notes = "Sesja prowadzona przez trenera: " . $liveWorkout['owner_id'];
} else {
    // Zwykły trening wspólny: zapisz dla wszystkich uczestników
    $usersToSaveFor = $liveWorkout['participants'];
    $notes = "Trening wspólny z " . $liveWorkout['owner_id'] . ". Sesja: " . $liveWorkoutId;
}

$usersToSaveFor = array_unique($usersToSaveFor);

foreach ($usersToSaveFor as $participantId) {
    if (empty($participantId)) continue;

    $participantWorkouts = get_user_workouts($participantId);
    
    $finalWorkout = [
        'workout_id' => 'w_' . date('YmdHis') . '_' . bin2hex(random_bytes(2)),
        'date' => date('Y-m-d', strtotime($liveWorkout['start_time'])),
        'notes' => ($participantId === $currentUserId) ? "Prowadziłeś/aś sesję jako trener" : $notes,
        'exercises' => [],
        'interactions' => ['likes' => [], 'comments' => []]
    ];
    
    $participantData = $liveWorkout['live_data'][$participantId] ?? [];

    foreach ($liveWorkout['base_plan']['exercises'] as $exIndex => $baseExercise) {
        $performedSets = [];
        $setsData = $participantData[$exIndex]['sets'] ?? [];

        foreach ($setsData as $setData) {
            if (isset($setData['status']) && $setData['status'] === 'completed') {
                $setRecord = [];
                if (isset($setData['reps']) && $setData['reps'] !== '') $setRecord['reps'] = (int)$setData['reps'];
                if (isset($setData['weight']) && $setData['weight'] !== '') $setRecord['weight'] = (float)$setData['weight'];
                if (isset($setData['time']) && $setData['time'] !== '') $setRecord['time'] = (int)$setData['time'];
                
                if (!empty($setRecord)) {
                    $performedSets[] = $setRecord;
                }
            }
        }
        
        if (!empty($performedSets)) {
             $finalWorkout['exercises'][] = [
                'exercise_id' => (int)$baseExercise['exercise_id'],
                'sets' => $performedSets
            ];
        }
    }
    
    if (!empty($finalWorkout['exercises'])) {
        $participantWorkouts[] = $finalWorkout;
        save_user_workouts($participantId, $participantWorkouts);
    }
}

// --- KROK 4: ARCHIWIZACJA I CZYSZCZENIE ---

$liveWorkout['status'] = 'finished';
fseek($fileHandle, 0);
ftruncate($fileHandle, 0);
fwrite($fileHandle, json_encode($liveWorkout, JSON_PRETTY_PRINT));
flock($fileHandle, LOCK_UN);
fclose($fileHandle);

$archiveDir = 'data/live_workouts/archive/';
if (!is_dir($archiveDir)) mkdir($archiveDir, 0777, true);
rename($liveWorkoutPath, $archiveDir . $safeLiveWorkoutId . '.json');

unset($_SESSION['active_live_workout_id']);

echo json_encode(['success' => true, 'message' => 'Sesja została pomyślnie zakończona i zapisana.']);
?>