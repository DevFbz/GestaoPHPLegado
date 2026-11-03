# Supabase temporário — passo a passo seguro

Este runbook prepara uma homologação temporária do **backend Laravel** usando o PostgreSQL gratuito do Supabase.

> O deploy raiz atual continua sendo o backend PHP legado. Não altere o Root Directory do projeto Vercel `gestao-brinde` antes de validar o Laravel. Para o primeiro teste, use um projeto Vercel separado, por exemplo `gestao-brindes-api-temp`.

## 0. Pré-requisitos

- Conta Supabase e conta Vercel.
- PHP **8.4+** e Composer. O `composer.lock` atual contém Symfony 8.1 e `endroid/qr-code` 6.1, que não instalam em PHP 8.2/8.3.
- Acesso ao repositório GitHub `brenofabrizio/GestaoBrinde`.
- Nenhuma senha, `APP_KEY`, URI do banco ou chave de Storage deve entrar no Git ou nesta conversa.

## 1. Criar o projeto gratuito no Supabase

1. Abra <https://supabase.com/dashboard> e crie um projeto no plano Free.
2. Escolha a região mais próxima dos usuários, se estiver disponível.
3. Defina uma senha forte para o banco e guarde-a em um gerenciador de senhas.
4. Aguarde o projeto terminar de provisionar.
5. Abra **Connect** e copie duas conexões, sem publicar os valores:
   - **Session Pooler** ou conexão direta: usada apenas para migrations locais;
   - **Transaction Pooler**, normalmente porta `6543`: usada pelas funções da Vercel.
6. A URI terá esta forma:

```text
postgresql://postgres.<PROJECT_REF>:<URL_ENCODED_PASSWORD>@<POOLER_HOST>:6543/postgres
```

Se a senha tiver `@`, `#`, `%`, `/`, `:` ou outro caractere reservado, faça URL encoding antes de colocá-la na URI.

## 2. Preparar o backend local

No PowerShell:

```powershell
Set-Location C:\Users\Fbz\Desktop\Projetos_Organizados\gestaobrindes\backend
Copy-Item .env.supabase.example .env
```

Edite `backend/.env` e preencha:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_TIMEZONE=America/Sao_Paulo
CORS_ALLOWED_ORIGINS=http://localhost:8000

# Para migrations locais, prefira Session Pooler ou conexão direta.
DB_CONNECTION=pgsql
DB_URL=postgresql://postgres.<PROJECT_REF>:<URL_ENCODED_PASSWORD>@<SESSION_OR_DIRECT_HOST>:5432/postgres
DB_SSLMODE=require
DB_PGSQL_DISABLE_PREPARES=true

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

Depois execute:

```powershell
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --seed
php artisan migrate:status
```

A migration cria usuários, perfis, permissões, estoque, solicitações, auditoria, cache, jobs e sessões.

### Criar o primeiro administrador

O `AccessSeeder` cria os cinco perfis, mas não cria uma conta com senha. Depois do seed, execute:

```powershell
php artisan tinker
```

No Tinker, use uma senha digitada somente localmente:

```php
$role = App\Models\Role::where('slug', 'admin')->firstOrFail();
$user = new App\Models\User();
$user->name = 'Administrador';
$user->email = 'seu-email-administrativo@example.com';
$user->password = 'DIGITE_AQUI_UMA_SENHA_FORTE_E_TROQUE_DEPOIS';
$user->role_id = $role->id;
$user->active = true;
$user->must_change_password = true;
$user->save();
```

Não copie a senha real para arquivos, commits, screenshots ou mensagens.

## 3. Importar dados legados, se necessário

Primeiro faça uma simulação:

```powershell
php artisan brindes:import-json --dry-run --path ..\database\json
```

Revise o relatório `import_invalid.json`. Só depois de conferir referências e saldos execute:

```powershell
php artisan brindes:import-json --path ..\database\json
```

Usuários legados são ignorados por segurança porque os JSON não possuem senhas confiáveis. Crie os usuários por convite/reset depois.

## 4. Criar uma API temporária na Vercel

Não mude ainda o projeto Vercel que serve o legado.

1. Na Vercel, crie um novo projeto conectado ao mesmo repositório GitHub.
2. Use um nome como `gestao-brindes-api-temp`.
3. Configure **Root Directory** como:

```text
backend
```

4. Use **Framework Preset: Other**.
5. Use o comando de instalação:

```text
composer install --no-dev --optimize-autoloader
```

6. Configure as variáveis no ambiente **Preview** primeiro:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_KEY=<mesma chave segura usada no ambiente Laravel>
APP_URL=https://<url-da-api-vercel>
APP_TIMEZONE=America/Sao_Paulo
CORS_ALLOWED_ORIGINS=https://gestao-brinde.vercel.app

DB_CONNECTION=pgsql
DB_URL=postgresql://postgres.<PROJECT_REF>:<URL_ENCODED_PASSWORD>@<TRANSACTION_POOLER_HOST>:6543/postgres
DB_SSLMODE=require
DB_PGSQL_DISABLE_PREPARES=true

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

7. Faça o deploy da branch `main`.

## 5. Validar a API temporária

Substitua `<url-da-api-vercel>` pelo domínio real, sem expor a URI do banco:

```powershell
curl.exe -i https://<url-da-api-vercel>/up
curl.exe -i https://<url-da-api-vercel>/api/v1/health
curl.exe -i https://<url-da-api-vercel>/api/v1/readiness
```

O readiness deve retornar `200` e indicar:

```json
{
  "ready": true,
  "checks": {
    "database": { "status": "ok" },
    "app_key": { "status": "ok" },
    "cors": { "status": "ok" },
    "storage": { "status": "ok", "disk": "local" }
  }
}
```

Com `FILESYSTEM_DISK=local`, o banco fica persistente no Supabase, mas assinaturas e arquivos continuam efêmeros na Vercel. Use esse modo apenas para homologação de cadastros, autenticação, solicitações e estoque.

## 6. Persistir assinaturas e PDFs também — opcional

Se for necessário testar entrega, assinatura e PDF de forma persistente:

1. No Supabase, habilite Storage compatível com S3.
2. Crie um bucket privado, por exemplo `brindes-private`.
3. Gere as credenciais S3 do Storage.
4. Na Vercel, troque para:

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<supabase-s3-access-key>
AWS_SECRET_ACCESS_KEY=<supabase-s3-secret-key>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=brindes-private
AWS_ENDPOINT=https://<PROJECT_REF>.storage.supabase.co/storage/v1/s3
AWS_USE_PATH_STYLE_ENDPOINT=true
```

5. Faça novo deploy e confirme no readiness que `storage.disk` é `s3`.

## 7. Smoke test mínimo

Depois do readiness:

1. Faça login em `POST /api/v1/auth/login`.
2. Guarde o token somente na sessão local.
3. Teste `/api/v1/auth/me`.
4. Teste listagem de brindes.
5. Teste entrada e saída com `idempotency_key`.
6. Repita a mesma entrada e confirme que o saldo não duplica.
7. Teste solicitação TRADE, aprovação e isolamento por indústria.
8. Só depois teste entrega, QR e PDF.

## 8. Rollback temporário

Para voltar ao comportamento atual:

- mantenha o projeto Vercel legado intacto;
- remova ou pause apenas `gestao-brindes-api-temp`;
- remova as variáveis temporárias da Vercel;
- não execute `migrate:fresh` no Supabase se houver dados que devam ser preservados.

O plano Free do Supabase pode pausar projetos sem atividade e possui limites de armazenamento, conexões e uso. Ele é adequado para homologação temporária, não deve ser tratado como garantia de produção.
