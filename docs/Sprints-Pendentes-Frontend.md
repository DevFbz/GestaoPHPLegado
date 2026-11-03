# Sprints pendentes — Frontend Gestão de Brindes

**Atualizado em:** 02/10/2026  
**Branch de referência:** `main`  
**Commit de referência:** `bc1c863`  
**Escopo:** frontend PHP/Bootstrap/Alpine.js, integração com a API existente, autenticação, permissões, estoque, uploads e documentos.

Este documento é o plano de execução da reestruturação atual do frontend. Ele complementa os planos históricos de Laravel e não substitui os contratos da API.

## Estado geral

### Já concluído

- diagnóstico da estrutura, rotas, assets, autenticação e integrações;
- branch de rollback `backup/frontend-before-redesign`;
- design system global, layout autenticado e layout de login;
- navegação, cabeçalhos contextuais, tabelas, cards, formulários e responsividade inicial;
- refinamento das telas de brindes, estoque, solicitações, eventos, entregas e administração;
- correção da persistência do snapshot demo que afetava a leitura posterior das entradas de estoque;
- validação local de sintaxe PHP, regressões de UI e rotas públicas/protegidas.

### Ainda não pode ser considerado concluído

- homologação autenticada ponta a ponta contra o ambiente remoto;
- validação de todos os cinco perfis com usuários reais;
- confirmação remota de upload em storage privado;
- confirmação remota de PDFs, assinaturas e QR Code;
- build/deploy do frontend após os commits mais recentes;
- smoke test de produção;
- integração de e-mail Gmail.

---

## Sprint 3 — Brindes, estoque e consistência operacional

**Status:** parcialmente concluída; correção de persistência implementada, homologação remota pendente.

### Objetivo

Garantir que uma entrada, saída, transferência ou correção atualize de forma consistente o saldo, o livro de movimentações, o histórico do brinde, as posições e os indicadores.

### Entregas restantes

- validar entrada manual com e sem anexo;
- validar entrada pelo fluxo de recebimento de solicitação;
- conferir livro de movimentações após nova requisição e após novo processo/lambda;
- conferir painel/listagem/detalhe do brinde após atualização;
- validar idempotência com replay da mesma chave;
- validar ausência de duplicidade de movimento;
- validar atualização de posições por local;
- validar conciliação de estoque e auditoria;
- confirmar o mesmo comportamento para saída, ajuste e transferência.

### Critérios de aceite

- uma entrada gera exatamente um movimento de tipo `entrada`;
- `qty_on_hand`, `available` e posições refletem a quantidade recebida;
- o livro exibe quantidade assinada, saldo posterior, usuário e data;
- o detalhe do brinde exibe o novo saldo e o movimento no histórico;
- repetir a mesma requisição com a mesma chave não gera segundo movimento;
- uma chave reutilizada com payload diferente é recusada;
- os dados continuam disponíveis após nova requisição e reinício da instância;
- saldo, posições, livro e auditoria permanecem reconciliáveis.

### Evidências obrigatórias

- POST autenticado de entrada;
- replay do POST;
- GET `/api/items/{id}`;
- GET `/api/items/{id}/history`;
- GET `/api/stock/movements?item_id={id}`;
- leitura do registro de auditoria;
- logs/retorno do ambiente remoto.

---

## Sprint 4 — Solicitações, eventos, entregas, QR Code, assinatura e PDF

**Status:** visualmente refinada; validação funcional remota pendente.

### Entregas restantes

- validar criação, edição, envio, aprovação, reprovação e cancelamento de solicitações;
- validar escopo de acesso por perfil e indústria;
- validar eventos, alocações, retiradas e devoluções;
- validar geração e consulta do protocolo de entrega;
- validar QR Code de consulta e confirmação;
- validar assinatura PNG no fluxo real;
- validar geração, download e visualização do PDF;
- confirmar que documentos usam storage privado e URLs protegidas;
- validar idempotência de retirada e confirmação.

### Critérios de aceite

- cada transição de status registra usuário, data e histórico;
- aprovação não permite autoaprovação quando a regra impedir;
- baixa de estoque ocorre uma única vez;
- QR Code inválido, expirado ou já utilizado é recusado corretamente;
- assinatura é validada pelo backend e não fica pública;
- PDF contém protocolo, QR Code, itens, quantidades e assinatura quando aplicável;
- usuário sem escopo não consegue consultar nem baixar documentos de outra indústria.

---

## Sprint 5 — Administração, perfis, permissões, auditoria e notificações

**Status:** telas refinadas; validação de autorização e comportamento remoto pendente.

### Entregas restantes

- validar usuários, ativação, inativação, restauração e exclusão;
- validar edição de perfis e permissões;
- conferir permissões efetivas no backend, não apenas no menu;
- validar configurações e branding;
- validar auditoria de alterações administrativas;
- validar notificações, leitura individual e “marcar todas como lidas”;
- conferir que dados sensíveis não aparecem em telas, exportações ou logs;
- validar escopo de indústria para todos os perfis.

### Critérios de aceite

- cada perfil vê e executa apenas as operações autorizadas;
- uma rota protegida retorna `403` mesmo quando chamada diretamente;
- inativação encerra ou bloqueia a sessão conforme a regra definida;
- ações administrativas possuem registro de auditoria;
- notificações refletem mudanças reais e não dados fictícios;
- hashes, tokens, APP_KEY e credenciais nunca são exibidos.

---

## Sprint 6 — Formulários, validações, mensagens e estados de interface

