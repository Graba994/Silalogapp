<?php
// Zawsze rozpoczynaj sesję, aby mieć do niej dostęp
session_start();

// Usuń wszystkie zmienne sesji
$_SESSION = array();

// Zniszcz sesję
session_destroy();

// Przekieruj użytkownika do strony głównej (wyboru profilu)
header('Location: index.php');
exit();
?>