<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Content-Type: application/json");

session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Brak autoryzacji']));
}
$currentUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $liveWorkoutId = $_GET['id'] ?? null;
    if (!$liveWorkoutId) { http_response_code(400); exit(json_encode(['status' => 'error', 'message' => 'Brak ID treningu'])); }
    
    $filePath = '../data/live_workouts/' . basename($liveWorkoutId) . '.json';
    if (file_exists($filePath)) {
        // === POPRAWKA: Dodajemy blokadę pliku do odczytu (LOCK_SH) ===
        // Zapobiega to odczytaniu pliku w trakcie, gdy inny proces go zapisuje.
        $fileHandle = fopen($filePath, 'r');
        if ($fileHandle && flock($fileHandle, LOCK_SH)) {
            $data = json_decode(stream_get_contents($fileHandle), true);
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            echo json_encode($data);
        } else {
             // Jeśli nie można zablokować pliku, zwracamy błąd "serwer zajęty"
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'Serwer zajęty, nie można odczytać pliku.']);
        }
    } else {
        echo json_encode(['status' => 'finished']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $liveWorkoutId = $data['live_workout_id'] ?? null;
    
    if (!$liveWorkoutId) { http_response_code(400); exit(json_encode(['status' => 'error', 'message' => 'Brak ID treningu'])); }

    $filePath = '../data/live_workouts/' . basename($liveWorkoutId) . '.json';
    if (!file_exists($filePath)) { http_response_code(404); exit(json_encode(['status' => 'error', 'message' => 'Nie znaleziono pliku'])); }

    $fileHandle = fopen($filePath, 'r+');
    if (!$fileHandle || !flock($fileHandle, LOCK_EX)) { http_response_code(503); exit(json_encode(['status' => 'error', 'message' => 'Serwer zajęty'])); }

    $liveWorkout = json_decode(stream_get_contents($fileHandle), true);
    
    // Dodatkowe zabezpieczenie przed uszkodzonym plikiem JSON
    if ($liveWorkout === null) {
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);
        unlink($filePath); // Usuń uszkodzony plik
        http_response_code(500);
        exit(json_encode(['status' => 'error', 'message' => 'Plik treningu był uszkodzony. Został usunięty.']));
    }

    $action = $data['action'] ?? '';
    
    $isCoach = ($liveWorkout['coach_mode'] ?? false) && ($liveWorkout['owner_id'] === $currentUserId);
    $canAnyoneAdd = !($liveWorkout['coach_mode'] ?? false);
    
    switch ($action) {
        case 'initialize':
            $needsUpdate = false;
            if ($liveWorkout['base_plan']['plan_id'] !== 'adhoc') {
                foreach ($liveWorkout['participants'] as $pId) {
                    if (empty($liveWorkout['live_data'][$pId])) {
                        $needsUpdate = true;
                        foreach ($liveWorkout['base_plan']['exercises'] as $exIndex => $exercise) {
                            $targetSets = isset($exercise['target_sets']) && is_array($exercise['target_sets']) ? $exercise['target_sets'] : [];
                            $liveWorkout['live_data'][$pId][$exIndex] = [
                                'exercise_id' => $exercise['exercise_id'],
                                'sets' => array_map(fn($ts) => ['reps' => $ts['reps'] ?? '', 'weight' => $ts['weight'] ?? '', 'status' => 'pending'], $targetSets)
                            ];
                        }
                    }
                }
            }
            if (!$needsUpdate) {
                flock($fileHandle, LOCK_UN); fclose($fileHandle);
                echo json_encode(['success' => true, 'updated_workout' => $liveWorkout]);
                exit;
            }
            break;

        case 'update_set':
            $pId = $data['pId']; $exIndex = $data['exIndex']; $setIndex = $data['setIndex']; $setData = $data['setData'];
            if ($pId === $currentUserId || $isCoach) {
                if (isset($liveWorkout['live_data'][$pId][$exIndex]['sets'][$setIndex])) {
                    $liveWorkout['live_data'][$pId][$exIndex]['sets'][$setIndex] = $setData;
                }
            }
            break;

        case 'add_set':
            $exIndex = $data['exIndex'];
            if ($isCoach || $canAnyoneAdd) {
                foreach ($liveWorkout['participants'] as $pId) {
                    if (!isset($liveWorkout['live_data'][$pId])) $liveWorkout['live_data'][$pId] = [];
                    if (!isset($liveWorkout['live_data'][$pId][$exIndex])) {
                        $exerciseId = $liveWorkout['base_plan']['exercises'][$exIndex]['exercise_id'] ?? 0;
                        $liveWorkout['live_data'][$pId][$exIndex] = ['exercise_id' => $exerciseId, 'sets' => []];
                    }
                    $liveWorkout['live_data'][$pId][$exIndex]['sets'][] = ['reps' => '', 'weight' => '', 'status' => 'pending'];
                }
            }
            break;

        case 'add_exercise':
             if ($isCoach || $canAnyoneAdd) {
                $exerciseId = (int)$data['exerciseId'];
                $liveWorkout['base_plan']['exercises'][] = ['exercise_id' => $exerciseId, 'target_sets' => []];
                $newExIndex = count($liveWorkout['base_plan']['exercises']) - 1;
                foreach ($liveWorkout['participants'] as $pId) {
                    if (!isset($liveWorkout['live_data'][$pId])) $liveWorkout['live_data'][$pId] = [];
                    $liveWorkout['live_data'][$pId][$newExIndex] = ['exercise_id' => $exerciseId, 'sets' => []];
                }
            }
            break;
    }
    
    fseek($fileHandle, 0);
    ftruncate($fileHandle, 0);
    fwrite($fileHandle, json_encode($liveWorkout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fileHandle, LOCK_UN);
    fclose($fileHandle);
    
    echo json_encode(['success' => true, 'updated_workout' => $liveWorkout]);
}
?>