<?php

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';

requireRole('student');

$errors = [];

$title = '';
$description = '';
$categoryId = '';
$priority = 'normal';

$priorities = [
    'low' => 'Matala',
    'normal' => 'Normaali',
    'high' => 'Korkea',
    'urgent' => 'Kiireellinen'
];

$stmt = $pdo->prepare(
    'SELECT id, name
     FROM categories
     ORDER BY name'
);

$stmt->execute();

$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $priority = $_POST['priority'] ?? 'normal';

    if ($title === '') {
        $errors[] = 'Anna tiketin otsikko.';
    } elseif (mb_strlen($title) < 3) {
        $errors[] = 'Otsikon täytyy sisältää vähintään 3 merkkiä.';
    } elseif (mb_strlen($title) > 255) {
        $errors[] = 'Otsikko voi sisältää enintään 255 merkkiä.';
    }

    if ($description === '') {
        $errors[] = 'Anna tiketin kuvaus.';
    } elseif (mb_strlen($description) < 10) {
        $errors[] = 'Kuvauksen täytyy sisältää vähintään 10 merkkiä.';
    }

    if (
        $categoryId === '' ||
        filter_var($categoryId, FILTER_VALIDATE_INT) === false
    ) {
        $errors[] = 'Valitse kategoria.';
    } else {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM categories
             WHERE id = ?'
        );

        $stmt->execute([$categoryId]);

        if (!$stmt->fetch()) {
            $errors[] = 'Valittu kategoria ei ole kelvollinen.';
        }
    }

    if (!array_key_exists($priority, $priorities)) {
        $errors[] = 'Valittu prioriteetti ei ole kelvollinen.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO tickets
                (
                    user_id,
                    category_id,
                    title,
                    description,
                    priority,
                    status
                )
             VALUES
                (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $_SESSION['user_id'],
            $categoryId,
            $title,
            $description,
            $priority,
            'new'
        ]);

        $ticketId = $pdo->lastInsertId();

        header(
            'Location: create-ticket.php?success=1&ticket=' .
            urlencode($ticketId)
        );

        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="fi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Uusi tiketti - OpiskelijaHelpdesk</title>

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

                <a href="create-ticket.php">
                    Uusi tiketti
                </a>

                <a href="my-tickets.php">
                    Omat tiketit
                </a>

                <a href="logout.php">
                    Kirjaudu ulos
                </a>

            </nav>

        </div>

    </header>

    <main>

        <section class="hero">

            <div class="container">

                <div class="form-section">

                    <h1>
                        Uusi tiketti
                    </h1>

                    <p>
                        Kuvaa ongelmasi mahdollisimman selkeästi.
                    </p>

                    <?php if (!empty($errors)): ?>

                        <div class="form-error">

                            <strong>
                                Tarkista seuraavat kohdat:
                            </strong>

                            <ul>

                                <?php foreach ($errors as $error): ?>

                                    <li>
                                        <?= htmlspecialchars($error) ?>
                                    </li>

                                <?php endforeach; ?>

                            </ul>

                        </div>

                    <?php endif; ?>

                    <?php if (
                        isset($_GET['success']) &&
                        $_GET['success'] === '1'
                    ): ?>

                        <div class="form-success">

                            Tiketti luotiin onnistuneesti.

                            <?php if (isset($_GET['ticket'])): ?>

                                Tiketin numero:

                                <strong>
                                    #<?= htmlspecialchars($_GET['ticket']) ?>
                                </strong>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                    <form method="POST" action="create-ticket.php">

                        <div class="form-group">

                            <label for="title">
                                Otsikko
                            </label>

                            <input type="text" id="title" name="title" maxlength="255"
                                value="<?= htmlspecialchars($title) ?>"
                                placeholder="Esimerkiksi: Kannettava tietokone ei käynnisty" required>

                        </div>

                        <div class="form-group">

                            <label for="description">
                                Kuvaus
                            </label>

                            <textarea id="description" name="description" rows="8"
                                placeholder="Kuvaa ongelma mahdollisimman tarkasti..."
                                required><?= htmlspecialchars($description) ?></textarea>

                        </div>

                        <div class="form-group">

                            <label for="category_id">
                                Kategoria
                            </label>

                            <select id="category_id" name="category_id" required>

                                <option value="">
                                    Valitse kategoria
                                </option>

                                <?php foreach ($categories as $category): ?>

                                    <option value="<?= htmlspecialchars($category['id']) ?>"
                                        <?= (string) $categoryId === (string) $category['id']
                                            ? 'selected'
                                            : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-group">

                            <label for="priority">
                                Prioriteetti
                            </label>

                            <select id="priority" name="priority" required>

                                <?php foreach ($priorities as $value => $label): ?>

                                    <option value="<?= htmlspecialchars($value) ?>" <?= $priority === $value
                                          ? 'selected'
                                          : '' ?>
                                    >
                                        <?= htmlspecialchars($label) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="form-actions">

                            <button type="submit" class="button">
                                Luo tiketti
                            </button>

                            <a href="index.php" class="button secondary">
                                Peruuta
                            </a>

                        </div>

                    </form>

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
