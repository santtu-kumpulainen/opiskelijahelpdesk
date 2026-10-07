<?php

/*
 * Ollama-kielimallin asetukset ja HTTP-kutsut.
 *
 * Asetukset luetaan ympäristömuuttujista:
 *  - OLLAMA_URL      Ollaman osoite (oletus http://127.0.0.1:11434)
 *  - OLLAMA_MODEL    oletusmalli (oletus jobautomation/OpenEuroLLM-Finnish:latest)
 *  - OLLAMA_TIMEOUT  vastauksen enimmäisodotus sekunteina (oletus 180)
 *
 * Docker-kontin sisällä 127.0.0.1 tarkoittaa konttia itseään,
 * joten docker-compose.yaml asettaa OLLAMA_URL-osoitteen isäntäkoneeseen.
 */

final class OllamaException extends RuntimeException
{
    /*
     * Viesti näytetään käyttäjälle. Tekninen yksityiskohta
     * kirjataan vain palvelimen lokiin.
     */
    public function __construct(
        string $userMessage,
        public readonly int $httpStatus = 502,
        public readonly string $logDetail = ''
    ) {
        parent::__construct($userMessage);
    }
}

function ollamaConfig(): array
{
    $url = getenv('OLLAMA_URL');
    $model = getenv('OLLAMA_MODEL');
    $timeout = filter_var(
        getenv('OLLAMA_TIMEOUT'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 5, 'max_range' => 600]]
    );

    return [
        'url' => rtrim(is_string($url) && $url !== '' ? $url : 'http://127.0.0.1:11434', '/'),
        'model' => is_string($model) && $model !== '' ? $model : 'jobautomation/OpenEuroLLM-Finnish:latest',
        'timeout' => $timeout === false ? 180 : $timeout
    ];
}

function ollamaRequest(string $method, string $path, ?array $payload, int $timeout): array
{
    $curl = curl_init(ollamaConfig()['url'] . $path);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ];

    if ($payload !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    curl_setopt_array($curl, $options);

    $body = curl_exec($curl);
    $errorNumber = curl_errno($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

    if ($errorNumber === CURLE_OPERATION_TIMEDOUT) {
        throw new OllamaException(
            'Kielimalli ei vastannut ajoissa. Yritä hetken kuluttua uudelleen.',
            504,
            "aikakatkaisu $path: $error"
        );
    }

    if ($errorNumber !== 0 || !is_string($body)) {
        throw new OllamaException(
            'Kielimallipalveluun ei saatu yhteyttä. Yritä myöhemmin uudelleen.',
            503,
            "yhteysvirhe $path: $error"
        );
    }

    $data = json_decode($body, true);

    if ($status >= 400) {
        $detail = is_array($data) && is_string($data['error'] ?? null)
            ? $data['error']
            : mb_substr($body, 0, 300);

        throw new OllamaException(
            $status === 404
                ? 'Valittua mallia ei löytynyt kielimallipalvelusta.'
                : 'Kielimalli palautti virheen. Yritä hetken kuluttua uudelleen.',
            502,
            "HTTP $status $path: $detail"
        );
    }

    if (!is_array($data)) {
        throw new OllamaException(
            'Kielimallin vastaus oli virheellinen.',
            502,
            "virheellinen JSON $path"
        );
    }

    return $data;
}

/*
 * Palauttaa Ollamaan asennettujen mallien nimet.
 */
function ollamaListModels(): array
{
    $data = ollamaRequest('GET', '/api/tags', null, 10);
    $models = [];

    foreach ($data['models'] ?? [] as $item) {
        if (is_array($item) && is_string($item['name'] ?? null)) {
            $models[] = $item['name'];
        }
    }

    $models = array_values(array_unique($models));
    sort($models, SORT_NATURAL | SORT_FLAG_CASE);

    return $models;
}

/*
 * Ollama lataa mallin muistiin, kun chat-pyynnössä ei ole viestejä.
 */
function ollamaLoadModel(string $model): void
{
    ollamaRequest(
        'POST',
        '/api/chat',
        ['model' => $model, 'messages' => [], 'stream' => false],
        ollamaConfig()['timeout']
    );
}

function ollamaChat(string $model, array $messages): string
{
    $data = ollamaRequest(
        'POST',
        '/api/chat',
        ['model' => $model, 'messages' => $messages, 'stream' => false],
        ollamaConfig()['timeout']
    );

    $content = $data['message']['content'] ?? null;

    if (!is_string($content) || trim($content) === '') {
        throw new OllamaException(
            'Kielimallin vastauksesta puuttui sisältö.',
            502,
            'tyhjä vastaus'
        );
    }

    return $content;
}
