<?php

session_start();

require_once __DIR__ . '/../src/config/database.php';

$error = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if (!is_string($emailInput) || !is_string($passwordInput)) {
        $error = 'Lomakkeen tiedot ovat virheellisiä.';
    } else {
        $email = trim($emailInput);
        $password = $passwordInput;
        $emailValue = $emailInput;

        if ($email === '' || $password === '') {
            $error = 'Täytä kaikki kentät.';
        } elseif (mb_strlen($email) > 255) {
            $error = 'Sähköpostiosoite on liian pitkä.';
        } else {

            $stmt = $pdo->prepare(
                'SELECT id, name, email, password, role
                 FROM users
                 WHERE email = ?'
            );

            $stmt->execute([$email]);

            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                $destination = match ($user['role']) {
                    'admin' => 'admin.php',
                    'support' => 'support-dashboard.php',
                    default => 'my-tickets.php'
                };

                header('Location: ' . $destination);
                exit;

            } else {
                $error = 'Virheellinen sähköposti tai salasana.';
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
    <title>Kirjautuminen - OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js" defer></script>
</head>

<body>

    <header class="site-header">
        <div class="container navbar">
            <a href="index.php" class="logo">OpiskelijaHelpdesk</a>
            <nav class="nav-links" aria-label="Päänavigaatio">
                <a href="index.php">Etusivu</a>
                <a href="register.php">Rekisteröidy</a>
            </nav>
        </div>
    </header>

    <main class="auth-main">
        <div class="container">
            <section class="form-section auth-card">
        <h1>Kirjaudu sisään</h1>

        <?php if ($error): ?>
            <p class="form-error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="POST">

            <div class="form-group">
                <label for="email">Sähköposti</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    maxlength="255"
                    value="<?= htmlspecialchars($emailValue) ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">Salasana</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit">Kirjaudu</button>

        </form>

        <p class="auth-switch">
            Ei vielä käyttäjää?
            <a href="register.php">Rekisteröidy</a>
        </p>

            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container"><p>OpiskelijaHelpdesk</p></div>
    </footer>

</body>
</html>
