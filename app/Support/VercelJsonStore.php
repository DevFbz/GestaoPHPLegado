<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/** Private, versioned JSON snapshots in Vercel Blob. */
final class VercelJsonStore
{
    private const PREFIX = 'brindes-json/v1/';
    private const API = 'https://blob.vercel-storage.com/';

    /** @var null|callable(string,string,string,?string,list<string>,int):array{code:int,body:string} */
    private $transport;
    private ?string $storeHost = null;

    /** @param null|callable(string,string,string,?string,list<string>,int):array{code:int,body:string} $transport */
    public function __construct(private readonly string $token, ?callable $transport = null, ?string $storeId = null)
    {
        $this->transport = $transport;
        if ($token !== '' && ($storeId === null || trim($storeId) === '')) {
            throw new RuntimeException('BLOB_STORE_ID é obrigatório para fixar o storage privado.');
        }
        if ($storeId !== null && trim($storeId) !== '') {
            $normalizedStoreId = preg_replace('/^store_/i', '', trim($storeId)) ?? '';
            if ($normalizedStoreId === '' || preg_match('/^[a-z0-9-]+$/i', $normalizedStoreId) !== 1) {
                throw new RuntimeException('BLOB_STORE_ID inválido.');
            }
            $this->storeHost = strtolower($normalizedStoreId) . '.private.blob.vercel-storage.com';
        }
    }

    /** @param array<string,array<string,mixed>> $documents */
    public function save(array $documents, ?string $expectedRevision = null): string
    {
        $this->assertToken();
        if ($documents === []) {
            throw new RuntimeException('O snapshot JSON não pode estar vazio.');
        }

        $revision = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(8));
        $previous = $this->currentManifest();
        $currentRevision = is_array($previous) ? (string) ($previous['revision'] ?? '') : null;
        if ($currentRevision !== $expectedRevision) {
            throw new JsonSnapshotConflict('Snapshot JSON mudou durante a operação; recarregue antes de tentar novamente.', 409);
        }
        $etag = is_array($previous) ? (string) ($previous['_etag'] ?? '') : '';
        if ($previous !== null && $etag === '') {
            throw new RuntimeException('Manifesto JSON sem ETag; gravação interrompida para evitar perda concorrente.');
        }
        $files = is_array($previous['files'] ?? null) ? $previous['files'] : [];
        foreach ($documents as $name => $document) {
            if (!preg_match('/^[a-z0-9_-]+\.json$/', $name)) {
                throw new RuntimeException('Nome de arquivo JSON inválido.');
            }
            $pathname = self::PREFIX . 'snapshots/' . $revision . '/' . $name;
            $body = json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            $result = $this->put($pathname, $body, false);
            $files[$name] = $result['url'];
        }

        // Publish the pointer last: readers see either the previous complete
        // snapshot or this complete set, never a half-uploaded group.
        $manifest = [
            'schema_version' => 1,
            'revision' => $revision,
            'created_at' => gmdate(DATE_ATOM),
            'files' => $files,
        ];
        $this->put(self::PREFIX . 'current.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n", $previous !== null, $etag !== '' ? $etag : null);
        return $revision;
    }

    /** @return array<string,array<string,mixed>>|null */
    public function load(): ?array
    {
        $this->assertToken();
        $manifest = $this->currentManifest();
        if ($manifest === null) {
            return null;
        }
        $documents = [];
        foreach ($manifest['files'] as $name => $url) {
            if (!is_string($name) || !preg_match('/^[a-z0-9_-]+\.json$/', $name) || !is_string($url)) {
                throw new RuntimeException('Manifesto JSON contém referência inválida.');
            }
            $response = $this->request('GET', $this->safeBlobUrl($url), null, [], 20);
            $decoded = json_decode($response['body'], true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Documento JSON persistido inválido: ' . $name);
            }
            $documents[$name] = $decoded;
        }
        return $documents;
    }

    public function hasSnapshot(): bool
    {
        $this->assertToken();
        return $this->currentManifest() !== null;
    }

    /** @return array{revision:string,documents:?array<string,array<string,mixed>>}|null */
    public function loadSnapshot(?string $knownRevision = null): ?array
    {
        $this->assertToken();
        $manifest = $this->currentManifest();
        if ($manifest === null) {
            return null;
        }
        $revision = (string) ($manifest['revision'] ?? '');
        if ($revision === '') {
            throw new RuntimeException('Manifesto JSON sem revisão.');
        }
        if ($knownRevision !== null && hash_equals($revision, $knownRevision)) {
            return ['revision' => $revision, 'documents' => null];
        }

        $documents = [];
        foreach ($manifest['files'] as $name => $url) {
            $response = $this->request('GET', $this->safeBlobUrl($url), null, [], 20);
            $decoded = json_decode($response['body'], true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Documento JSON persistido inválido: ' . $name);
            }
            $documents[$name] = $decoded;
        }
        return ['revision' => $revision, 'documents' => $documents];
    }

