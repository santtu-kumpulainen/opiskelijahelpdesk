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

$stmt = $pdo->prepare(
    'SELECT
        tickets.id,
        tickets.title,
        tickets.status,
        tickets.priority,
        tickets.created_at,
        users.name AS student_name,
        users.email AS student_email,
        categories.name AS category_name,
        support_users.name AS support_name
     FROM tickets
     INNER JOIN users
        ON tickets.user_id = users.id
     INNER JOIN categories
        ON tickets.category_id = categories.id
     LEFT JOIN users AS support_users
        ON tickets.assigned_to = support_users.id
     ORDER BY
        FIELD(
            tickets.status,
            "new",
            "in_progress",
            "waiting_student",
            "resolved",
            "closed"
        ),
        FIELD(
            tickets.priority,
            "urgent",
            "high",
            "normal",
            "low"
        ),
        tickets.created_at ASC'
);

$stmt->execute();

$allTickets = $stmt->fetchAll();

/*
 * Tilakohtaiset lukumäärät suodatinvalikkoa varten.
 */
$statusCounts = array_fill_keys(array_keys($statuses), 0);

foreach ($allTickets as $ticket) {

    if (isset($statusCounts[$ticket['status']])) {
        $statusCounts[$ticket['status']]++;
    }
}

/*
 * Suodatin: oletuksena näytetään kaikki paitsi suljetut.
 */
$filters = [
    '' => [
        'label' => 'Avoimet',
        'count' => count($allTickets) - $statusCounts['closed']
    ]
];

foreach ($statuses as $value => $label) {
    $filters[$value] = [
        'label' => $label,
        'count' => $statusCounts[$value]
    ];
}

$filters['all'] = [
    'label' => 'Kaikki',
    'count' => count($allTickets)
];

$filter = $_GET['status'] ?? '';

if (!is_string($filter) || !array_key_exists($filter, $filters)) {
    $filter = '';
}

$tickets = array_filter(
    $allTickets,
    fn ($ticket) => match ($filter) {
        '' => $ticket['status'] !== 'closed',
        'all' => true,
        default => $ticket['status'] === $filter
    }
);

?>

<!DOCTYPE html>
<html lang="fi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Support Dashboard - OpiskelijaHelpdesk
    </title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>

</head>

<body>

    <header class="site-header">

        <div class="container navbar">

            <a href="support-dashboard.php" class="logo">
                OpiskelijaHelpdesk
            </a>

            <a href="logout.php" class="button secondary">
                Kirjaudu ulos
            </a>

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

                <nav aria-label="Suodata tilan mukaan">

                    <ul class="status-tabs">

                        <?php foreach ($filters as $value => $item): ?>

                            <li>
                                <a
                                    href="support-dashboard.php<?= $value === ''
                                        ? ''
                                        : '?status=' . urlencode($value) ?>"
                                    <?= $filter === $value
                                        ? 'aria-current="page"'
                                        : '' ?>
                                >
                                    <?= htmlspecialchars($item['label']) ?>
                                    <span class="count">
                                        <?= $item['count'] ?>
                                    </span>
                                </a>
                            </li>

                        <?php endforeach; ?>

                    </ul>

                </nav>

                <div class="dashboard-section">

                    <h2>
                        Tiketit – <?= htmlspecialchars($filters[$filter]['label']) ?>
                    </h2>

                    <?php if (empty($tickets)): ?>

                        <div class="dashboard-placeholder">

                            <h3>
                                Ei tikettejä
                            </h3>

                            <p>
                                Tässä näkymässä ei ole tikettejä.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="ticket-table-wrap">

                            <table class="ticket-table">

                                <caption class="visually-hidden">
                                    Tiketit – <?= htmlspecialchars($filters[$filter]['label']) ?>
                                </caption>

                                <thead>
                                    <tr>
                                        <th scope="col">Tiketti</th>
                                        <th scope="col">Opiskelija</th>
                                        <th scope="col">Tila</th>
                                        <th scope="col">Prioriteetti</th>
                                        <th scope="col">Käsittelijä</th>
                                        <th scope="col">Luotu</th>
                                        <th scope="col">Toiminnot</th>
                                    </tr>
                                </thead>

                                <tbody>

                                    <?php foreach ($tickets as $ticket): ?>

                                        <tr>

                                            <td class="cell-title" data-label="Tiketti">
                                                <a
                                                    href="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                                                    class="ticket-title"
                                                >
                                                    <?= htmlspecialchars($ticket['title']) ?>
                                                </a>
                                                <span class="sub">
                                                    #<?= htmlspecialchars($ticket['id']) ?>
                                                    · <?= htmlspecialchars($ticket['category_name']) ?>
                                                </span>
                                            </td>

                                            <td data-label="Opiskelija">
                                                <div>
                                                    <?= htmlspecialchars($ticket['student_name']) ?>
                                                    <span class="sub">
                                                        <?= htmlspecialchars($ticket['student_email']) ?>
                                                    </span>
                                                </div>
                                            </td>

                                            <td data-label="Tila">
                                                <span class="status-badge status-<?= htmlspecialchars($ticket['status']) ?>">
                                                    <?= htmlspecialchars(
                                                        $statuses[$ticket['status']]
                                                        ?? $ticket['status']
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td data-label="Prioriteetti">
                                                <span class="priority-<?= htmlspecialchars($ticket['priority']) ?>">
                                                    <?= htmlspecialchars(
                                                        $priorities[$ticket['priority']]
                                                        ?? $ticket['priority']
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td data-label="Käsittelijä">

                                                <?php if (!empty($ticket['support_name'])): ?>

                                                    <?= htmlspecialchars($ticket['support_name']) ?>

                                                <?php else: ?>

                                                    <span class="unassigned">Ei määritetty</span>

                                                <?php endif; ?>

                                            </td>

                                            <td data-label="Luotu">
                                                <time datetime="<?= htmlspecialchars(
                                                    date('Y-m-d\TH:i', strtotime($ticket['created_at']))
                                                ) ?>">
                                                    <?= htmlspecialchars(
                                                        date('j.n.Y H.i', strtotime($ticket['created_at']))
                                                    ) ?>
                                                </time>
                                            </td>

                                            <td data-label="Toiminnot">
                                                <div class="row-actions">
                                                    <a
                                                        href="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                                                        class="button small"
                                                        aria-label="Avaa tiketti #<?= htmlspecialchars($ticket['id']) ?>"
                                                    >
                                                        Avaa
                                                    </a>
                                                    <a
                                                        href="assign-ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                                                        class="button secondary small"
                                                        aria-label="Määritä käsittelijä tiketille #<?= htmlspecialchars($ticket['id']) ?>"
                                                    >
                                                        Käsittelijä
                                                    </a>
                                                </div>
                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

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
