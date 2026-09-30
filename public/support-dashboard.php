<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireRole('support');

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

$stmt = $pdo->query(
    'SELECT
        tickets.id,
        tickets.title,
        tickets.status,
        tickets.priority,
        tickets.created_at,
        users.name AS student_name,
        users.email AS student_email,
        categories.name AS category_name
     FROM tickets
     INNER JOIN users
        ON tickets.user_id = users.id
     INNER JOIN categories
        ON tickets.category_id = categories.id
     WHERE tickets.status != "closed"
     ORDER BY
        FIELD(
            tickets.priority,
            "urgent",
            "high",
            "normal",
            "low"
        ),
        tickets.created_at ASC'
);

$tickets = $stmt->fetchAll();

$newCount = 0;
$inProgressCount = 0;
$waitingCount = 0;
$resolvedCount = 0;

foreach ($tickets as $ticket) {

    switch ($ticket['status']) {

        case 'new':
            $newCount++;
            break;

        case 'in_progress':
            $inProgressCount++;
            break;

        case 'waiting_student':
            $waitingCount++;
            break;

        case 'resolved':
            $resolvedCount++;
            break;
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
        Support Dashboard - OpiskelijaHelpdesk
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

            <h1>
                Support Dashboard
            </h1>

            <p>
                Tikettien seuranta ja käsittely.
            </p>

            <div class="dashboard-stats">

                <div class="stat-card">

                    <strong>
                        <?= $newCount ?>
                    </strong>

                    <span>
                        Uudet
                    </span>

                </div>

                <div class="stat-card">

                    <strong>
                        <?= $inProgressCount ?>
                    </strong>

                    <span>
                        Käsittelyssä
                    </span>

                </div>

                <div class="stat-card">

                    <strong>
                        <?= $waitingCount ?>
                    </strong>

                    <span>
                        Odottaa opiskelijaa
                    </span>

                </div>

                <div class="stat-card">

                    <strong>
                        <?= $resolvedCount ?>
                    </strong>

                    <span>
                        Ratkaistut
                    </span>

                </div>

            </div>

            <div class="dashboard-section">

                <h2>
                    Avoimet tiketit
                </h2>

                <?php if (empty($tickets)): ?>

                    <div class="dashboard-placeholder">

                        <h3>
                            Ei avoimia tikettejä
                        </h3>

                        <p>
                            Kaikki tiketit on käsitelty.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="ticket-list">

                        <?php foreach ($tickets as $ticket): ?>

                            <article class="ticket-card">

                                <div class="ticket-card-header">

                                    <div>

                                        <p class="ticket-number">
                                            Tiketti #<?= htmlspecialchars(
                                                $ticket['id']
                                            ) ?>
                                        </p>

                                        <h2>
                                            <?= htmlspecialchars(
                                                $ticket['title']
                                            ) ?>
                                        </h2>

                                    </div>

                                </div>

                                <div class="ticket-card-info">

                                    <p>
                                        <strong>Opiskelija:</strong>
                                        <?= htmlspecialchars(
                                            $ticket['student_name']
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Sähköposti:</strong>
                                        <?= htmlspecialchars(
                                            $ticket['student_email']
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Kategoria:</strong>
                                        <?= htmlspecialchars(
                                            $ticket['category_name']
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Tila:</strong>
                                        <?= htmlspecialchars(
                                            $statuses[$ticket['status']]
                                            ?? $ticket['status']
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Prioriteetti:</strong>
                                        <?= htmlspecialchars(
                                            $priorities[$ticket['priority']]
                                            ?? $ticket['priority']
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Luotu:</strong>
                                        <?= htmlspecialchars(
                                            $ticket['created_at']
                                        ) ?>
                                    </p>

                                </div>

                                <a
                                    href="ticket.php?id=<?= htmlspecialchars(
                                        $ticket['id']
                                    ) ?>"
                                    class="button"
                                >
                                    Avaa tiketti
                                </a>

                            </article>

                        <?php endforeach; ?>

                    </div>

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