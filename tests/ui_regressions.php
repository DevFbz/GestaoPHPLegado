<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function expectContains(string $path, string $needle, string $label): void
{
    global $root, $checks, $failures;
    $checks++;
    $content = (string) file_get_contents($root . '/' . $path);
    if (!str_contains($content, $needle)) {
        $failures[] = $label . " — faltando: {$needle}";
    }
}

function expectNotContains(string $path, string $needle, string $label): void
{
    global $root, $checks, $failures;
    $checks++;
    $content = (string) file_get_contents($root . '/' . $path);
    if (str_contains($content, $needle)) {
        $failures[] = $label . " — encontrado: {$needle}";
    }
}

expectContains('resources/views/pages/events/index.php', 'loadError', 'Lista de eventos possui estado de erro visível');
expectContains('resources/views/pages/events/index.php', 'catch (e) { this.loadError', 'Lista de eventos captura falha da API no load');
expectContains('resources/views/pages/stock/index.php', 'Api.fmt.qty(m.quantity)', 'Livro de movimentações usa quantity da API');
expectNotContains('resources/views/pages/stock/index.php', 'Api.fmt.qty(m.qty)', 'Livro de movimentações não usa chave inexistente qty');
expectContains('resources/views/pages/stock/index.php', 'journal-type-cell', 'Tipo da movimentação tem célula semântica para espaçamento');
expectContains('resources/views/pages/stock/index.php', 'journal-item-cell', 'Brinde tem célula semântica para espaçamento');
expectContains('public/assets/css/app.css', '.stock-journal-table .journal-type-cell .badge', 'Badge de tipo não quebra nem invade a coluna Brinde');
expectContains('public/assets/css/app.css', '.stock-journal-table .journal-type { width: 122px; }', 'Coluna Tipo comporta Transferência');
expectContains('resources/views/pages/trade/show.php', 'loadError', 'Detalhe TRADE possui estado de erro');
expectContains('resources/views/pages/trade/show.php', 'catch (e)', 'Detalhe TRADE trata falha da API');
expectContains('resources/views/pages/trade/form.php', 'items.length === 0', 'Formulário TRADE valida inclusão de brindes');
expectContains('resources/views/pages/trade/form.php', 'UI.fieldErrors', 'Formulário TRADE exibe erros de campos');
expectContains('resources/views/layouts/app.php', 'notificationPopover', 'Layout possui modal flutuante de notificações');
expectContains('resources/views/layouts/auth.php', 'auth-login-shell', 'Login usa o novo shell compacto alinhado à interface do sistema');
expectContains('resources/views/layouts/auth.php', 'auth-login-card', 'Login mantém cartão central focado nas credenciais');
expectContains('public/assets/css/app.css', '.auth-login-shell', 'Estilo do novo layout de autenticação está definido');
expectContains('resources/views/pages/auth/login.php', 'auth-submit', 'Login possui ação visual principal');
expectContains('resources/views/pages/auth/login.php', 'noRedirect: true', 'Login exibe erro de credencial sem redirecionar para a própria tela');
expectContains('resources/views/layouts/app.php', 'Marcar todas como lidas', 'Modal de notificações possui marcar todas como lidas');
expectContains('resources/views/layouts/app.php', 'refreshDataBtn', 'Cabeçalho possui botão para atualizar os dados');
expectContains('public/assets/js/app.js', "searchParams.set('_refresh'", 'Botão atualizar força nova leitura da página');
expectContains('app/Services/SettingsService.php', 'lecom_supply_form_url', 'URL Lecom é configurável');
expectContains('app/Services/SettingsService.php', 'lecom_portal_url', 'Portal Lecom é configurável');
expectContains('app/Services/SettingsService.php', 'lecomApiStartUrl', 'Endpoint de abertura Lecom é gerado');
expectContains('app/Services/SettingsService.php', 'lecomWorkspaceStartUrl', 'Rota Workspace da biblioteca é gerada');
expectContains('resources/views/pages/settings/index.php', 'lecom_process_version', 'Versão do processo Lecom é configurável');
expectContains('resources/views/pages/settings/index.php', 'data-no-loading', 'Downloads de Excel e backup não ativam o carregamento de navegação');
expectContains('resources/views/pages/settings/index.php', 'Exportar todos os dados (Excel)', 'Configurações mantém exportação completa em português');
expectContains('resources/views/pages/deliveries/index.php', 'last_page', 'Comprovantes considera todas as páginas da API');
expectContains('resources/views/pages/deliveries/index.php', 'per_page: 100', 'Comprovantes busca páginas no limite permitido');
expectContains('resources/views/pages/deliveries/index.php', "'Não enviado'", 'Status alternativo de transporte aparece como não enviado');
expectContains('resources/views/pages/deliveries/show.php', 'Enviar email automático', 'Detalhe usa o nome correto para reenvio automático');
expectContains('resources/views/pages/deliveries/show.php', 'email_status', 'Detalhe do protocolo exibe status do e-mail');
expectContains('resources/views/pages/deliveries/index.php', 'groupedRows()', 'Comprovantes agrupa protocolos por e-mail');
expectContains('resources/views/pages/deliveries/index.php', 'Enviar e-mail (manualmente)', 'Comprovantes oferece envio manual para qualquer protocolo');
expectContains('resources/views/pages/deliveries/index.php', 'sendSingle(d)', 'Comprovantes permite envio manual individual');
expectContains('resources/views/pages/deliveries/index.php', 'loadError', 'Comprovantes exibe erro de carregamento');
expectContains('resources/views/pages/deliveries/index.php', 'group.rows.forEach', 'Comprovantes atualiza o status visual de todos os protocolos agrupados');
expectContains('app/routes.php', '/api/deliveries/group-resend', 'API possui rota de reenvio agrupado');
expectContains('app/Services/DeliveryService.php', 'resendGroup', 'Serviço monta reenvio agrupado');
expectContains('app/Services/DeliveryService.php', 'Auth::industryId()', 'Reenvio individual e agrupado respeitam o escopo da indústria');
expectContains('app/Services/DeliveryService.php', 'delivery-group-ids:', 'Envio agrupado vincula a notificação todos os protocolos');
expectContains('app/Services/DeliveryService.php', 'usort($rows', 'Status combina notificações individuais e agrupadas por ordem persistida');
expectContains('app/Services/DeliveryService.php', "related_type = 'delivery_group'", 'Status do protocolo consulta envio agrupado persistido');
expectContains('app/Services/DeliveryService.php', 'role="table"', 'E-mail agrupado usa tabela HTML');
expectContains('app/Services/DeliveryService.php', 'Itens retirados', 'E-mail agrupado apresenta os itens em tabela');
expectContains('app/Services/DeliveryService.php', 'precisam ter o mesmo e-mail', 'Reenvio agrupado valida o destinatário único');
expectContains('app/Services/DeliveryService.php', 'mesma indústria', 'Reenvio agrupado não mistura indústrias');
expectContains('app/Services/DeliveryService.php', 'ZipArchive', 'Reenvio agrupado anexa os PDFs em um ZIP');
expectContains('app/Services/DeliveryService.php', 'sort($ids, SORT_NUMERIC)', 'Reenvio agrupado deduplica independentemente da ordem');
expectContains('app/Services/DeliveryService.php', 'attachment_path FROM notifications', 'Reenvio agrupado confirma o status e anexo persistidos');
expectContains('app/Services/DeliveryService.php', "'-retry-'", 'Reenvio agrupado permite nova tentativa após falha');
expectContains('app/Services/DeliveryService.php', '@unlink($archive)', 'Reenvio agrupado limpa ZIP órfão');
expectContains('app/Services/DeliveryService.php', 'WHERE dedupe_key = ?', 'Reenvio agrupado evita ZIP duplicado');
expectContains('app/Services/DeliveryService.php', "'label' => 'Enviado'", 'API identifica envio confirmado como Enviado');
expectContains('app/Controllers/DeliveriesController.php', "'resent' =>", 'Reenvio só confirma quando o status é enviado');
expectContains('app/Services/NotificationService.php', '$attemptKey', 'Reenvio gera uma nova chave de tentativa');
expectNotContains('app/Services/DeliveryService.php', 'Aceito por outro transporte', 'API não exibe aceite por transporte alternativo');
expectContains('app/Services/DeliveryService.php', 'brevo_smtp', 'API identifica o transporte Brevo do comprovante');
expectContains('app/Services/NotificationService.php', 'sent_via', 'Fila persiste o transporte que aceitou o e-mail');
expectContains('resources/views/pages/reports/index.php', "type === 'event_report'", 'Relatório de evento exibe seletor específico');
expectContains('resources/views/pages/reports/index.php', 'eventId', 'Relatório de evento envia o identificador do evento');
expectContains('app/Services/ReportService.php', 'buildEventWorkbook', 'Excel do evento possui workbook executivo');
expectContains('app/Services/ReportService.php', 'event_industry_withdrawn_chart', 'Excel do evento possui gráfico de retiradas por indústria');
expectContains('app/Services/ReportService.php', 'event_industry_compare_chart', 'Excel do evento possui gráfico comparativo alocado versus retirado');
expectContains('app/Services/ReportService.php', 'Aproveitamento (%)', 'Relatório do evento calcula indicador de aproveitamento');
expectContains('app/Services/ReportService.php', "['Por indústria', 'industry_columns', 'industry_rows']", 'Dashboard possui aba por indústria');
expectContains('app/Services/ReportService.php', "['Evolução diária', 'timeline_columns', 'timeline_rows']", 'Dashboard possui aba de evolução diária');
expectContains('app/Services/ReportService.php', "'title' => \$title", 'Abas operacionais recebem títulos explícitos');
expectContains('app/Services/ReportService.php', "'title' => 'Retiradas'", 'Aba de retiradas recebe título explícito');

