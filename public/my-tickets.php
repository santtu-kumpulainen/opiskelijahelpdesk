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

$categoryStmt = $pdo->query(
    'SELECT id, name
     FROM categories
     ORDER BY name'
);

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
                Hae ja suodata omia tukipyyntöjäsi.
            </p>

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

                    <div class="ticket-list">

                        <?php foreach ($tickets as $ticket): ?>

                            <article class="ticket-card">

                                <div class="ticket-card-header">

                                    <h2>
                                        <?= htmlspecialchars(
                                            $ticket['title']
                                        ) ?>
                                    </h2>

                                    <span>
                                        #<?= htmlspecialchars(
                                            $ticket['id']
                                        ) ?>
                                    </span>

                                </div>

                                <div class="ticket-card-info">

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
                                    class="button secondary"
                                >
                                    Näytä tiketti
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