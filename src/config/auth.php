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

        echo '403 Forbidden';
        echo '<p>Sinulla ei ole oikeuksia tälle sivulle.</p>';

        exit;
    }
}

function requireAnyRole(array $roles): void
{
    requireLogin();

    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);

        echo '403 Forbidden';
        echo '<p>Sinulla ei ole oikeuksia tälle sivulle.</p>';

        exit;
    }
}