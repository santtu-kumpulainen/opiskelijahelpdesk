<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireRole('student');

$stmt = $pdo->prepare(
    'SELECT
        tickets.id,
        tickets.title,
        tickets.priority,
        tickets.status,
        tickets.created_at,
        categories.name AS category_name
     FROM tickets
     INNER JOIN categories
        ON tickets.category_id = categories.id
     WHERE tickets.user_id = ?
     ORDER BY tickets.created_at DESC'
);

$stmt->execute([
    $_SESSION['user_id']
]);

$tickets = $stmt->fetchAll();

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

    <title>Omat tiketit - OpiskelijaHelpdesk</title>

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

            <a href="create-ticket.php">
                Uusi tiketti
            </a>

            <a href="my-tickets.php">
                Omat tiketit
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

            <h1>
                Omat tiketit
            </h1>

            <p>
                Täällä näet luomasi tukipyynnöt.
            </p>

            <?php if (empty($tickets)): ?>

                <div class="dashboard-placeholder">

                    <h2>
                        Ei tikettejä
                    </h2>

                    <p>
                        Et ole vielä luonut yhtään tikettiä.
                    </p>

                    <a
                        href="create-ticket.php"
                        class="button"
                    >
                        Luo uusi tiketti
                    </a>

                </div>

            <?php else: ?>

                <div class="ticket-list">

                    <?php foreach ($tickets as $ticket): ?>

                        <article class="ticket-card">

                            <div class="ticket-card-header">

                                <h2>
                                    <?= htmlspecialchars($ticket['title']) ?>
                                </h2>

                                <span>
                                    #<?= htmlspecialchars($ticket['id']) ?>
                                </span>

                            </div>

                            <div class="ticket-card-info">

                                <p>
                                    <strong>Kategoria:</strong>
                                    <?= htmlspecialchars($ticket['category_name']) ?>
                                </p>

                                <p>
                                    <strong>Tila:</strong>
                                    <?= htmlspecialchars(
                                        $statusLabels[$ticket['status']]
                                        ?? $ticket['status']
                                    ) ?>
                                </p>

                                <p>
                                    <strong>Prioriteetti:</strong>
                                    <?= htmlspecialchars(
                                        $priorityLabels[$ticket['priority']]
                                        ?? $ticket['priority']
                                    ) ?>
                                </p>

                                <p>
                                    <strong>Luotu:</strong>
                                    <?= htmlspecialchars($ticket['created_at']) ?>
                                </p>

                            </div>

                            <a
                                href="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                                class="button secondary"
                            >
                                Näytä tiketti
                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

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