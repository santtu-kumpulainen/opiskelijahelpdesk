<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';

requireLogin();

$ticketId = $_GET['id'] ?? '';

if (
    $ticketId === '' ||
    filter_var($ticketId, FILTER_VALIDATE_INT) === false
) {
    http_response_code(400);
    exit('Virheellinen tiketin numero.');
}

/*
 * Haetaan tiketti
 */
$stmt = $pdo->prepare(
    'SELECT
        tickets.id,
        tickets.user_id,
        tickets.assigned_to,
        tickets.title,
        tickets.description,
        tickets.priority,
        tickets.status,
        tickets.created_at,

        users.name AS user_name,
        users.email AS user_email,

        categories.name AS category_name,

        support_users.name AS support_name

     FROM tickets

     INNER JOIN users
        ON tickets.user_id = users.id

     INNER JOIN categories
        ON tickets.category_id = categories.id

     LEFT JOIN users AS support_users
        ON tickets.assigned_to = support_users.id

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
 *
 * Tukihenkilö ja ylläpitäjä voivat nähdä kaikki tiketit.
 */
if (
    $_SESSION['role'] === 'student' &&
    (int) $ticket['user_id'] !== (int) $_SESSION['user_id']
) {
    http_response_code(403);
    exit('Sinulla ei ole oikeutta nähdä tätä tikettiä.');
}

$errors = [];
$commentText = '';

/*
 * Kommentin / tukihenkilön vastauksen käsittely
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isValidCsrfToken()) {
        $errors[] = CSRF_ERROR_MESSAGE;
    }

    $commentInput = $_POST['comment'] ?? '';
    $commentText = is_string($commentInput) ? trim($commentInput) : '';

    /*
     * Tarkistetaan kommentti
     */
    if ($commentText === '') {
        $errors[] = 'Vastaus ei voi olla tyhjä.';
    }

    if (mb_strlen($commentText) > 5000) {
        $errors[] = 'Vastaus voi sisältää enintään 5000 merkkiä.';
    }

    /*
     * Jos virheitä ei ole, tallennetaan kommentti.
     */
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

        /*
         * Jos tukihenkilö vastaa, tiketti siirtyy
         * käsittelyssä-tilaan, jos se on vielä uusi.
         */
        if (
            $_SESSION['role'] === 'support' &&
            $ticket['status'] === 'new'
        ) {

            $stmt = $pdo->prepare(
                'UPDATE tickets
                 SET status = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                'in_progress',
                $ticket['id']
            ]);
        }

        header(
            'Location: ticket.php?id=' .
            urlencode($ticket['id']) .
            '&reply=1#comments'
        );

        exit;
    }
}

/*
 * Haetaan kaikki kommentit
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

/*
 * Tilojen nimet
 */
$statusLabels = [
    'new' => 'Uusi',
    'in_progress' => 'Käsittelyssä',
    'waiting_student' => 'Odottaa opiskelijaa',
    'resolved' => 'Ratkaistu',
    'closed' => 'Suljettu'
];

/*
 * Prioriteettien nimet
 */
$priorityLabels = [
    'low' => 'Matala',
    'normal' => 'Normaali',
    'high' => 'Korkea',
    'urgent' => 'Kiireellinen'
];

/*
 * Roolien nimet
 */
