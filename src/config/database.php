<?php

/*
 * Keskitetty PDO-tietokantayhteys.
 *
 * Tietokantatiedot tulevat Dockerin ympäristömuuttujista.
 * Yhteysasetukset luetaan ympäristömuuttujista.
 */

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (
    $host === false || $port === false || $dbname === false ||
    $username === false || $password === false
) {
    error_log('Tietokannan ympäristömuuttujia puuttuu.');
    http_response_code(500);
    exit('Palvelimen tietokantamääritykset puuttuvat.');
}

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

try {

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false
        ]
    );

} catch (PDOException $e) {

    /*
     * Älä näytä tietokantavirheen tarkkaa sisältöä
     * käyttäjälle.
     */
    error_log('Tietokantayhteyden muodostaminen epäonnistui: ' . $e->getMessage());
    http_response_code(500);

    exit(
        'Tietokantayhteyden muodostaminen epäonnistui.'
    );
}

set_exception_handler(static function (Throwable $e): void {
    error_log('Sovellusvirhe: ' . $e->getMessage());
    http_response_code(500);
    exit('Palvelimella tapahtui virhe. Yritä myöhemmin uudelleen.');
});
