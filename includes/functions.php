<?php
// Plik z funkcjami pomocniczymi

/**
 * Definiuje ścieżkę do głównego katalogu z danymi, aby unikać pomyłek.
 */
define('DATA_DIR', __DIR__ . '/../data/');

// ===================================================================
// === NOWE, UNIWERSALNE FUNKCJE POMOCNICZE (REFAKTORYZACJA) ===
// ===================================================================

/**
 * Wyszukuje element w tablicy na podstawie wartości klucza i zwraca jego indeks.
 * @param array $array Przeszukiwana tablica.
 * @param mixed $value Wartość do znalezienia.
 * @param string $key Klucz, po którym przeszukujemy (domyślnie 'id').
 * @return int|null Indeks znalezionego elementu lub null, jeśli nie znaleziono.
 */
function find_item_index_in_array(array $array, $value, string $key = 'id'): ?int {
    foreach ($array as $index => $item) {
        // Upewniamy się, że klucz istnieje w elemencie tablicy przed porównaniem
        if (isset($item[$key]) && $item[$key] === $value) {
            return $index;
        }
    }
    return null;
}

/**
 * Wyszukuje element w tablicy na podstawie wartości klucza i zwraca ten element.
 * @param array $array Przeszukiwana tablica.
 * @param mixed $value Wartość do znalezienia.
 * @param string $key Klucz, po którym przeszukujemy (domyślnie 'id').
 * @return array|null Znaleziony element (jako tablica) lub null.
 */
function find_item_in_array(array $array, $value, string $key = 'id'): ?array {
    $index = find_item_index_in_array($array, $value, $key);
    return $index !== null ? $array[$index] : null;
}


// ===================================================================
// === ISTNIEJĄCE FUNKCJE (BEZ ZMIAN STRUKTURALNYCH) ===
// ===================================================================

/**
 * Wczytuje i zwraca listę wszystkich ćwiczeń.
 * @return array Tablica z ćwiczeniami.
 */
