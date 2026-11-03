# Supabase + Vercel — banco persistente do backend Laravel

## Decisão

O backend Laravel usa o PostgreSQL do Supabase como fonte única dos dados operacionais. A Vercel executa a API, mas não guarda estoque, solicitações ou movimentações no filesystem local.

O frontend legado na raiz continua separado até a migração dos fluxos para `/api/v1`. Enquanto essa troca não for concluída, o deploy raiz ainda usa o backend PHP legado.

## Por que usar o pooler de transação

As funções da Vercel são serverless e podem abrir conexões curtas em várias instâncias. Para esse cenário, use no Supabase o **Transaction Pooler**, normalmente na porta `6543`, e não a conexão direta na porta `5432`.

O pooler de transação não suporta prepared statements nomeados no mesmo formato de uma conexão persistente. Por isso o projeto ativa `DB_PGSQL_DISABLE_PREPARES=true` quando o driver PDO PostgreSQL oferece essa opção.

Referência oficial: <https://supabase.com/docs/guides/database/connecting-to-postgres>

## Criar o projeto Supabase

1. Criar um projeto no [Supabase](https://supabase.com/dashboard).
2. Definir uma senha forte para o banco e guardá-la fora do Git.
3. Abrir **Connect** no projeto.
4. Selecionar **Transaction pooler**.
5. Copiar a URI completa, incluindo o host, o usuário `postgres.<PROJECT_REF>`, a porta `6543` e o banco `postgres`.
6. Substituir a senha na URI e percent-encodar caracteres reservados da senha.

Exemplo de formato, sem usar estes valores literalmente:

```text
postgresql://postgres.<PROJECT_REF>:<PASSWORD>@<POOLER_HOST>:6543/postgres
```

## Configurar o projeto Vercel

No projeto que hospeda a API:

- **Root Directory:** `backend`
- **Framework Preset:** `Other`
- **Install Command:** `composer install --no-dev --optimize-autoloader`
- **Environment:** `Production` e `Preview`, conforme o ambiente que será testado

Variáveis obrigatórias:

```text
APP_ENV=production
APP_DEBUG=false
APP_KEY=<chave gerada com php artisan key:generate --show>
APP_URL=https://<dominio-da-api>
APP_TIMEZONE=America/Sao_Paulo
CORS_ALLOWED_ORIGINS=https://<dominio-do-frontend>

DB_CONNECTION=pgsql
DB_URL=postgresql://postgres.<PROJECT_REF>:<PASSWORD>@<POOLER_HOST>:6543/postgres
DB_SSLMODE=require
DB_PGSQL_DISABLE_PREPARES=true

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

`DB_URL` deve ficar somente na Vercel. Nunca colocar a URI, senha do banco, `APP_KEY` ou credenciais de Storage no repositório.

## Executar migrations e dados iniciais

As migrations devem ser executadas uma vez contra o Supabase, usando um ambiente PHP/Composer confiável. Para essa tarefa, prefira a conexão **direta** ou o **Session Pooler** do Supabase; deixe o Transaction Pooler `6543` para as requisições da Vercel.

No `.env` local, preencha temporariamente `DB_URL` com a conexão escolhida para migrations e depois use a URL de Transaction Pooler nas variáveis da Vercel:

```powershell
Set-Location backend
Copy-Item .env.example .env
php artisan migrate --force
php artisan db:seed --force
```

Antes de executar, preencher o `.env` local com a `DB_URL` do Supabase. Não usar `migrate:fresh` contra um banco com dados reais.

Depois do deploy, validar:

```text
GET https://<dominio-da-api>/up
GET https://<dominio-da-api>/api/v1/health
GET https://<dominio-da-api>/api/v1/readiness
```

O readiness precisa retornar `ready: true`, com banco, chave da aplicação, CORS e storage configurados.

## O que ainda depende de configuração

- O Supabase precisa ser criado pelo responsável da conta.
- As variáveis precisam ser cadastradas na Vercel.
- O frontend precisa receber a URL da API Laravel e ser migrado por fluxo.
- Assinaturas, PDFs e anexos não devem depender do disco temporário da Vercel. O Supabase Storage é compatível com S3 e poderá ser conectado em uma etapa separada.
- O plano Free é adequado para testes e homologação, mas pode pausar projetos com baixa atividade; não tratar isso como garantia de produção.

O passo a passo para homologação temporária sem alterar o deploy legado está em [Setup temporário do Supabase](Setup-Supabase-Temporario.md).

Referências oficiais:
- <https://supabase.com/docs/guides/database/connecting-to-postgres>
- <https://supabase.com/docs/guides/storage/s3/compatibility>
- <https://supabase.com/docs/guides/platform/free-project-pausing>
