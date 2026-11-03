# PRD — Reestruturação do Gestão de Brindes com Laravel

**Versão:** 1.0  
**Data:** 28/09/2026  
**Status:** Em reestruturação  
**Repositório:** `GestaoBrinde`

## 1. Visão do produto

O Gestão de Brindes controla cadastros, estoque, solicitações TRADE, aprovações, eventos, retiradas, protocolos e auditoria. A reestruturação separa o backend operacional do frontend e elimina a dependência de estado temporário da Vercel.

Durante a fase de testes, os dados fictícios podem continuar sendo importados de JSON. O JSON não será tratado como fonte transacional definitiva: entradas, saídas, aprovações e retiradas devem passar pelo backend Laravel e por persistência controlada.

## 2. Problema que motivou a reestruturação

O backend legado utiliza PHP próprio, PDO, SQLite e sincronização de snapshots para o ambiente serverless. Como a Vercel executa funções em instâncias diferentes, registros gravados em filesystem temporário podem desaparecer, voltar a um snapshot anterior ou apresentar divergência após recarregar a página.

Sintomas observados:

- entrada registrada não aparece no livro de movimentações;
- saldo da aba Brindes não acompanha a movimentação;
- registros mudam depois de atualizar a página;
- datas e horários são recriados ou aparecem no futuro;
- risco de baixa duplicada em reenvio do navegador;
- permissões e fluxo de solicitação dependem de regras espalhadas.

## 3. Objetivos

- Criar backend Laravel modular e testável.
- Manter estoque e movimentações em transações atômicas.
- Garantir que cada baixa tenha usuário, motivo, protocolo e origem.
- Aplicar perfis e permissões no backend, não somente no menu.
- Permitir JSON temporário por importação segura e modo simulação.
- Preparar PostgreSQL/MySQL para a fase corporativa.
- Permitir que a Vercel hospede o frontend sem ser o banco operacional.
- Preparar o sistema para futura distribuição como `.exe`.

## 4. Perfis do produto

| Perfil | Permissões principais |
|---|---|
| Administrador | Usuários, perfis, cadastros, ajustes, regras, configurações, auditoria e tudo do sistema |
| TRADE / Gestor | Solicitações TRADE, cadastros, aprovações, estoque, retiradas, eventos e relatórios |
| CD / Estoque | Recebimento, entrada, confirmação de retirada, transferência, QR e comprovantes |
| TRADE | Criar e acompanhar solicitações de compra e consultar cadastros |
| Indústria | Consultar apenas informações vinculadas à própria indústria |

O perfil CD/Estoque não cria solicitação TRADE, não registra saída manual genérica, não acessa cadastros administrativos e não usa separação de entregas fora do fluxo autorizado.

## 5. Escopo funcional

### P0 — obrigatório

- autenticação e sessão/token;
- perfis e permissões;
- categorias, departamentos, indústrias, locais, fornecedores e brindes;
- entrada, saída, saldo e livro de movimentações;
- solicitações TRADE;
- aprovação e reprovação;
- eventos e cotas por indústria;
- entrega/protocolo e retirada idempotente;
- QR assinado para verificação;
- auditoria e histórico de status.

### P1 — próxima evolução

- troca obrigatória de senha e recuperação de senha;
- importação de usuários por convite seguro;
- PDF de comprovante;
- armazenamento persistente de assinatura e anexos;
- integração completa com Lecom;
- notificações internas e e-mail;
- dashboard e relatórios;
- frontend atual consumindo a API Laravel.

### P2 — futuro

- modo offline controlado;
- PostgreSQL gerenciado em produção;
- empacotamento Tauri como `.exe`;
- integração Microsoft 365/Graph;
- filas Redis/Celery-equivalentes no ecossistema PHP;
- serviços de IA isolados e somente leitura inicialmente.

## 6. Critérios de sucesso

- Uma entrada permanece visível depois de várias requisições e recarregamentos.
- Uma saída atualiza saldo, livro e histórico na mesma transação.
- Repetir a mesma chave de idempotência não duplica a baixa.
- CD/Estoque não consegue criar solicitação TRADE.
- Solicitação TRADE registra histórico de criação, envio e aprovação.
- Retirada gera um único protocolo e baixa vinculada.
- Indústria não acessa dados de outra indústria.
- JSON pode ser importado sem apagar dados existentes automaticamente.

## 7. Estado atual do produto

### Já implementado no novo backend Laravel

- estrutura Laravel em `backend/`;
- configuração PostgreSQL e compatibilidade com Vercel por Root Directory;
- migrations de usuários, perfis, permissões, cadastros, brindes e estoque;
- `InventoryService` com transação, bloqueio de linha e ledger imutável;
- autenticação Sanctum, login, logout e usuário atual;
- perfis e permissões granulares;
- importador JSON com `--dry-run`;
- solicitações TRADE e itens;
- aprovação/reprovação e histórico;
- eventos e cotas por indústria;
- protocolos de entrega;
- baixa transacional e idempotência;
- QR assinado com verificação pública limitada;
- testes automatizados preparados;
- pipeline GitHub Actions preparado.

### Ainda não concluído

- PHP, Composer e PostgreSQL ainda não foram executados neste computador;
- frontend legado ainda aponta para o backend PHP antigo;
- novo backend ainda não foi colocado como destino principal da Vercel;
- importação de solicitações, movimentações e auditoria legadas ainda precisa ser concluída;
- usuários não são importados porque os JSON não contêm senhas;
- troca obrigatória e recuperação de senha ainda precisam ser migradas;
- geração de PDF e armazenamento de assinatura ainda não foram conectados;
- integração Lecom ainda não foi migrada para Laravel;
- notificações, relatórios e dashboard ainda não foram migrados;
- push e deploy ainda não foram executados.

## 8. Fora do escopo da fase atual

- apagar o backend legado antes da validação;
- usar JSON gravável como banco concorrente de produção;
- trocar o frontend antes de os contratos da API serem testados;
- distribuir o `.exe` antes de estabilizar a versão web.

## 9. Dependências e riscos

| Dependência/risco | Mitigação |
|---|---|
| PHP/Composer ausentes | instalar runtime ou validar pela pipeline do GitHub |
| JSON gravável na Vercel | usar JSON apenas como importação ou storage externo temporário |
| Dados legados incompletos | importador com simulação, relatório e transação |
| Usuários sem senha exportada | convite/redefinição segura, sem criar senha fictícia |
| Conflito com frontend antigo | manter API legada até a migração dos contratos |
| QR expondo dados pessoais | QR assinado retorna somente protocolo, tipo e data |

## 10. Aceite do produto

A reestruturação será considerada pronta para homologação quando as migrations e testes executarem, o frontend estiver conectado à API Laravel, os fluxos P0 forem validados nos cinco perfis, a importação for conferida e o deploy utilizar armazenamento persistente.
