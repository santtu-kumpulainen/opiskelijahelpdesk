<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireLogin();

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
        tickets.id,
        tickets.user_id,
        tickets.title,
        tickets.description,
        tickets.priority,
        tickets.status,
        tickets.created_at,
        users.name AS user_name,
        users.email AS user_email,
        categories.name AS category_name
     FROM tickets
     INNER JOIN users
        ON tickets.user_id = users.id
     INNER JOIN categories
        ON tickets.category_id = categories.id
     WHERE tickets.id = ?'
);

$stmt->execute([$ticketId]);

$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('Tikettiä ei löytynyt.');
}

/*
 * Opiskelija saa nähdä vain omat tikettinsä.
 * Support ja admin voivat tarkastella tikettejä.
 */
if (
    $_SESSION['role'] === 'student' &&
    (int)$ticket['user_id'] !== (int)$_SESSION['user_id']
) {
    http_response_code(403);
    exit('Sinulla ei ole oikeutta nähdä tätä tikettiä.');
}

$statusLabels = [
    'new' => 'Uusi',
    'in_progress' => 'Käsittelyssä',
    'waiting_student' => 'Odottaa opiskelijaa',
    'resolved' => 'Ratkaistu',
    'closed' => 'Suljettu'
];

$priorityLabels = [
    'low' => 'Matala',
    'normal' => 'Normaali',
    'high' => 'Korkea',
    'urgent' => 'Kiireellinen'
];

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
        Tiketti #<?= htmlspecialchars($ticket['id']) ?>
        - OpiskelijaHelpdesk
    </title>

    <link rel="stylesheet" href="css/style.css">

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

            <?php if ($_SESSION['role'] === 'student'): ?>

                <a href="create-ticket.php">
                    Uusi tiketti
                </a>

                <a href="my-tickets.php">
                    Omat tiketit
                </a>

            <?php else: ?>

                <a href="#">
                    Tiketit
                </a>

            <?php endif; ?>

            <a href="logout.php">
                Kirjaudu ulos
            </a>

        </nav>

    </div>

</header>

<main>

    <section class="hero">

        <div class="container">

            <div class="ticket-detail">

                <div class="ticket-detail-header">

                    <div>

                        <p class="ticket-number">
                            Tiketti #<?= htmlspecialchars($ticket['id']) ?>
                        </p>

                        <h1>
                            <?= htmlspecialchars($ticket['title']) ?>
                        </h1>

                    </div>

                    <div class="ticket-status">

                        <?= htmlspecialchars(
                            $statusLabels[$ticket['status']]
                            ?? $ticket['status']
                        ) ?>

                    </div>

                </div>

                <div class="ticket-description">

                    <h2>
                        Kuvaus
                    </h2>

                    <p>
                        <?= nl2br(
                            htmlspecialchars($ticket['description'])
                        ) ?>
                    </p>

                </div>

                <div class="ticket-info">

                    <div>

                        <strong>
                            Kategoria
                        </strong>

                        <span>
                            <?= htmlspecialchars($ticket['category_name']) ?>
                        </span>

                    </div>

                    <div>

                        <strong>
                            Prioriteetti
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $priorityLabels[$ticket['priority']]
                                ?? $ticket['priority']
                            ) ?>
                        </span>

                    </div>

                    <div>

                        <strong>
                            Tila
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $statusLabels[$ticket['status']]
                                ?? $ticket['status']
                            ) ?>
                        </span>

                    </div>

                    <div>

                        <strong>
                            Luotu
                        </strong>

                        <span>
                            <?= htmlspecialchars($ticket['created_at']) ?>
                        </span>

                    </div>

                    <div>

                        <strong>
                            Kirjoittaja
                        </strong>

                        <span>
                            <?= htmlspecialchars($ticket['user_name']) ?>
                        </span>

                    </div>

                    <div>

                        <strong>
                            Sähköposti
                        </strong>

                        <span>
                            <?= htmlspecialchars($ticket['user_email']) ?>
                        </span>

                    </div>

                </div>

                <div class="ticket-actions">

                    <?php if ($_SESSION['role'] === 'student'): ?>

                        <a
                            href="my-tickets.php"
                            class="button secondary"
                        >
                            Takaisin omiin tiketteihin
                        </a>

                    <?php else: ?>

                        <a
                            href="index.php"
                            class="button secondary"
                        >
                            Takaisin
                        </a>

                    <?php endif; ?>

                </div>

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