**Status:** parcialmente implementada; revisão sistemática pendente.

### Entregas restantes

- revisar todos os formulários de cadastro e operação;
- padronizar mensagens de validação de campo;
- tratar erros `401`, `403`, `404`, `409`, `422` e `500`;
- garantir loading, disabled, retry e empty state em todas as consultas;
- impedir duplo envio em ações mutáveis;
- validar filtros, paginação, busca e limpeza de filtros;
- revisar confirmação para ações destrutivas;
- garantir que falhas da API não deixem telas brancas.

### Critérios de aceite

- cada formulário informa o erro junto ao campo correspondente;
- o botão fica protegido durante o envio;
- falhas recuperáveis oferecem nova tentativa;
- estados vazios explicam o próximo passo;
- nenhum fluxo depende de mock permanente;
- respostas da API são tratadas pelo cliente comum `Api`.

---

## Sprint 7 — Responsividade e acessibilidade

**Status:** base implementada; auditoria de todas as telas pendente.

### Entregas restantes

- testar desktop, notebook, tablet e celular;
- revisar tabelas largas e ações horizontais;
- validar sidebar e navegação mobile;
- conferir foco visível e ordem de tabulação;
- garantir labels, mensagens e nomes acessíveis;
- revisar contraste de textos, badges e estados;
- garantir uso sem mouse nos formulários e modais;
- revisar `aria-*`, skip link, headings e landmarks;
- validar zoom de 200% sem perda de operação.

### Critérios de aceite

- nenhuma ação essencial fica inacessível em viewport móvel;
- foco não desaparece durante modal, dropdown ou navegação;
- campos têm label associado;
- alertas de erro e sucesso são perceptíveis;
- contraste atende o padrão definido para texto e controles;
- não há overflow horizontal indevido nas telas principais.

---

## Sprint 8 — Integração, regressão e segurança operacional

**Status:** parcialmente validada localmente; homologação remota pendente.

### Entregas restantes

- validar autenticação e renovação/expiração de sessão;
- validar permissões por rota e por ação;
- validar CORS e origem oficial do frontend;
- validar uploads no bucket privado Supabase/S3;
- validar download protegido de anexos;
- validar PDFs, QR Code e assinaturas no ambiente remoto;
- executar testes de regressão de estoque e solicitações;
- revisar cache de navegador, service worker e respostas `no-store` da API;
- confirmar migrations e schema do ambiente final;
- confirmar logs sem segredos.

### Critérios de aceite

- frontend e API usam o contrato correto em todas as telas;
- POSTs mutáveis possuem CSRF e idempotência quando necessário;
- anexos não são acessíveis por URL pública sem autorização;
- dados persistem após nova instância do runtime;
- regressões existentes continuam aprovadas;
- nenhum segredo aparece no Git, bundle, HTML ou log.

---

## Sprint 9 — Homologação, build, smoke test e deploy controlado

**Status:** pendente.

### Entregas restantes

- executar lint e testes completos;
- executar build de produção, quando aplicável;
- conferir manifests, assets e URLs públicas;
- revisar variáveis de ambiente sem expor valores;
- confirmar banco externo persistente;
- confirmar storage persistente privado;
- executar migrations no ambiente final;
- validar readiness remoto;
- executar smoke test autenticado;
- publicar somente após aprovação dos critérios;
- registrar commit, URL, logs e resultado do deploy;
- manter rollback conhecido e verificável.

### Smoke test mínimo

1. login;
2. consulta de dashboard;
3. consulta de brindes;
4. entrada de estoque;
5. leitura do livro;
6. leitura do detalhe/histórico do brinde;
7. criação e consulta de solicitação;
8. aprovação conforme perfil;
9. entrega/QR/assinatura/PDF;
10. logout;
11. repetição dos GETs após nova requisição para confirmar persistência.

### Critérios de aceite

- todas as sprints anteriores possuem evidência;
- `php -l`, regressões, build e smoke test passam;
- readiness remoto retorna sucesso em todas as dependências críticas;
- deploy é confirmado pelo provedor e pelo domínio final;
- rollback foi identificado e não depende de segredo compartilhado no chat.

---

## Integração Gmail — fora do escopo até a Sprint 9

A integração de envio de e-mail será iniciada somente depois de todas as sprints acima concluídas e validadas.

### Pré-requisitos

- definir remetente Gmail e política de autenticação;
- escolher OAuth ou SMTP conforme decisão operacional;
- cadastrar credenciais somente no provedor/dashboard;
- configurar filas, retry, limites e logs sanitizados;
- validar envio real, falha, reenvio e auditoria;
- nunca armazenar senha ou token no repositório.

A configuração do Gmail não deve ser usada para mascarar pendências de persistência, permissões, storage, PDF ou smoke test.

---

## Bloqueios externos atuais

- ambiente local MySQL não está iniciado;
- extensão GD não está disponível para a suíte completa de imagens;
- ainda falta smoke test autenticado remoto após o push;
- é necessário confirmar a variável de persistência Blob ou banco externo no projeto que hospeda o frontend legado;
- upload real no bucket privado e download protegido ainda não foram comprovados;
- credenciais e usuários reais para homologação devem ser fornecidos/configurados fora do repositório.

## Regra de conclusão

Uma sprint só será marcada como concluída quando houver implementação no código, teste executado e evidência compatível com o ambiente-alvo. Configuração local ou documentação, isoladamente, não equivale a homologação nem produção.
