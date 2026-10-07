<?php

session_start();

require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';

$message = '';
$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!isValidCsrfToken()) {
        $error = CSRF_ERROR_MESSAGE;
    } elseif ($name === '' || $email === '' || $password === '') {
        $error = 'Täytä kaikki kentät.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Nimi voi sisältää enintään 100 merkkiä.';
    } elseif (mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Anna kelvollinen sähköpostiosoite.';
    } elseif (strlen($password) < 8 || strlen($password) > 255) {
        $error = 'Salasanan tulee olla 8–255 merkkiä pitkä.';
    } else {
        $name = trim($nameInput);
        $email = trim($emailInput);
        $password = $passwordInput;
        $nameValue = $nameInput;
        $emailValue = $emailInput;

        if ($name === '' || $email === '' || $password === '') {
            $error = 'Täytä kaikki kentät.';
        } elseif (mb_strlen($name) > 100) {
            $error = 'Nimi on liian pitkä.';
        } elseif (mb_strlen($email) > 255) {
            $error = 'Sähköpostiosoite on liian pitkä.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Anna kelvollinen sähköpostiosoite.';
        } elseif (strlen($password) < 8) {
            $error = 'Salasanan tulee olla vähintään 8 merkkiä.';
        } elseif (strlen($password) > 255) {
            $error = 'Salasana on liian pitkä.';
        } else {

            $stmt = $pdo->prepare(
                'SELECT id FROM users WHERE email = ?'
            );

            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = 'Sähköpostiosoite on jo käytössä.';
            } else {

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role)
                     VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    $name,
                    $email,
                    $passwordHash,
                    'student'
                ]);

                $message = 'Rekisteröinti onnistui. Voit nyt kirjautua sisään.';
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekisteröinti - OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
</head>

<body>

    <header class="site-header">
        <div class="container navbar">
            <a href="index.php" class="logo">OpiskelijaHelpdesk</a>
            <nav class="nav-links" aria-label="Päänavigaatio">
                <a href="index.php">Etusivu</a>
                <a href="login.php">Kirjaudu</a>
            </nav>
        </div>
    </header>

    <main class="auth-main">
        <div class="container">
            <section class="form-section auth-card">
        <h1>Rekisteröidy</h1>

        <?php if ($error): ?>
            <p class="form-error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($message): ?>
            <p class="form-success">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="form-group">
                <label for="name">Nimi</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    maxlength="100"
                    value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Sähköposti</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    maxlength="255"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Salasana</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    minlength="8"
                >
            </div>

            <button type="submit">Rekisteröidy</button>

        </form>

        <p class="auth-switch">
            Onko sinulla jo käyttäjä?
            <a href="login.php">Kirjaudu sisään</a>
        </p>

            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container"><p>OpiskelijaHelpdesk</p></div>
    </footer>

</body>
</html>
