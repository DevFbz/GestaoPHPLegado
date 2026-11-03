# TRD — Reestruturação técnica com Laravel

**Versão:** 1.0  
**Data:** 28/09/2026  
**Status:** Em reestruturação

## 1. Arquitetura alvo

```text
Browser / futuro Tauri .exe
        |
        v
Frontend na Vercel
        |
        v
API Laravel versionada (/api/v1)
        |
        +-- autenticação Sanctum
        +-- autorização por perfil/permissão
        +-- Services transacionais
        +-- histórico e auditoria
        |
        +-- PostgreSQL/MySQL persistente
        +-- storage de arquivos/PDF
        +-- filas e notificações
```

O backend legado continua na raiz até a troca ser validada. O novo backend fica em `backend/`.

## 2. Stack

- PHP 8.4+;
- Laravel 13;
- Laravel Sanctum;
- Eloquent ORM e migrations;
- PostgreSQL como banco recomendado;
- MySQL/MariaDB como alternativa corporativa;
- JSON somente para fixtures, importação, exportação e testes;
- Vercel para frontend e homologação do runtime, com banco externo;
- GitHub Actions para testes;
- Tauri futuramente para o `.exe`.

## 3. Organização do código

```text
backend/
  app/Models/                 entidades e relações
  app/Services/              regras transacionais
  app/Http/Controllers/Api/  contratos HTTP
  database/migrations/       esquema versionado
  database/seeders/          perfis e permissões
  routes/api.php             API versionada
  routes/console.php         comandos operacionais
  tests/Feature/              testes de fluxo
```

## 4. Persistência e consistência

### Fonte de verdade

O banco relacional é a fonte de verdade do backend Laravel. JSON não recebe gravações concorrentes do sistema.

### Estoque

Toda alteração passa por `InventoryService`:

1. abre transação;
2. bloqueia o item e a linha de estoque;
3. valida saldo disponível;
4. calcula novo saldo;
5. atualiza o saldo;
6. grava uma movimentação imutável;
7. confirma a transação.

`idempotency_key` impede a criação de dois lançamentos para a mesma operação reenviada.

### Solicitações

Solicitações possuem itens, status e histórico. Os estados atuais da nova API são:

```text
rascunho -> aguardando_aprovacao -> aprovada
                              \-> reprovada
aprovada -> finalizada  (após protocolo/retirada)
```

### Entregas

O protocolo é criado dentro da mesma transação da baixa. Se qualquer item falhar por falta de saldo, protocolo, movimentações e alterações da solicitação sofrem rollback.

## 5. Migrations implementadas

- `000100`: perfis, cadastros, usuários, brindes e estoque;
- `000200`: permissões e relação perfil-permissão;
- `000300`: tokens pessoais do Sanctum;
- `000400`: solicitações TRADE, itens, histórico e aprovações;
- `000500`: eventos, cotas, entregas e itens entregues;
- `000600`: vínculo de entrega/evento/indústria nas movimentações.

## 6. Endpoints implementados

### Saúde e autenticação

- `GET /api/v1/health`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`

### Estoque

- `GET /api/v1/items`
- `GET /api/v1/stock/movements`
- `POST /api/v1/items/{item}/entry`
- `POST /api/v1/items/{item}/exit`

### Solicitações TRADE

- `GET /api/v1/trade-requests`
- `POST /api/v1/trade-requests`
- `GET /api/v1/trade-requests/{id}`
- `POST /api/v1/trade-requests/{id}/submit`
- `POST /api/v1/trade-requests/{id}/approve`

### Entregas e QR

- `GET /api/v1/deliveries`
- `GET /api/v1/deliveries/{id}`
- `GET /api/v1/deliveries/{id}/qr`
- `POST /api/v1/trade-requests/{id}/deliver`
- `GET /api/v1/verify/{code}` — URL assinada e sem dados pessoais.

### Eventos

- `GET /api/v1/events`
- `GET /api/v1/events/{id}`
- `POST /api/v1/events`
- `POST /api/v1/events/{id}/open`
- `POST /api/v1/events/{id}/close`
- `POST /api/v1/events/{id}/allocations`

## 7. Segurança

- tokens Sanctum para a API;
- autorização por permissão no backend;
- perfis administrativos separados;
- QR com assinatura temporária;
- nenhuma senha exportada para JSON;
- nenhum segredo no frontend ou nos arquivos versionados;
- validação de entrada nos controllers;
- transações para operações de estoque, solicitação e entrega.

## 8. Testes preparados

- persistência de entrada e saída após nova leitura;
- autenticação e retorno do perfil;
- restrição do perfil CD/Estoque;
- criação e aprovação de TRADE;
- entrega com baixa única;
- criação e alocação de evento.

Os testes ainda precisam ser executados em ambiente com PHP, Composer e banco configurados.

## 9. Deploy

### Homologação Vercel

- definir `backend` como Root Directory;
- configurar `DB_CONNECTION` e credenciais do banco externo;
- configurar `APP_KEY`, `APP_URL` e `FRONTEND_URL`;
- executar migrations no banco de homologação;
- executar seed de perfis;
- testar `/up` e `/api/v1/health`.

### Produção

Recomenda-se backend Laravel em serviço PHP persistente, com PostgreSQL/MySQL, armazenamento persistente, HTTPS, logs e backup. A Vercel pode continuar hospedando o frontend.

## 10. Pendências técnicas

- instalar/validar PHP e Composer;
- executar `composer install`, migrations e testes;
- adicionar importação de movimentações, solicitações e auditoria;
- migrar troca/recuperação obrigatória de senha;
- adicionar auditoria formal no Laravel;
- conectar PDF, anexos, assinatura e storage;
- migrar integração Lecom;
- conectar frontend atual;
- validar CORS, cookies/token e domínio final;
- realizar teste de concorrência;
- preparar build Tauri.