expectContains('app/Services/DeliveryService.php', '$d[\'items\'] = self::items($id)', 'Reenvio carrega os itens antes de montar o e-mail');
expectContains('app/Services/DeliveryService.php', 'filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)', 'Reenvio valida o e-mail do recebedor antes de enviar');
expectContains('app/Services/DeliveryService.php', 'filter_var($industryEmail, FILTER_VALIDATE_EMAIL)', 'Reenvio valida o e-mail da indústria antes de enviar');
expectNotContains('resources/views/pages/settings/index.php', 'testMail()', 'Configurações não exibem a aba/ação administrativa de teste de e-mail');
expectNotContains('resources/views/pages/settings/index.php', "'/api/settings/test-email'", 'Configurações não chamam a rota removida de teste de e-mail');
expectNotContains('app/routes.php', '/api/settings/test-email', 'Rota administrativa de teste de e-mail foi removida');
expectNotContains('app/Controllers/SettingsController.php', 'function testEmail', 'Controller não mantém ação administrativa de teste de e-mail');
expectNotContains('app/pages.php', "'path' => '/email'", 'Página administrativa Email foi removida');
expectNotContains('app/Support/Menu.php', "'label' => 'Email'", 'Item Email foi removido do menu');
expectContains('app/Services/DataExportService.php', "'Exportação completa de dados'", 'Excel possui aba de resumo didática');
expectContains('app/Services/DataExportService.php', 'Campos de autenticação e tokens foram omitidos', 'Excel informa os campos omitidos por segurança');
expectContains('app/Services/DataExportService.php', 'COLUMN_LABELS', 'Excel usa cabeçalhos traduzidos para português');

