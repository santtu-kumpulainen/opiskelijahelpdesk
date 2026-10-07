<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';

requireRole('admin');

$roles = [
    'student' => 'Opiskelija',
    'support' => 'Tukihenkilö',
    'admin' => 'Ylläpitäjä'
];

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

/*
 * Avoimet tilat: tiketti odottaa vielä toimenpiteitä.
 */
$openStatuses = ['new', 'in_progress', 'waiting_student'];

$errors = [];
$success = '';
$action = '';
$csrfToken = csrfToken();
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
        $errors[] = CSRF_ERROR_MESSAGE;
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

            case 'assign_ticket':
                $ticketIdInput = $_POST['ticket_id'] ?? '';
                $assignedInput = $_POST['assigned_to'] ?? '';
                $ticketId = is_string($ticketIdInput) ? $ticketIdInput : '';
                $assignedTo = is_string($assignedInput) ? $assignedInput : '';

                // "Poista käsittelijä" -painike tyhjentää määrityksen.
                if (($_POST['unassign'] ?? '') === '1') {
                    $assignedTo = '';
                }

                if (filter_var($ticketId, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'Valitse kelvollinen tiketti.';
                    break;
                }

                if ($assignedTo !== '' && filter_var($assignedTo, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'Valittu käsittelijä ei ole kelvollinen.';
                    break;
                }

                $stmt = $pdo->prepare(
                    'SELECT id
                     FROM tickets
                     WHERE id = ?'
                );
                $stmt->execute([$ticketId]);

                if (!$stmt->fetch()) {
                    $errors[] = 'Tikettiä ei löytynyt.';
                    break;
                }

                $supportName = null;

                if ($assignedTo !== '') {
                    $stmt = $pdo->prepare(
                        'SELECT name
                         FROM users
                         WHERE id = ?
                         AND role = ?'
                    );
                    $stmt->execute([$assignedTo, 'support']);
                    $supportName = $stmt->fetchColumn();

                    if ($supportName === false) {
                        $errors[] = 'Valittu käyttäjä ei ole tukihenkilö.';
                        break;
                    }
                }

                $stmt = $pdo->prepare(
                    'UPDATE tickets
                     SET assigned_to = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $assignedTo === '' ? null : (int) $assignedTo,
                    $ticketId
                ]);

                $success = $supportName === null
                    ? 'Tiketin #' . (int) $ticketId . ' käsittelijä poistettiin.'
                    : 'Tiketin #' . (int) $ticketId . ' käsittelijäksi määritettiin ' . $supportName . '.';
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

/*
 * Analytiikka: tilajakauma.
 */
$statusCounts = array_fill_keys(array_keys($statuses), 0);

$stmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS ticket_count
     FROM tickets
     GROUP BY status'
);
$stmt->execute();

foreach ($stmt->fetchAll() as $row) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['ticket_count'];
    }
}

$openCount = 0;
foreach ($openStatuses as $openStatus) {
    $openCount += $statusCounts[$openStatus];
}

$placeholders = implode(', ', array_fill(0, count($openStatuses), '?'));

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM tickets
     WHERE assigned_to IS NULL
     AND status IN (' . $placeholders . ')'
);
$stmt->execute($openStatuses);
$unassignedOpenCount = (int) $stmt->fetchColumn();

/*
 * Analytiikka: kategoriat suurimmasta pienimpään.
 * Käytetään jo haettua kategorialistaa.
 */
$categoryChart = $categories;
usort(
    $categoryChart,
    fn ($a, $b) => (int) $b['ticket_count'] <=> (int) $a['ticket_count']
);

/*
 * Analytiikka: viimeisen 12 kuukauden aikana luodut tiketit
 * kuukausittain nykyisen tilan mukaan. Tietokanta ei tallenna
 * ratkaisuajankohtaa, joten jaottelu perustuu luontikuukauteen.
 */
$monthKeys = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKeys[] = date('Y-m', strtotime("first day of -$i months"));
}

$monthly = [];
foreach ($monthKeys as $monthKey) {
    $monthly[$monthKey] = ['open' => 0, 'resolved' => 0, 'closed' => 0];
}

$stmt = $pdo->prepare(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, status, COUNT(*) AS ticket_count
     FROM tickets
     WHERE created_at >= ?
     GROUP BY month, status"
);
$stmt->execute([$monthKeys[0] . '-01 00:00:00']);

foreach ($stmt->fetchAll() as $row) {
    if (!isset($monthly[$row['month']])) {
        continue;
    }

    $group = in_array($row['status'], $openStatuses, true) ? 'open' : $row['status'];

    if (isset($monthly[$row['month']][$group])) {
        $monthly[$row['month']][$group] += (int) $row['ticket_count'];
    }
}

$monthLabels = array_map(
    fn ($monthKey) => date('n/Y', strtotime($monthKey . '-01')),
    $monthKeys
);

