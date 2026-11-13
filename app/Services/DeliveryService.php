<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Present;
use Throwable;

final class DeliveryService
{
    public const SELECT = 'SELECT d.id, d.code, d.type, d.request_id, d.event_id, d.industry_id,
            d.delivered_by, d.received_by_name, d.received_by_document, d.received_by_email, d.received_by_phone,
            d.signature_path, d.pdf_path, d.verification_hash, d.notes, d.created_at,
            (d.signature_path IS NOT NULL OR d.signature_png IS NOT NULL) AS has_signature,
            u.name AS delivered_by_name, i.name AS industry_name, i.contact_email AS industry_email, r.code AS request_code, e.name AS event_name';
    public const FROM = 'FROM deliveries d
            JOIN users u ON u.id = d.delivered_by
       LEFT JOIN industries i ON i.id = d.industry_id
       LEFT JOIN requests r ON r.id = d.request_id
       LEFT JOIN events e ON e.id = d.event_id';

    public static function list(Request $request): array
    {
        $where = [];
        $params = [];
        if ($iid = Auth::industryId()) {
            $where[] = 'd.industry_id = ?';
            $params[] = $iid;
        }
        if (in_array($request->query('type'), ['dia_a_dia', 'evento'], true)) {
            $where[] = 'd.type = ?';
            $params[] = $request->query('type');
        }
        foreach (['event_id' => 'd.event_id', 'industry_id' => 'd.industry_id', 'request_id' => 'd.request_id'] as $k => $c) {
            if (($v = $request->queryInt($k)) !== null) {
                $where[] = "{$c} = ?";
                $params[] = $v;
            }
        }
        if (($q = (string) $request->query('q', '')) !== '') {
            $where[] = '(d.code LIKE ? OR d.received_by_name LIKE ? OR r.code LIKE ?)';
            array_push($params, Db::like($q), Db::like($q), Db::like($q));
        }
        if (\App\Controllers\StockController::isDate($from = (string) $request->query('from', ''))) {
            $where[] = 'd.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if (\App\Controllers\StockController::isDate($to = (string) $request->query('to', ''))) {
            $where[] = 'd.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        [$rows, $meta] = Paginator::run($request, self::SELECT, self::FROM . $sqlWhere, $params, 'ORDER BY d.created_at DESC, d.id DESC');
        return [array_map([self::class, 'present'], $rows), $meta];
    }

    public static function find(int $id): array
    {
        $row = Db::fetch(self::SELECT . ' ' . self::FROM . ' WHERE d.id = ?', [$id]);
        if ($row === null) {
            throw HttpException::notFound('Protocolo não encontrado.');
        }
        self::assertIndustryAccess($row);
        $out = self::present($row);
        $out['items'] = self::items($id);
        return $out;
    }

    /** Day-to-day delivery: request must be `pronta`. Duplicate write-off is blocked. */
    public static function deliverRequest(int $requestId, array $input): array
    {
        Auth::authorize('requests.process');
        $data = Validator::validate($input, [
            'received_by_name' => 'required|string|max:150',
            'received_by_email' => 'nullable|email|max:190',
            'received_by_document' => 'nullable|string|max:30',
            'received_by_phone' => 'nullable|string|max:30',
            'signature' => 'required|string',
            'notes' => 'nullable|string|max:1000',
        ]);
        $deliveryId = Db::transaction(function () use ($requestId, $data) {
            $req = Db::fetch('SELECT * FROM requests WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$requestId]);
            if ($req === null) {
                throw HttpException::notFound('Solicitação não encontrada.');
            }
            if ($req['status'] !== 'pronta') {
                throw HttpException::conflict('INVALID_TRANSITION', 'A entrega só pode ser registrada quando a solicitação está pronta para retirada.');
            }
            $existing = Db::fetch('SELECT id FROM deliveries WHERE request_id = ?', [$requestId]);
            if ($existing) {
                throw HttpException::conflict('DUPLICATE_DELIVERY', 'Esta solicitação já possui um protocolo de entrega.');
            }
            $sig = ImageService::persistSignature((string) $data['signature']);
            $year = date('Y');
            $code = sprintf('PROT-%s-%04d', $year, Sequence::next('protocol', $year));
            $id = Db::insert('deliveries', [
                'code' => $code,
                'type' => 'dia_a_dia',
                'request_id' => $requestId,
                'industry_id' => $req['industry_id'],
                'delivered_by' => Auth::id(),
                'received_by_name' => $data['received_by_name'],
                'received_by_email' => $data['received_by_email'],
                'received_by_document' => $data['received_by_document'],
                'received_by_phone' => $data['received_by_phone'],
                'signature_path' => $sig['path'],
                'signature_png' => $sig['png'],
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
            ]);
            $lines = Db::fetchAll('SELECT * FROM request_items WHERE request_id = ? FOR UPDATE', [$requestId]);
            foreach ($lines as $line) {
                $qty = (int) $line['qty_reserved'] - (int) $line['qty_delivered'];
                if ($qty <= 0) {
                    continue;
                }
                $result = StockService::consumeReserved((int) $line['item_id'], $qty, [
                    'purpose' => $req['purpose'],
                    'recipient' => $data['received_by_name'],
                    'industry_id' => $req['industry_id'],
                    'department_id' => $req['department_id'],
                    'requester_id' => $req['requester_id'],
                    'request_id' => $requestId,
                    'delivery_id' => $id,
                    'purchase_ticket_no' => $req['purchase_ticket_no'],
                ]);
                $after = $result['stock']['on_hand'];
                Db::insert('delivery_items', [
                    'delivery_id' => $id,
                    'item_id' => (int) $line['item_id'],
                    'qty' => $qty,
                    'balance_after' => $after,
                ]);
                Db::update('request_items', [
                    'qty_delivered' => (int) $line['qty_delivered'] + $qty,
                    'qty_reserved' => (int) $line['qty_delivered'] + $qty,
                ], ['id' => (int) $line['id']]);
                self::maybeStockAlert((int) $line['item_id']);
            }
            RequestWorkflow::apply($requestId, 'finalizada', 'Protocolo ' . $code);
            Audit::log('deliver', 'delivery', $id, null, ['code' => $code, 'request' => $req['code']], $code);
            return $id;
        });

        self::finalizePdfAndMail($deliveryId);
        return self::find($deliveryId);
    }

    public static function present(array $d): array
    {
        return [
            'id' => (int) $d['id'],
            'code' => $d['code'],
            'type' => $d['type'],
            'type_label' => $d['type'] === 'evento' ? 'Evento' : 'Dia a dia',
            'request' => $d['request_id'] ? ['id' => (int) $d['request_id'], 'code' => $d['request_code'] ?? null] : null,
            'event' => $d['event_id'] ? ['id' => (int) $d['event_id'], 'name' => $d['event_name'] ?? null] : null,
            'industry' => $d['industry_id'] ? [
                'id' => (int) $d['industry_id'],
                'name' => $d['industry_name'] ?? null,
                'contact_email' => $d['industry_email'] ?? null,
            ] : null,
            'delivered_by' => ['id' => (int) $d['delivered_by'], 'name' => $d['delivered_by_name'] ?? null],
            'received_by_name' => $d['received_by_name'],
            'received_by_email' => $d['received_by_email'],
            'received_by_document' => $d['received_by_document'],
            'received_by_phone' => $d['received_by_phone'],
            'signature_url' => (!empty($d['signature_path']) || !empty($d['has_signature']) || !empty($d['signature_png']))
                ? url('/api/deliveries/' . $d['id'] . '/signature') : null,
            'pdf_url' => absolute_url('/api/deliveries/' . $d['id'] . '/pdf'),
            'verification_hash' => $d['verification_hash'],
            'notes' => $d['notes'],
            'created_at' => $d['created_at'],
            'email_status' => self::emailStatus((int) $d['id']),
        ];
    }

    private static function assertIndustryAccess(array $row): void
    {
        $industryId = Auth::industryId();
        if ($industryId !== null && (int) ($row['industry_id'] ?? 0) !== $industryId) {
            throw HttpException::forbidden('Você não pode acessar protocolos de outra indústria.');
        }
    }

    public static function emailStatus(int $deliveryId): array
    {
        $rows = Db::fetchAll(
            "SELECT to_email, status, sent_via, sent_at, id FROM notifications
              WHERE channel = 'email' AND related_type = 'delivery' AND related_id = ?
              ORDER BY id ASC",
            [$deliveryId]
        );
        $groupRows = Db::fetchAll(
            "SELECT to_email, status, sent_via, sent_at, id, body FROM notifications
              WHERE channel = 'email' AND related_type = 'delivery_group'
              ORDER BY id ASC"
        );
        foreach ($groupRows as $groupRow) {
            preg_match('/<!--\\s*delivery-group-ids:([0-9,]+)\\s*-->/', (string) ($groupRow['body'] ?? ''), $match);
            $groupIds = isset($match[1]) ? array_map('intval', explode(',', $match[1])) : [];
            if (in_array($deliveryId, $groupIds, true)) {
                $rows[] = $groupRow;
            }
        }
        if ($rows === []) {
            return ['key' => 'not_sent', 'label' => 'Não enviado', 'sent_at' => null];
        }
        usort($rows, static fn (array $a, array $b): int => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
        $latest = [];
        foreach ($rows as $row) {
            $latest[strtolower((string) $row['to_email'])] = $row;
        }
        $allBrevo = $latest !== [];
        $hasQueued = false;
        $hasFailed = false;
        $latestSentAt = null;
        foreach ($latest as $row) {
            if ($row['status'] !== 'enviado' || $row['sent_via'] !== 'brevo_smtp') {
                $allBrevo = false;
            }
            if ($row['status'] === 'fila') {
                $hasQueued = true;
            }
            if ($row['status'] === 'falhou') {
                $hasFailed = true;
            }
            if ($row['status'] === 'enviado' && $row['sent_at'] !== null) {
                $latestSentAt = $latestSentAt === null || (string) $row['sent_at'] > $latestSentAt ? $row['sent_at'] : $latestSentAt;
            }
        }
        if ($allBrevo) {
            return ['key' => 'sent', 'label' => 'Enviado', 'sent_at' => $latestSentAt];
        }
        if ($hasFailed) {
            return ['key' => 'failed', 'label' => 'Falha no envio', 'sent_at' => $latestSentAt];
        }
        if ($hasQueued) {
            return ['key' => 'queued', 'label' => 'Na fila de envio', 'sent_at' => $latestSentAt];
        }
        return ['key' => 'not_sent', 'label' => 'Não enviado', 'sent_at' => $latestSentAt];
    }

    public static function items(int $deliveryId): array
    {
        $delivery = Db::fetch('SELECT request_id, event_id, industry_id FROM deliveries WHERE id = ?', [$deliveryId]);
        $rows = Db::fetchAll(
            'SELECT di.*, i.code, i.name FROM delivery_items di JOIN items i ON i.id = di.item_id WHERE di.delivery_id = ?',
            [$deliveryId]
        );
        return array_map(function (array $r) use ($delivery) {
            $qty = (int) $r['qty'];
            $stored = $r['balance_after'] !== null ? (int) $r['balance_after'] : null;
            $remaining = self::remainingForLine($delivery ?? [], (int) $r['item_id'], $stored);
            return [
                'item' => ['id' => (int) $r['item_id'], 'code' => $r['code'], 'name' => $r['name']],
                'qty' => $qty,
                'qty_withdrawn' => $qty,
                'balance_after' => $remaining,
                'remaining' => $remaining,
            ];
        }, $rows);
    }

    /** Remaining units after this withdrawal: event quota, TRADE request, or stock. */
    private static function remainingForLine(array $delivery, int $itemId, ?int $stored): int
    {
        if (!empty($delivery['event_id']) && !empty($delivery['industry_id'])) {
            $alloc = Db::fetch(
                'SELECT qty_allocated, qty_withdrawn FROM event_allocations WHERE event_id = ? AND industry_id = ? AND item_id = ?',
                [$delivery['event_id'], $delivery['industry_id'], $itemId]
            );
            if ($alloc) {
                return max(0, (int) $alloc['qty_allocated'] - (int) $alloc['qty_withdrawn']);
            }
        }
        if (!empty($delivery['request_id'])) {
            $ri = Db::fetch(
                'SELECT qty_received, qty_requested, qty_delivered FROM request_items WHERE request_id = ? AND item_id = ?',
                [$delivery['request_id'], $itemId]
            );
            if ($ri) {
                $have = max((int) $ri['qty_received'], (int) $ri['qty_requested']);
                return max(0, $have - (int) $ri['qty_delivered']);
            }
        }
        if ($stored !== null) {
            return max(0, $stored);
        }
        try {
            return max(0, (int) (StockService::snapshot($itemId)['available'] ?? 0));
        } catch (Throwable) {
            return 0;
        }
    }

    public static function pdfPath(int $id): string
    {
        $row = Db::fetch('SELECT id, industry_id FROM deliveries WHERE id = ?', [$id]);
        if ($row === null) {
            throw HttpException::notFound('Protocolo não encontrado.');
        }
        self::assertIndustryAccess($row);
        $relative = PdfService::generate($id);
        $abs = ImageService::absolute($relative);
        if (!is_file($abs)) {
            throw HttpException::notFound('Não foi possível gerar o PDF do protocolo.');
        }
        return $abs;
    }

    public static function signaturePath(int $id): string
    {
        $row = Db::fetch('SELECT industry_id, signature_path, signature_png FROM deliveries WHERE id = ?', [$id]);
        if ($row === null) {
            throw HttpException::notFound('Protocolo não encontrado.');
        }
        self::assertIndustryAccess($row);
        $rel = (string) ($row['signature_path'] ?? '');
        $abs = $rel !== '' ? ImageService::absolute($rel) : '';
        if ($abs !== '' && is_file($abs)) {
            return $abs;
        }
        $b64 = (string) ($row['signature_png'] ?? '');
        if ($b64 === '') {
            throw HttpException::notFound('Assinatura não encontrada.');
        }
        $bin = base64_decode($b64, true);
        if ($bin === false || $bin === '') {
            throw HttpException::notFound('Assinatura não encontrada.');
        }
        $rel = $rel !== '' ? $rel : ('signatures/prot-' . $id . '.png');
        return ImageService::writeBytes($rel, $bin);
    }

    /** Creates a ZIP containing the PDFs for one protocol or an e-mail group. */
    public static function downloadZip(array $ids): \App\Core\Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === [] || count($ids) > 100) {
            throw HttpException::badRequest('INVALID_PROTOCOL_GROUP', 'Selecione pelo menos um protocolo válido.');
        }
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('A extensão ZIP é necessária para preparar os comprovantes.');
        }
        $archive = tempnam(sys_get_temp_dir(), 'delivery-zip-');
        if ($archive === false) {
            throw new \RuntimeException('Não foi possível preparar o arquivo ZIP.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($archive);
            throw new \RuntimeException('Não foi possível preparar o arquivo ZIP.');
        }
        $codes = [];
        foreach ($ids as $id) {
            $row = Db::fetch('SELECT id, code, industry_id FROM deliveries WHERE id = ?', [$id]);
            if ($row === null) {
                $zip->close();
                @unlink($archive);
                throw HttpException::notFound('Um dos protocolos selecionados não foi encontrado.');
            }
            self::assertIndustryAccess($row);
            $pdf = self::pdfPath($id);
            $code = (string) $row['code'];
            $codes[] = $code;
            if (!$zip->addFile($pdf, $code . '.pdf')) {
                $zip->close();
                @unlink($archive);
                throw new \RuntimeException('Não foi possível incluir todos os PDFs no ZIP.');
            }
        }
        if (!$zip->close() || !is_file($archive)) {
            @unlink($archive);
            throw new \RuntimeException('Não foi possível finalizar o arquivo ZIP.');
        }
        $content = file_get_contents($archive);
        @unlink($archive);
        if ($content === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo ZIP.');
        }
        $name = count($codes) === 1 ? 'comprovante-' . $codes[0] . '.zip' : 'comprovantes-' . count($codes) . '-protocolos.zip';
        return \App\Core\Response::download($content, $name, 'application/zip');
    }

    public static function resend(int $id): array
    {
        $row = Db::fetch(self::SELECT . ' ' . self::FROM . ' WHERE d.id = ?', [$id]);
        if ($row === null) {
            throw HttpException::notFound('Protocolo não encontrado.');
        }
        $industryId = Auth::industryId();
        if ($industryId !== null && (int) ($row['industry_id'] ?? 0) !== $industryId) {
            throw HttpException::forbidden('Você não pode enviar protocolos de outra indústria.');
        }
        $recipientEmail = is_string($row['received_by_email'] ?? null) ? strtolower(trim((string) $row['received_by_email'])) : '';
        $recipientEmail = filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) ?: null;
        $industryEmail = is_string($row['industry_email'] ?? null) ? strtolower(trim((string) $row['industry_email'])) : '';
        $industryEmail = filter_var($industryEmail, FILTER_VALIDATE_EMAIL) ?: null;
        if ($recipientEmail === null && $industryEmail === null) {
            return ['key' => 'not_sent', 'label' => 'Não enviado', 'sent_at' => null];
        }
        $d = self::present($row);
        $d['items'] = self::items($id);
        $attemptKey = 'retry-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
        NotificationService::protocolEmail(
            $d + ['pdf_path' => Db::value('SELECT pdf_path FROM deliveries WHERE id = ?', [$id]), 'user_id' => Auth::id()],
            $d['items'],
            $industryEmail,
            null,
            $attemptKey
        );
        NotificationService::flush();
        return self::emailStatus($id);
    }

