<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';

requireLogin();

$roleNames = [
    'student' => 'Opiskelija',
    'support' => 'Tukihenkilö',
    'admin' => 'Ylläpitäjä'
];

$roleName = $roleNames[$_SESSION['role']] ?? 'Tuntematon rooli';

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
        <strong>
            <?= htmlspecialchars($roleName) ?>
        </strong>
    </p>

    <?php if ($_SESSION['role'] === 'student'): ?>

        <h2>Opiskelija</h2>

        <p>
            Opiskelijan tulevat myöhemmissä issueissa.
        </p>

    <?php elseif ($_SESSION['role'] === 'support'): ?>

        <h2>Tukihenkilö</h2>

        <p>
            Tukihenkilön toiminnot ja dashboard myöhemmin.
        </p>

    <?php elseif ($_SESSION['role'] === 'admin'): ?>

        <h2>Ylläpitäjä</h2>

        <p>
            Ylläpitäjän hallintatoiminnot toteutetaan myöhemmin.
        </p>

    <?php endif; ?>

    <p>
        <a href="logout.php">Kirjaudu ulos</a>
    </p>

</main>

</body>
</html>