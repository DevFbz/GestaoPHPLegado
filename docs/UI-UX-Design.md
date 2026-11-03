# UI/UX Design — Controle de Brindes

## 1. Direção visual

Interface operacional clara, com contraste alto, textos curtos e foco em ação. O azul primário atual comunica confiança e mantém continuidade com o produto já publicado. O layout deve funcionar em desktop e tablet sem depender de hover.

## 2. Regras de layout

- Menu lateral no desktop e offcanvas no tablet.
- Cabeçalho com título da tela, busca rápida, notificações e usuário.
- Conteúdo em cards simples, sem excesso de caixas decorativas.
- Formulários em duas colunas quando houver espaço e uma coluna no tablet.
- Ações primárias à direita ou junto ao final do formulário.
- Botões com estado desabilitado e indicador de carregamento.
- Tabelas com toque confortável e linhas clicáveis somente quando a ação estiver evidente.

## 3. Nova solicitação TRADE

Hierarquia recomendada:

1. Indústria e tipo de ação.
2. Finalidade e destinatário.
3. Local de entrega e observações.
4. Busca e seleção de brindes.
5. Quantidade e valor unitário.
6. Ações: `Enviar solicitação de compra` e `Abrir chamado no Lecom`.

O botão Lecom abre diretamente uma nova aba, mostra `Abrindo chamado…`, evita duplo clique e apresenta erro acionável se o Lecom recusar a abertura.

## 4. Estados que precisam de design

- Carregando: spinner e texto contextual.
- Vazio: explicar o que fazer, não apenas mostrar “nenhum registro”.
- Offline: indicador persistente com quantidade pendente.
- Sincronizando: não permitir sair sem avisar sobre pendências.
- Falha de validação: mensagem junto ao campo.
- Falha de integração: informar o próximo passo sem mostrar segredo técnico.
- Sucesso: toast curto e navegação coerente.

## 5. Tablet

- Alvos de toque com pelo menos 44 px.
- Formulários sem depender de teclado físico.
- Ações fixadas no fim da área visível quando o formulário for longo.
- QR e assinatura em área grande, com confirmação antes de concluir.
- Evitar duas colunas em telas estreitas.

## 6. Acessibilidade

- `label` associado a todo campo.
- Foco visível e navegação por teclado.
- `aria-live` para carregamento, conexão e toasts relevantes.
- Cor nunca é o único indicador de status.
- Contraste compatível com WCAG AA como objetivo.
- Ícones decorativos com `aria-hidden` e texto sempre presente nas ações importantes.

## 7. Componentes e padrões

| Padrão | Uso |
|---|---|
| Card de formulário | Cadastro e criação |
| Tabela responsiva | Listas e relatórios |
| Badge de status | Estado da solicitação/estoque |
| Toast | Resultado breve de operação |
| Alert inline | Erro que precisa permanecer visível |
| Modal/offcanvas | Notificações, menu e confirmação |
| QR + assinatura | Retirada e comprovante |

## 8. Testes de usabilidade

- Criar uma solicitação TRADE em tablet.
- Abrir o Lecom sem popup bloqueado.
- Perder conexão durante o envio e sincronizar depois.
- Encontrar uma solicitação e abrir seus detalhes.
- Receber e retirar um item usando somente toque.
- Entender por que uma operação falhou sem consultar o console.
