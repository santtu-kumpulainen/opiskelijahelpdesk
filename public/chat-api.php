<?php

/*
 * Tekoälychatin JSON-rajapinta. Selain kutsuu tätä, ja PHP välittää
 * pyynnön Ollamalle. Selain ei ota suoraan yhteyttä mallipalvelimeen.
 *
 *  GET  chat-api.php?action=models  asennetut mallit ja oletusmalli
 *  POST chat-api.php?action=load    {"model"} lataa mallin muistiin
 *  POST chat-api.php?action=chat    {"model", "messages"} → {"answer"}
 *
 * POST-pyynnöt vaativat CSRF-tokenin X-CSRF-Token-otsakkeessa.
 */

session_start();

require_once __DIR__ . '/../src/config/auth.php';
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/csrf.php';
require_once __DIR__ . '/../src/config/ollama.php';

const CHAT_MAX_REQUEST_BYTES = 200000;
const CHAT_MAX_MESSAGES = 40;
const CHAT_MAX_MESSAGE_LENGTH = 4000;
const CHAT_MAX_TOTAL_LENGTH = 24000;

const CHAT_SYSTEM_PROMPT = 'Olet OpiskelijaHelpdeskin IT-tukiavustaja. Vastaa aina suomeksi. '
    . 'Auta opiskelijoita IT- ja ohjelmistokehitysongelmissa selkeästi, ystävällisesti ja '
    . 'täsmällisesti, tarvittaessa vaihe vaiheelta. Jos et tiedä vastausta, kerro se avoimesti. '
    . 'Älä koskaan pyydä salasanoja tai muita tunnistetietoja. Jos ongelma vaatii '
    . 'henkilökohtaista apua, kuten käyttäjätunnusten tai laitteiden käsittelyä, ohjaa '
    . 'käyttäjä tekemään tukipyyntö.';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function sendJson(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendOllamaError(OllamaException $exception): never
{
    if ($exception->logDetail !== '') {
        error_log('Ollama: ' . $exception->logDetail);
    }

    sendJson($exception->httpStatus, ['error' => $exception->getMessage()]);
}

/*
 * Varmistaa, että malli on asennettu Ollamaan.
 */
function requireInstalledModel(mixed $model): string
{
    if (
        !is_string($model) ||
        $model === '' ||
        strlen($model) > 200 ||
        !preg_match('/^[A-Za-z0-9._:\/-]+$/', $model)
    ) {
        sendJson(400, ['error' => 'Mallin nimi on virheellinen.']);
    }

    try {
        $installed = ollamaListModels();
    } catch (OllamaException $exception) {
        sendOllamaError($exception);
    }

    if (!in_array($model, $installed, true)) {
        sendJson(400, ['error' => 'Valittu malli ei ole saatavilla. Valitse toinen malli.']);
    }

    return $model;
}

/*
 * Tarkistaa selaimen lähettämän viestihistorian. Järjestelmäkehote
 * lisätään palvelimella, joten selain saa lähettää vain user- ja
 * assistant-viestejä.
 */
function validateMessages(mixed $messages): array
{
    if (!is_array($messages) || !array_is_list($messages) || $messages === []) {
        sendJson(400, ['error' => 'Viestihistoria puuttuu tai on virheellinen.']);
    }

    if (count($messages) > CHAT_MAX_MESSAGES) {
        sendJson(413, [
            'error' => 'Keskustelu on liian pitkä. Aloita uusi keskustelu.',
            'code' => 'history_too_long'
        ]);
    }

    $validated = [];
    $totalLength = 0;

    foreach ($messages as $message) {
        $role = is_array($message) ? ($message['role'] ?? null) : null;
        $content = is_array($message) ? ($message['content'] ?? null) : null;

        if (
            !in_array($role, ['user', 'assistant'], true) ||
            !is_string($content) ||
            trim($content) === ''
        ) {
            sendJson(400, ['error' => 'Viestihistoria on virheellinen.']);
        }

        $length = mb_strlen($content);

        if ($role === 'user' && $length > CHAT_MAX_MESSAGE_LENGTH) {
            sendJson(413, ['error' => 'Viesti voi sisältää enintään ' . CHAT_MAX_MESSAGE_LENGTH . ' merkkiä.']);
        }

        $totalLength += $length;
        $validated[] = ['role' => $role, 'content' => $content];
    }

    if ($totalLength > CHAT_MAX_TOTAL_LENGTH) {
        sendJson(413, [
            'error' => 'Keskustelu on liian pitkä. Aloita uusi keskustelu.',
            'code' => 'history_too_long'
        ]);
    }

    if (end($validated)['role'] !== 'user') {
        sendJson(400, ['error' => 'Viimeisen viestin täytyy olla käyttäjän kysymys.']);
    }

    return $validated;
}

if (!isLoggedIn()) {
    sendJson(401, ['error' => 'Istuntosi on vanhentunut. Kirjaudu sisään uudelleen.']);
}

// Tarkistaa myös, että käyttäjä on yhä olemassa tietokannassa.
requireLogin();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($action === 'models') {
    if ($method !== 'GET') {
        header('Allow: GET');
        sendJson(405, ['error' => 'Pyyntötapa ei ole sallittu.']);
    }

    // Vapautetaan istunto, jotta hidas Ollama ei lukitse muita sivupyyntöjä.
    session_write_close();

    try {
        $models = ollamaListModels();
    } catch (OllamaException $exception) {
        sendOllamaError($exception);
    }

    $default = ollamaConfig()['model'];

    sendJson(200, [
        'models' => $models,
        'default' => in_array($default, $models, true) ? $default : ($models[0] ?? null)
    ]);
}

if (!in_array($action, ['load', 'chat'], true)) {
    sendJson(404, ['error' => 'Tuntematon toiminto.']);
}

if ($method !== 'POST') {
    header('Allow: POST');
    sendJson(405, ['error' => 'Pyyntötapa ei ole sallittu.']);
}

if (!isValidCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    sendJson(403, ['error' => CSRF_ERROR_MESSAGE]);
}

session_write_close();

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($contentLength > CHAT_MAX_REQUEST_BYTES) {
    sendJson(413, [
        'error' => 'Keskustelu on liian pitkä. Aloita uusi keskustelu.',
        'code' => 'history_too_long'
    ]);
}

$rawBody = file_get_contents('php://input', false, null, 0, CHAT_MAX_REQUEST_BYTES + 1);

if (!is_string($rawBody) || $rawBody === '') {
    sendJson(400, ['error' => 'Pyyntö puuttuu.']);
}

if (strlen($rawBody) > CHAT_MAX_REQUEST_BYTES) {
    sendJson(413, [
        'error' => 'Keskustelu on liian pitkä. Aloita uusi keskustelu.',
        'code' => 'history_too_long'
    ]);
}

$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    sendJson(400, ['error' => 'Pyyntö ei ole kelvollista JSON-dataa.']);
}

// Viestit tarkistetaan ennen mallilistan hakua, koska se on halvempaa.
$messages = $action === 'chat'
    ? validateMessages($payload['messages'] ?? null)
    : [];

$model = requireInstalledModel($payload['model'] ?? ollamaConfig()['model']);

// Mallin lataus ja vastaus voivat kestää pitkään.
set_time_limit(ollamaConfig()['timeout'] + 30);

try {
    if ($action === 'load') {
        ollamaLoadModel($model);
        sendJson(200, ['loaded' => $model]);
    }

    $answer = ollamaChat($model, [
        ['role' => 'system', 'content' => CHAT_SYSTEM_PROMPT],
        ...$messages
    ]);

    sendJson(200, ['answer' => $answer]);
} catch (OllamaException $exception) {
    sendOllamaError($exception);
} catch (Throwable $exception) {
    error_log('Chat-rajapinnan virhe: ' . $exception->getMessage());
    sendJson(500, ['error' => 'Odottamaton virhe. Yritä myöhemmin uudelleen.']);
}