expectContains('resources/views/pages/trade/form.php', 'Abrir chamado no Lecom', 'Nova solicitação possui botão para abrir chamado');
expectContains('resources/views/pages/trade/form.php', '@click="openLecom()"', 'Botão da solicitação abre o processo Lecom explicitamente');
expectContains('resources/views/pages/trade/form.php', 'LecomSSOTicket', 'Nova solicitação lê o ticket SSO do cookie');
expectContains('resources/views/pages/trade/form.php', 'ticket-sso', 'Nova solicitação envia o header ticket-sso');
expectContains('resources/views/pages/trade/form.php', 'workspace/api/process/start', 'Nova solicitação usa o endpoint Workspace');
expectNotContains('resources/views/pages/trade/index.php', '/abrir-chamado?', 'Índice TRADE não possui aba separada de abertura');
expectNotContains('app/pages.php', "'/abrir-chamado'", 'Rota da aba separada de abertura foi removida');
expectContains('resources/views/pages/items/show.php', 'Abrir formulário Lecom', 'Detalhe do brinde possui botão Lecom');
expectContains('resources/views/pages/items/show.php', 'item-detail-actions', 'Ações do detalhe do brinde têm agrupamento visual próprio');
expectContains('public/assets/css/app.css', '.item-detail-actions', 'Ações do detalhe do brinde não esticam na altura do cabeçalho');
expectContains('resources/views/pages/events/show.php', 'event-detail-actions', 'Ações do evento têm agrupamento visual próprio');






