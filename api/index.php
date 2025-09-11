<?php
// Plik: koks/api/index.php
// Centralny router dla wszystkich zapytań API

// --- KROK 1: INICJALIZACJA I USTAWIENIA GŁÓWNE ---

// Ustawienie nagłówków, aby zapewnić, że odpowiedź jest zawsze w formacie JSON
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dołączamy wszystkie potrzebne pliki z funkcjami
require_once '../includes/functions.php';
require_once '../includes/coach_functions.php';

// --- KROK 2: FUNKCJE POMOCNICZE DO WYSYŁANIA ODPOWIEDZI ---

/**
 * Wysyła standardową odpowiedź JSON z podanym kodem statusu HTTP.
 * @param array $data Dane do zakodowania w JSON.
 * @param int $statusCode Kod statusu HTTP (domyślnie 200 OK).
 */
function send_json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

/**
 * Wysyła odpowiedź JSON z błędem.
 * @param string $message Komunikat błędu.
 * @param int $statusCode Kod statusu HTTP (domyślnie 400 Bad Request).
 * @param array $extraData Dodatkowe dane do dołączenia do odpowiedzi.
 */
function send_json_error(string $message, int $statusCode = 400, array $extraData = []): void {
    $response = array_merge(['success' => false, 'message' => $message], $extraData);
    send_json_response($response, $statusCode);
}

// --- KROK 3: POBRANIE I WALIDACJA DANYCH WEJŚCIOWYCH ---

// Pobieramy dane wejściowe - albo z ciała żądania (dla POST), albo z parametrów URL (dla GET)
$requestData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestData = json_decode(file_get_contents('php://input'), true);
    if ($requestData === null) {
        send_json_error('Nieprawidłowe dane JSON.', 400);
    }
}
// Łączymy dane z GET, aby router obsługiwał obie metody
$requestData = array_merge($_GET, $requestData);

$action = $requestData['action'] ?? null;
if (!$action) {
    send_json_error('Nie określono akcji API.', 400);
}

// --- KROK 4: ZABEZPIECZENIA I UWIERZYTELNIANIE ---

// Większość akcji wymaga zalogowanego użytkownika.
// Można dodać wyjątki, jeśli jakaś akcja ma być publiczna.
$publicActions = []; // Na razie brak publicznych akcji
if (!in_array($action, $publicActions) && !isset($_SESSION['user_id'])) {
    send_json_error('Brak autoryzacji. Wymagane logowanie.', 403);
}

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserRole = $_SESSION['user_role'] ?? 'user';
$currentUserName = $_SESSION['user_name'] ?? 'Użytkownik';

// --- KROK 5: GŁÓWNY ROUTER - WYWOŁANIE ODPOWIEDNIEJ AKCJI ---

