require_once __DIR__ . '/../src/config/database.php';

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (name, email, password, role)
    VALUES (?, ?, ?, ?)
");

$stmt->execute([
    $name,
    $email,
    $passwordHash,
    'student'
]);