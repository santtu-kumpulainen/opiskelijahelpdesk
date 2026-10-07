<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

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
        status,
        priority
     FROM tickets
     WHERE id = ?'
);

$stmt->execute([$ticketId]);

$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Tikettiä ei löytynyt.');
}

$statuses = [
    'new' => 'Uusi',
    'in_progress' => 'Käsittelyssä',
    'waiting_student' => 'Odottaa opiskelijaa',
    'resolved' => 'Ratkaistu',
    'closed' => 'Suljettu'
];

$priorities = [
    'low' => 'Matala',
    'normal' => 'Normaali',
    'high' => 'Korkea',
    'urgent' => 'Kiireellinen'
];

$errors = [];
$success = '';

$status = $ticket['status'];
$priority = $ticket['priority'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $statusInput = $_POST['status'] ?? '';
    $priorityInput = $_POST['priority'] ?? '';
    $status = is_string($statusInput) ? $statusInput : '';
    $priority = is_string($priorityInput) ? $priorityInput : '';

    if (!array_key_exists($status, $statuses)) {
        $errors[] = 'Valittu tila ei ole kelvollinen.';
    }

    if (!array_key_exists($priority, $priorities)) {
        $errors[] = 'Valittu prioriteetti ei ole kelvollinen.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'UPDATE tickets
             SET status = ?, priority = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $status,
            $priority,
            $ticketId
        ]);

        header(
            'Location: ticket.php?id=' .
            urlencode($ticketId) .
            '&updated=1'
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
        Muokkaa tikettiä - OpiskelijaHelpdesk
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
                    Muokkaa tikettiä
                </h1>

                <p>
                    Muuta tiketin tilaa ja prioriteettia.
                </p>

                <?php if (!empty($errors)): ?>

                    <div class="form-error">

                        <strong>
                            Tarkista seuraavat kohdat:
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

                <form
                    method="POST"
                    action="update-ticket.php?id=<?= htmlspecialchars($ticketId) ?>"
                >

                    <div class="form-group">

                        <label for="status">
                            Tila
                        </label>

                        <select
                            id="status"
                            name="status"
                            required
                        >

                            <?php foreach ($statuses as $value => $label): ?>

                                <option
                                    value="<?= htmlspecialchars($value) ?>"
                                    <?= $status === $value
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars($label) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-group">

                        <label for="priority">
                            Prioriteetti
                        </label>

                        <select
                            id="priority"
                            name="priority"
                            required
                        >

                            <?php foreach ($priorities as $value => $label): ?>

                                <option
                                    value="<?= htmlspecialchars($value) ?>"
                                    <?= $priority === $value
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars($label) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="button"
                        >
                            Tallenna muutokset
                        </button>

                        <a
                            href="ticket.php?id=<?= htmlspecialchars($ticketId) ?>"
                            class="button secondary"
                        >
                            Peruuta
                        </a>

                    </div>

                </form>

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