$roleLabels = [
    'student' => 'Opiskelija',
    'support' => 'Tukihenkilö',
    'admin' => 'Ylläpitäjä'
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

    <link
        rel="stylesheet"
        href="css/style.css"
    >
    <script src="js/app.js" defer></script>

</head>

<body>

<header class="site-header">

    <div class="container navbar">

        <a
            href="index.php"
            class="logo"
        >
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

            <?php elseif ($_SESSION['role'] === 'support'): ?>

                <a href="support-dashboard.php">
                    Support Dashboard
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

                    <span class="status-badge status-<?= htmlspecialchars($ticket['status']) ?>">

                        <?= htmlspecialchars(
                            $statusLabels[$ticket['status']]
                            ?? $ticket['status']
                        ) ?>

                    </span>

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
                            <?= htmlspecialchars(
                                $ticket['category_name']
                            ) ?>
                        </span>

                    </div>


                    <div>

                        <strong>
                            Prioriteetti
                        </strong>

                        <span class="priority-<?= htmlspecialchars($ticket['priority']) ?>">
                            <?= htmlspecialchars(
                                $priorityLabels[$ticket['priority']]
                                ?? $ticket['priority']
                            ) ?>
                        </span>

                    </div>


                    <div>

                        <strong>
                            Käsittelijä
                        </strong>

                        <span>

                            <?php if (!empty($ticket['support_name'])): ?>

                                <?= htmlspecialchars(
                                    $ticket['support_name']
                                ) ?>

                            <?php else: ?>

                                Ei määritetty

                            <?php endif; ?>

                        </span>

                    </div>


                    <div>

                        <strong>
                            Luotu
                        </strong>

                        <span>
                            <time datetime="<?= htmlspecialchars(
                                date('Y-m-d\TH:i', strtotime($ticket['created_at']))
                            ) ?>">
                                <?= htmlspecialchars(
                                    date('j.n.Y H.i', strtotime($ticket['created_at']))
                                ) ?>
                            </time>
                        </span>

                    </div>


                    <div>

                        <strong>
                            Kirjoittaja
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $ticket['user_name']
                            ) ?>
                        </span>

                    </div>


                    <div>

                        <strong>
                            Sähköposti
                        </strong>

                        <span>
                            <?= htmlspecialchars(
                                $ticket['user_email']
                            ) ?>
                        </span>

                    </div>

                </div>


                <!-- Kommentit ja vastaukset -->

                <section
                    class="comments-section"
                    id="comments"
                >

                    <h2>
                        Keskustelu
                    </h2>


                    <?php if (isset($_GET['reply']) && $_GET['reply'] === '1'): ?>

                        <div class="form-success">
                            Vastauksesi tallennettiin onnistuneesti.
                        </div>

                    <?php endif; ?>


                    <?php if (empty($comments)): ?>

                        <p>
                            Tikettiin ei ole vielä lisätty kommentteja.
                        </p>

                    <?php else: ?>

                        <div class="comment-list">

                            <?php foreach ($comments as $comment): ?>

                                <article class="comment<?= $comment['user_role'] !== 'student' ? ' is-staff' : '' ?>">

                                    <div class="comment-header">

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $comment['user_name']
                                                ) ?>
                                            </strong>

                                            <span class="comment-role">
                                                <?= htmlspecialchars(
                                                    $roleLabels[$comment['user_role']]
                                                    ?? $comment['user_role']
                                                ) ?>
                                            </span>

                                        </div>

                                        <time datetime="<?= htmlspecialchars(
                                            date('Y-m-d\TH:i', strtotime($comment['created_at']))
                                        ) ?>">
                                            <?= htmlspecialchars(
                                                date('j.n.Y H.i', strtotime($comment['created_at']))
                                            ) ?>
                                        </time>

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


                    <?php if ($_SESSION['role'] === 'support'): ?>

                        <div class="response-form">

                            <h2>
                                Vastaa opiskelijalle
                            </h2>

                            <p>
                                Kirjoita tähän opiskelijalle lähetettävä vastaus.
                            </p>

                            <form
                                method="POST"
                                action="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>#comments"
                                class="comment-form"
                            >
                                <?= csrfField() ?>

                                <div class="form-group">

                                    <label for="comment">
                                        Vastaus
                                    </label>

                                    <textarea
                                        id="comment"
                                        name="comment"
                                        rows="6"
                                        maxlength="5000"
                                        placeholder="Kirjoita vastaus opiskelijalle..."
                                        required
                                    ><?= htmlspecialchars($commentText) ?></textarea>

                                </div>

                                <button
                                    type="submit"
                                    class="button"
                                >
                                    Lähetä vastaus
                                </button>

                            </form>

                        </div>

                    <?php elseif ($_SESSION['role'] === 'student'): ?>

                        <div class="response-form">

                            <h2>
                                Lisää kommentti
                            </h2>

                            <form
                                method="POST"
                                action="ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>#comments"
                                class="comment-form"
                            >
                                <?= csrfField() ?>

                                <div class="form-group">

                                    <label for="comment">
                                        Kommentti
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

                        </div>

                    <?php endif; ?>

                </section>


                <div class="ticket-actions">


                    <?php if (
                        isset($_GET['updated']) &&
                        $_GET['updated'] === '1'
                    ): ?>

                        <div class="form-success">
                            Tiketin tiedot päivitettiin onnistuneesti.
                        </div>

                    <?php endif; ?>


                    <?php if (
                        isset($_GET['assigned']) &&
                        $_GET['assigned'] === '1'
                    ): ?>

                        <div class="form-success">
                            Tiketin käsittelijä päivitettiin onnistuneesti.
                        </div>

                    <?php endif; ?>


                    <?php if (
                        $_SESSION['role'] === 'support' ||
                        $_SESSION['role'] === 'admin'
                    ): ?>

                        <a
                            href="update-ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                            class="button"
                        >
                            Muuta tilaa tai prioriteettia
                        </a>

                        <a
                            href="assign-ticket.php?id=<?= htmlspecialchars($ticket['id']) ?>"
                            class="button"
                        >
                            Määritä käsittelijä
                        </a>

                    <?php endif; ?>


                    <?php if ($_SESSION['role'] === 'student'): ?>

                        <a
                            href="my-tickets.php"
                            class="button secondary"
                        >
                            Takaisin omiin tiketteihin
                        </a>

                    <?php elseif ($_SESSION['role'] === 'support'): ?>

                        <a
                            href="support-dashboard.php"
                            class="button secondary"
                        >
                            Takaisin dashboardille
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
