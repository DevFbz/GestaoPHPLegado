# PRD — Controle de Brindes

## 1. Visão do produto

O Controle de Brindes organiza o ciclo completo de brindes da empresa: cadastros, estoque, solicitação, aprovação, compra, recebimento no CD, retirada, entrega, comprovante e auditoria.

O produto atende computadores e tablets pelo navegador. A implantação produtiva recomendada é em uma VM com PHP, MariaDB/MySQL, armazenamento persistente, HTTPS e tarefas agendadas. A Vercel pode hospedar uma demonstração ou o frontend, mas não deve ser tratada como banco de dados nem como filesystem persistente.

## 2. Estado atual do produto

A aplicação possui duas camadas:

- **Sistema legado PHP/PDO:** funcional para a operação atual, com cadastros, estoque, solicitações, Lecom, notificações e auditoria.
- **Backend Laravel em `backend/`:** API versionada `/api/v1`, com estoque transacional, entregas, QR, PDF, importação legada, autenticação, dashboard, relatórios, readiness e operações.

A substituição completa do legado pelo Laravel ainda depende da conexão definitiva do frontend, banco persistente e homologação ponta a ponta.

## 3. Problema

O processo dependia de planilhas, mensagens, chamados e controles locais, dificultando conhecer o saldo real, localizar solicitações, acompanhar aprovações e comprovar retiradas.

## 4. Objetivos

- Centralizar estoque, solicitações e aprovações.
- Reduzir erros de saldo, duplicidade e retirada sem autorização.
- Registrar usuário, motivo, origem e data em cada movimentação.
- Conectar a abertura de chamados ao Lecom sem expor a chave no navegador.
- Funcionar em computadores e tablets.
- Manter histórico, auditoria, QR Code, assinatura e protocolo digital.
- Permitir migração controlada do legado para o backend Laravel.

## 5. Usuários e permissões

| Perfil | Necessidade principal |
|---|---|
| Administrador | Configura usuários, permissões, backup e integrações |
| TRADE / Gestor | Cria e aprova solicitações, acompanha compras, estoque e eventos |
| TRADE / Solicitante | Cria e acompanha suas solicitações |
| CD / Estoque | Recebe, movimenta, separa, confirma saída e registra retirada |
| Indústria | Consulta somente dados permitidos da própria indústria |

## 6. Escopo funcional

### P0 — operação essencial

- Login, sessão, troca obrigatória e recuperação de senha.
- Cadastros de brindes, categorias, indústrias, departamentos, locais e fornecedores.
- Entrada, saída, ajuste, transferência e livro de movimentações.
- Solicitações internas e TRADE.
- Aprovação e reprovação com regras configuráveis.
- Recebimento no CD com NF.
- Retirada com QR Code e assinatura.
- Protocolo digital em PDF.
- Auditoria e permissões por ação.
- Abertura de instância Lecom pela solicitação TRADE.

**Status:** implementado no legado; os fluxos equivalentes principais estão implementados na API Laravel e aguardam homologação produtiva integrada.

### P1 — operação assistida

- Fila offline no navegador com IndexedDB e sincronização posterior.
- Notificações internas.
- Dashboard, relatórios JSON/CSV e alertas de estoque.
- Backup e restauração.
- Readiness operacional.

**Status:** implementado parcialmente. A fila offline, dashboard, relatórios, backup SQLite e readiness existem. Backup produtivo e worker persistente ainda dependem da infraestrutura escolhida.

### P2 — evolução

- SMTP/Graph para notificações externas.
- Worker robusto para tarefas assíncronas e conflitos offline.
- Serviço de IA isolado para busca, resumo e apoio operacional.
- Aplicativo desktop/Tauri.
- Integrações adicionais com ERP.

**Status:** pendente. E-mail e Tauri estão explicitamente fora do escopo desta entrega.

## 7. Critérios de sucesso

- Uma solicitação TRADE percorre criação, aprovação, compra, recebimento e retirada sem planilha paralela.
- Cada baixa possui usuário, motivo, origem e timestamp.
- Repetição da mesma requisição não cria movimentação duplicada.
- O histórico permanece após reinício do servidor e nova sessão.
- O usuário visualiza estados offline e operações pendentes.
- O chamado Lecom abre a partir da solicitação TRADE quando o ambiente estiver configurado.
- Perfis não acessam dados fora do próprio escopo.

## 8. Requisitos não funcionais

- PHP 8.2+; validar PHP 8.3 no ambiente de produção.
- MySQL 8.0.16+ ou MariaDB 10.4+ para o legado; InnoDB e `utf8mb4`.
- PostgreSQL gerenciado pode ser usado pelo backend Laravel após a validação da conexão e migrations.
- HTTPS obrigatório.
- Segredos fora do Git e fora do navegador.
- CSRF, sessão HttpOnly/SameSite, rate limit e autorização no backend.
- Uploads fora da pasta pública e backup separado do servidor.
- Logs sem senhas, tokens, APP_KEY ou connection strings.
- Layout responsivo para desktop e tablet.

## 9. Dependências externas

- Lecom: processo publicado, tenant, portal e chave do ambiente correto.
- Banco relacional persistente.
- Storage persistente para fotos, assinaturas, PDFs e NFs.
- SMTP/Graph somente quando o escopo de e-mail for reaberto.
- VM, DNS, certificado TLS, cron e política de backup.

## 10. Riscos e mitigação

| Risco | Mitigação |
|---|---|
| SQLite efêmero na Vercel | Usar banco relacional persistente; Blob apenas como fallback de demonstração |
| Perda de arquivos locais | Usar storage externo ou backup de `storage/` |
| Conflitos offline | Idempotency-Key, outbox ordenada e revisão de conflitos |
| Conexões excessivas em serverless | Usar pooler transacional do provedor PostgreSQL |
| Permissões divergentes do Lecom | Validar processo, versão, ambiente e usuário em homologação |
| Falha de SMTP | Não bloquear retirada; processar notificações por fila/cron |

## 11. Aceite da entrega atual

A entrega atual é considerada funcional em homologação quando a suíte Laravel, as regressões legadas e os smoke tests passam. O aceite produtivo exige ainda banco persistente, storage persistente, migrations executadas no ambiente final, backup restaurado em teste, validação dos cinco perfis e smoke de produção.

E-mail/SMTP/Graph e Tauri não fazem parte do aceite desta entrega.
