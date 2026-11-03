# Plano de execução — Controle de Brindes

## Objetivo

Corrigir os fluxos atuais, reforçar os perfis, preparar a integração com o formulário Lecom de suprimentos, melhorar notificações e deixar a homologação reproduzível.

> O banco relacional continua sendo a fonte transacional do sistema: estoque, reservas, auditoria e concorrência dependem de transações. JSON será usado como fixture, snapshot/exportação e reset de homologação, não como substituto de um banco transacional.

## Sprint 0 — Baseline, dados e tempo

### Entregas

- Criar estrutura organizada `storage/json/` por domínio.
- Criar exportador de dados para JSON sem expor hashes, tokens ou segredos.
- Criar reset/rebuild de homologação a partir do schema + seed/demo.
- Corrigir e validar timezone `America/Sao_Paulo`.
- Separar claramente dados demo de dados reais.
- Remover dados artificiais da homologação quando solicitado, sem tocar produção.

### Critérios de aceite

- Datas novas aparecem no horário configurado da aplicação.
- O reset de homologação é explícito e reproduzível.
- JSON exportado contém usuários anonimizados, cadastros, brindes, estoque, solicitações, movimentos, notificações e auditoria em arquivos separados.
- Nenhum segredo ou senha é exportado.

## Sprint 1 — Correções funcionais imediatas

### Entregas

- Corrigir `Qtd = NaN` no Livro de movimentações.
- Corrigir tela branca ao abrir uma Solicitação TRADE.
- Exibir estado de carregamento, erro e ausência de dados no detalhe TRADE.
- Corrigir validação da Nova Solicitação TRADE.
- Mostrar erros de campo e erros do backend dentro do formulário.
- Validar indústria, finalidade, brinde e quantidade antes do envio.

### Critérios de aceite

- A coluna de quantidade exibe números assinados e nunca `NaN`.
- Falha de API no detalhe TRADE não deixa a tela branca.
- Solicitação TRADE válida é criada e redireciona para o detalhe.
- Solicitação sem indústria, brinde ou quantidade bloqueia o envio com mensagem objetiva.

## Sprint 2 — Perfis e permissões

### Perfis

- **Administrador:** acesso total.
- **TRADE / Gestor:** solicitações TRADE, cadastros, aprovações, estoque, retiradas e relatórios.
- **CD / Estoque:** recebimento, entrada, confirmação de saída por QR, transferência, retirada por QR e comprovantes.
- **TRADE:** solicitações de compra e consulta de brindes/cadastros.
- **Indústria:** somente dados da indústria vinculada.

### Entregas

- Ajustar permissões de seed e instalações existentes.
- Garantir que CD não crie solicitações, não registre saída manual, não acesse Cadastros e não use Separação/entregas internas.
- Permitir que CD processe recebimento e retirada TRADE por QR sem receber a permissão de saída manual.
- Validar backend e menu; esconder menu não substitui autorização no backend.

## Sprint 3 — Lecom e notificações

### Entregas

- Adicionar botão “Abrir formulário Lecom de suprimentos” no detalhe do brinde.
- Tornar a URL do formulário configurável em Configurações.
- Suportar placeholders `{id}`, `{code}`, `{name}`, `{quantity}`, `{unit_value}` e `{category}`.
- Abrir o formulário em nova aba com os dados do brinde.
- Trocar o link direto da campainha por modal flutuante.
- Modal com notificações recentes, marcar todas como lidas e link “Ver todas”.
- Fechar modal por clique externo e tecla Escape.

### Dependência externa

A URL real do formulário Lecom ainda precisa ser configurada. O sistema não deve inventar domínio, `COD_FORM` ou campos do formulário.

## Sprint 4 — QA e entrega

### Entregas

- Executar `php tests/scenarios.php` com banco de teste.
- Executar lint PHP em arquivos alterados e `node --check` nos scripts JS.
- Testar manualmente os cinco perfis.
- Validar Livro de movimentações, Nova Solicitação TRADE, detalhe TRADE, notificações e botão Lecom.
- Atualizar contrato da API e documentação de perfis.
- Conferir `git diff`, build/servidor local e estado final limpo.

## Ordem de execução

1. Sprint 0 — dados e tempo.
2. Sprint 1 — bugs visíveis e formulário TRADE.
3. Sprint 2 — segurança e perfis.
4. Sprint 3 — Lecom e notificações.
5. Sprint 4 — testes e documentação.
