<?php
// Plik: koks/includes/coach_functions.php

/**
 * Wczytuje i zwraca relacje trener-klient.
 * @return array
 */
function get_coaching_data(): array {
    $filePath = __DIR__ . '/../data/coaching.json';
    if (!file_exists($filePath)) return [];
    $json = file_get_contents($filePath);
    return json_decode($json, true) ?? [];
}

/**
 * Zapisuje relacje trener-klient.
 * @param array $data
 * @return bool
 */
function save_coaching_data(array $data): bool {
    $filePath = __DIR__ . '/../data/coaching.json';
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents($filePath, $json) !== false;
}