<?php
require_once 'includes/admin_guard.php';
$pageTitle = 'Edytuj Użytkownika';
require_once 'includes/functions.php';

$userIdToEdit = $_GET['id'] ?? null;
$isEditing = !empty($userIdToEdit);
$userToEdit = null;
$allUsers = json_decode(file_get_contents('data/users.json'), true);

if ($isEditing) {
    // UŻYCIE NOWEJ FUNKCJI POMOCNICZEJ
    $userToEdit = find_item_in_array($allUsers, $userIdToEdit);
}

// Logika zapisu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['id'];
    $newUserData = [
        'id' => strtolower(trim($userId)),
        'name' => trim($_POST['name']),
        'icon' => $_POST['icon'],
        'role' => $_POST['role'],
        'body_params' => [
            'birth_date' => $_POST['birth_date']
        ]
    ];

    if (!$isEditing) { // Jeśli dodajemy nowego usera, hasło jest wymagane
        if (empty($_POST['password'])) {
            $errorMessage = 'Hasło jest wymagane dla nowego użytkownika.';
        } else {
            $newUserData['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
    } else { // Jeśli edytujemy, zmień hasło tylko jeśli zostało podane
        if (!empty($_POST['password'])) {
            $newUserData['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        } else {
            // UŻYCIE NOWEJ FUNKCJI POMOCNICZEJ do znalezienia oryginalnego użytkownika
            $originalUser = find_item_in_array($allUsers, $userIdToEdit);
            $newUserData['password_hash'] = $originalUser['password_hash'] ?? ''; // Zachowaj stare hasło
        }
    }

    if (empty($errorMessage)) {
        // UŻYCIE NOWEJ FUNKCJI POMOCNICZEJ
        $userIndex = $isEditing ? find_item_index_in_array($allUsers, $userIdToEdit) : null;
        
        if ($userIndex !== null) {
            // Bezpieczne łączenie, zachowujące klucze, których nie ma w formularzu (np. key_exercises)
            $allUsers[$userIndex] = array_merge($allUsers[$userIndex], $newUserData);
        } else {
            $allUsers[] = $newUserData;
            // Inicjalizuj pliki dla nowego usera
            save_user_workouts($newUserData['id'], []);
            save_user_plans($newUserData['id'], []);
            ensure_social_profile_exists($newUserData['id']);
        }

        file_put_contents('data/users.json', json_encode($allUsers, JSON_PRETTY_PRINT));
        header('Location: admin.php');
        exit();
    }
}

require_once 'includes/header.php';
?>

<h1 class="mb-4"><?= $isEditing ? 'Edytuj użytkownika: ' . htmlspecialchars($userToEdit['name']) : 'Dodaj nowego użytkownika' ?></h1>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="id" class="form-label">ID (login)</label>
                    <input type="text" class="form-control" id="id" name="id" value="<?= htmlspecialchars($userToEdit['id'] ?? '') ?>" <?= $isEditing ? 'readonly' : 'required' ?>>
                </div>
                <div class="col-md-6">
                    <label for="name" class="form-label">Nazwa wyświetlana</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($userToEdit['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label for="password" class="form-label">Nowe hasło</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="<?= $isEditing ? 'Wpisz, aby zmienić' : '' ?>" <?= !$isEditing ? 'required' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label for="birth_date" class="form-label">Data urodzenia</label>
                    <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= htmlspecialchars($userToEdit['body_params']['birth_date'] ?? '') ?>">
                </div>
                 <div class="col-md-6">
                    <label for="icon" class="form-label">Ikona</label>
                    <input type="text" class="form-control" id="icon" name="icon" value="<?= htmlspecialchars($userToEdit['icon'] ?? 'bi-person') ?>">
                </div>
                <div class="col-md-6">
                    <label for="role" class="form-label">Rola</label>
                    <select id="role" name="role" class="form-select">
                        <option value="user" <?= (($userToEdit['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>User</option>
                        <option value="coach" <?= (($userToEdit['role'] ?? '') === 'coach') ? 'selected' : '' ?>>Trener</option> <!-- NOWA ROLA -->
                        <option value="admin" <?= (($userToEdit['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <a href="admin.php" class="btn btn-secondary">Anuluj</a>
                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script src="assets/js/app.js" type="module"></script>