<?php
// Plik: koks/includes/theme_functions.php

/**
 * Wczytuje i zwraca konfigurację skórki aplikacji.
 * Zapewnia pełną strukturę domyślną, aby uniknąć błędów.
 *
 * @return array Tablica z ustawieniami skórki.
 */
function get_theme_config(): array {
    $defaultConfig = [
        'appName' => 'SiłaLog',
        'logoPath' => null,
        'faviconPath' => null,
        'navbarBg' => '#212529',
        'navbarText' => '#ffffff',
        'footerText' => 'SiłaLog © {rok} - Prosta aplikacja do śledzenia treningów.',
        'colors' => [
            '--bs-primary' => '#0d6efd',
            '--bs-secondary' => '#6c757d',
            '--bs-success' => '#198754',
            '--bs-danger' => '#dc3545',
            '--bs-info' => '#0dcaf0',
            '--bs-dark' => '#212529'
        ],
        'dashboardWidgets' => [
            'welcome' => ['enabled' => true, 'title' => 'Karta Powitalna'],
            'quote' => ['enabled' => true, 'title' => 'Cytat Motywacyjny'],
            'weeklySummary' => ['enabled' => true, 'title' => 'Twój Tydzień w Liczbach'],
            'recentPRs' => ['enabled' => true, 'title' => 'Ostatnie Osiągnięcia (PRs)'],
            'strengthRankings' => ['enabled' => true, 'title' => 'Rankingi Siły vs Znajomi'],
            'activityHeatmap' => ['enabled' => true, 'title' => 'Mapa Aktywności'],
            'quickStart' => ['enabled' => false, 'title' => 'Szybki Start (Twoje Plany)'],
            'goalProgress' => ['enabled' => false, 'title' => 'Postęp w Celach']
        ],
        'loginPage' => [
            'backgroundType' => 'color',
            'backgroundColor' => '#f8f9fa',
            'backgroundImage' => null,
            'boxColor' => '#ffffff',
            'textColor' => '#212529',
            'welcomeWidgets' => [
                [
                    'enabled' => true,
                    'icon' => 'bi-trophy-fill',
                    'title' => 'Śledź Swój Progres',
                    'text' => 'Zapisuj każdy trening, serię i powtórzenie. Obserwuj, jak Twoja siła rośnie z tygodnia na tydzień.'
                ],
                [
                    'enabled' => true,
                    'icon' => 'bi-journal-text',
                    'title' => 'Twórz i Dziel Się Planami',
                    'text' => 'Projektuj własne plany treningowe lub korzystaj z tych przygotowanych przez Twojego trenera.'
                ],
                [
                    'enabled' => false,
                    'icon' => 'bi-people-fill',
                    'title' => 'Trenuj Razem',
                    'text' => 'Dołącz do wspólnych sesji treningowych na żywo i motywujcie się nawzajem ze znajomymi.'
                ]
            ]
        ]
    ];
    
    $filePath = __DIR__ . '/../data/theme.json'; // Używamy __DIR__ dla pewności ścieżki
    if (!file_exists($filePath)) {
        return $defaultConfig;
    }
    
    $json = file_get_contents($filePath);
    $customConfig = json_decode($json, true) ?? [];
    
    // Użyj array_replace_recursive, aby połączyć konfiguracje.
    // Zachowa to głęboką strukturę (np. poszczególne widżety), nawet jeśli w pliku JSON brakuje jakiegoś klucza.
    return array_replace_recursive($defaultConfig, $customConfig);
}