    private function currentManifest(): ?array
    {
        $manifestBlob = $this->findExact(self::PREFIX . 'current.json');
        if ($manifestBlob === null) {
            return null;
        }
        $manifestUrl = $this->safeBlobUrl((string) ($manifestBlob['url'] ?? ''));
        $response = $this->request('GET', $manifestUrl, null, [], 15);
        $manifest = json_decode($response['body'], true);
        if (!is_array($manifest) || (int) ($manifest['schema_version'] ?? 0) !== 1 || !is_array($manifest['files'] ?? null)) {
            throw new RuntimeException('Manifesto JSON persistido inválido.');
        }
        $revision = (string) ($manifest['revision'] ?? '');
        if (!preg_match('/^\\d{8}T\\d{6}Z-[a-f0-9]{16}$/D', $revision)) {
            throw new RuntimeException('Revisão inválida no manifesto JSON.');
        }
        foreach ($manifest['files'] as $name => $url) {
            if (!is_string($name) || !preg_match('/^[a-z0-9_-]+\\.json$/', $name) || !is_string($url)) {
                throw new RuntimeException('Manifesto JSON contém referência inválida.');
            }
            $safeUrl = $this->safeBlobUrl($url);
            $path = rawurldecode((string) (parse_url($safeUrl, PHP_URL_PATH) ?? ''));
            $prefix = '/' . self::PREFIX . 'snapshots/';
            $relative = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : '';
            $segments = explode('/', $relative);
            if (count($segments) !== 2 || !preg_match('/^\\d{8}T\\d{6}Z-[a-f0-9]{16}$/D', $segments[0]) || $segments[1] !== $name) {
                throw new RuntimeException('Documento JSON fora do caminho versionado esperado.');
            }
            $manifest['files'][$name] = $safeUrl;
        }
        $manifestEtag = (string) ($manifestBlob['etag'] ?? $manifestBlob['eTag'] ?? '');
        $manifest['_etag'] = $manifestEtag !== '' ? $manifestEtag : $this->headEtag($manifestUrl);
        return $manifest;
    }

    private function headEtag(string $url): string
    {
        $response = $this->request(
            'GET',
            self::API . '?' . http_build_query(['url' => $url]),
            null,
            ['x-api-version: 12'],
            10,
        );
        $metadata = json_decode($response['body'], true);
        $etag = is_array($metadata) ? trim((string) ($metadata['etag'] ?? '')) : '';
        return $etag !== '' ? $etag : $this->headEtagFallback($url);
    }

