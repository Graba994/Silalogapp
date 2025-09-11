<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['group_id']) || !isset($_GET['tag_id'])) {
    header('Location: index.php');
    exit();
}

$groupId = $_GET['group_id'];
$tagIdToDelete = $_GET['tag_id'];
$allGroups = get_all_tags();

foreach ($allGroups as &$group) { // & jest kluczowe
    if ($group['group_id'] === $groupId) {
        $group['tags'] = array_filter($group['tags'], fn($tag) => $tag['id'] !== $tagIdToDelete);
        // Zresetuj indeksy w tablicy tagów
        $group['tags'] = array_values($group['tags']);
        break;
    }
}

save_tags($allGroups);

header('Location: manage_tags.php');
exit();