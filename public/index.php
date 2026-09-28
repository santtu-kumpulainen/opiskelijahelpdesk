<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpiskelijaHelpdesk</title>
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <main>

        <h1>OpiskelijaHelpdesk</h1>

        <p>
            Tervetuloa,
            <strong>
                <?= htmlspecialchars($_SESSION['user_name']) ?>
            </strong>
        </p>

        <p>
            Rooli:
            <?= htmlspecialchars($_SESSION['role']) ?>
        </p>

        <p>
            Kirjautuminen onnistui.
        </p>

        <a href="logout.php">Kirjaudu ulos</a>

    </main>

</body>
</html>