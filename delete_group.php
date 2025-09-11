<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$groupIdToDelete = $_GET['id'];
$allGroups = get_all_tags();

$updatedGroups = array_filter($allGroups, fn($group) => $group['group_id'] !== $groupIdToDelete);

save_tags(array_values($updatedGroups));

header('Location: manage_tags.php');
exit();