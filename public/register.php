<?php

session_start();

require_once __DIR__ . '/../src/config/database.php';

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

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Täytä kaikki kentät.';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Nimi voi sisältää enintään 100 merkkiä.';
    } elseif (mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Anna kelvollinen sähköpostiosoite.';
    } elseif (strlen($password) < 8 || strlen($password) > 255) {
        $error = 'Salasanan tulee olla 8–255 merkkiä pitkä.';
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

?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekisteröinti - OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <main>
        <h1>Rekisteröidy</h1>

        <?php if ($error): ?>
            <p class="error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($message): ?>
            <p class="success">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <form method="POST">

            <div>
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

            <div>
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

            <div>
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

        <p>
            Onko sinulla jo käyttäjä?
            <a href="login.php">Kirjaudu sisään</a>
        </p>

    </main>

</body>
</html>
