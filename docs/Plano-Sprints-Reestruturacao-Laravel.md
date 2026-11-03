# Plano de sprints — Reestruturação Laravel

## Legenda

- **Concluída:** implementação e validação local realizadas.
- **Parcial:** parte implementada; depende de infraestrutura, integração ou homologação.
- **Pendente:** ainda não implementada.
- **Fora do escopo:** decisão explícita para esta entrega.

## Sprint 0 — Diagnóstico e arquitetura

**Status: Concluída**

- diagnóstico do backend PHP próprio;
- identificação da perda de persistência do SQLite/snapshot na Vercel;
- definição do backend Laravel e API versionada;
- separação entre legado, homologação e produção;
- definição de banco relacional como fonte de verdade.

## Sprint 1 — Núcleo Laravel e estoque

**Status: Concluída e validada localmente**

- estrutura Laravel em `backend/`;
- migrations de cadastros, brindes e estoque;
- `InventoryService` transacional;
- entradas, saídas e ajustes;
- livro de movimentações;
- validações de saldo;
- idempotência;
- testes de persistência e duplicidade.

## Sprint 2 — Identidade e permissões

**Status: Concluída e validada localmente**

- Sanctum;
- login, logout e usuário atual;
- cinco perfis e permissões granulares;
- middleware de autorização;
- troca obrigatória de senha;
- recuperação e redefinição de senha;
- rate limit;
- expiração e revogação de tokens;
- auditoria de autenticação;
- escopo por indústria.

## Sprint 3 — Solicitações TRADE

**Status: Concluída e validada localmente**

- criação de solicitação e itens;
- cálculo de valor e necessidade de compra;
- envio para aprovação;
- aprovação/reprovação;
- histórico de status;
- validação de escopo por indústria;
- proteção de acesso a solicitações de terceiros.

## Sprint 4 — Eventos, entregas e QR

**Status: Concluída e validada localmente**

- protocolo de entrega;
- baixa transacional de estoque;
- idempotência da retirada;
- QR Code visual;
- verificação do QR;
- assinatura PNG validada e armazenada em filesystem privado;
- PDF do protocolo com QR e assinatura;
- proteção de acesso por indústria.

**Pendente para produção:** storage persistente externo e validação com usuários reais.

## Sprint 5 — Migração completa de dados

**Status: Concluída localmente; migração real pendente**

- importação de cadastros e brindes;
- importação de solicitações e itens;
- importação de movimentações;
- importação de auditoria com snapshots seguros;
- `--dry-run`;
- relatório `import_invalid.json`;
- validação de referências;
- idempotência;
- reconciliação de saldos;
- exclusão de senhas, hashes e tokens dos dados importados.

**Falta:** executar contra o banco legado real e conferir contagens/saldos com o responsável do negócio.

## Sprint 6 — Segurança e experiência de acesso

**Status: Concluída e validada localmente**

- troca obrigatória de senha;
- recuperação e reset;
- expiração/revogação de tokens;
- rate limit;
- auditoria de login;
- escopo por indústria;
- bloqueio de acesso cruzado a entregas e solicitações.

**Falta:** validar política de senha e perfis com usuários reais.

## Sprint 7 — Lecom, notificações e relatórios

**Status: Parcial**

### Concluído

- dashboard operacional Laravel;
- relatórios de estoque, solicitações e entregas;
- JSON e CSV;
- filtros e paginação limitada;
- proteção contra CSV formula injection;
- configuração e código de integração Lecom;
- notificações internas existentes no legado.

### Pendente

- validar Lecom com tenant, processo, versão e API key reais;
- decidir e implementar SMTP/Graph;
- configurar mailbox e política do Microsoft 365;
- worker/cron de notificações.

**E-mail está fora do escopo desta entrega por decisão do usuário.**

## Sprint 8 — Frontend, API e Vercel

**Status: Parcial**

### Concluído

- ponte opcional `laravel-api-bridge.js`;
- normalização de respostas e erros Laravel;
- Bearer token, upload e idempotência;
- CORS parametrizado;
- documentação do contrato e deploy;
- correção da persistência do snapshot Blob na homologação Vercel;
- readiness com verificações de banco, APP_KEY, CORS e storage.

### Pendente

- apontar o frontend principal definitivamente para `/api/v1`;
- configurar PostgreSQL/Supabase ou outro banco persistente;
- executar migrations e seed no ambiente final;
- configurar S3/Supabase Storage;
- configurar variáveis de produção;
- testar os cinco perfis em homologação remota;
- executar smoke de login, estoque, solicitação, entrega, QR e PDF.

## Sprint 9 — Operação corporativa e desktop

**Status: Parcial**

### Concluído

- readiness;
- backup/restore SQLite para uso local;
- documentação operacional;
- timezone parametrizado;
- critérios de deploy produtivo seguro.

### Pendente

- banco persistente de produção;
- backup automatizado do banco real;
- storage externo e política de retenção;
- monitoramento externo;
- worker/scheduler persistente;
- teste periódico de restauração;
- build desktop com Tauri;
- modo remoto e eventual modo local/offline avançado.

**Tauri está fora do escopo desta entrega.**

## Sprint 10 — Implantação em VM

**Status: Planejada**

- provisionar Ubuntu Server;
- instalar Nginx, PHP-FPM, extensões, banco e Supervisor;
- configurar domínio e HTTPS;
- criar banco e usuário de menor privilégio;
- importar schema e dados;
- configurar storage e permissões;
- configurar cron/worker;
- configurar backups externos;
- executar checklist de aceite da VM.

Os requisitos estão em `docs/Requisitos-VM.md`.

## Critérios para trocar o backend legado

1. migrations executadas sem erro;
2. testes automatizados aprovados;
3. entradas e saídas persistem após reinício;
4. estoque, livro e histórico batem;
5. retirada duplicada bloqueada;
6. cinco perfis validados;
7. importação conferida;
8. frontend conectado;
9. deploy de homologação aprovado;
10. backup e restauração testados;
11. storage de arquivos persistente;
12. logs e monitoramento configurados.

Até todos os critérios serem atendidos, o backend legado deve permanecer disponível como referência e fallback controlado.
