<?php
// Zawsze rozpoczynamy sesję, aby mieć do niej dostęp
session_start();
// Dołączamy funkcje. Nawet jeśli nie są tu bezpośrednio używane, 
// mogą ustawiać konfiguracje (np. error reporting), co jest dobrą praktyką.
require_once 'includes/functions.php'; 

// Ustawienie nagłówka na JSON jest kluczowe, aby odpowiedź była prawidłowo
// zinterpretowana przez JavaScript (fetch)
header("Content-Type: application/json");

// --- KROK 1: ZABEZPIECZENIA ---

// Sprawdzamy, czy użytkownik jest zalogowany i czy żądanie jest typu POST
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Brak dostępu.']);
    exit; // Zatrzymujemy wykonywanie skryptu
}

// --- KROK 2: ODCZYT DANYCH WEJŚCIOWYCH ---

$currentUserId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$liveWorkoutId = $data['live_workout_id'] ?? null;

// Sprawdzamy, czy otrzymaliśmy ID treningu
if (!$liveWorkoutId) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Brak ID treningu w żądaniu.']);
    exit;
}

// --- KROK 3: GŁÓWNA LOGIKA ---

// Używamy basename(), aby zapobiec atakom typu "directory traversal"
$safeLiveWorkoutId = basename($liveWorkoutId);
$liveWorkoutPath = 'data/live_workouts/' . $safeLiveWorkoutId . '.json';

// Sprawdzamy, czy plik treningu w ogóle istnieje
if (!file_exists($liveWorkoutPath)) {
    // Jeśli plik już nie istnieje, operacja się "udała". Czyścimy sesję.
    unset($_SESSION['active_live_workout_id']);
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Ten trening już nie jest aktywny.']);
    exit;
}

$liveWorkout = json_decode(file_get_contents($liveWorkoutPath), true);

// **KLUCZOWA POPRAWKA**: Sprawdzamy, czy plik nie jest uszkodzony lub pusty
if ($liveWorkout === null) {
    // Jeśli json_decode się nie powiodło, plik jest nieprawidłowy.
    // Zwracamy błąd serwera i bezpiecznie usuwamy taki plik.
    unlink($liveWorkoutPath);
    unset($_SESSION['active_live_workout_id']);
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Plik treningu był uszkodzony i został usunięty.']);
    exit;
}


// Sprawdzamy, czy klucz 'owner_id' istnieje i czy bieżący użytkownik jest właścicielem
if (!isset($liveWorkout['owner_id']) || $liveWorkout['owner_id'] !== $currentUserId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Tylko założyciel treningu może go anulować.']);
    exit;
}

// Próbujemy usunąć plik
if (unlink($liveWorkoutPath)) {
    // Jeśli usunięcie się powiodło, czyścimy sesję
    unset($_SESSION['active_live_workout_id']);
    echo json_encode(['success' => true, 'message' => 'Trening został pomyślnie anulowany.']);
} else {
    // Błąd po stronie serwera, jeśli nie udało się usunąć pliku
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Wystąpił błąd serwera podczas usuwania pliku treningu.']);
}

exit; // Dobre praktyki nakazują zakończyć skrypt po wysłaniu odpowiedzi
?>