$chartData = [
    'categories' => [
        'labels' => array_column($categoryChart, 'name'),
        'counts' => array_map('intval', array_column($categoryChart, 'ticket_count'))
    ],
    'statuses' => [
        'keys' => array_keys($statuses),
        'labels' => array_values($statuses),
        'counts' => array_values($statusCounts)
    ],
    'monthly' => [
        'labels' => $monthLabels,
        'open' => array_column(array_values($monthly), 'open'),
        'resolved' => array_column(array_values($monthly), 'resolved'),
        'closed' => array_column(array_values($monthly), 'closed')
    ]
];

/*
 * Tukihenkilöt ja heidän avoimet tikettinsä käsittelijän valintaa varten.
 */
$stmt = $pdo->prepare(
    'SELECT users.id, users.name, COUNT(tickets.id) AS open_count
     FROM users
     LEFT JOIN tickets
        ON tickets.assigned_to = users.id
        AND tickets.status IN (' . $placeholders . ')
     WHERE users.role = ?
     GROUP BY users.id, users.name
     ORDER BY users.name'
);
$stmt->execute([...$openStatuses, 'support']);
$supportUsers = $stmt->fetchAll();

/*
 * Tikettilistan suodatus. Oletuksena näytetään kaikki avoimet tilat.
 */
$ticketFilters = [
    'open' => 'Avoimet (uusi, käsittelyssä, odottaa opiskelijaa)'
] + $statuses + [
    'all' => 'Kaikki tiketit'
];

$filterInput = $_GET['status'] ?? 'open';
$ticketFilter = is_string($filterInput) && isset($ticketFilters[$filterInput])
    ? $filterInput
    : 'open';
$onlyUnassigned = ($_GET['unassigned'] ?? '') === '1';

$conditions = [];
$params = [];

if ($ticketFilter === 'open') {
    $conditions[] = 'tickets.status IN (' . $placeholders . ')';
    $params = $openStatuses;
} elseif ($ticketFilter !== 'all') {
    $conditions[] = 'tickets.status = ?';
    $params[] = $ticketFilter;
}

if ($onlyUnassigned) {
    $conditions[] = 'tickets.assigned_to IS NULL';
}

$stmt = $pdo->prepare(
    'SELECT
        tickets.id,
        tickets.title,
        tickets.status,
        tickets.priority,
        tickets.assigned_to,
        tickets.created_at,
        students.name AS student_name,
        students.email AS student_email,
        categories.name AS category_name,
        support_users.name AS support_name
     FROM tickets
     INNER JOIN users AS students
        ON tickets.user_id = students.id
     INNER JOIN categories
        ON tickets.category_id = categories.id
     LEFT JOIN users AS support_users
        ON tickets.assigned_to = support_users.id
     ' . ($conditions ? 'WHERE ' . implode(' AND ', $conditions) : '') . '
     ORDER BY
        FIELD(tickets.status, "new", "in_progress", "waiting_student", "resolved", "closed"),
        FIELD(tickets.priority, "urgent", "high", "normal", "low"),
        tickets.created_at ASC'
);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$filterQuery = http_build_query(array_filter([
    'status' => $ticketFilter === 'open' ? '' : $ticketFilter,
    'unassigned' => $onlyUnassigned ? '1' : ''
]));

$formatDate = static fn (string $value): string => date('j.n.Y H.i', strtotime($value));

/*
 * Käsittelijän määrityksen viestit näytetään tikettiosiossa,
 * koska lomake palauttaa käyttäjän sinne.
 */
$messagesInTickets = $action === 'assign_ticket';

