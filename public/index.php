<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';

?>

<!DOCTYPE html>
<html lang="fi">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>OpiskelijaHelpdesk</title>

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

            <?php if (isset($_SESSION['user_id'])): ?>

                <?php if ($_SESSION['role'] === 'student'): ?>

                    <a href="create-ticket.php">
                        Uusi tiketti
                    </a>

                    <a href="my-tickets.php">
                        Omat tiketit
                    </a>

                <?php elseif ($_SESSION['role'] === 'support'): ?>

                    <a href="support-dashboard.php">
                        Tiketit
                    </a>

                    <a href="support-dashboard.php">
                        Dashboard
                    </a>

                <?php elseif ($_SESSION['role'] === 'admin'): ?>

                    <a href="#">
                        Hallinta
                    </a>

                    <a href="#">
                        Dashboard
                    </a>

                <?php endif; ?>

                <a href="logout.php">
                    Kirjaudu ulos
                </a>

            <?php else: ?>

                <a href="login.php">
                    Kirjaudu
                </a>

                <a href="register.php">
                    Rekisteröidy
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>

<main>

    <section class="hero">

        <div class="container">

            <?php if (!isset($_SESSION['user_id'])): ?>

                <h1>
                    OpiskelijaHelpdesk
                </h1>

                <p>
                    Opiskelijoiden tukipyyntöjen hallintajärjestelmä.
                </p>

                <div class="hero-actions">

                    <a href="login.php" class="button">
                        Kirjaudu
                    </a>

                    <a href="register.php" class="button secondary">
                        Rekisteröidy
                    </a>

                </div>

            <?php elseif ($_SESSION['role'] === 'student'): ?>

                <h1>
                    Tervetuloa,
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </h1>

                <p>
                    Olet kirjautunut OpiskelijaHelpdeskiin.
                </p>

                <div class="dashboard-placeholder">

                    <h2>Opiskelija</h2>

                    <p>
                        Voit luoda tukipyyntöjä ja seurata omia tikettejäsi.
                    </p>

                    <div class="hero-actions">

                        <a href="create-ticket.php" class="button">
                            Luo uusi tiketti
                        </a>

                        <a href="my-tickets.php" class="button secondary">
                            Omat tiketit
                        </a>

                    </div>

                </div>

            <?php elseif ($_SESSION['role'] === 'support'): ?>

                <h1>
                    Tervetuloa,
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </h1>

                <p>
                    Olet kirjautunut tukihenkilönä.
                </p>

                <div class="dashboard-placeholder">

                    <h2>Tukihenkilö</h2>

                    <p>
                        Tukihenkilön dashboard ja tikettien käsittely
                        toteutetaan myöhemmissä issueissa.
                    </p>

                </div>

            <?php elseif ($_SESSION['role'] === 'admin'): ?>

                <h1>
                    Tervetuloa,
                    <?= htmlspecialchars($_SESSION['user_name']) ?>
                </h1>

                <p>
                    Olet kirjautunut ylläpitäjänä.
                </p>

                <div class="dashboard-placeholder">

                    <h2>Ylläpitäjä</h2>

                    <p>
                        Ylläpitäjän hallintanäkymä toteutetaan
                        myöhemmässä issuessa.
                    </p>

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