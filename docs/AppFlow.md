# App Flow — Controle de Brindes

## 1. Fluxo principal TRADE

```text
Login
  -> Solicitações TRADE
  -> Nova solicitação
  -> [Abrir chamado no Lecom] -> instância Lecom -> formulário Lecom
  -> Enviar solicitação de compra
  -> Aguardando aprovação
  -> Gestor aprova e informa chamado
  -> Compra realizada / aguardando recebimento
  -> CD recebe material e NF
  -> Recebido no CD / pronto para retirada
  -> Retirada por QR + assinatura
  -> Entregue
  -> Protocolo e notificações
```

## 2. Fluxo de abertura Lecom

```text
Clique no botão
  -> abre uma aba vazia para evitar bloqueio de popup
  -> tenta cookie LecomSSOTicket + Workspace PUT
  -> sem cookie: POST interno /api/lecom/process/start
  -> backend chama API Lecom com segredo
  -> recebe processInstanceId/activityInstanceId/cycle
  -> monta /workspace/form-app/{id}/{activity}/{cycle}?isNewForm=true
  -> aba navega para o formulário
```

## 3. Fluxo offline

```text
Usuário envia operação
  -> internet disponível? API responde normalmente
  -> sem conexão? IndexedDB grava na outbox
  -> indicador mostra pendências
  -> evento online/foco sincroniza em ordem
  -> sucesso remove item
  -> erro transitório mantém pendente
  -> erro de regra marca falha para revisão
```

## 4. Estados TRADE

| Estado | Próximo responsável | Ação |
|---|---|---|
| `solicitada` | Gestor | Aprovar e informar chamado |
| `compra_realizada` | CD | Receber e anexar NF |
| `aguardando_recebimento` | CD | Registrar chegada |
| `recebido_cd` | Operação | Marcar pronto |
| `pronta` | Gestor/CD | Registrar retirada |
| `retirado` | Operação | Marcar entregue |
| `entregue` | Todos autorizados | Consultar comprovante |
| `reprovada` | Solicitante | Consultar motivo |

## 5. Estados da fila de e-mail

```text
Evento de negócio -> notifications/outbox -> cron/send_notifications.php
  -> MAIL_DRIVER=log: grava log local
  -> MAIL_DRIVER=smtp: envia via PHPMailer
  -> sucesso: marca enviado
  -> falha: registra erro e permite nova tentativa
```

## 6. Rotas de navegação principais

- `/dashboard`
- `/solicitacoes-trade`
- `/solicitacoes-trade/nova`
- `/solicitacoes-trade/{id}`
- `/recebimento`
- `/retirada`
- `/protocolos`
- `/configuracoes`

Não existe mais uma aba separada de abertura de chamado; o botão está em Nova solicitação TRADE.
