<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Db;
use Throwable;

final class SettingsService
{
    public const DEFAULTS = [
        'company_name' => 'Controle de Brindes',
        'primary_color' => '#2563EB',
        'logo_path' => '',
        'stalled_days' => '3',
        'event_email_mode' => 'por_retirada',
        'alert_emails' => '',
        // URL template configured by the administrator; placeholders are resolved on the item page.
        'lecom_supply_form_url' => '',
        // Lecom process settings used by the "Abrir chamado" flow.
        // Homologation portal found in the local Lecom library; replace in Settings for production.
        'lecom_portal_url' => 'https://cp-hom.grupoemefarma.com.br',
        'lecom_api_base_url' => 'https://api.lecom.com.br/service/bpm/api',
        'lecom_process_id' => '26',
        'lecom_process_version' => '10',
    ];

    /** Keys editable through PUT /api/settings with their validation rules. */
    public const EDITABLE = [
        'company_name' => 'sometimes|required|string|max:100',
        'primary_color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        'stalled_days' => 'sometimes|required|int|min:1|max:60',
        'event_email_mode' => 'sometimes|required|in:por_retirada,consolidado',
        'alert_emails' => 'sometimes|nullable|string|max:500',
        'lecom_supply_form_url' => 'sometimes|nullable|string|max:500',
        'lecom_portal_url' => 'sometimes|nullable|string|max:500',
        'lecom_api_base_url' => 'sometimes|nullable|string|max:500',
        'lecom_process_id' => 'sometimes|required|int|min:1|max:999999',
        'lecom_process_version' => 'sometimes|required|int|min:1|max:999999',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            try {
                $rows = Db::fetchAll('SELECT `key`, value FROM settings');
                self::$cache = self::DEFAULTS;
                foreach ($rows as $row) {
                    self::$cache[$row['key']] = (string) $row['value'];
                }
            } catch (Throwable) {
                return self::DEFAULTS; // DB not installed yet: render with defaults
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::all()[$key] ?? $default;
    }

    /** Normalized base URL of the Lecom portal, or null when not configured. */
    public static function lecomPortalUrl(): ?string
    {
        $value = trim((string) self::get('lecom_portal_url', ''));
        return $value === '' ? null : rtrim($value, '/');
    }

    /** Normalized base URL of the Lecom API documented in the local library. */
    public static function lecomApiBaseUrl(): string
    {
        $value = trim((string) self::get('lecom_api_base_url', self::DEFAULTS['lecom_api_base_url']));
        return rtrim($value !== '' ? $value : self::DEFAULTS['lecom_api_base_url'], '/');
    }

    /** URL for the published Lecom form that lets the user start the process. */
    public static function lecomFormUrl(?int $processId = null, ?int $version = null): ?string
    {
        $portal = self::lecomPortalUrl();
        if ($portal === null) {
            return null;
        }
        $processId ??= (int) self::get('lecom_process_id', self::DEFAULTS['lecom_process_id']);
        $version ??= (int) self::get('lecom_process_version', self::DEFAULTS['lecom_process_version']);
        return $portal . '/form-web/?' . http_build_query([
            'processId' => $processId,
            'version' => $version,
            'newWS' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** API endpoint used to create a process instance, without exposing credentials. */
    public static function lecomApiStartUrl(): string
    {
        return self::lecomApiBaseUrl() . '/v1/process-instances';
    }

    /** Workspace endpoint used by the local Lecom scripts with the portal SSO ticket. */
    public static function lecomWorkspaceStartUrl(?int $processId = null, ?int $version = null): ?string
    {
        $portal = self::lecomPortalUrl();
        if ($portal === null) {
            return null;
        }
        $processId ??= (int) self::get('lecom_process_id', self::DEFAULTS['lecom_process_id']);
        $version ??= (int) self::get('lecom_process_version', self::DEFAULTS['lecom_process_version']);
        return $portal . '/workspace/api/process/start?' . http_build_query([
            'processId' => $processId,
            'version' => $version,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** X-Server value required by the Lecom API, derived from the portal host. */
    public static function lecomApiServer(): ?string
    {
        $portal = self::lecomPortalUrl();
        $host = $portal !== null ? parse_url($portal, PHP_URL_HOST) : null;
        return is_string($host) && $host !== '' ? $host : null;
    }

    /** Branding values used by every page and the login screen. */
    public static function branding(): array
    {
        $all = self::all();
        $logo = $all['logo_path'] ?? '';
        return [
            'company_name' => $all['company_name'],
            'primary_color' => $all['primary_color'],
            'logo_url' => $logo !== '' ? url('/api/settings/logo') . '?v=' . substr(md5($logo), 0, 8) : null,
        ];
    }

    public static function set(array $values): void
    {
        $before = self::all();
        Db::transaction(function () use ($values, $before) {
            foreach ($values as $key => $value) {
                $text = $value === null ? '' : (string) $value;
                if (Db::isSqlite()) {
                    Db::query(
                        'INSERT INTO settings (`key`, value, updated_by) VALUES (?, ?, ?)
                         ON CONFLICT(`key`) DO UPDATE SET value = excluded.value, updated_by = excluded.updated_by',
                        [$key, $text, Auth::id()]
                    );
                } else {
                    Db::query(
                        'INSERT INTO settings (`key`, value, updated_by) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE value = ?, updated_by = ?',
                        [$key, $text, Auth::id(), $text, Auth::id()]
                    );
                }
            }
            $after = array_map(fn ($v) => $v === null ? '' : (string) $v, $values);
            [$old, $new] = Audit::diff(array_intersect_key($before, $after), $after);
            if ($new !== []) {
                Audit::log('settings_update', 'settings', null, $old, $new, 'Configurações');
            }
        });
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
