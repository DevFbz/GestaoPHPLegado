<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Log;
use JsonException;

final class LecomService
{
    /**
     * Creates an instance through the server-to-server API and returns the
     * workspace form URL. The apikey never leaves this process.
     *
     * @return array{process_instance_id:string, activity_instance_id:string, cycle:string, url:string}
     */
    public static function startConfiguredProcess(): array
    {
        $apiKey = trim((string) Config::get('lecom.api_key', ''));
        if ($apiKey === '') {
            throw new HttpException(
                503,
                'LECOM_API_NOT_CONFIGURED',
                'A integração com o Lecom ainda não foi configurada. Peça ao administrador para informar LECOM_API_KEY no .env.'
            );
        }

        $portal = SettingsService::lecomPortalUrl();
        $server = SettingsService::lecomApiServer();
        if ($portal === null || $server === null) {
            throw new HttpException(503, 'LECOM_PORTAL_NOT_CONFIGURED', 'O endereço do portal Lecom ainda não foi configurado.');
        }

        $processId = (int) SettingsService::get('lecom_process_id', SettingsService::DEFAULTS['lecom_process_id']);
        $version = (int) SettingsService::get('lecom_process_version', SettingsService::DEFAULTS['lecom_process_version']);
        if ($processId < 1 || $version < 1) {
            throw new HttpException(503, 'LECOM_PROCESS_NOT_CONFIGURED', 'O processo Lecom configurado é inválido.');
        }

        $response = self::post(SettingsService::lecomApiStartUrl(), [
            'processId' => $processId,
            'version' => $version,
            'language' => 'pt_BR',
        ], $apiKey, $server);

        $processInstanceId = self::findValue($response, 'processInstanceId');
        if ($processInstanceId === null) {
            Log::error('Lecom start returned no process instance id', [
                'http_status' => $response['_http_status'] ?? 0,
                'process_id' => $processId,
                'version' => $version,
            ]);
            throw new HttpException(502, 'LECOM_INVALID_RESPONSE', 'O Lecom não retornou o identificador da instância.');
        }

        $activityInstanceId = self::findValue($response, 'activityInstanceId') ?? '1';
        $cycle = self::findValue($response, 'cycle') ?? '1';
        $formUrl = $portal . '/workspace/form-app/'
            . rawurlencode($processInstanceId) . '/'
            . rawurlencode($activityInstanceId) . '/'
            . rawurlencode($cycle) . '?isNewForm=true';

        return [
            'process_instance_id' => $processInstanceId,
            'activity_instance_id' => $activityInstanceId,
            'cycle' => $cycle,
            'url' => $formUrl,
        ];
    }

    private static function post(string $url, array $payload, string $apiKey, string $server): array
    {
        if (!function_exists('curl_init')) {
            throw new HttpException(503, 'LECOM_HTTP_NOT_AVAILABLE', 'A extensão HTTP necessária para acessar o Lecom não está disponível no servidor.');
        }

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $e) {
            Log::error('Lecom request encoding failed', ['error' => $e->getMessage()]);
            throw new HttpException(502, 'LECOM_REQUEST_FAILED', 'Não foi possível preparar a chamada ao Lecom.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new HttpException(502, 'LECOM_REQUEST_FAILED', 'Não foi possível iniciar a chamada ao Lecom.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'apikey: ' . $apiKey,
                'X-Server: ' . $server,
                ...self::testHeaders(),
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => max(1, (int) Config::get('lecom.connect_timeout', 8)),
            CURLOPT_TIMEOUT => max(2, (int) Config::get('lecom.timeout', 20)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($handle);
        $curlError = curl_error($handle);
        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        // curl_close() is a deprecated no-op in PHP 8.5. The application's
        // error handler promotes deprecations to exceptions, so only call it
        // on PHP versions where it still performs cleanup.
        if (PHP_VERSION_ID < 80500) {
            curl_close($handle);
        }

        if ($raw === false) {
            Log::error('Lecom request failed', [
                'http_status' => $httpStatus,
                'curl_error' => $curlError,
            ]);
            throw new HttpException(502, 'LECOM_UNAVAILABLE', 'Não foi possível conectar ao Lecom. Tente novamente em instantes.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Log::error('Lecom returned invalid JSON', ['http_status' => $httpStatus]);
            throw new HttpException(502, 'LECOM_INVALID_RESPONSE', 'O Lecom retornou uma resposta inválida.');
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $remoteMessage = self::findRemoteMessage($decoded);
            Log::error('Lecom start rejected', [
                'http_status' => $httpStatus,
                'error_code' => is_scalar($decoded['code'] ?? null) ? (string) $decoded['code'] : null,
                'remote_message' => $remoteMessage,
            ]);
            if ($httpStatus === 403) {
                throw new HttpException(
                    502,
                    'LECOM_FORBIDDEN',
                    'O Lecom recusou a chave ou a permissão para este ambiente. Confirme a API key de homologação e a permissão para abrir o processo configurado.'
                );
            }
            throw new HttpException(502, 'LECOM_START_FAILED', 'O Lecom recusou a abertura do chamado. Confira o processo publicado e as credenciais da API.');
        }

        $decoded['_http_status'] = $httpStatus;
        return $decoded;
    }

    /** @return list<string> */
    private static function testHeaders(): array
    {
        if (!(bool) Config::get('lecom.test_mode', false)) {
            return [];
        }

        $headers = ['test-mode: true'];
        $testUser = trim((string) Config::get('lecom.test_user', ''));
        if ($testUser !== '' && ctype_digit($testUser)) {
            $headers[] = 'test-user: ' . $testUser;
        }
        return $headers;
    }

    private static function findRemoteMessage(array $data): ?string
    {
        foreach (['message', 'error_description', 'detail', 'error'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $message = trim((string) $data[$key]);
                if ($message !== '') {
                    return substr($message, 0, 300);
                }
            }
        }
        return null;
    }

    private static function findValue(array $data, string $key): ?string
    {
        if (array_key_exists($key, $data) && (is_int($data[$key]) || is_string($data[$key])) && (string) $data[$key] !== '') {
            return (string) $data[$key];
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::findValue($value, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
}
