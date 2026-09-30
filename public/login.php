<?php

session_start();

require_once __DIR__ . '/../src/config/database.php';

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($email === '' || $password === '') {
        $error = 'Täytä kaikki kentät.';
    } elseif (mb_strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Tarkista sähköpostiosoitteen muoto.';
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

            header('Location: index.php');
            exit;

        } else {
            $error = 'Virheellinen sähköposti tai salasana.';
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
</head>

<body>

    <main>
        <h1>Kirjaudu sisään</h1>

        <?php if ($error): ?>
            <p class="error">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <form method="POST">

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
                >
            </div>

            <button type="submit">Kirjaudu</button>

        </form>

        <p>
            Ei vielä käyttäjää?
            <a href="register.php">Rekisteröidy</a>
        </p>

    </main>

</body>
</html>
