# Deploy produtivo na Vercel

## Estado

O backend está preparado para homologação e para o deploy produtivo depois que as dependências externas forem provisionadas. O deploy não deve ser considerado saudável enquanto `GET /api/v1/readiness` não retornar HTTP 200 com todos os checks `ok`.

## Configuração da Vercel

O projeto Vercel atual da aplicação raiz continua apontando para o backend legado. Para homologar o Laravel com Supabase sem interromper o legado, crie um projeto Vercel separado.

- Root Directory: `backend`
- Framework preset: Other
- Build command: deixar vazio; o Vercel detectará o `npm run build` do `backend/package.json`
- Install command: deixar vazio; o `vercel-php@0.9.0` detecta `composer.json` e executa o Composer no build
- Output Directory: `public`
- Não usar SQLite, JSON gravável ou filesystem local como persistência.

### Exceção temporária do app raiz

Para o fluxo atual no projeto raiz `gestao-brinde`, foi configurado armazenamento JSON privado no Vercel Blob por solicitação. O SQLite em `/tmp` é somente cache e a persistência ainda não é prova de prontidão transacional de produção. Essa exceção não torna a API Laravel separada saudável nem substitui os critérios de `readiness`; ver [Persistência JSON no Vercel](Persistencia-JSON-Vercel.md).

## Variáveis obrigatórias

Configurar no ambiente Production da Vercel, sem versionar valores:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<gerado com php artisan key:generate --show>
APP_URL=https://api.<dominio>
CORS_ALLOWED_ORIGINS=https://<frontend>
DB_CONNECTION=pgsql
# Supabase Transaction Pooler copied from Dashboard > Connect (normally port 6543).
DB_URL=postgresql://postgres.<PROJECT_REF>:<SUPABASE_PASSWORD>@<POOLER_HOST>:6543/postgres
DB_SSLMODE=require
DB_PGSQL_DISABLE_PREPARES=true
# Alternatively, configure DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD separately.
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<access-key>
AWS_SECRET_ACCESS_KEY=<secret-key>
AWS_DEFAULT_REGION=<region>
AWS_BUCKET=<bucket>
QUEUE_CONNECTION=sync
CACHE_STORE=database
SESSION_DRIVER=database
```

`QUEUE_CONNECTION=sync` é adequado apenas enquanto não houver jobs assíncronos do produto. Quando houver jobs, usar um worker persistente externo; funções Vercel não substituem worker contínuo.

Para Supabase, `DB_URL` deve ser a conexão **Transaction Pooler** copiada em **Connect**, normalmente na porta `6543`. A conexão direta na porta `5432` não é a opção indicada para funções serverless.

O passo a passo para uma homologação temporária com projeto separado na Vercel está em [Setup temporário do Supabase](Setup-Supabase-Temporario.md).

## Banco e migrations

Executar contra o PostgreSQL de produção antes de liberar tráfego:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```

Não executar `migrate:fresh` em produção.

## Validação pós-deploy

```bash
curl -i https://api.<dominio>/up
curl -i https://api.<dominio>/api/v1/health
curl -i https://api.<dominio>/api/v1/readiness
```

O readiness deve indicar `database`, `app_key`, `cors` e `storage` como `ok`. Em produção, `storage` somente passa com `FILESYSTEM_DISK=s3`.

Depois validar login, troca obrigatória de senha, permissões dos cinco perfis, estoque, solicitação, entrega, QR, PDF e isolamento por indústria.

## Limitações e dependências externas

- PostgreSQL gerenciado, bucket S3 e credenciais precisam ser provisionados pelo responsável da infraestrutura.
- Backups devem ser enviados para armazenamento externo e testados por restauração.
- O cenário legado `tests/scenarios.php` depende do MariaDB antigo e não valida o backend Laravel.
- Integração de e-mail/SMTP permanece fora do escopo atual.
- Lecom exige API key, portal e processo publicados no ambiente correto.
