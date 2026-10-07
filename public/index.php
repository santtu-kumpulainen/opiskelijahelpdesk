<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';

/*
 * Kirjautuneelle käyttäjälle etusivu näyttää tekoälychatin.
 * requireLogin() varmistaa, että käyttäjä on yhä olemassa.
 */
$isLoggedIn = isLoggedIn();

if ($isLoggedIn) {
    requireLogin();
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

    <title>OpiskelijaHelpdesk</title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>

    <?php if ($isLoggedIn): ?>
        <script src="js/chat.js" defer></script>
    <?php endif; ?>
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

                    <a href="admin.php">
                        Hallinta
                    </a>

                    <a href="admin.php#users">
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

            <?php if (!$isLoggedIn): ?>

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

            <?php else: ?>

                <h1>
                    Tervetuloa,
                    <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
                </h1>

                <p>
                    Kuvaile IT- tai ohjelmointiongelmasi tekoälylle ja saat ohjeita heti.
                </p>

                <section
                    class="chat-panel"
                    id="chat"
                    aria-labelledby="chat-title"
                    data-csrf-token="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                >

                    <div class="chat-header">

                        <div>
                            <h2 id="chat-title">Kysy tekoälyltä</h2>
                            <p>
                                Vastaukset tuottaa paikallinen kielimalli. Tarkista ohjeet
                                ennen kuin teet muutoksia, äläkä kirjoita salasanoja chattiin.
                            </p>
                        </div>

                        <div class="chat-toolbar">

                            <div class="chat-model">
                                <label for="chat-model">Malli</label>
                                <select id="chat-model" disabled>
                                    <option value="">Haetaan malleja…</option>
                                </select>
                            </div>

                            <p class="chat-model-status" id="chat-model-status" role="status"></p>

                        </div>

                    </div>

                    <noscript>
                        <p class="form-error">
                            Chat tarvitsee JavaScriptin. Voit silti
                            <?php if ($_SESSION['role'] === 'student'): ?>
                                <a href="create-ticket.php">luoda tukipyynnön</a>.
                            <?php else: ?>
                                käyttää muita toimintoja.
                            <?php endif; ?>
                        </p>
                    </noscript>

                    <div
                        class="chat-log"
                        id="chat-log"
                        role="log"
                        aria-live="polite"
                        aria-label="Keskustelu tekoälyn kanssa"
                        tabindex="0"
                    >
                        <p class="chat-welcome" id="chat-welcome">
                            Esimerkiksi: ”VS Code ei löydä Pythonia” tai ”Wifi katkeilee luokassa”.
                        </p>
                    </div>

                    <div class="form-error chat-error" id="chat-error" role="alert" hidden></div>

                    <form class="chat-form" id="chat-form" novalidate>

                        <label for="chat-input" class="chat-input-label">Viestisi</label>

                        <div class="chat-input-row">

                            <textarea
                                id="chat-input"
                                rows="2"
                                maxlength="4000"
                                placeholder="Kirjoita kysymyksesi…"
                                aria-describedby="chat-hint"
                            ></textarea>

                            <div class="chat-input-actions">
                                <button type="button" class="button secondary" id="chat-mic" aria-pressed="false">
                                    Sanele
                                </button>
                                <button type="submit" class="button" id="chat-send">
                                    Lähetä
                                </button>
                            </div>

                        </div>

                        <p class="chat-hint" id="chat-hint">
                            <span>Enter lähettää, Shift + Enter tekee rivinvaihdon.</span>
                            <span id="chat-count">0 / 4000</span>
                        </p>

                        <p class="chat-voice-status" id="chat-voice-status" role="status" hidden></p>

                    </form>

                    <div class="chat-footer">

                        <div class="chat-footer-actions">
                            <button type="button" class="button secondary small" id="chat-speak" aria-pressed="false">
                                Lue vastaukset ääneen
                            </button>
                            <button type="button" class="button secondary small" id="chat-stop-speaking" hidden>
                                Lopeta puhe
                            </button>
                            <button type="button" class="button secondary small" id="chat-clear">
                                Aloita uusi keskustelu
                            </button>
                        </div>

                        <?php if ($_SESSION['role'] === 'student'): ?>
                            <p class="chat-ticket">
                                Eikö ongelma ratkennut?
                                <a href="create-ticket.php" class="button small" id="chat-create-ticket">
                                    Luo tukipyyntö
                                </a>
                            </p>
                        <?php endif; ?>

                    </div>

                    <p class="chat-note">
                        Keskustelu säilyy vain tämän sivun ajan, eikä sitä tallenneta.
                        Selaimen puheentunnistus voi käsitellä ääntä selaimen valmistajan palvelussa.
                    </p>

                </section>

                <?php if ($_SESSION['role'] === 'student'): ?>

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

                    <div class="dashboard-placeholder">

                        <h2>Tukihenkilö</h2>

                        <p>
                            Käsittele opiskelijoiden tukipyyntöjä support-dashboardissa.
                        </p>

                        <div class="hero-actions">
                            <a href="support-dashboard.php" class="button">Avaa dashboard</a>
                        </div>

                    </div>

                <?php elseif ($_SESSION['role'] === 'admin'): ?>

                    <div class="dashboard-placeholder">

                        <h2>Ylläpitäjä</h2>

                        <p>
                            Hallitse käyttäjätilejä, käyttöoikeuksia ja tikettien kategorioita.
                        </p>

                        <div class="hero-actions">
                            <a href="admin.php" class="button">Avaa hallinta</a>
                        </div>

                    </div>

                <?php endif; ?>

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
