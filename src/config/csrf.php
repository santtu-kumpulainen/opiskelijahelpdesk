<?php

/*
 * CSRF-suojaus lomakkeille.
 *
 * Istunnolla on yksi satunnainen token, joka lisätään jokaiseen
 * POST-lomakkeeseen piilokenttänä ja tarkistetaan palvelimella.
 */

const CSRF_ERROR_MESSAGE = 'Pyyntö vanheni. Päivitä sivu ja yritä uudelleen.';

function csrfToken(): string
{
    if (
        !isset($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token']) ||
        $_SESSION['csrf_token'] === ''
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/*
 * Ilman parametria token luetaan lomakkeen csrf_token-kentästä.
 * JSON-rajapinnat välittävät tokenin otsakkeesta parametrina.
 */
function isValidCsrfToken(mixed $token = null): bool
{
    $token ??= $_POST['csrf_token'] ?? '';

    return is_string($token)
        && $token !== ''
        && hash_equals(csrfToken(), $token);
}
