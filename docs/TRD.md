# TRD — Documento Técnico de Requisitos

## 1. Arquitetura atual

O projeto mantém o sistema legado durante a migração para o backend Laravel:

```text
Navegador / tablet
  -> frontend PHP server-rendered
  -> app/Core + Router + Auth + CSRF
  -> Controllers legados
  -> Services legados
  -> PDO
  -> MySQL/MariaDB persistente

Navegador / cliente migrado
  -> frontend/API bridge
  -> backend Laravel /api/v1
  -> Controllers + Services + Policies
  -> Eloquent/migrations
  -> PostgreSQL ou outro banco suportado pelo ambiente
```

A Vercel não deve ser a fonte de verdade dos dados. O filesystem de funções serverless é temporário.

## 2. Componentes entregues

### Legado

- MVC pequeno com PDO e Composer.
- Controle de sessão, CSRF e autorização por permissão.
- Cadastros, solicitações, estoque, Lecom, notificações e auditoria.
- Fila offline com IndexedDB e `Idempotency-Key`.
- SQLite/Blob somente para demonstração Vercel; não é arquitetura produtiva recomendada.

### Laravel

- API versionada em `/api/v1`.
- `InventoryService` transacional e idempotente.
- Entregas com protocolo, QR, assinatura PNG privada e PDF.
- Importador de JSON legado com dry-run, inválidos e reconciliação.
- Sanctum, expiração/revogação, troca obrigatória, reset e rate limit.
- Dashboard, relatórios JSON/CSV e escopo por indústria.
- CORS parametrizado, timezone parametrizado e readiness.
- Backup/restore SQLite para operações locais; backup produtivo depende de banco e storage externos.

## 3. Contratos principais da API Laravel

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/health`
- `GET /api/v1/readiness`
- `GET /api/v1/items`
- `POST /api/v1/items/{item}/entry`
- `POST /api/v1/items/{item}/exit`
- `GET /api/v1/stock/movements`
- `GET /api/v1/trade-requests`
- `POST /api/v1/trade-requests`
- `POST /api/v1/deliveries`
- `GET /api/v1/deliveries/{delivery}/pdf`
- `GET /api/v1/dashboard`
- `GET /api/v1/reports/{type}`

O contrato detalhado está em `docs/backend-api-contract.md` e `docs/api-contract.md`.

## 4. Persistência

- Banco relacional é a fonte de verdade.
- Todas as mutações de estoque e entrega usam transação e idempotência.
- O frontend usa IndexedDB apenas como outbox offline.
- JSON é fixture, exportação ou inspeção; nunca substitui transações.
- Assinaturas, NFs, fotos e PDFs devem usar storage persistente.
- Em Vercel serverless, não gravar arquivos definitivos em `/tmp`.

## 5. Banco e conexões

### VM com o legado

- MariaDB 10.11 ou MySQL 8.
- InnoDB, `utf8mb4`, backup com `mysqldump`.
- PHP-FPM mantém conexão por requisição.

### Vercel com PostgreSQL gerenciado

- Usar Supabase, Neon ou provedor equivalente.
- Preferir pooler transacional para funções serverless.
- Executar migrations contra o banco real antes de liberar tráfego.
- Não usar SQLite como banco operacional.

A migração completa do frontend legado para a API Laravel é uma etapa ainda pendente de homologação. Não declarar a migração final apenas porque a API local passa nos testes.

## 6. Segurança

- Segredos somente em `.env` local não versionado ou variáveis da plataforma.
- Nunca versionar APP_KEY, senhas, tokens, API keys ou connection strings.
- CSRF em escritas do legado.
- Sanctum e expiração de tokens na API Laravel.
- Sessão HttpOnly/SameSite e Secure com HTTPS.
- Autorização no backend em todos os endpoints.
- Isolamento por indústria.
- Uploads privados com validação de MIME e tamanho.
- CSV com proteção contra formula injection.
- Logs sem payloads sensíveis.

## 7. Operação e observabilidade

- `GET /api/health` verifica o serviço.
- `GET /api/v1/readiness` verifica banco, APP_KEY, CORS e storage produtivo.
- VM: logs em `storage/logs/`, Nginx, PHP-FPM, MariaDB e Supervisor.
- Vercel: logs de função e alertas da plataforma.
- Backup deve ser automatizado, retido e restaurado periodicamente em ambiente de teste.
- Fila e scheduler exigem cron/worker persistente; uma função Vercel não substitui um worker contínuo.

## 8. E-mail

A integração SMTP/Graph está fora do escopo desta entrega. O código e a documentação não devem exigir e-mail para o funcionamento do estoque, solicitações ou entregas.

Quando reaberta, a implementação deverá usar OAuth/Graph ou SMTP AUTH aprovado pelo tenant, com fila e worker.

## 9. Estado técnico e pendências

### Concluído

- Núcleo Laravel, estoque, solicitações, entregas, importação, autenticação, dashboard, relatórios, CORS, timezone, readiness e documentação operacional.
- Testes Laravel atuais: 34 testes e 161 assertions.
- Regressões legadas atuais: 39 checks.
- JavaScript, PHP lint e Composer validados.

### Pendente ou condicionado

- PostgreSQL/Supabase ou MariaDB produtivo provisionado.
- Storage S3/Supabase Storage provisionado.
- Migração final do frontend para `/api/v1`.
- Execução das migrations no ambiente produtivo.
- Smoke de produção e teste dos cinco perfis.
- Backup/restore do banco final.
- Worker/scheduler persistente.
- Lecom com tenant e credencial reais.
- SMTP/Graph e Tauri: fora do escopo atual.
