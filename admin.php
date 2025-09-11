<?php
require_once 'includes/admin_guard.php'; // Pierwsza linia - sprawdzamy uprawnienia!
$pageTitle = 'Panel Administratora';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$allUsers = json_decode(file_get_contents('data/users.json'), true);
?>

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Panel Administratora</h1>
    <div class="btn-group">
        <a href="admin_theme.php" class="btn btn-success"><i class="bi bi-palette-fill me-2"></i>Wygląd</a>
        <a href="admin_stats.php" class="btn btn-info"><i class="bi bi-bar-chart-line-fill me-2"></i>Globalne Statystyki</a>
        <a href="admin_update.php" class="btn btn-warning"><i class="bi bi-cloud-arrow-up-fill me-2"></i>Aktualizacje i Backup</a>
        <a href="admin_edit_user.php" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Dodaj użytkownika</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Lista użytkowników</h5></div>
    <div class="table-responsive">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th></th>
                    <th>Nazwa (ID)</th>
                    <th>Rola</th>
                    <th>Data urodzenia</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($allUsers as $user): ?>
                <tr>
                    <td><i class="bi <?= htmlspecialchars($user['icon'] ?? 'bi-person') ?> fs-4"></i></td>
                    <td>
                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                        <small class="text-muted d-block"><?= htmlspecialchars($user['id']) ?></small>
                    </td>
                    <td>
                        <span class="badge <?= $user['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                            <?= htmlspecialchars($user['role']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($user['body_params']['birth_date'] ?? 'Brak danych') ?></td>
                    <td>
                        <a href="admin_edit_user.php?id=<?= urlencode($user['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Edytuj">
                            <i class="bi bi-pencil-fill"></i>
                        </a>
                        <?php if ($user['id'] !== $_SESSION['user_id']): // Nie można usunąć samego siebie ?>
                        <a href="admin_delete_user.php?id=<?= urlencode($user['id']) ?>" class="btn btn-sm btn-outline-danger" title="Usuń" onclick="return confirm('Czy na pewno chcesz usunąć użytkownika <?= htmlspecialchars($user['name']) ?>? Ta operacja jest nieodwracalna!')">
                            <i class="bi bi-trash3-fill"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>