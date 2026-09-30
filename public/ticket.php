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
 * Tukihenkilö ja ylläpitäjä voivat nähdä tikettejä.
 */
if (
    $_SESSION['role'] === 'student' &&
    (int)$ticket['user_id'] !== (int)$_SESSION['user_id']
) {
    http_response_code(403);
    exit('Sinulla ei ole oikeutta nähdä tätä tikettiä.');
}

$errors = [];
$commentText = '';

/*
 * Lisätään uusi kommentti
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $commentText = trim($_POST['comment'] ?? '');

    if ($commentText === '') {
        $errors[] = 'Kommentti ei voi olla tyhjä.';
    }

    if (mb_strlen($commentText) > 5000) {
        $errors[] = 'Kommentti voi sisältää enintään 5000 merkkiä.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO comments
                (ticket_id, user_id, comment)
             VALUES
                (?, ?, ?)'
        );

        $stmt->execute([
            $ticket['id'],
            $_SESSION['user_id'],
            $commentText
        ]);

        header(
            'Location: ticket.php?id=' .
            urlencode($ticket['id']) .
            '#comments'
        );

        exit;
    }
}

/*
 * Haetaan tiketin kommentit
 */
$stmt = $pdo->prepare(
    'SELECT
        comments.id,
        comments.comment,
        comments.created_at,
        users.name AS user_name,
        users.role AS user_role
     FROM comments
     INNER JOIN users
        ON comments.user_id = users.id
     WHERE comments.ticket_id = ?
     ORDER BY comments.created_at ASC, comments.id ASC'
);

$stmt->execute([$ticket['id']]);

$comments = $stmt->fetchAll();

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

                <section
                    class="comments-section"
                    id="comments"
                >

                    <h2>
                        Kommentit
                    </h2>

                    <?php if (empty($comments)): ?>

                        <p>
                            Tiketillä ei ole vielä kommentteja.
                        </p>

                    <?php else: ?>

                        <div class="comment-list">

                            <?php foreach ($comments as $comment): ?>

                                <article class="comment">

                                    <div class="comment-header">

                                        <strong>
                                            <?= htmlspecialchars(
                                                $comment['user_name']
                                            ) ?>
                                        </strong>

                                        <span>
                                            <?= htmlspecialchars(
                                                $comment['created_at']
                                            ) ?>
                                        </span>

                                    </div>

                                    <p>
                                        <?= nl2br(
                                            htmlspecialchars(
                                                $comment['comment']
                                            )
                                        ) ?>
                                    </p>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>

                        <div class="form-error">

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
                        action="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>#comments"
                        class="comment-form"
                    >

                        <div class="form-group">

                            <label for="comment">
                                Lisää kommentti
                            </label>

                            <textarea
                                id="comment"
                                name="comment"
                                rows="5"
                                maxlength="5000"
                                placeholder="Kirjoita kommentti..."
                                required
                            ><?= htmlspecialchars($commentText) ?></textarea>

                        </div>

                        <button
                            type="submit"
                            class="button"
                        >
                            Lisää kommentti
                        </button>

                    </form>

                </section>

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