    private function headEtagFallback(string $url): string
    {
        $headers = ['x-api-version: 12', 'Authorization: Bearer ' . $this->token];
        if ($this->transport !== null) {
            $response = ($this->transport)('HEAD', $url, $this->token, null, $headers, 10);
            $status = (int) ($response['code'] ?? 0);
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('Não foi possível obter ETag do manifesto JSON (HTTP ' . $status . ').');
            }
            $etag = $this->extractEtag((array) ($response['headers'] ?? []));
        } elseif (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'HEAD',
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $rawHeaders = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (PHP_VERSION_ID < 80500) {
                curl_close($ch);
            }
            if (!is_string($rawHeaders) || $status < 200 || $status >= 300) {
                throw new RuntimeException('Não foi possível obter ETag do manifesto JSON (HTTP ' . $status . ').');
            }
            $etag = $this->extractEtag(preg_split('/\\r?\\n/', $rawHeaders) ?: []);
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'HEAD',
                'header' => implode("\\r\\n", $headers),
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => 0,
            ], 'ssl' => ['verify_peer' => true]]);
            @file_get_contents($url, false, $context);
            $status = 0;
            foreach ($http_response_header ?? [] as $line) {
                if ($status === 0 && preg_match('/\\s(\\d{3})\\s/', $line, $match)) {
                    $status = (int) $match[1];
                }
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('Não foi possível obter ETag do manifesto JSON (HTTP ' . $status . ').');
            }
            $etag = $this->extractEtag($http_response_header ?? []);
        }

        if ($etag === '') {
            throw new RuntimeException('O storage não retornou ETag; gravação interrompida para evitar perda concorrente.');
        }
        return $etag;
    }

    /** @param array<mixed> $headers */
    private function extractEtag(array $headers): string
    {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === 'etag') {
                return trim((string) $value);
            }
            if (is_string($value) && preg_match('/^etag:\\s*(.+)$/i', $value, $match)) {
                return trim($match[1]);
            }
        }
        return '';
    }

    private function put(string $pathname, string $body, bool $overwrite, ?string $ifMatch = null): array
    {
        $headers = [
            'x-api-version: 12',
            'x-vercel-blob-access: private',
            'x-add-random-suffix: 0',
            'x-allow-overwrite: ' . ($overwrite ? '1' : '0'),
            'x-content-type: application/json',
            'Content-Type: application/json',
        ];
        if ($ifMatch !== null) {
            $headers[] = 'x-if-match: ' . $ifMatch;
        }
        $response = $this->request('PUT', self::API . '?' . http_build_query(['pathname' => $pathname]), $body, $headers, 25);
        $result = json_decode($response['body'], true);
        if (!is_array($result) || !is_string($result['url'] ?? null)) {
            throw new RuntimeException('O storage não confirmou o upload JSON.');
        }
        $result['url'] = $this->safeBlobUrl($result['url']);
        return $result;
    }

    private function findExact(string $pathname): ?array
    {
        $url = self::API . '?' . http_build_query(['prefix' => $pathname]);
        $response = $this->request('GET', $url, null, ['x-api-version: 12'], 12);
        $result = json_decode($response['body'], true);
        if (!is_array($result) || !is_array($result['blobs'] ?? null)) {
            throw new RuntimeException('O storage retornou uma listagem inválida.');
        }
        foreach ($result['blobs'] as $blob) {
            if (is_array($blob) && ($blob['pathname'] ?? null) === $pathname) {
                return $blob;
            }
        }
        return null;
    }

    private function safeBlobUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $suffix = '.private.blob.vercel-storage.com';
        $storeId = str_ends_with($host, $suffix) ? substr($host, 0, -strlen($suffix)) : '';
        $validPrivateHost = $storeId !== '' && !str_contains($storeId, '.') && preg_match('/^[a-z0-9_-]+$/', $storeId) === 1;
        if (($parts['scheme'] ?? '') !== 'https' || !$validPrivateHost) {
            throw new RuntimeException('URL de Blob privada inválida no manifesto JSON.');
        }
        if ($this->storeHost === null) {
            $this->storeHost = $host;
        } elseif ($this->storeHost !== $host) {
            throw new RuntimeException('URL de Blob pertence a outro storage; acesso recusado.');
        }
        return $url;
    }

    /** @param list<string> $headers @return array{code:int,body:string} */
    private function request(string $method, string $url, ?string $body, array $headers, int $timeout): array
    {
        $headers[] = 'Authorization: Bearer ' . $this->token;
        if ($this->transport !== null) {
            $response = ($this->transport)($method, $url, $this->token, $body, $headers, $timeout);
            $status = (int) ($response['code'] ?? 0);
            if (in_array($status, [409, 412], true)) {
                throw new JsonSnapshotConflict('Snapshot JSON concorrente; a gravação não foi publicada.', $status);
            }
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('Falha de persistência JSON no Blob (HTTP ' . $status . ').');
            }
            return [
                'code' => $status,
                'body' => (string) ($response['body'] ?? ''),
                'headers' => (array) ($response['headers'] ?? []),
            ];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => false,
            ];
            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
            curl_setopt_array($ch, $options);
            $responseBody = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (PHP_VERSION_ID < 80500) {
                curl_close($ch);
            }
            if (in_array($status, [409, 412], true)) {
                throw new JsonSnapshotConflict('Snapshot JSON concorrente; a gravação não foi publicada.', $status);
            }
            if (!is_string($responseBody) || $status < 200 || $status >= 300) {
                throw new RuntimeException('Falha de persistência JSON no Blob (HTTP ' . $status . ').');
            }
            return ['code' => $status, 'body' => $responseBody];
        }

        $http = [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
        ];
        if ($body !== null) {
            $http['content'] = $body;
        }
        $responseBody = @file_get_contents($url, false, stream_context_create(['http' => $http, 'ssl' => ['verify_peer' => true]]));
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
            $status = (int) $match[1];
        }
        if (in_array($status, [409, 412], true)) {
            throw new JsonSnapshotConflict('Snapshot JSON concorrente; a gravação não foi publicada.', $status);
        }
        if (!is_string($responseBody) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Falha de persistência JSON no Blob (HTTP ' . $status . ').');
        }
        return ['code' => $status, 'body' => $responseBody];
    }

    private function assertToken(): void
    {
        if ($this->token === '') {
            throw new RuntimeException('Persistência JSON requer a credencial do storage privado.');
        }
    }
}
