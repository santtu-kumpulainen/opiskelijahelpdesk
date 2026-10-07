<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireRole('student');

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$categoryId = $_GET['category_id'] ?? '';

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

$categoryStmt = $pdo->prepare(
    'SELECT id, name
     FROM categories
     ORDER BY name'
);

$categoryStmt->execute();

$categories = $categoryStmt->fetchAll();

$conditions = [
    'tickets.user_id = ?'
];

$params = [
    $_SESSION['user_id']
];

if ($search !== '') {

    $conditions[] = 'tickets.title LIKE ?';

    $params[] = '%' . $search . '%';
}

if (array_key_exists($status, $statuses)) {

    $conditions[] = 'tickets.status = ?';

    $params[] = $status;

} elseif ($status !== '') {

    $status = '';
}

if (array_key_exists($priority, $priorities)) {

    $conditions[] = 'tickets.priority = ?';

    $params[] = $priority;

} elseif ($priority !== '') {

    $priority = '';
}

if ($categoryId !== '') {

    if (filter_var($categoryId, FILTER_VALIDATE_INT) !== false) {

        $categoryExists = false;

        foreach ($categories as $category) {

            if ((int)$category['id'] === (int)$categoryId) {
                $categoryExists = true;
                break;
            }
        }

        if ($categoryExists) {

            $conditions[] = 'tickets.category_id = ?';

            $params[] = $categoryId;

        } else {

            $categoryId = '';
        }

    } else {

        $categoryId = '';
    }
}

$sql = '
    SELECT
        tickets.id,
        tickets.title,
        tickets.priority,
        tickets.status,
        tickets.created_at,
        categories.name AS category_name
    FROM tickets
    INNER JOIN categories
        ON tickets.category_id = categories.id
    WHERE ' . implode(' AND ', $conditions) . '
    ORDER BY tickets.created_at DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$tickets = $stmt->fetchAll();

/*
 * Jaetaan tiketit aktiivisiin ja valmiisiin.
 * Opiskelijalta vastausta odottavat nostetaan aktiivisten kärkeen.
 */
$ticketGroups = [
    'active' => [
        'title' => 'Aktiiviset tiketit',
        'tickets' => []
    ],
    'closed' => [
        'title' => 'Ratkaistut ja suljetut',
        'tickets' => []
    ]
];

foreach ($tickets as $ticket) {

    $groupKey = in_array($ticket['status'], ['resolved', 'closed'], true)
        ? 'closed'
        : 'active';

    $ticketGroups[$groupKey]['tickets'][] = $ticket;
}

usort(
    $ticketGroups['active']['tickets'],
    fn ($a, $b) => ($b['status'] === 'waiting_student')
        <=> ($a['status'] === 'waiting_student')
);

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
        Omat tiketit - OpiskelijaHelpdesk
    </title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>

</head>

<body>

