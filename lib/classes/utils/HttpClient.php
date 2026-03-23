<?php
/**
 * HttpClient avanzato con cURL
 * - Supporta: GET, POST, PUT, PATCH, DELETE
 * - Body: JSON o form-url-encoded
 * - Query string separata dal body
 * - Header personalizzati
 * - Auth: Bearer, Basic
 * - Timeout, SSL verify, cattura header risposta
 * - Parsing automatico JSON di risposta
 */
class HttpClient
{
    private string $ip;
    private int $port;
    private string $endpoint;
    private string $method;
    private array $query = [];
    private array|string|null $body = null;
    private array $headers = [];
    private ?string $bearerToken = null;
    private ?array $basicAuth = null; // [username, password]
    private int $timeout = 30; // sec
    private bool $sslVerify = true;
    private string $scheme = 'http'; // 'http'|'https'
    private bool $sendJson = false;

    public function __construct(
        string $ip,
        int $port = 80,
        string $endpoint = '',
        string $method = 'GET'
    ) {
        $this->ip = $ip;
        $this->port = $port;
        $this->endpoint = ltrim($endpoint, '/');
        $this->method = strtoupper($method);
        $this->scheme = ($port === 443) ? 'https' : 'http';
    }

    // ---------- Configurazione fluente ----------
    public function setMethod(string $method): self { $this->method = strtoupper($method); return $this; }
    public function setEndpoint(string $endpoint): self { $this->endpoint = ltrim($endpoint, '/'); return $this; }
    public function setQuery(array $query): self { $this->query = $query; return $this; }
    public function addQuery(array $query): self { $this->query = array_merge($this->query, $query); return $this; }
    public function setBody(array|string|null $body): self { $this->body = $body; return $this; }
    public function sendAsJson(bool $yes = true): self { $this->sendJson = $yes; return $this; }
    public function setHeaders(array $headers): self { $this->headers = $headers; return $this; }
    public function addHeaders(array $headers): self { $this->headers = array_merge($this->headers, $headers); return $this; }
    public function setBearerToken(string $token): self { $this->bearerToken = $token; return $this; }
    public function setBasicAuth(string $username, string $password): self { $this->basicAuth = [$username, $password]; return $this; }
    public function setTimeout(int $seconds): self { $this->timeout = $seconds; return $this; }
    public function verifySSL(bool $verify = true): self { $this->sslVerify = $verify; return $this; }
    public function setScheme(string $scheme): self { $this->scheme = $scheme === 'https' ? 'https' : 'http'; return $this; }

    // ---------- Invio ----------
    public function send(): array
    {
        $url = $this->buildUrl();

        $ch = curl_init();
        $curlHeaders = $this->prepareHeaders();

        // Metodo + body
        $method = strtoupper($this->method);
        $encodedBody = $this->encodeBody();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_HEADER         => true, // per catturare header risposta
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Anche DELETE/PATCH possono avere body
            if ($encodedBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
            }
        }

        // SSL
        if ($this->scheme === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);
        }

        // Auth
        if ($this->bearerToken) {
            // già aggiunto in header, qui nulla
        }
        if ($this->basicAuth) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->basicAuth[0] . ':' . $this->basicAuth[1]);
        }

        $start = microtime(true);
        $raw = curl_exec($ch);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        $info = curl_getinfo($ch);
        curl_close($ch);

        // Se fallisce a livello di cURL
        if ($raw === false) {
            return [
                'ok' => false,
                'http_code' => $info['http_code'] ?? 0,
                'error' => $error,
                'errno' => $errno,
                'duration_ms' => $durationMs,
                'info' => $info,
            ];
        }

        // Separa header e body
        $headerSize = $info['header_size'] ?? 0;
        $rawHeaders = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $responseHeaders = $this->parseResponseHeaders($rawHeaders);

        // Prova decode JSON se content-type lo indica
        $contentType = $responseHeaders['content-type'][0] ?? '';
        $json = null;
        if (stripos($contentType, 'application/json') !== false) {
            $json = json_decode($body, true);
        }

        return [
            'ok' => ($info['http_code'] >= 200 && $info['http_code'] < 300),
            'http_code' => $info['http_code'],
            'headers' => $responseHeaders,
            'body' => $body,
            'json' => $json, // null se non JSON o se decode fallisce
            'duration_ms' => $durationMs,
            'info' => $info,
            'error' => $error ?: null,
        ];
    }

    // ---------- Helpers ----------
    private function buildUrl(): string
    {
        $qs = $this->query ? '?' . http_build_query($this->query) : '';
        return "{$this->scheme}://{$this->ip}:{$this->port}/{$this->endpoint}{$qs}";
    }

    private function prepareHeaders(): array
    {
        $headers = [];

        // Content-Type
        if ($this->sendJson) {
            $headers['Content-Type'] = 'application/json';
        } else {
            // Se inviamo form o niente body, lasciamo form di default solo se body presente.
            if ($this->body !== null && is_array($this->body)) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        }

        // Accept
        if (!isset($headers['Accept'])) {
            $headers['Accept'] = 'application/json, */*;q=0.9';
        }

        // Bearer
        if ($this->bearerToken) {
            $headers['Authorization'] = 'Bearer ' . $this->bearerToken;
        }

        // Merge con custom
        $headers = array_merge($headers, $this->headers);

        // Normalizza in array "Header: value"
        $out = [];
        foreach ($headers as $k => $v) {
            // se l'utente passa già "Header: value", lascialo passare
            if (is_int($k)) {
                $out[] = $v;
            } else {
                $out[] = "{$k}: {$v}";
            }
        }
        return $out;
    }

    private function encodeBody(): string|array|null
    {
        if ($this->body === null) return null;

        if ($this->sendJson) {
            // Consente body già stringa JSON
            if (is_string($this->body)) return $this->body;
            return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Form o stringa raw
        if (is_array($this->body)) {
            return http_build_query($this->body);
        }
        if (is_string($this->body)) {
            return $this->body; // raw (es. XML, testo, ecc.)
        }
        return null;
    }

    private function parseResponseHeaders(string $raw): array
    {
        // Gestisce eventuali redirect (più blocchi header)
        $blocks = preg_split("/\r\n\r\n/", trim($raw));
        $last = end($blocks);

        $lines = preg_split("/\r\n/", $last);
        $headers = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                $headers[':status-line'] = [$line];
                continue;
            }
            if (strpos($line, ':') !== false) {
                [$key, $val] = explode(':', $line, 2);
                $key = strtolower(trim($key));
                $val = trim($val);
                $headers[$key] = $headers[$key] ?? [];
                $headers[$key][] = $val;
            }
        }
        return $headers;
    }
}
