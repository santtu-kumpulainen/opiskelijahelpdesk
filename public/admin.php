<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireRole('admin');

$roles = [
    'student' => 'Opiskelija',
    'support' => 'Tukihenkilö',
    'admin' => 'Ylläpitäjä'
];

$errors = [];
$success = '';
$csrfToken = $_SESSION['admin_csrf_token'] ??= bin2hex(random_bytes(32));
$escape = static fn($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionInput = $_POST['action'] ?? '';
    $tokenInput = $_POST['csrf_token'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    $token = is_string($tokenInput) ? $tokenInput : '';

    if (!hash_equals($csrfToken, $token)) {
        $errors[] = 'Pyyntö vanheni. Päivitä sivu ja yritä uudelleen.';
    } else {
        switch ($action) {
            case 'change_role':
                $userIdInput = $_POST['user_id'] ?? '';
                $roleInput = $_POST['role'] ?? '';
                $userId = is_string($userIdInput) ? $userIdInput : '';
                $newRole = is_string($roleInput) ? $roleInput : '';

                if (filter_var($userId, FILTER_VALIDATE_INT) === false || !isset($roles[$newRole])) {
                    $errors[] = 'Valitse käyttäjä ja kelvollinen rooli.';
                    break;
                }

                if ((int) $userId === (int) $_SESSION['user_id']) {
                    $errors[] = 'Et voi muuttaa omaa rooliasi.';
                    break;
                }

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT id, role
                         FROM users
                         WHERE id = ?
                         FOR UPDATE'
                    );
                    $stmt->execute([$userId]);
                    $targetUser = $stmt->fetch();

                    if (!$targetUser) {
                        $pdo->rollBack();
                        $errors[] = 'Käyttäjää ei löytynyt.';
                        break;
                    }

                    if ($targetUser['role'] === 'admin' && $newRole !== 'admin') {
                        $stmt = $pdo->prepare(
                            'SELECT id
                             FROM users
                             WHERE role = ?
                             FOR UPDATE'
                        );
                        $stmt->execute(['admin']);

                        if (count($stmt->fetchAll()) <= 1) {
                            $pdo->rollBack();
                            $errors[] = 'Järjestelmässä täytyy säilyä vähintään yksi ylläpitäjä.';
                            break;
                        }
                    }

                    $stmt = $pdo->prepare(
                        'UPDATE users
                         SET role = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([$newRole, $userId]);
                    $pdo->commit();
                    $success = 'Käyttäjän rooli päivitettiin.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Ylläpitäjän roolimuutos epäonnistui: ' . $e->getMessage());
                    $errors[] = 'Roolin päivittäminen epäonnistui. Yritä myöhemmin uudelleen.';
                }
                break;

            case 'delete_user':
                $userIdInput = $_POST['user_id'] ?? '';
                $userId = is_string($userIdInput) ? $userIdInput : '';

                if (filter_var($userId, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'Valitse kelvollinen käyttäjä.';
                    break;
                }

                if ((int) $userId === (int) $_SESSION['user_id']) {
                    $errors[] = 'Et voi poistaa omaa käyttäjätiliäsi.';
                    break;
                }

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'SELECT id, role
                         FROM users
                         WHERE id = ?
                         FOR UPDATE'
                    );
                    $stmt->execute([$userId]);
                    $targetUser = $stmt->fetch();

                    if (!$targetUser) {
                        $pdo->rollBack();
                        $errors[] = 'Käyttäjää ei löytynyt.';
                        break;
                    }

                    if ($targetUser['role'] === 'admin') {
                        $stmt = $pdo->prepare(
                            'SELECT id
                             FROM users
                             WHERE role = ?
                             FOR UPDATE'
                        );
                        $stmt->execute(['admin']);

                        if (count($stmt->fetchAll()) <= 1) {
                            $pdo->rollBack();
                            $errors[] = 'Järjestelmässä täytyy säilyä vähintään yksi ylläpitäjä.';
                            break;
                        }
                    }

                    $stmt = $pdo->prepare(
                        'DELETE FROM users
                         WHERE id = ?'
                    );
                    $stmt->execute([$userId]);
                    $pdo->commit();
                    $success = 'Käyttäjä poistettiin.';
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Käyttäjän poistaminen epäonnistui: ' . $e->getMessage());
                    $errors[] = 'Käyttäjän poistaminen epäonnistui. Yritä myöhemmin uudelleen.';
                }
                break;

            case 'add_category':
                $nameInput = $_POST['name'] ?? '';
                $name = is_string($nameInput) ? trim($nameInput) : '';

                if ($name === '') {
                    $errors[] = 'Anna kategorialle nimi.';
                } elseif (mb_strlen($name) > 100) {
                    $errors[] = 'Kategorian nimi voi sisältää enintään 100 merkkiä.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO categories (name)
                             VALUES (?)'
                        );
                        $stmt->execute([$name]);
                        $success = 'Kategoria lisättiin.';
                    } catch (PDOException $e) {
                        error_log('Kategorian lisääminen epäonnistui: ' . $e->getMessage());
                        $errors[] = $e->getCode() === '23000'
                            ? 'Samanniminen kategoria on jo olemassa.'
                            : 'Kategorian lisääminen epäonnistui. Yritä myöhemmin uudelleen.';
                    }
                }
                break;

            case 'rename_category':
                $categoryIdInput = $_POST['category_id'] ?? '';
                $nameInput = $_POST['name'] ?? '';
                $categoryId = is_string($categoryIdInput) ? $categoryIdInput : '';
                $name = is_string($nameInput) ? trim($nameInput) : '';

                if (filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'Valitse kelvollinen kategoria.';
                } elseif ($name === '') {
                    $errors[] = 'Anna kategorialle nimi.';
                } elseif (mb_strlen($name) > 100) {
                    $errors[] = 'Kategorian nimi voi sisältää enintään 100 merkkiä.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'UPDATE categories
                             SET name = ?
                             WHERE id = ?'
                        );
                        $stmt->execute([$name, $categoryId]);
                        $success = $stmt->rowCount() > 0
                            ? 'Kategorian nimi päivitettiin.'
                            : 'Kategoriaa ei löytynyt tai nimi ei muuttunut.';
                    } catch (PDOException $e) {
                        error_log('Kategorian nimen muuttaminen epäonnistui: ' . $e->getMessage());
                        $errors[] = $e->getCode() === '23000'
                            ? 'Samanniminen kategoria on jo olemassa.'
                            : 'Kategorian nimen muuttaminen epäonnistui. Yritä myöhemmin uudelleen.';
                    }
                }
                break;

            case 'delete_category':
                $categoryIdInput = $_POST['category_id'] ?? '';
                $categoryId = is_string($categoryIdInput) ? $categoryIdInput : '';

                if (filter_var($categoryId, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'Valitse kelvollinen kategoria.';
                    break;
                }

                $stmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM tickets
                     WHERE category_id = ?'
                );
                $stmt->execute([$categoryId]);

                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = 'Kategoriaa ei voi poistaa, koska siihen liittyy tikettejä.';
                    break;
                }

                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM categories
                         WHERE id = ?'
                    );
                    $stmt->execute([$categoryId]);
                    $success = $stmt->rowCount() > 0
                        ? 'Kategoria poistettiin.'
                        : 'Kategoriaa ei löytynyt.';
                } catch (PDOException $e) {
                    error_log('Kategorian poistaminen epäonnistui: ' . $e->getMessage());
                    $errors[] = 'Kategorian poistaminen epäonnistui. Yritä myöhemmin uudelleen.';
                }
                break;

            default:
                $errors[] = 'Tuntematon toiminto.';
        }
    }
}