    /** Sends all selected protocols for one normalized recipient in a single e-mail. */
    public static function resendGroup(mixed $ids): array
    {
        if (!is_array($ids)) {
            throw HttpException::badRequest('INVALID_PROTOCOL_GROUP', 'Selecione protocolos válidos para agrupar.');
        }
        $ids = array_values(array_unique(array_filter(array_map(static fn ($id) => is_numeric($id) ? (int) $id : 0, $ids), static fn (int $id) => $id > 0)));
        if (count($ids) < 2 || count($ids) > 100) {
            throw HttpException::badRequest('INVALID_PROTOCOL_GROUP', 'O grupo precisa ter entre 2 e 100 protocolos.');
        }
        sort($ids, SORT_NUMERIC);
        $rows = [];
        $recipient = null;
        foreach ($ids as $id) {
            $row = Db::fetch(self::SELECT . ' ' . self::FROM . ' WHERE d.id = ?', [$id]);
            if ($row === null) {
                throw HttpException::notFound('Um dos protocolos selecionados não foi encontrado.');
            }
            $industryId = Auth::industryId();
            if ($industryId !== null && (int) ($row['industry_id'] ?? 0) !== $industryId) {
                throw HttpException::forbidden('Você não pode enviar protocolos de outra indústria.');
            }
            $email = is_string($row['received_by_email'] ?? null) ? strtolower(trim((string) $row['received_by_email'])) : '';
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
            if ($email === null) {
                throw HttpException::badRequest('INVALID_PROTOCOL_GROUP', 'Todos os protocolos precisam ter um e-mail válido.');
            }
            if ($recipient === null) {
                $recipient = $email;
            } elseif ($recipient !== $email) {
                throw HttpException::badRequest('INVALID_PROTOCOL_GROUP', 'Os protocolos selecionados precisam ter o mesmo e-mail.');
            }
            $d = self::present($row);
            $d['items'] = self::items($id);
            $rows[] = $d + ['pdf_path' => $row['pdf_path'] ?? null];
        }
        $subject = 'Comprovantes de retirada — ' . count($rows) . ' protocolos';
        $groupKey = sha1($recipient . ':' . implode(',', $ids));
        $dedupePrefix = 'protocol-group-' . $groupKey;
        $existing = Db::fetch(
            'SELECT id, status, sent_via, sent_at, dedupe_key FROM notifications WHERE dedupe_key = ? OR dedupe_key LIKE ? ORDER BY id DESC LIMIT 1',
            [$dedupePrefix, $dedupePrefix . '-retry-%']
        );
        if ($existing !== null && $existing['status'] !== 'falhou') {
            $status = ($existing['status'] === 'enviado' && $existing['sent_via'] === 'brevo_smtp')
                ? ['key' => 'sent', 'label' => 'Enviado', 'sent_at' => $existing['sent_at']]
                : ['key' => 'queued', 'label' => 'Na fila de envio', 'sent_at' => $existing['sent_at']];
            return ['count' => count($rows), 'email' => $recipient, 'email_status' => $status];
        }
        $dedupe = $existing !== null
            ? $dedupePrefix . '-retry-' . (int) $existing['id']
            : $dedupePrefix;
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('A extensão ZIP é necessária para preparar os comprovantes agrupados.');
        }
        $archiveRelative = 'mail/archives/protocolos-' . bin2hex(random_bytes(8)) . '.zip';
        $archive = ImageService::absolute($archiveRelative);
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Não foi possível preparar o arquivo dos comprovantes.');
        }
        $sections = [];
        foreach ($rows as $d) {
            $pdfRelative = (string) ($d['pdf_path'] ?? '');
            if ($pdfRelative === '' || !is_file(ImageService::absolute($pdfRelative))) {
                $pdfRelative = PdfService::generate((int) $d['id']);
            }
            $pdfAbsolute = ImageService::absolute($pdfRelative);
            if (!is_file($pdfAbsolute) || !$zip->addFile($pdfAbsolute, (string) $d['code'] . '.pdf')) {
                $zip->close();
                @unlink($archive);
                throw new \RuntimeException('Não foi possível incluir todos os PDFs dos protocolos.');
            }
            $items = [];
            $quantity = 0;
            foreach ($d['items'] as $item) {
                $itemQty = (int) ($item['qty'] ?? 0);
                $quantity += $itemQty;
                $items[] = e((string) ($item['item']['name'] ?? 'Brinde')) . ': ' . $itemQty;
            }
            $sections[] = '<tr>'
                . '<td style="width:120px;min-width:120px;padding:10px;border:1px solid #dbe3ef;font-weight:700;vertical-align:top;white-space:nowrap">' . e((string) $d['code']) . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ef;vertical-align:top">' . e((string) ($d['industry']['name'] ?? '')) . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ef;vertical-align:top">' . e((string) ($d['type_label'] ?? '')) . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ef;vertical-align:top">' . e((string) ($d['received_by_name'] ?? '')) . '</td>'
                . '<td align="right" style="padding:10px;border:1px solid #dbe3ef;vertical-align:top;font-weight:700">' . $quantity . '</td>'
                . '<td style="padding:10px;border:1px solid #dbe3ef;vertical-align:top">' . implode('<br>', $items) . '</td>'
                . '</tr>';
        }
        if (!$zip->close() || !is_file($archive)) {
            @unlink($archive);
            throw new \RuntimeException('Não foi possível finalizar o arquivo dos comprovantes.');
        }
        $table = '<table role="table" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;font-size:13px;margin-top:16px">'
            . '<thead><tr style="background:#eff4fb">'
            . '<th align="left" style="padding:10px;border:1px solid #dbe3ef">Protocolo</th>'
            . '<th align="left" style="padding:10px;border:1px solid #dbe3ef">Indústria</th>'
            . '<th align="left" style="padding:10px;border:1px solid #dbe3ef">Tipo</th>'
            . '<th align="left" style="padding:10px;border:1px solid #dbe3ef">Recebido por</th>'
            . '<th align="right" style="padding:10px;border:1px solid #dbe3ef">Quantidade</th>'
            . '<th align="left" style="padding:10px;border:1px solid #dbe3ef">Itens retirados</th>'
            . '</tr></thead><tbody>' . implode('', $sections) . '</tbody></table>';
        $groupMarker = '<!-- delivery-group-ids:' . implode(',', $ids) . ' -->';
        $html = Mailer::layout($subject, $groupMarker . '<p>Olá,</p><p>Segue um único e-mail com os comprovantes selecionados. Os PDFs estão reunidos no arquivo ZIP anexado.</p>' . $table . '<p style="margin-top:16px;color:#4b5563;font-size:12px">Há um PDF correspondente a cada protocolo dentro do arquivo ZIP.</p>');
        try {
            NotificationService::queueEmail(Auth::id(), (string) $recipient, $subject, $html, $archiveRelative, $dedupe, 'delivery_group', null);
            NotificationService::flush();
        } catch (Throwable $e) {
            @unlink($archive);
            throw $e;
        }
        $notification = Db::fetch('SELECT status, sent_via, sent_at, attachment_path FROM notifications WHERE dedupe_key = ? ORDER BY id DESC LIMIT 1', [$dedupe]);
        if ($notification === null || (string) ($notification['attachment_path'] ?? '') !== $archiveRelative) {
            @unlink($archive);
        }
        $status = ($notification && $notification['status'] === 'enviado' && $notification['sent_via'] === 'brevo_smtp')
            ? ['key' => 'sent', 'label' => 'Enviado', 'sent_at' => $notification['sent_at']]
            : (($notification['status'] ?? '') === 'falhou'
                ? ['key' => 'failed', 'label' => 'Falha no envio', 'sent_at' => $notification['sent_at'] ?? null]
                : ['key' => 'queued', 'label' => 'Na fila de envio', 'sent_at' => $notification['sent_at'] ?? null]);
        return ['count' => count($rows), 'email' => $recipient, 'email_status' => $status];
    }

    public static function finalizePdfAndMail(int $deliveryId): array
    {
        try {
            PdfService::generate($deliveryId);
        } catch (Throwable $e) {
            \App\Core\Log::error('PDF protocol failed', ['id' => $deliveryId, 'error' => $e->getMessage()]);
        }
        $addresses = [];
        try {
            $d = self::find($deliveryId);
            $industryEmail = null;
            if ($d['industry']) {
                $industryEmail = Db::value('SELECT contact_email FROM industries WHERE id = ?', [$d['industry']['id']]);
            }
            $remaining = null;
            if ($d['type'] === 'evento' && $d['event'] && $d['industry']) {
                $remaining = (int) Db::value(
                    'SELECT COALESCE(SUM(qty_allocated - qty_withdrawn),0) FROM event_allocations WHERE event_id = ? AND industry_id = ?',
                    [$d['event']['id'], $d['industry']['id']]
                );
            }
            $row = Db::fetch('SELECT pdf_path FROM deliveries WHERE id = ?', [$deliveryId]);
            $addresses = NotificationService::protocolEmail($d + ['pdf_path' => $row['pdf_path'] ?? null], $d['items'], $industryEmail ? (string) $industryEmail : null, $remaining);
            NotificationService::flush();
        } catch (Throwable $e) {
            \App\Core\Log::error('Protocol e-mail failed', ['id' => $deliveryId, 'error' => $e->getMessage()]);
        }
        return [
            'addresses' => $addresses,
            'driver' => Mailer::driver(),
            'smtp' => Mailer::isSmtp(),
        ];
    }

    public static function maybeStockAlert(int $itemId): void
    {
        $item = Db::fetch('SELECT i.code, i.name, i.min_stock, s.qty_on_hand, s.qty_reserved FROM items i JOIN stock s ON s.item_id = i.id WHERE i.id = ?', [$itemId]);
        if (!$item) {
            return;
        }
        $available = (int) $item['qty_on_hand'] - (int) $item['qty_reserved'];
        $level = StockService::level($available, (int) $item['min_stock']);
        if ($level !== 'ok') {
            NotificationService::stockAlert($itemId, $item['code'], $item['name'], $level);
        }
    }
}
