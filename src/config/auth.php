<?php

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare(
            'SELECT role
             FROM users
             WHERE id = ?'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $currentRole = $stmt->fetchColumn();

        if ($currentRole === false) {
            session_unset();
            session_destroy();
            header('Location: login.php');
            exit;
        }

        $_SESSION['role'] = $currentRole;
    }
}

function hasRole(string $role): bool
{
    return isset($_SESSION['role'])
        && $_SESSION['role'] === $role;
}

function requireRole(string $role): void
{
    requireLogin();

    if (!hasRole($role)) {
        http_response_code(403);

        echo '<h1>403 Forbidden</h1>';
        echo '<p>Sinulla ei ole oikeuksia tälle sivulle.</p>';
        exit;
    }
}

function requireAnyRole(array $roles): void
{
    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        !in_array($_SESSION['role'], $roles, true)
    ) {
        http_response_code(403);

        echo '<h1>403 Forbidden</h1>';
        echo '<p>Sinulla ei ole oikeuksia tälle sivulle.</p>';
        exit;
    }
}
