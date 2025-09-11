<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy użytkownik jest zalogowany I czy ma rolę 'admin'
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    // Jeśli nie, przekieruj go na stronę główną z komunikatem o błędzie
    // Możesz też stworzyć dedykowaną stronę "Brak dostępu"
    http_response_code(403); // Forbidden
    echo "<h1>403 - Brak dostępu</h1><p>Nie masz uprawnień, aby wyświetlić tę stronę.</p>";
    echo '<a href="../dashboard.php">Wróć do panelu</a>';
    exit();
}