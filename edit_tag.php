<?php
$pageTitle = 'Edytuj Tag';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$groupId = $_GET['group_id'] ?? null;
$tagId = $_GET['tag_id'] ?? null;
if (!$groupId || !$tagId) { header('Location: manage_tags.php'); exit(); }

$allGroups = get_all_tags();
$tagToEdit = null;
$groupIndex = null;
$tagIndex = null;

foreach ($allGroups as $gIdx => $group) {
    if ($group['group_id'] === $groupId) {
        foreach($group['tags'] as $tIdx => $tag) {
            if ($tag['id'] === $tagId) {
                $tagToEdit = $tag;
                $groupIndex = $gIdx;
                $tagIndex = $tIdx;
                break 2; // Wyjdź z obu pętli
            }
        }
    }
}

if (!$tagToEdit) {
    echo "<div class='alert alert-danger'>Nie znaleziono taga.</div>";
    require_once 'includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTagName = trim($_POST['tag_name'] ?? '');
    if (!empty($newTagName)) {
        $allGroups[$groupIndex]['tags'][$tagIndex]['name'] = htmlspecialchars($newTagName);
        save_tags($allGroups);
        header('Location: manage_tags.php');
        exit();
    }
}
?>
<div class="card mx-auto" style="max-width: 500px;">
    <div class="card-header bg-dark text-white">
        <h1 class="h4 mb-0">Edytujesz tag: <?= htmlspecialchars($tagToEdit['name']) ?></h1>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="tag_name" class="form-label">Nowa nazwa taga</label>
                <input type="text" class="form-control" id="tag_name" name="tag_name" value="<?= htmlspecialchars($tagToEdit['name']) ?>" required>
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="manage_tags.php" class="btn btn-secondary">Anuluj</a>
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>