<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\Audit;
use App\Services\DataExportService;
use App\Services\DataImportService;
use App\Services\ImageService;
use App\Services\SettingsService;

final class SettingsController
{
    /** GET /api/settings/public - branding for the login page (no login required). */
    public function public(Request $request): Response
    {
        return Response::ok(SettingsService::branding());
    }

    /** GET /api/settings/logo */
    public function logo(Request $request): Response
    {
        $path = SettingsService::get('logo_path');
        if (!$path || !is_file(ImageService::absolute($path))) {
            throw HttpException::notFound('Logo não configurado.');
        }
        return Response::file(ImageService::absolute($path), 'image/png', 86400);
    }

    /** GET /api/settings - editable settings (admin). */
    public function index(Request $request): Response
    {
        $all = SettingsService::all();
        return Response::ok([
            'company_name' => $all['company_name'],
            'primary_color' => $all['primary_color'],
            'logo_url' => SettingsService::branding()['logo_url'],
            'stalled_days' => (int) $all['stalled_days'],
            'event_email_mode' => $all['event_email_mode'],
            'alert_emails' => $all['alert_emails'] === '' ? [] : explode(',', $all['alert_emails']),
            'lecom_supply_form_url' => $all['lecom_supply_form_url'] ?? '',
            'lecom_portal_url' => $all['lecom_portal_url'] ?? '',
            'lecom_api_base_url' => $all['lecom_api_base_url'] ?? SettingsService::DEFAULTS['lecom_api_base_url'],
            'lecom_process_id' => (int) ($all['lecom_process_id'] ?? SettingsService::DEFAULTS['lecom_process_id']),
            'lecom_process_version' => (int) ($all['lecom_process_version'] ?? SettingsService::DEFAULTS['lecom_process_version']),
            'lecom_instance_form_url' => SettingsService::lecomFormUrl(),
            'lecom_api_start_url' => SettingsService::lecomApiStartUrl(),
            'lecom_api_x_server' => SettingsService::lecomApiServer(),
            'lecom_workspace_start_url' => SettingsService::lecomWorkspaceStartUrl(),
        ]);
    }

    /** PUT /api/settings  (any subset of the editable keys) */
    public function update(Request $request): Response
    {
        $input = $request->all();
        if (isset($input['alert_emails']) && is_array($input['alert_emails'])) {
            $input['alert_emails'] = implode(',', $input['alert_emails']);
        }
        $data = Validator::validate($input, SettingsService::EDITABLE);

        if (array_key_exists('alert_emails', $data)) {
            $emails = array_values(array_filter(array_map(
                fn ($e) => mb_strtolower(trim($e)),
                preg_split('/[,;\s]+/', (string) $data['alert_emails']) ?: []
            )));
            foreach ($emails as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw HttpException::validation(['alert_emails' => "E-mail inválido: {$email}"]);
                }
            }
            $data['alert_emails'] = implode(',', array_unique($emails));
        }
        if (isset($data['primary_color'])) {
            $data['primary_color'] = strtoupper($data['primary_color']);
        }
        foreach (['lecom_portal_url', 'lecom_api_base_url'] as $urlField) {
            if (array_key_exists($urlField, $data) && $data[$urlField] !== null && $data[$urlField] !== '') {
                $value = trim((string) $data[$urlField]);
                $parsed = parse_url($value);
                if ($parsed === false || !in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true) || empty($parsed['host'])) {
                    throw HttpException::validation([$urlField => 'Informe uma URL válida iniciando com http:// ou https://.']);
                }
                $data[$urlField] = rtrim($value, '/');
            }
        }
        SettingsService::set($data);
        return $this->index($request);
    }

    /** POST multipart/form-data with field "logo" (PNG/JPG/WEBP). */
    public function uploadLogo(Request $request): Response
    {
        $file = $request->file('logo');
        if ($file === null) {
            throw HttpException::validation(['logo' => 'Selecione uma imagem.']);
        }
        $old = SettingsService::get('logo_path');
        $path = ImageService::storeLogo($file);
        SettingsService::set(['logo_path' => $path]);
        ImageService::delete($old);
        return $this->index($request);
    }

    public function deleteLogo(Request $request): Response
    {
        $old = SettingsService::get('logo_path');
        if ($old) {
            SettingsService::set(['logo_path' => '']);
            ImageService::delete($old);
        }
        return $this->index($request);
    }

    /** POST /api/admin/reset-data - keeps access roles/users, clears all operational data. */
    public function resetData(Request $request): Response
    {
        if (!\App\Core\Auth::isAdmin()) {
            throw HttpException::forbidden('Apenas o Administrador pode zerar os dados.', 'ADMIN_ONLY');
        }
        \App\Support\Installer::resetVercelDemoToBlank(true);
        Audit::log('reset_data', 'system', null, null, ['scope' => 'operational'], 'Zerado pelo administrador');
        return Response::ok(['message' => 'Dados operacionais zerados com sucesso.']);
    }

    public function backup(Request $request): Response
    {
        $path = \App\Services\BackupService::create();
        return Response::file($path, 'application/sql', 0, basename($path));
    }

    public function exportAll(Request $request): Response
    {
        return DataExportService::exportAll();
    }

    public function previewImportAll(Request $request): Response
    {
        return Response::ok(DataImportService::preview($request->file('file') ?? []));
    }

    public function importAll(Request $request): Response
    {
        return Response::ok(DataImportService::import($request->file('file') ?? []));
    }
}