switch ($action) {
    
    case 'save_solo_session':
        $sessionFilePath = __DIR__ . '/../data/solo_sessions/solo_' . $currentUserId . '.json';
        if (file_put_contents($sessionFilePath, json_encode($requestData, JSON_PRETTY_PRINT))) {
            send_json_response(['success' => true, 'message' => 'Sesja zapisana.']);
        } else {
            send_json_error('Błąd serwera podczas zapisu sesji.', 500);
        }
        break;

    case 'get_user_plans':
        $targetUserId = $requestData['user_id'] ?? null;
        if (!$targetUserId) {
            send_json_error('Nie podano ID użytkownika.');
        }
        $plans = get_user_plans($targetUserId);
        send_json_response($plans);
        break;
        
    case 'handle_interaction':
        $ownerId = $requestData['ownerId'] ?? null;
        $workoutId = $requestData['workoutId'] ?? null;
        $subAction = $requestData['subAction'] ?? null; // Zmieniono na subAction dla jasności
        $commentText = $requestData['commentText'] ?? null;

        if (!$ownerId || !$workoutId || !$subAction) {
            send_json_error('Niekompletne dane interakcji.');
        }

        $ownerWorkouts = get_user_workouts($ownerId);
        // UŻYCIE NOWEJ FUNKCJI POMOCNICZEJ
        $workoutIndex = find_item_index_in_array($ownerWorkouts, $workoutId, 'workout_id');

        if ($workoutIndex === null) {
            send_json_error('Nie znaleziono treningu.', 404);
        }

        if (!isset($ownerWorkouts[$workoutIndex]['interactions'])) {
            $ownerWorkouts[$workoutIndex]['interactions'] = ['likes' => [], 'comments' => []];
        }

        $response = ['success' => false];
        switch ($subAction) {
            case 'like':
                if (!in_array($currentUserId, $ownerWorkouts[$workoutIndex]['interactions']['likes'])) {
                    $ownerWorkouts[$workoutIndex]['interactions']['likes'][] = $currentUserId;
                    $response['action'] = 'liked';
                } else {
                    $ownerWorkouts[$workoutIndex]['interactions']['likes'] = array_diff($ownerWorkouts[$workoutIndex]['interactions']['likes'], [$currentUserId]);
                    $response['action'] = 'unliked';
                }
                $response['success'] = true;
                break;
            case 'comment':
                if (!empty(trim($commentText))) {
                    $newComment = ['comment_id' => 'c_' . date('YmdHis') . '_' . bin2hex(random_bytes(2)), 'user_id' => $currentUserId, 'user_name' => $currentUserName, 'text' => htmlspecialchars(trim($commentText)), 'timestamp' => date('c')];
                    $ownerWorkouts[$workoutIndex]['interactions']['comments'][] = $newComment;
                    $response['success'] = true;
                    $response['newComment'] = $newComment;
                } else {
                    send_json_error('Komentarz nie może być pusty.');
                }
                break;
        }

        if ($response['success']) {
            if (save_user_workouts($ownerId, $ownerWorkouts)) {
                $response['likesCount'] = count($ownerWorkouts[$workoutIndex]['interactions']['likes']);
                send_json_response($response);
            } else {
                send_json_error('Błąd zapisu danych.', 500);
            }
        }
        break;

    case 'handle_calendar':
        $subAction = $requestData['subAction'] ?? null;
        $targetUserId = $requestData['targetUserId'] ?? $currentUserId;

        // Walidacja uprawnień trenera
        if ($targetUserId !== $currentUserId && $currentUserRole !== 'admin') {
            $coachingData = get_coaching_data();
            $myClientIds = $coachingData[$currentUserId] ?? [];
            if (!in_array($targetUserId, $myClientIds)) {
                send_json_error('Brak uprawnień do edycji kalendarza tego użytkownika.', 403);
            }
        }

        $schedule = get_user_schedule($targetUserId);
        $response = ['success' => false];

        switch ($subAction) {
            case 'add':
                $newEvent = [ 'id' => 'event_' . time() . '_' . bin2hex(random_bytes(2)), 'title' => trim(htmlspecialchars($requestData['title'])), 'start' => $requestData['date'], 'planId' => $requestData['planId'], 'type' => $requestData['type'], 'color' => $requestData['color'], 'isCoachSession' => $requestData['isCoachSession'] ?? false, 'createdBy' => $currentUserId ];
                $schedule[] = $newEvent;
                $response['success'] = true;
                break;
            case 'edit':
                $eventId = $requestData['id'];
                $eventIndex = find_item_index_in_array($schedule, $eventId);
                if ($eventIndex !== null) {
                    if (($schedule[$eventIndex]['createdBy'] ?? null) === $currentUserId || $currentUserRole === 'admin' || $targetUserId !== $currentUserId) {
                        $schedule[$eventIndex]['title'] = trim(htmlspecialchars($requestData['title']));
                        $schedule[$eventIndex]['planId'] = $requestData['planId'];
                        $schedule[$eventIndex]['color'] = $requestData['color'];
                        $schedule[$eventIndex]['isCoachSession'] = $requestData['isCoachSession'] ?? false;
                        $response['success'] = true;
                    }
                }
                if (!$response['success']) $response['message'] = "Nie znaleziono wydarzenia lub brak uprawnień do edycji.";
                break;
            case 'update_date':
                $eventId = $requestData['id'];
                $eventIndex = find_item_index_in_array($schedule, $eventId);
                if ($eventIndex !== null) {
                    $schedule[$eventIndex]['start'] = $requestData['newDate'];
                    $response['success'] = true;
                }
                break;
            case 'delete':
                $eventId = $requestData['id'];
                $eventToDelete = find_item_in_array($schedule, $eventId);
                if ($eventToDelete) {
                    if (($eventToDelete['createdBy'] ?? null) === $currentUserId || $currentUserRole === 'admin' || $targetUserId !== $currentUserId) {
                        $schedule = array_filter($schedule, fn($event) => $event['id'] !== $eventId);
                        $response['success'] = true;
                    }
                }
                if (!$response['success']) $response['message'] = "Nie znaleziono wydarzenia lub brak uprawnień do usunięcia.";
                break;
        }

        if ($response['success']) {
            if (save_user_schedule($targetUserId, $schedule)) {
                send_json_response($response);
            } else {
                send_json_error('Błąd serwera podczas zapisu harmonogramu.', 500);
            }
        } else {
            send_json_error($response['message'] ?? 'Operacja kalendarza nie powiodła się.', 403);
        }
        break;

    default:
        send_json_error('Nieznana akcja API: ' . htmlspecialchars($action), 404);
        break;
}