$stmt = $pdo->prepare(
    'SELECT id, name, email, role, created_at
     FROM users
     ORDER BY name, id'
);
$stmt->execute();
$users = $stmt->fetchAll();

$stmt = $pdo->prepare(
    'SELECT categories.id, categories.name, COUNT(tickets.id) AS ticket_count
     FROM categories
     LEFT JOIN tickets
        ON tickets.category_id = categories.id
     GROUP BY categories.id, categories.name
     ORDER BY categories.name'
);
$stmt->execute();
$categories = $stmt->fetchAll();

$stats = [];
foreach ([
    'users' => 'SELECT COUNT(*) FROM users',
    'tickets' => 'SELECT COUNT(*) FROM tickets',
    'categories' => 'SELECT COUNT(*) FROM categories'
] as $key => $sql) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats[$key] = (int) $stmt->fetchColumn();
}

?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hallinta - OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
</head>
<body>

<header class="site-header">
    <div class="container navbar">
        <a href="admin.php" class="logo">OpiskelijaHelpdesk</a>
        <a href="logout.php" class="button secondary">Kirjaudu ulos</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container">
            <h1>Ylläpitäjän hallinta</h1>
            <p>Hallinnoi käyttäjiä, rooleja ja tikettien kategorioita.</p>

            <div class="dashboard-stats admin-stats">
                <article class="stat-card"><strong><?= $stats['users'] ?></strong><span>Käyttäjää</span></article>
                <article class="stat-card"><strong><?= $stats['tickets'] ?></strong><span>Tikettiä</span></article>
                <article class="stat-card"><strong><?= $stats['categories'] ?></strong><span>Kategoriaa</span></article>
            </div>

            <?php if ($errors): ?>
                <div class="form-error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= $escape($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="form-success" role="status"><?= $escape($success) ?></div>
            <?php endif; ?>

            <section class="dashboard-section admin-panel" id="users">
                <h2>Käyttäjät</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Nimi</th><th>Sähköposti</th><th>Rooli</th><th>Luotu</th><th>Toiminto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $escape($user['name']) ?></td>
                                    <td><?= $escape($user['email']) ?></td>
                                    <td><?= $escape($roles[$user['role']] ?? $user['role']) ?></td>
                                    <td><?= $escape($user['created_at']) ?></td>
                                    <td>
                                        <?php if ((int) $user['id'] === (int) $_SESSION['user_id']): ?>
                                            <span>Oma käyttäjä</span>
                                        <?php else: ?>
                                            <div class="admin-user-actions">
                                            <form method="POST" class="admin-inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="change_role">
                                                <input type="hidden" name="user_id" value="<?= $escape($user['id']) ?>">
                                                <label class="visually-hidden" for="role-<?= $escape($user['id']) ?>">Käyttäjän <?= $escape($user['name']) ?> rooli</label>
                                                <select name="role" id="role-<?= $escape($user['id']) ?>">
                                                    <?php foreach ($roles as $role => $label): ?>
                                                        <option value="<?= $escape($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="button">Tallenna</button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Poistetaanko käyttäjä? Hänen luomansa tiketit ja kommentit poistetaan. Tiketteihin osoitettu käyttäjä irrotetaan.')">
                                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $escape($user['id']) ?>">
                                                <button type="submit" class="button danger">Poista käyttäjä</button>
                                            </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-section admin-panel" id="categories">
                <h2>Kategoriat</h2>
                <form method="POST" class="admin-add-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label for="new-category">Lisää kategoria</label>
                        <input type="text" name="name" id="new-category" maxlength="100" required>
                    </div>
                    <button type="submit" class="button">Lisää</button>
                </form>

                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>Kategoria</th><th>Tikettejä</th><th>Nimen muutos</th><th>Poisto</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= $escape($category['name']) ?></td>
                                    <td><?= (int) $category['ticket_count'] ?></td>
                                    <td>
                                        <form method="POST" class="admin-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="rename_category">
                                            <input type="hidden" name="category_id" value="<?= $escape($category['id']) ?>">
                                            <label class="visually-hidden" for="category-<?= $escape($category['id']) ?>">Kategorian uusi nimi</label>
                                            <input type="text" name="name" id="category-<?= $escape($category['id']) ?>" value="<?= $escape($category['name']) ?>" maxlength="100" required>
                                            <button type="submit" class="button secondary">Tallenna</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('Poistetaanko kategoria?')">
                                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $escape($category['id']) ?>">
                                            <button type="submit" class="button danger" <?= (int) $category['ticket_count'] > 0 ? 'disabled' : '' ?>>Poista</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="admin-note">Tiketteihin liitettyä kategoriaa ei voi poistaa. Nimeäminen säilyttää aiemmat tiketit.</p>
            </section>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container"><p>OpiskelijaHelpdesk</p></div>
</footer>

</body>
</html>