<header class="site-header">

    <div class="container navbar">

        <a href="my-tickets.php" class="logo">
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
                Omat tiketit
            </h1>

            <p>
                Hae ja suodata omia tukipyyntöjäsi.
            </p>

            <div class="hero-actions">
                <a href="create-ticket.php" class="button">
                    Luo uusi tiketti
                </a>

                <a href="index.php#chat" class="button secondary">
                    Kysy tekoälyltä
                </a>
            </div>

            <form
                method="GET"
                action="my-tickets.php"
                class="ticket-filters"
            >

                <div class="form-group">

                    <label for="search">
                        Haku otsikosta
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Hae tiketin otsikolla..."
                    >

                </div>

                <div class="form-group">

                    <label for="status">
                        Tila
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option value="">
                            Kaikki tilat
                        </option>

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
                    >

                        <option value="">
                            Kaikki prioriteetit
                        </option>

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

                <div class="form-group">

                    <label for="category_id">
                        Kategoria
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                    >

                        <option value="">
                            Kaikki kategoriat
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= htmlspecialchars($category['id']) ?>"
                                <?= (string)$categoryId ===
                                    (string)$category['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars($category['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="button"
                    >
                        Hae
                    </button>

                    <a
                        href="my-tickets.php"
                        class="button secondary"
                    >
                        Tyhjennä
                    </a>

                </div>

            </form>

            <div class="ticket-results">

                <p class="result-count">
                    Löytyi
                    <strong><?= count($tickets) ?></strong>
                    tikettiä.
                </p>

                <?php if (empty($tickets)): ?>

                    <div class="dashboard-placeholder">

                        <h2>
                            Ei tuloksia
                        </h2>

                        <p>
                            Hakuehdoilla ei löytynyt tikettejä.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($ticketGroups as $groupKey => $group): ?>

                        <?php
                        /*
                         * Suljettujen osio näytetään vain, jos siinä on tikettejä.
                         * Aktiivisten osio näytetään aina, ellei tilasuodatin
                         * rajaa sitä pois.
                         */
                        if (
                            empty($group['tickets']) &&
                            ($groupKey !== 'active' || $status !== '')
                        ) {
                            continue;
                        }
                        ?>

                        <section
                            class="ticket-group<?= $groupKey === 'closed' ? ' is-closed' : '' ?>"
                            aria-labelledby="group-<?= $groupKey ?>"
                        >

                            <div class="ticket-group-header">

                                <h2 id="group-<?= $groupKey ?>">
                                    <?= htmlspecialchars($group['title']) ?>
                                </h2>

                                <span>
                                    <?= count($group['tickets']) ?> kpl
                                </span>

                            </div>

                            <?php if (empty($group['tickets'])): ?>

                                <p class="empty-note">
                                    Sinulla ei ole avoimia tikettejä.
                                </p>

                            <?php else: ?>

                                <div class="ticket-list">

                                    <?php foreach ($group['tickets'] as $ticket): ?>

                                        <article class="ticket-card<?= $groupKey === 'closed' ? ' is-closed' : '' ?>">

                                            <div class="ticket-card-header">

                                                <div>

                                                    <p class="ticket-number">
                                                        Tiketti #<?= htmlspecialchars(
                                                            $ticket['id']
                                                        ) ?>
                                                    </p>

                                                    <h3>
                                                        <a href="ticket.php?id=<?= htmlspecialchars(
                                                            $ticket['id']
                                                        ) ?>">
                                                            <?= htmlspecialchars(
                                                                $ticket['title']
                                                            ) ?>
                                                        </a>
                                                    </h3>

                                                </div>

                                                <span class="status-badge status-<?= htmlspecialchars(
                                                    $ticket['status']
                                                ) ?>">
                                                    <?= htmlspecialchars(
                                                        $statuses[$ticket['status']]
                                                        ?? $ticket['status']
                                                    ) ?>
                                                </span>

                                            </div>

                                            <dl class="ticket-meta">

                                                <div>
                                                    <dt>Kategoria</dt>
                                                    <dd>
                                                        <?= htmlspecialchars(
                                                            $ticket['category_name']
                                                        ) ?>
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt>Prioriteetti</dt>
                                                    <dd class="priority-<?= htmlspecialchars(
                                                        $ticket['priority']
                                                    ) ?>">
                                                        <?= htmlspecialchars(
                                                            $priorities[$ticket['priority']]
                                                            ?? $ticket['priority']
                                                        ) ?>
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt>Luotu</dt>
                                                    <dd>
                                                        <time datetime="<?= htmlspecialchars(
                                                            date('Y-m-d\TH:i', strtotime($ticket['created_at']))
                                                        ) ?>">
                                                            <?= htmlspecialchars(
                                                                date('j.n.Y H.i', strtotime($ticket['created_at']))
                                                            ) ?>
                                                        </time>
                                                    </dd>
                                                </div>

                                            </dl>

                                        </article>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </section>

                    <?php endforeach; ?>

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