?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hallinta - OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
    <script src="js/vendor/chart.umd.min.js" defer></script>
    <script src="js/admin.js" defer></script>
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
            <p>Tikettien analytiikka, käsittelijöiden määritys sekä käyttäjien ja kategorioiden hallinta.</p>

            <nav class="admin-subnav" aria-label="Hallintapaneelin osiot">
                <a href="#analytics">Analytiikka</a>
                <a href="#tickets">Tiketit</a>
                <a href="#users">Käyttäjät (<?= $stats['users'] ?>)</a>
                <a href="#categories">Kategoriat (<?= $stats['categories'] ?>)</a>
            </nav>

            <div class="dashboard-stats admin-stats">
                <article class="stat-card"><strong><?= $openCount ?></strong><span>Avoimia tikettejä</span></article>
                <article class="stat-card"><strong><?= $unassignedOpenCount ?></strong><span>Avoimia ilman käsittelijää</span></article>
                <article class="stat-card"><strong><?= $statusCounts['resolved'] ?></strong><span>Ratkaistu</span></article>
                <article class="stat-card"><strong><?= $statusCounts['closed'] ?></strong><span>Suljettu</span></article>
                <article class="stat-card"><strong><?= $stats['tickets'] ?></strong><span>Tikettejä yhteensä</span></article>
            </div>

            <?php if (!$messagesInTickets): ?>
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
            <?php endif; ?>

            <section class="dashboard-section" id="analytics" aria-labelledby="analytics-title">
                <h2 id="analytics-title">Analytiikka</h2>

                <?php if ($stats['tickets'] === 0): ?>
                    <div class="dashboard-placeholder">
                        <h3>Ei vielä tikettejä</h3>
                        <p>Kaaviot näkyvät, kun järjestelmään on luotu tikettejä.</p>
                    </div>
                <?php else: ?>
                    <script type="application/json" id="admin-chart-data"><?= json_encode(
                        $chartData,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                    ) ?></script>

                    <div class="chart-grid">
                        <figure class="chart-card">
                            <figcaption>
                                <h3>Tiketit kategorioittain</h3>
                                <p>Kaikki tiketit tilasta riippumatta.</p>
                            </figcaption>
                            <div class="chart-canvas chart-canvas-tall">
                                <canvas id="chart-categories" role="img" aria-label="Pylväskaavio tikettien määrästä kategorioittain. Luvut löytyvät taulukosta kaavion alta."></canvas>
                            </div>
                            <details class="chart-table">
                                <summary>Näytä luvut taulukkona</summary>
                                <table class="admin-table">
                                    <thead><tr><th scope="col">Kategoria</th><th scope="col">Tikettejä</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($categoryChart as $category): ?>
                                            <tr><td><?= $escape($category['name']) ?></td><td><?= (int) $category['ticket_count'] ?></td></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        </figure>

                        <figure class="chart-card">
                            <figcaption>
                                <h3>Nykyinen tilajakauma</h3>
                                <p>Ratkaistu ja suljettu näytetään erikseen.</p>
                            </figcaption>
                            <div class="chart-canvas chart-canvas-tall">
                                <canvas id="chart-statuses" role="img" aria-label="Rengaskaavio tikettien nykyisestä tilajakaumasta. Luvut löytyvät taulukosta kaavion alta."></canvas>
                            </div>
                            <details class="chart-table">
                                <summary>Näytä luvut taulukkona</summary>
                                <table class="admin-table">
                                    <thead><tr><th scope="col">Tila</th><th scope="col">Tikettejä</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($statuses as $value => $label): ?>
                                            <tr>
                                                <td><span class="status-badge status-<?= $escape($value) ?>"><?= $escape($label) ?></span></td>
                                                <td><?= $statusCounts[$value] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </details>
                        </figure>

                        <figure class="chart-card chart-card-wide">
                            <figcaption>
                                <h3>Luodut tiketit kuukausittain (12 kk)</h3>
                                <p>Pylväs kertoo kuukauden aikana luodut tiketit ja niiden nykyisen tilan. Tietokanta ei tallenna ratkaisupäivää, joten ratkaisuja ei voi kohdistaa ratkaisukuukauteen.</p>
                            </figcaption>
                            <div class="chart-canvas">
                                <canvas id="chart-monthly" role="img" aria-label="Pinottu pylväskaavio viimeisen 12 kuukauden aikana luoduista tiketeistä nykyisen tilan mukaan. Luvut löytyvät taulukosta kaavion alta."></canvas>
                            </div>
                            <details class="chart-table">
                                <summary>Näytä luvut taulukkona</summary>
                                <div class="admin-table-wrap">
                                    <table class="admin-table">
                                        <thead>
                                            <tr><th scope="col">Kuukausi</th><th scope="col">Avoimet</th><th scope="col">Ratkaistu</th><th scope="col">Suljettu</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_values($monthly) as $index => $month): ?>
                                                <tr>
                                                    <td><?= $escape($monthLabels[$index]) ?></td>
                                                    <td><?= $month['open'] ?></td>
                                                    <td><?= $month['resolved'] ?></td>
                                                    <td><?= $month['closed'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </figure>
                    </div>
                <?php endif; ?>
            </section>

            <section class="dashboard-section admin-panel" id="tickets" aria-labelledby="tickets-title">
                <h2 id="tickets-title">Tikettien hallinta</h2>

                <?php if ($messagesInTickets): ?>
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
                <?php endif; ?>

                <form method="GET" action="admin.php#tickets" class="admin-ticket-filters">
                    <div class="form-group">
                        <label for="ticket-status-filter">Tila</label>
                        <select name="status" id="ticket-status-filter">
                            <?php foreach ($ticketFilters as $value => $label): ?>
                                <option value="<?= $escape($value) ?>" <?= $ticketFilter === $value ? 'selected' : '' ?>>
                                    <?= $escape($label) ?><?= isset($statusCounts[$value]) ? ' (' . $statusCounts[$value] . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="checkbox-label">
                        <input type="checkbox" name="unassigned" value="1" <?= $onlyUnassigned ? 'checked' : '' ?>>
                        Vain ilman käsittelijää
                    </label>
                    <div class="filter-actions">
                        <button type="submit" class="button">Suodata</button>
                        <a href="admin.php#tickets" class="button secondary">Tyhjennä</a>
                    </div>
                </form>

                <p class="result-count" role="status">
                    Näytetään <strong><?= count($tickets) ?></strong> / <?= $stats['tickets'] ?> tikettiä:
                    <?= $escape($ticketFilters[$ticketFilter]) ?><?= $onlyUnassigned ? ', vain ilman käsittelijää' : '' ?>.
                </p>

                <?php if (empty($tickets)): ?>
                    <p class="empty-note">Valituilla suodattimilla ei löytynyt tikettejä.</p>
                <?php else: ?>
                    <div class="ticket-table-wrap">
                        <table class="ticket-table">
                            <caption class="visually-hidden">Tiketit: <?= $escape($ticketFilters[$ticketFilter]) ?></caption>
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
                                            <a href="ticket.php?id=<?= $escape($ticket['id']) ?>" class="ticket-title"><?= $escape($ticket['title']) ?></a>
                                            <span class="sub">#<?= $escape($ticket['id']) ?> · <?= $escape($ticket['category_name']) ?></span>
                                        </td>
                                        <td data-label="Opiskelija">
                                            <div>
                                                <?= $escape($ticket['student_name']) ?>
                                                <span class="sub"><?= $escape($ticket['student_email']) ?></span>
                                            </div>
                                        </td>
                                        <td data-label="Tila">
                                            <span class="status-badge status-<?= $escape($ticket['status']) ?>"><?= $escape($statuses[$ticket['status']] ?? $ticket['status']) ?></span>
                                        </td>
                                        <td data-label="Prioriteetti">
                                            <span class="priority-<?= $escape($ticket['priority']) ?>"><?= $escape($priorities[$ticket['priority']] ?? $ticket['priority']) ?></span>
                                        </td>
                                        <td data-label="Käsittelijä">
                                            <?php if (!empty($ticket['support_name'])): ?>
                                                <?= $escape($ticket['support_name']) ?>
                                            <?php else: ?>
                                                <span class="unassigned">Ei määritetty</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Luotu">
                                            <time datetime="<?= $escape(date('Y-m-d\TH:i', strtotime($ticket['created_at']))) ?>"><?= $escape($formatDate($ticket['created_at'])) ?></time>
                                        </td>
                                        <td data-label="Toiminnot">
                                            <div class="row-actions">
                                                <a
                                                    href="assign-ticket.php?id=<?= $escape($ticket['id']) ?>"
                                                    class="button small"
                                                    data-assign-ticket="<?= $escape($ticket['id']) ?>"
                                                    data-ticket-title="<?= $escape($ticket['title']) ?>"
                                                    data-assigned-to="<?= $escape($ticket['assigned_to'] ?? '') ?>"
                                                    data-assigned-name="<?= $escape($ticket['support_name'] ?? '') ?>"
                                                    aria-label="Määritä käsittelijä tiketille #<?= $escape($ticket['id']) ?>"
                                                >Määritä käsittelijä</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <dialog class="assign-dialog" id="assign-dialog" aria-labelledby="assign-dialog-title">
                    <form method="POST" action="admin.php<?= $filterQuery !== '' ? '?' . $escape($filterQuery) : '' ?>#tickets">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <input type="hidden" name="action" value="assign_ticket">
                        <input type="hidden" name="ticket_id" value="">

                        <h3 id="assign-dialog-title">Määritä käsittelijä</h3>
                        <p class="assign-dialog-ticket" data-dialog-ticket></p>
                        <p class="assign-dialog-current">Nykyinen käsittelijä: <strong data-dialog-current>Ei määritetty</strong></p>

                        <?php if (empty($supportUsers)): ?>
                            <p class="form-error">Järjestelmässä ei ole tukihenkilöitä. Vaihda käyttäjän rooli tukihenkilöksi Käyttäjät-osiossa.</p>
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="assign-support">Uusi käsittelijä</label>
                            <select name="assigned_to" id="assign-support">
                                <option value="">Ei käsittelijää</option>
                                <?php foreach ($supportUsers as $supportUser): ?>
                                    <option value="<?= $escape($supportUser['id']) ?>">
                                        <?= $escape($supportUser['name']) ?> (<?= (int) $supportUser['open_count'] ?> avointa)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dialog-actions">
                            <button type="submit" class="button">Tallenna</button>
                            <button type="submit" class="button secondary" name="unassign" value="1" data-dialog-unassign>Poista käsittelijä</button>
                            <button type="button" class="button secondary" data-dialog-close>Peruuta</button>
                        </div>
                    </form>
                </dialog>
            </section>

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
