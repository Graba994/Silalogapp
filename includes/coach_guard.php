<?php
// Plik: koks/includes/coach_guard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dołącz plik z samymi funkcjami
require_once __DIR__ . '/coach_functions.php';

// Sprawdź, czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "<h1>403 - Brak dostępu</h1><p>Musisz być zalogowany, aby wyświetlić tę stronę.</p>";
    echo '<a href="../index.php">Zaloguj się</a>';
    exit();
}

// Sprawdź, czy użytkownik ma rolę 'coach' LUB 'admin'
$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'coach' && $userRole !== 'admin') {
    http_response_code(403);
    echo "<h1>403 - Brak dostępu</h1><p>Nie masz uprawnień trenera, aby wyświetlić tę stronę.</p>";
    echo '<a href="../dashboard.php">Wróć do panelu</a>';
    exit();
}