expectContains('resources/views/pages/events/show.php', 'Cadastre uma indústria antes de incluir a cota.', 'Evento explica quando não há indústria disponível');
expectContains('resources/views/pages/events/show.php', "UI.toast('Selecione uma indústria e um brinde para incluir a cota.'", 'Incluir cota informa seleção incompleta');
expectContains('resources/views/pages/events/show.php', 'this.row = { industry_id: \'\', item_id: \'\', qty_allocated: 1 }', 'Incluir cota limpa o formulário após adicionar');
expectContains('resources/views/pages/events/mode.php', 'async next()', 'Modo evento recarrega os saldos ao iniciar a próxima retirada');
expectContains('resources/views/pages/events/mode.php', 'await this.init();', 'Modo evento atualiza a lista de indústrias após uma retirada');
expectContains('resources/views/pages/events/mode.php', 'stockLevel', 'Modo tablet classifica o saldo disponível');
expectContains('resources/views/pages/events/mode.php', 'saldo-critical', 'Modo tablet sinaliza saldo crítico');
expectContains('resources/views/pages/events/mode.php', 'saldo-warning', 'Modo tablet sinaliza saldo de atenção');
expectContains('resources/views/pages/events/mode.php', 'saldo-ok', 'Modo tablet sinaliza saldo adequado');
expectContains('resources/views/pages/events/mode.php', 'Legenda de saldo', 'Modo tablet explica as cores do saldo');
expectContains('resources/views/pages/events/mode-pc.php', 'stockLevel', 'Modo PC usa a mesma classificação de saldo');
expectContains('public/assets/css/app.css', 'align-self: flex-start', 'Botões não esticam verticalmente dentro de barras flex');
expectContains('resources/views/pages/lookups/index.php', 'modal-dialog-centered modal-dialog-scrollable', 'Modal de cadastros permite rolagem quando há muitos campos');
expectContains('resources/views/pages/lookups/index.php', 'lookup-modal-form', 'Formulário de cadastro participa do layout flex do modal');
expectContains('public/assets/css/app.css', '.lookup-modal-form .modal-body', 'Corpo do cadastro é a área rolável do modal');
expectContains('resources/views/pages/lookups/index.php', 'lookup-modal-portal', 'Modal de cadastro fica fora do card que pode recortar o conteúdo');
expectContains('public/assets/css/app.css', '.modal-backdrop.show', 'Backdrop dos cadastros tem opacidade controlada');
expectContains('app/Support/LookupRegistry.php', "'categories'", 'Cadastro de categorias está registrado');
expectContains('app/Support/LookupRegistry.php', "'departments'", 'Cadastro de departamentos está registrado');
expectContains('app/Support/LookupRegistry.php', "'industries'", 'Cadastro de indústrias está registrado');
expectContains('app/Support/LookupRegistry.php', "'locations'", 'Cadastro de locais está registrado');
expectContains('app/Support/LookupRegistry.php', "'suppliers'", 'Cadastro de fornecedores está registrado');
expectContains('resources/views/pages/stock/_form.php', 'data-can-lookups', 'Entrada não consulta cadastros sem permissão');
expectContains('app/Support/Installer.php', "'stock.entry'", 'Perfil CD recebe permissão de entrada na atualização');
expectContains('app/Support/DemoSqliteStore.php', "'homolog-blank-v3'", 'Homologação usa marcador de limpeza zerada v3 compatível com o schema');
expectContains('app/Support/VercelJsonStore.php', 'Authorization: Bearer ', 'Snapshots JSON autenticam o Blob com Bearer token');
expectContains('app/Support/Installer.php', 'resetVercelDemoToBlank', 'Homologação limpa dados operacionais');
expectNotContains('app/Support/Installer.php', 'DemoSeeder::runCatalogs($noop)', 'Homologação não recria catálogos nem brindes fictícios');
expectContains('app/Support/Installer.php', "Db::pdo()->exec('PRAGMA foreign_keys = OFF')", 'Reset SQLite desativa FKs durante a limpeza ordenada');
expectContains('app/Support/Installer.php', "Db::pdo()->exec('PRAGMA foreign_keys = ON')", 'Reset SQLite reativa FKs após a limpeza');
expectContains('app/bootstrap.php', 'date_default_timezone_set((string) Config::get', 'Fuso é aplicado antes da criação da base demo');
expectContains('app/bootstrap.php', "json-runtime-v1.sqlite", 'SQLite é apenas cache efêmero do snapshot JSON');
expectContains('app/bootstrap.php', '$run(static fn () => \\App\\Support\\Installer::ensureVercelDemo());', 'Bootstrap normaliza os dados após restaurar o snapshot');
expectContains('app/routes.php', '/api/admin/reset-data', 'Admin possui endpoint protegido para zerar os dados');
expectContains('app/Controllers/SettingsController.php', 'resetData', 'Backend possui ação administrativa para zerar dados');
expectContains('app/Support/VercelJsonStore.php', 'public function currentRevision', 'Storage permite atualizar a revisão após conflito');
expectContains('app/Core/Db.php', 'JsonSnapshotConflict', 'Persistência trata conflito concorrente com retentativa');
expectContains('app/Support/Installer.php', "'admin@brindes.local', 1", 'Homologação garante a conta administrativa configurada');
expectContains('app/Support/Installer.php', 'password_verify($password', 'Bootstrap não regrava hashes em toda requisição');
expectContains('app/Core/App.php', "manifest-src 'self'", 'CSP permite o manifesto apenas na própria origem');
expectContains('resources/views/pages/settings/index.php', 'Zerar todos os dados', 'Configurações exibem botão de limpeza somente ao administrador');
expectContains('public/sw.js', "const VERSION = 'gestao-brindes-v16'", 'Cache de assets usa a versão atual do service worker');
expectContains('public/sw.js', "fetch(request, { cache: 'no-store' })", 'APIs não usam snapshots antigos');
expectContains('public/sw.js', "'/assets/js/state-sync.js'", 'Cache inclui sincronização entre abas');
expectContains('public/assets/js/api.js', 'BrindesSync?.touch(path)', 'Mutações invalidam os dados das telas abertas');
expectContains('public/assets/js/api.js', 'CSRF_INVALID', 'Mutações renovam CSRF expirado antes de falhar');
expectContains('resources/views/pages/trade/show.php', 'Solicitação aprovada e encaminhada', 'Aprovação TRADE confirma a transição concluída');
expectContains('resources/views/pages/trade/show.php', "this.loadError = ''", 'Falha de atualização não bloqueia detalhe TRADE já carregado');
expectContains('resources/views/pages/requests/show.php', 'Não foi possível carregar a solicitação', 'Detalhe interno exibe erro em vez de ficar em branco');
expectContains('resources/views/pages/requests/show.php', 'BrindesSync?.listen', 'Detalhe interno atualiza após alterações');
expectContains('public/assets/js/state-sync.js', "localStorage.setItem(KEY", 'localStorage guarda somente a revisão dos dados');
expectContains('public/assets/js/state-sync.js', 'server database remains the source of truth', 'Sincronização não cria uma segunda base de negócio no navegador');
expectNotContains('public/assets/js/state-sync.js', 'setInterval', 'Sincronização não cria polling concorrente');
expectContains('public/assets/js/state-sync.js', 'origin !== TAB_ID', 'A aba que gravou não recarrega snapshot antigo da própria alteração');
expectContains('public/assets/js/state-sync.js', '/api/sync/revision', 'Sincronização consulta revisões compartilhadas entre usuários');
expectContains('public/assets/js/state-sync.js', 'pollInFlight', 'Polling impede requisições simultâneas de revisão');
expectContains('public/assets/js/state-sync.js', 'listenerCount === 0', 'Telas sem listener recarregam após alteração remota');
expectContains('public/assets/js/state-sync.js', 'hasUnsavedEdits', 'Alteração remota não sobrescreve formulário em edição');
expectContains('app/Controllers/SyncController.php', 'user_id <> ?', 'Endpoint só sinaliza gravações feitas por outro usuário');
expectContains('app/routes.php', "'/api/sync/revision'", 'Revisão remota exige autenticação pela rota privada');
expectContains('public/assets/js/app.js', 'brindes:data-stale', 'Usuário é avisado quando uma edição local impede atualização automática');
expectContains('resources/views/pages/requests/index.php', 'Solicitações internas', 'Tela interna explica seu fluxo');
expectContains('resources/views/pages/trade/index.php', 'Solicitações TRADE', 'Tela TRADE explica seu fluxo de compra');
expectContains('resources/views/pages/trade/index.php', 'Number.isInteger(Number(r.id))', 'Lista TRADE bloqueia links com identificador inválido');
expectContains('resources/views/pages/items/index.php', 'BrindesSync?.listen', 'Aba Brindes atualiza após alterações de estoque');
expectContains('app/Support/JsonDatabase.php', "use App\\Core\\Env;", 'Espelho JSON resolve Env no namespace correto');
expectContains('app/Support/JsonDatabase.php', "sys_get_temp_dir() . '/brindes/storage/json'", 'Espelho JSON funciona no runtime Vercel');
expectContains('app/Support/JsonDatabase.php', 'new VercelJsonStore', 'Snapshots JSON são sincronizados ao storage persistente');
expectContains('app/Support/JsonDatabase.php', 'BLOB_STORE_ID', 'Runtime exige o identificador do storage alvo');
expectContains('app/Support/JsonDatabase.php', 'documentsForTables', 'Snapshots JSON são agrupados por domínio alterado');
expectContains('app/Core/Db.php', 'mutatedTables', 'Runtime rastreia tabelas alteradas para sincronização incremental');
expectContains('app/Core/Db.php', "Env::get('JSON_DB_MIRROR', false) === true && !Env::get('VERCEL')", 'Inicialização Vercel não publica o seed antes de restaurar o snapshot remoto');
expectContains('app/Support/VercelJsonStore.php', 'x-if-match: ', 'Manifesto JSON usa escrita condicional para evitar perda concorrente');
expectContains('app/Support/VercelJsonStore.php', 'headEtag', 'CAS lê ETag atual do manifesto privado');
expectContains('app/Support/JsonDatabase.php', 'restoreSnapshot', 'Instância fria restaura tabelas a partir dos JSONs');
expectContains('app/Support/JsonDatabase.php', 'sqlite_fingerprint', 'Cache local não é confiável após interrupção de gravação');
expectContains('app/bootstrap.php', 'if (!$restored)', 'Primeira inicialização detecta que ainda não há snapshot remoto');
expectContains('app/bootstrap.php', 'JsonDatabase::mirrorFromPdo();', 'Primeira inicialização publica baseline completo independente de SQL rastreado');
expectContains('public/index.php', 'CONCURRENT_UPDATE', 'Conflito concorrente retorna 409 explícito');
expectContains('public/index.php', 'rawurldecode', 'Servidor local entrega assets com espaços e caracteres UTF-8');
expectContains('resources/views/layouts/auth.php', 'rel="icon"', 'Login declara favicon sem depender do caminho padrão');
expectContains('resources/views/layouts/app.php', 'rel="icon"', 'Aplicação autenticada declara favicon sem depender do caminho padrão');
expectContains('app/Controllers/LookupController.php', 'LOWER(name)', 'Cadastros rejeitam duplicidade sem diferenciar maiúsculas');
expectNotContains('public/assets/css/app.css', '.page > :first-child { animation:', 'Conteúdo da página não cria um contexto que bloqueia cliques nos modais');
expectNotContains('public/assets/css/app.css', '@keyframes fade-in', 'Animação de página que aprisionava modais foi removida');
expectContains('app/Support/Installer.php', 'lookups.view', 'Restrição do CD remove acesso a Cadastros');
expectContains('app/Support/Installer.php', 'requests.view_all', 'Restrição do CD remove acesso geral a solicitações');
expectContains('app/Support/SqliteSchema.php', "datetime('now','-3 hours')", 'SQLite grava timestamps no timezone da aplicação');
expectContains('bin/export-json.php', 'redacted_user_fields', 'Exportação JSON declara campos sensíveis removidos');

if ($failures !== []) {
    fwrite(STDERR, "FAIL {$checks} checks\n" . implode("\n", array_map(fn (string $f) => '- ' . $f, $failures)) . "\n");
    exit(1);
}

echo "OK {$checks} checks\n";
