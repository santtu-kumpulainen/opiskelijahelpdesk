<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';

requireAnyRole(['support', 'admin']);

$ticketId = $_GET['id'] ?? '';

if (
    $ticketId === '' ||
    filter_var($ticketId, FILTER_VALIDATE_INT) === false
) {
    http_response_code(400);
    exit('Virheellinen tiketin numero.');
}

$stmt = $pdo->prepare(
    'SELECT
        id,
        title,
        assigned_to
     FROM tickets
     WHERE id = ?'
);

$stmt->execute([$ticketId]);

$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Tikettiä ei löytynyt.');
}

/*
 * Haetaan kaikki support-käyttäjät.
 */
$stmt = $pdo->prepare(
    'SELECT
        id,
        name,
        email
     FROM users
     WHERE role = "support"
     ORDER BY name'
);

$stmt->execute();

$supportUsers = $stmt->fetchAll();

$errors = [];
$assignedTo = $ticket['assigned_to'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isValidCsrfToken()) {
        $errors[] = CSRF_ERROR_MESSAGE;
    }

    $assignedInput = $_POST['assigned_to'] ?? '';
    $assignedTo = is_string($assignedInput) ? $assignedInput : '';

    if (!is_string($assignedInput)) {
        $errors[] = 'Valitse kelvollinen tukihenkilö.';
    }

    /*
     * Tyhjä arvo tarkoittaa, että tikettiä ei ole
     * osoitettu kenellekään.
     */
    if (!empty($errors)) {
        $assignedToValue = null;

    } elseif ($assignedTo === '') {

        $assignedToValue = null;

    } elseif (
        filter_var($assignedTo, FILTER_VALIDATE_INT) === false
    ) {

        $errors[] = 'Valittu tukihenkilö ei ole kelvollinen.';
        $assignedToValue = null;

    } else {

        $assignedToValue = (int)$assignedTo;

        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE id = ?
             AND role = "support"'
        );

        $stmt->execute([
            $assignedToValue
        ]);

        if (!$stmt->fetch()) {
            $errors[] = 'Valittu käyttäjä ei ole tukihenkilö.';
        }
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'UPDATE tickets
             SET assigned_to = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $assignedToValue,
            $ticketId
        ]);

        header(
            'Location: ticket.php?id=' .
            urlencode($ticketId) .
            '&assigned=1'
        );

        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="fi">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Osoita tiketti - OpiskelijaHelpdesk
    </title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>

</head>

<body>

<header class="site-header">

    <div class="container navbar">

        <a href="index.php" class="logo">
            OpiskelijaHelpdesk
        </a>

        <nav class="nav-links">

            <a href="index.php">
                Etusivu
            </a>

            <a href="support-dashboard.php">
                Dashboard
            </a>

            <a href="logout.php">
                Kirjaudu ulos
            </a>

        </nav>

    </div>

</header>

<main>

    <section class="hero">

        <div class="container">

            <div class="form-section">

                <p class="ticket-number">
                    Tiketti #<?= htmlspecialchars($ticket['id']) ?>
                </p>

                <h1>
                    Osoita tiketti
                </h1>

                <p>
                    <?= htmlspecialchars($ticket['title']) ?>
                </p>

                <?php if (!empty($errors)): ?>

                    <div class="form-error">

                        <strong>
                            Virhe:
                        </strong>

                        <ul>

                            <?php foreach ($errors as $error): ?>

                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>

                <?php if (empty($supportUsers)): ?>

                    <div class="dashboard-placeholder">

                        <h2>
                            Tukihenkilöitä ei löytynyt
                        </h2>

                        <p>
                            Järjestelmässä ei ole vielä yhtään
                            support-roolista käyttäjää.
                        </p>

                    </div>

                <?php else: ?>

                    <form
                        method="POST"
                        action="assign-ticket.php?id=<?= htmlspecialchars($ticketId) ?>"
                    >
                        <?= csrfField() ?>

                        <div class="form-group">

                            <label for="assigned_to">
                                Tukihenkilö
                            </label>

                            <select
                                id="assigned_to"
                                name="assigned_to"
                            >

                                <option value="">
                                    Ei osoitettua tukihenkilöä
                                </option>

                                <?php foreach ($supportUsers as $supportUser): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $supportUser['id']
                                        ) ?>"
                                        <?= (string)$assignedTo ===
                                            (string)$supportUser['id']
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= htmlspecialchars(
                                            $supportUser['name']
                                        ) ?>
                                        -
                                        <?= htmlspecialchars(
                                            $supportUser['email']
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-actions">

                            <button
                                type="submit"
                                class="button"
                            >
                                Tallenna käsittelijä
                            </button>

                            <a
                                href="ticket.php?id=<?= htmlspecialchars(
                                    $ticketId
                                ) ?>"
                                class="button secondary"
                            >
                                Peruuta
                            </a>

                        </div>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

<footer class="site-footer">

    <div class="container">

        <p>
            OpiskelijaHelpdesk
        </p>

    </div>

</footer>

</body>
</html>