function get_all_exercises(): array {
    $filePath = DATA_DIR . 'exercises.json';
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Wczytuje i zwraca treningi dla konkretnego użytkownika.
 * @param string $userId ID użytkownika.
 * @return array Tablica z treningami użytkownika.
 */
function get_user_workouts(string $userId): array {
    $filePath = DATA_DIR . "workouts_{$userId}.json";
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje treningi dla konkretnego użytkownika do pliku JSON.
 * @param string $userId ID użytkownika.
 * @param array $workouts Tablica z treningami do zapisania.
 * @return bool
 */
function save_user_workouts(string $userId, array $workouts): bool {
    $filePath = DATA_DIR . "workouts_{$userId}.json";
    $json = json_encode($workouts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

/**
 * Wczytuje i zwraca plany treningowe dla konkretnego użytkownika.
 * @param string $userId ID użytkownika.
 * @return array
 */
function get_user_plans(string $userId): array {
    $filePath = DATA_DIR . "plans_{$userId}.json";
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje plany treningowe dla konkretnego użytkownika do pliku JSON.
 * @param string $userId ID użytkownika.
 * @param array $plans Tablica z planami do zapisania.
 * @return bool
 */
function save_user_plans(string $userId, array $plans): bool {
    $filePath = DATA_DIR . "plans_{$userId}.json";
    $json = json_encode($plans, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

/**
 * Wczytuje i zwraca cele dla konkretnego użytkownika.
 * @param string $userId ID użytkownika.
 * @return array
 */
function get_user_goals(string $userId): array {
    $filePath = DATA_DIR . "goals_{$userId}.json";
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje cele dla konkretnego użytkownika do pliku JSON.
 * @param string $userId ID użytkownika.
 * @param array $goals Tablica z celami do zapisania.
 * @return bool
 */
function save_user_goals(string $userId, array $goals): bool {
    $filePath = DATA_DIR . "goals_{$userId}.json";
    $json = json_encode($goals, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

/**
 * Wczytuje i zwraca listę wszystkich tagów z pliku JSON.
 * @return array
 */
function get_all_tags(): array {
    $filePath = DATA_DIR . 'tags.json';
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje tagi do pliku JSON.
 * @param array $tags Tablica z tagami do zapisania.
 * @return bool
 */
function save_tags(array $tags): bool {
    $filePath = DATA_DIR . 'tags.json';
    $json = json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

function save_exercises(array $exercises): bool {
    usort($exercises, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    $filePath = DATA_DIR . 'exercises.json';
    $json = json_encode($exercises, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

/**
 * Wczytuje i zwraca wszystkie dane społecznościowe (znajomi, zaproszenia).
 * @return array
 */
function get_social_data(): array {
    $filePath = DATA_DIR . 'social.json';
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje wszystkie dane społecznościowe do pliku.
 * @param array $socialData Tablica z danymi do zapisania.
 * @return bool
 */
function save_social_data(array $socialData): bool {
    $filePath = DATA_DIR . 'social.json';
    $json = json_encode($socialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}

// ... (pozostałe funkcje pomocnicze jak ensure_social_profile_exists, calculate_new_prs, get_exercise_prs itd. - BEZ ZMIAN) ...
// Poniżej wklejam resztę funkcji, abyś miał pewność, że wszystko jest kompletne

function ensure_social_profile_exists(string $userId): array {
    $socialData = get_social_data();
    if (!isset($socialData[$userId])) {
        $socialData[$userId] = [ "friends" => [], "pending_sent" => [], "pending_received" => [] ];
        save_social_data($socialData);
    }
    return $socialData;
}
function calculate_new_prs_for_workout(array $currentWorkout, string $userId): array {
    $allWorkouts = get_user_workouts($userId); $oldPRs = [];
    foreach ($allWorkouts as $workout) {
        if (strtotime($workout['date']) < strtotime($currentWorkout['date']) || (strtotime($workout['date']) == strtotime($currentWorkout['date']) && $workout['workout_id'] < $currentWorkout['workout_id'])) {
            foreach ($workout['exercises'] as $exercise) {
                $exId = $exercise['exercise_id']; if (!isset($oldPRs[$exId])) $oldPRs[$exId] = ['max_weight' => 0, 'e1rm' => 0];
                foreach ($exercise['sets'] as $set) {
                    $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; if ($weight > $oldPRs[$exId]['max_weight']) $oldPRs[$exId]['max_weight'] = $weight;
                    if ($reps > 0 && $weight > 0) { $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1); if ($e1rm > $oldPRs[$exId]['e1rm']) $oldPRs[$exId]['e1rm'] = $e1rm; }
                }
            }
        }
    }
    $newPRsFound = [];
    foreach ($currentWorkout['exercises'] as $exercise) {
        $exId = $exercise['exercise_id']; $oldPrForEx = $oldPRs[$exId] ?? ['max_weight' => 0, 'e1rm' => 0]; $prInThisSession = ['max_weight' => 0, 'e1rm' => 0];
        foreach ($exercise['sets'] as $set) {
            $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; if ($weight > $prInThisSession['max_weight']) $prInThisSession['max_weight'] = $weight;
            if ($reps > 0 && $weight > 0) { $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1); if ($e1rm > $prInThisSession['e1rm']) $prInThisSession['e1rm'] = $e1rm; }
        }
        if ($prInThisSession['max_weight'] > $oldPrForEx['max_weight']) $newPRsFound[$exId]['max_weight'] = $prInThisSession['max_weight'];
        if ($prInThisSession['e1rm'] > $oldPrForEx['e1rm']) $newPRsFound[$exId]['e1rm'] = $prInThisSession['e1rm'];
    }
    return $newPRsFound;
}
function get_user_measurements(string $userId): array {
    $filePath = DATA_DIR . "measurements_{$userId}.json";
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath); $data = json_decode($json, true) ?? []; usort($data, fn($a, $b) => strcmp($a['date'], $b['date'])); return $data;
}
function save_user_measurements(string $userId, array $measurements): bool {
    usort($measurements, fn($a, $b) => strcmp($a['date'], $b['date']));
    $filePath = DATA_DIR . "measurements_{$userId}.json";
    $json = json_encode($measurements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}
function get_last_performance(int $exerciseId, string $userId): ?array {
    $userWorkouts = get_user_workouts($userId); usort($userWorkouts, fn($a, $b) => strcmp($b['date'], $a['date']));
    foreach ($userWorkouts as $workout) { foreach ($workout['exercises'] as $exercise) { if ($exercise['exercise_id'] === $exerciseId) return $exercise['sets']; } }
    return null;
}
function get_exercise_prs(int $exerciseId, string $userId): array {
    $prs = ['max_weight' => 0, 'e1rm' => 0]; $userWorkouts = get_user_workouts($userId);
    foreach ($userWorkouts as $workout) { foreach ($workout['exercises'] as $exercise) { if ($exercise['exercise_id'] === $exerciseId) { foreach ($exercise['sets'] as $set) {
        $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; if ($weight > $prs['max_weight']) $prs['max_weight'] = $weight;
        if ($reps > 0 && $weight > 0) { $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1); if ($e1rm > $prs['e1rm']) $prs['e1rm'] = $e1rm; }
    } } } } return $prs;
}
function auto_embed_youtube_videos(?string $content): string {
    if (empty($content)) return ''; $pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})([^\s<]*)/';
    $replacement = '<div class="ratio ratio-16x9 my-3"><iframe src="https://www.youtube.com/embed/$1" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
    return preg_replace($pattern, $replacement, $content);
}
function get_global_rankings_for_users(array $exerciseIds, array $userIds): array {
    $allUsersData = json_decode(file_get_contents(DATA_DIR . 'users.json'), true); $usersMap = array_column($allUsersData, null, 'id'); $rankings = [];
    foreach ($exerciseIds as $exId) $rankings[$exId] = [];
    foreach ($userIds as $userId) {
        if (!isset($usersMap[$userId])) continue;
        $userName = $usersMap[$userId]['name']; $userIcon = $usersMap[$userId]['icon']; $userWorkouts = get_user_workouts($userId); $userTopLifts = [];
        foreach ($exerciseIds as $exId) $userTopLifts[$exId] = ['value' => 0, 'date' => null];
        foreach ($userWorkouts as $workout) { foreach ($workout['exercises'] as $exercise) { $exId = $exercise['exercise_id']; if (in_array($exId, $exerciseIds)) {
            foreach ($exercise['sets'] as $set) { $weight = $set['weight'] ?? 0; $reps = $set['reps'] ?? 0; if ($reps > 0 && $weight > 0) {
                $e1rm = ($reps === 1) ? $weight : round($weight / (1.0278 - (0.0278 * $reps)), 1);
                if ($e1rm > $userTopLifts[$exId]['value']) { $userTopLifts[$exId]['value'] = $e1rm; $userTopLifts[$exId]['date'] = $workout['date']; }
            } }
        } } }
        foreach ($userTopLifts as $exId => $topLift) { if ($topLift['value'] > 0) $rankings[$exId][] = ['user_id' => $userId, 'user_name' => $userName, 'user_icon' => $userIcon, 'value' => $topLift['value'], 'date' => $topLift['date']]; }
    }
    foreach ($rankings as &$rankingData) usort($rankingData, fn($a, $b) => $b['value'] <=> $a['value']); return $rankings;
}
function get_daily_rep_PR(string $userId, int $exerciseId, ?string $excludeWorkoutId = null): int {
    $allWorkouts = get_user_workouts($userId); $repsByDay = [];
    foreach ($allWorkouts as $workout) {
        if ($workout['workout_id'] === $excludeWorkoutId) continue;
        $date = $workout['date']; if (!isset($repsByDay[$date])) $repsByDay[$date] = 0;
        $repsInThisWorkout = 0; foreach ($workout['exercises'] as $exercise) { if ($exercise['exercise_id'] === $exerciseId) { $repsInThisWorkout += array_sum(array_column($exercise['sets'], 'reps')); } }
        $repsByDay[$date] += $repsInThisWorkout;
    }
    return empty($repsByDay) ? 0 : max($repsByDay);
}
function get_all_categories(): array {
    $filePath = DATA_DIR . 'categories.json'; if (!file_exists($filePath)) return []; return json_decode(file_get_contents($filePath), true) ?? [];
}
function save_categories(array $categories): bool {
    sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
    $filePath = DATA_DIR . 'categories.json';
    $json = json_encode(array_values($categories), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}
function get_all_trackable_params(): array {
    $filePath = DATA_DIR . 'trackable_params.json'; if (!file_exists($filePath)) return []; return json_decode(file_get_contents($filePath), true) ?? [];
}
function save_trackable_params(array $params): bool {
    usort($params, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    $filePath = DATA_DIR . 'trackable_params.json';
    $json = json_encode(array_values($params), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}
function create_backup(): array {
    $projectRoot = realpath(__DIR__ . '/../../'); $sourceDir = $projectRoot . '/koks'; $backupDir = $projectRoot . '/AutoBackup/'; $maxBackups = 20;
    if (!extension_loaded('zip')) return ['success' => false, 'message' => 'Rozszerzenie PHP "zip" nie jest zainstalowane na serwerze.'];
    if (!is_dir($backupDir)) { if (!mkdir($backupDir, 0775, true)) return ['success' => false, 'message' => 'Nie można utworzyć katalogu na kopie zapasowe.']; }
    $filename = $backupDir . 'backup_' . date('Y-m-d_H-i-s') . '.zip'; $zip = new ZipArchive();
    if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return ['success' => false, 'message' => 'Nie można otworzyć pliku archiwum ZIP.'];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) { if (!$file->isDir()) { $filePath = $file->getRealPath(); $relativePath = 'koks/' . substr($filePath, strlen($sourceDir) + 1); $zip->addFile($filePath, $relativePath); } }
    $zip->close(); $backupFiles = glob($backupDir . '*.zip');
    if (count($backupFiles) > $maxBackups) { usort($backupFiles, fn($a, $b) => filemtime($a) <=> filemtime($b)); $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - $maxBackups); foreach ($filesToDelete as $file) unlink($file); }
    return ['success' => true, 'message' => 'Kopia zapasowa została pomyślnie utworzona.'];
}

/**
 * Wczytuje harmonogram zaplanowanych treningów dla użytkownika.
 * @param string $userId ID użytkownika.
 * @return array
 */
function get_user_schedule(string $userId): array {
    $filePath = DATA_DIR . "schedule_{$userId}.json";
    if (!file_exists($filePath)) {
        return [];
    }
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje harmonogram zaplanowanych treningów dla użytkownika.
 * @param string $userId ID użytkownika.
 * @param array $schedule
 * @return bool
 */
function save_user_schedule(string $userId, array $schedule): bool {
    $filePath = DATA_DIR . "schedule_{$userId}.json";
    $json = json_encode(array_values($schedule), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($filePath, $json) !== false;
}